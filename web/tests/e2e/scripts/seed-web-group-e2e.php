<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E shim — insert a web group row for bulk assign tests.
 *
 * Usage (inside the web container):
 *
 *   echo '{"name":"E2E Web"}' | php seed-web-group-e2e.php
 *
 * Prints JSON: {"gid":N,"name":"..."}
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-web-group-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed a web group on DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-web-group-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "seed-web-group-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$name = trim((string) ($decoded['name'] ?? ''));
if ($name === '') {
    fwrite(STDERR, "seed-web-group-e2e.php: missing 'name' key.\n");
    exit(2);
}

$GLOBALS['PDO']->query(
    'INSERT INTO `:prefix_groups` (`type`, `name`, `flags`) VALUES (1, :name, 0)'
);
$GLOBALS['PDO']->bind(':name', $name);
$GLOBALS['PDO']->execute();

$GLOBALS['PDO']->query('SELECT LAST_INSERT_ID() AS gid');
$row = $GLOBALS['PDO']->single();
$gid = (int) ($row['gid'] ?? 0);
if ($gid <= 0) {
    fwrite(STDERR, "seed-web-group-e2e.php: insert failed.\n");
    exit(2);
}

fwrite(STDOUT, json_encode(['gid' => $gid, 'name' => $name], JSON_THROW_ON_ERROR) . "\n");
