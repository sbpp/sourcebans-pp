<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/**
 * E2E system-log seeder (#1462).
 *
 * The `:prefix_log` table is empty in the bare e2e seed — `data.sql`
 * doesn't ship any audit rows, and `Fixture::truncateAndReseed`
 * truncates every table on every reset. The mobile-card responsive
 * spec needs at least one row in the table so the System Log sub-tab
 * actually paints log content (the `{if count($log_items) > 0}`
 * gate would otherwise render "No log entries." and the card surface
 * wouldn't be exercised).
 *
 * Why a dedicated shim instead of driving the existing `Log::add`
 * path:
 *   - `Log::add(LogType::Message, ...)` is the production write
 *     surface, but it's called from inside a panel request — it
 *     reads `$_SERVER['REMOTE_ADDR']`, `$userbank->GetAid()`, etc.
 *     A spec running outside a panel request can't seed via this
 *     path without a Playwright `page.goto(...)` that triggers an
 *     audited action as a side effect.
 *   - Driving e.g. `Actions.BansAdd` to produce an audit row would
 *     couple the System Log spec to the bans-add surface; a
 *     refactor of the bans-add audit message would break the
 *     unrelated System Log mobile spec for the wrong reason.
 *   - Direct INSERT keeps the spec independent of every other audit
 *     surface — same shape `seed-comms-e2e.php` uses for the
 *     `comms.type=3` rows that `Actions.CommsAdd` never emits.
 *
 * This shim is e2e-only: refuses any DB other than the e2e schema
 * (default `sourcebans_e2e`) so a stray host-side invocation can't
 * trash the dev DB. Mirrors `seed-comms-e2e.php`.
 *
 * Usage (inside the web container):
 *
 *   echo '<JSON>' | php seed-system-log-e2e.php
 *
 * The JSON payload is a list of row dicts:
 *
 *   [
 *     {"type": "m", "title": "E2E info",  "message": "first row",
 *      "function": "e2e/spec",         "query": "n/a", "host": "127.0.0.1"},
 *     {"type": "w", "title": "E2E warn", "message": "second row", ...},
 *     ...
 *   ]
 *
 * Type letters mirror `:prefix_log.type enum('m','w','e')`. Defaults:
 *   - `type`:     "m"
 *   - `title`:    "E2E seeded entry"
 *   - `message`:  ""
 *   - `function`: "tests/e2e/seed"
 *   - `query`:    ""
 *   - `host`:     "127.0.0.1"
 *
 * `aid` is resolved live from the seeded admin row (mirrors
 * `seed-comms-e2e.php`'s lookup shape so this script stays
 * independent of `Sbpp\Tests\Fixture::adminAid`'s in-process cache).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-system-log-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed system log against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-system-log-e2e.php: empty stdin payload.\n");
    exit(2);
}

$rows = json_decode($payload, true);
if (!is_array($rows)) {
    fwrite(STDERR, "seed-system-log-e2e.php: stdin is not a JSON array.\n");
    exit(2);
}

$adminRow = $GLOBALS['PDO']->query(
    "SELECT aid FROM `:prefix_admins` WHERE user = ? AND authid = ?"
)->single(['admin', 'STEAM_0:0:0']);
if (empty($adminRow['aid'])) {
    fwrite(STDERR, "seed-system-log-e2e.php: cannot resolve admin row; was Fixture::install() run?\n");
    exit(2);
}
$adminAid = (int)$adminRow['aid'];

$now           = time();
$allowedTypes  = ['m', 'w', 'e'];
$insertedLids  = [];

foreach ($rows as $i => $row) {
    if (!is_array($row)) {
        fwrite(STDERR, "seed-system-log-e2e.php: row $i is not an object.\n");
        exit(2);
    }

    $type     = (string)($row['type']     ?? 'm');
    $title    = (string)($row['title']    ?? 'E2E seeded entry');
    $message  = (string)($row['message']  ?? '');
    $function = (string)($row['function'] ?? 'tests/e2e/seed');
    $query    = (string)($row['query']    ?? '');
    $host     = (string)($row['host']     ?? '127.0.0.1');

    if (!in_array($type, $allowedTypes, true)) {
        fwrite(STDERR, "seed-system-log-e2e.php: row $i has unknown type '$type' (expected 'm', 'w', or 'e').\n");
        exit(2);
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_log` "
        . "(type, title, message, function, query, aid, host, created) "
        . "VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $type,
        $title,
        $message,
        $function,
        $query,
        $adminAid,
        $host,
        $now,
    ]);

    $insertedLids[] = (int)$GLOBALS['PDO']->lastInsertId();
}

fwrite(STDOUT, json_encode(['lids' => $insertedLids]) . "\n");
