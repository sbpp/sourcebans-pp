<?php
/**
 * E2E per-row comments seeder (banlist + commslist).
 *
 * Why this exists alongside `seed-comms-e2e.php`:
 *
 *   - There is no JSON API action for adding admin-authored
 *     per-row comments (the legacy `?p=banlist&comment=N` POST
 *     handler is the production write path; it owns CSRF + the
 *     `:prefix_comments.commenttxt` substitution that turns a few
 *     literal sequences into `<br/>` etc., and isn't worth
 *     re-implementing as a JSON action just for tests).
 *   - `Sbpp\Tests\Synthesizer` has a comments-seeding path but it
 *     refuses any DB other than `sourcebans` (dev DB only); same
 *     refusal guard as `seed-comms-e2e.php`.
 *   - The banlist-comments-visibility e2e spec needs both a row
 *     AND comments on it. Driving an HTML POST through Playwright
 *     would couple the spec to the comment-edit page's chrome and
 *     CSRF handshake — unnecessary for what we're verifying (the
 *     disclosure renders, the drawer paints the same data).
 *
 * This shim is e2e-only: refuses any DB other than the e2e schema
 * (default `sourcebans_e2e`). Same shape and guardrails as the
 * sister `seed-comms-e2e.php` shim.
 *
 * Caller responsibility: the schema must already exist; the bid /
 * cid the comments attach to must already be seeded (use
 * `seedBanViaApi` for bans, `seedCommsRawE2e` for comm-blocks).
 *
 * Usage (inside the web container):
 *
 *   echo '<JSON>' | php seed-comments-e2e.php
 *
 * The JSON payload is a list of row dicts:
 *
 *   [
 *     {"type": "B", "bid": 42, "text": "first line\nsecond"},
 *     {"type": "C", "bid": 17, "text": "comm-block comment"}
 *   ]
 *
 * Type:  'B' (ban comment) | 'C' (comm-block comment) — matches
 * `:prefix_comments.type` directly. `bid` is the parent row's
 * bid (for type=B) or cid (for type=C); the column name is
 * `bid` for both because the v1.x table didn't differentiate.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-comments-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed comments against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-comments-e2e.php: empty stdin payload.\n");
    exit(2);
}

$rows = json_decode($payload, true);
if (!is_array($rows)) {
    fwrite(STDERR, "seed-comments-e2e.php: stdin is not a JSON array.\n");
    exit(2);
}

// Resolve the seeded admin's aid via a live lookup — the same
// shape `seed-comms-e2e.php` uses, for the same reason
// (`Fixture::adminAid()` only carries the value within the PHP
// process that called `seedAdmin()`; this shim runs in a fresh
// process driven by Playwright).
$adminRow = $GLOBALS['PDO']->query(
    "SELECT aid FROM `:prefix_admins` WHERE user = ? AND authid = ?"
)->single(['admin', 'STEAM_0:0:0']);
if (empty($adminRow['aid'])) {
    fwrite(STDERR, "seed-comments-e2e.php: cannot resolve admin row; was Fixture::install() run?\n");
    exit(2);
}
$adminAid = (int)$adminRow['aid'];

$now = time();

foreach ($rows as $i => $row) {
    if (!is_array($row)) {
        fwrite(STDERR, "seed-comments-e2e.php: row $i is not an object.\n");
        exit(2);
    }

    $type = (string)($row['type'] ?? '');
    $bid  = (int)($row['bid']  ?? 0);
    $text = (string)($row['text'] ?? '');

    if (!in_array($type, ['B', 'C'], true)) {
        fwrite(STDERR, "seed-comments-e2e.php: row $i has unknown type '$type' (expected 'B' or 'C').\n");
        exit(2);
    }
    if ($bid <= 0) {
        fwrite(STDERR, "seed-comments-e2e.php: row $i has missing/invalid bid.\n");
        exit(2);
    }
    if ($text === '') {
        fwrite(STDERR, "seed-comments-e2e.php: row $i has empty text.\n");
        exit(2);
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_comments` "
        . "(type, bid, aid, commenttxt, added) "
        . "VALUES (?, ?, ?, ?, ?)"
    )->execute([$type, $bid, $adminAid, $text, $now]);
}

fwrite(STDOUT, "seeded " . count($rows) . " comments on " . DB_NAME . "\n");
