<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E shim — point a ban at a known admin issuer and clear admin_name.
 *
 * Used to exercise the hard-delete snapshot path: mint a ban as the
 * logged-in owner via Actions.BansAdd, reassign aid to a disposable
 * admin, clear the snapshot column, then delete that admin through
 * the panel UI. After remove, bans.admin_name must carry the issuer
 * name so the banlist Admin cell never paints "deleted admin".
 *
 * Usage (inside the web container):
 *
 *   echo '{"bid":42,"aid":3}' | php reassign-ban-issuer-e2e.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to reassign a ban on DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$bid = isset($decoded['bid']) ? (int) $decoded['bid'] : 0;
$aid = isset($decoded['aid']) ? (int) $decoded['aid'] : 0;

if ($bid <= 0) {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php: missing / non-positive 'bid' key.\n");
    exit(2);
}
if ($aid <= 0) {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php: missing / non-positive 'aid' key.\n");
    exit(2);
}

$GLOBALS['PDO']->query('SELECT aid FROM `:prefix_admins` WHERE aid = :aid');
$GLOBALS['PDO']->bind(':aid', $aid);
$exists = $GLOBALS['PDO']->single();
if ($exists === false || $exists === null || $exists === []) {
    fwrite(STDERR, "reassign-ban-issuer-e2e.php: aid=$aid does not exist in :prefix_admins.\n");
    exit(2);
}

$GLOBALS['PDO']->query(
    'UPDATE `:prefix_bans` SET aid = :aid, admin_name = :empty WHERE bid = :bid'
);
$GLOBALS['PDO']->bindMultiple([
    ':aid'   => $aid,
    ':empty' => '',
    ':bid'   => $bid,
]);
$GLOBALS['PDO']->execute();

fwrite(STDOUT, "reassigned bid=$bid (aid → $aid, admin_name cleared) on " . DB_NAME . "\n");
