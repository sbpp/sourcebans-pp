<?php
/**
 * E2E comms-row seeder.
 *
 * Why this exists alongside reset-e2e-db.php:
 *
 *   - `Actions.CommsAdd` is the production write path and what
 *     `comms-affordances.spec.ts` / `comms-gag-mute.spec.ts` use to
 *     seed rows. It accepts `type=1|2|3`, but `type=3` does NOT
 *     produce a row with `:prefix_comms.type=3`; it inserts BOTH a
 *     `type=1` and a `type=2` row (the "combined gag+mute" path —
 *     see `web/api/handlers/comms.php`).
 *   - The page.commslist.php render path *does* recognise `type=3`
 *     as the SourceComms-fork "silence" label (see the `$typeLabel`
 *     switch ~line 825 of page.commslist.php and the page_comms.tpl
 *     icon switch ~line 176), and the #1274 chip filter wires
 *     `?type=silence` → SQL `CO.type = 3`.
 *   - Seeding via the API would let us exercise mute/gag chips, but
 *     never silence — the test would either skip the silence chip's
 *     narrowing behaviour or assert "0 results", neither of which
 *     proves the filter actually picks up `type=3` rows.
 *
 * This shim is e2e-only: it refuses any DB other than the e2e schema
 * (default `sourcebans_e2e`) so a stray `--db sourcebans` from the
 * host can't trash the dev DB. The dev seeder
 * (`web/tests/Synthesizer.php`) has the same guardrail for the same
 * reason.
 *
 * Caller responsibility: the schema must already exist (the e2e
 * harness's `global-setup.ts` calls `Fixture::install()` once on
 * boot; spec `beforeAll`/`beforeEach` then calls `truncateE2eDb()`
 * which uses the lighter `truncateOnly()` path). This shim deliberately
 * does NOT call `Fixture::install()` or `Fixture::truncateOnly()` —
 * both are destructive (DROP+CREATE / TRUNCATE all tables) and would
 * wipe rows seeded by sibling specs. We just open a PDO connection
 * pointed at the e2e DB and INSERT the requested rows.
 *
 * Usage (inside the web container):
 *
 *   echo '<JSON>' | php seed-comms-e2e.php
 *
 * The JSON payload is a list of row dicts:
 *
 *   [
 *     {"steam": "STEAM_0:1:1", "nickname": "alice", "type": "mute",
 *      "state": "active"},
 *     {"steam": "STEAM_0:1:2", "nickname": "bob",   "type": "silence",
 *      "state": "permanent"},
 *     ...
 *   ]
 *
 * Type:  mute|gag|silence  →  :prefix_comms.type 1|2|3
 * State: active|unmuted|expired|permanent  →  RemoveType + length + ends
 *
 *   active     length=3600s, ends=now+3600,  RemoveType=NULL
 *   permanent  length=0,     ends=0,         RemoveType=NULL
 *   unmuted    length=3600s, ends=now+3600,  RemoveType='U'
 *   expired    length=60s,   ends=now-60,    RemoveType='E'
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-comms-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed comms against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

// Open a PDO connection against the existing e2e DB without
// touching the schema. Mirrors Fixture::truncateOnly()'s
// connection-only branch (without the truncate+reseed that
// follows it there) so the rows other specs already seeded
// stay intact.
if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-comms-e2e.php: empty stdin payload.\n");
    exit(2);
}

$rows = json_decode($payload, true);
if (!is_array($rows)) {
    fwrite(STDERR, "seed-comms-e2e.php: stdin is not a JSON array.\n");
    exit(2);
}

$typeMap = ['mute' => 1, 'gag' => 2, 'silence' => 3];

// Resolve the seeded admin's aid via a live lookup rather than
// `Fixture::adminAid()` — the static cache only carries the value
// when this same PHP process called `seedAdmin()`, but the shim
// runs in a fresh process and the row was seeded by the global-setup
// install call. The seeded row is `(user='admin', authid='STEAM_0:0:0')`
// (see `Fixture::seedAdmin`).
$adminRow = $GLOBALS['PDO']->query(
    "SELECT aid FROM `:prefix_admins` WHERE user = ? AND authid = ?"
)->single(['admin', 'STEAM_0:0:0']);
if (empty($adminRow['aid'])) {
    fwrite(STDERR, "seed-comms-e2e.php: cannot resolve admin row; was Fixture::install() run?\n");
    exit(2);
}
$adminAid = (int)$adminRow['aid'];

$now = time();

foreach ($rows as $i => $row) {
    if (!is_array($row)) {
        fwrite(STDERR, "seed-comms-e2e.php: row $i is not an object.\n");
        exit(2);
    }

    $steam    = (string)($row['steam']    ?? '');
    $nickname = (string)($row['nickname'] ?? '');
    $type     = (string)($row['type']     ?? '');
    $state    = (string)($row['state']    ?? 'active');
    $reason   = (string)($row['reason']   ?? 'e2e seed');

    if (!isset($typeMap[$type])) {
        fwrite(STDERR, "seed-comms-e2e.php: row $i has unknown type '$type'.\n");
        exit(2);
    }
    $typeInt = $typeMap[$type];

    // Map state → (length, ends, RemoveType).
    switch ($state) {
        case 'permanent':
            $length     = 0;
            $ends       = 0;
            $removeType = null;
            break;
        case 'unmuted':
            $length     = 3600;
            $ends       = $now + 3600;
            $removeType = 'U';
            break;
        case 'expired':
            $length     = 60;
            $ends       = $now - 60;
            $removeType = 'E';
            break;
        case 'active':
        default:
            $length     = 3600;
            $ends       = $now + 3600;
            $removeType = null;
            break;
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_comms` "
        . "(created, type, authid, name, ends, length, reason, aid, adminIp, RemovedOn, RemovedBy, RemoveType, ureason) "
        . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $now,
        $typeInt,
        $steam,
        $nickname,
        $ends,
        $length,
        $reason,
        $adminAid,
        '127.0.0.1',
        $removeType !== null ? $now : null,
        $removeType !== null ? $adminAid : null,
        $removeType,
        $removeType !== null ? 'e2e: pre-lifted' : '',
    ]);
}

fwrite(STDOUT, "seeded " . count($rows) . " comm rows on " . DB_NAME . "\n");
