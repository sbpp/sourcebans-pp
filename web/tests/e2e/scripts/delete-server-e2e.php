<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E server-deletion shim.
 *
 * Deletes a single `:prefix_servers` row by `sid`. **Deliberately
 * bypasses `api_servers_remove`** because that action runs a
 * cleanup cascade (`DELETE FROM :prefix_servers_groups WHERE
 * server_id = ?`, `DELETE FROM :prefix_admins_servers_groups WHERE
 * server_id = ?`, etc.) that defeats the test purpose of the
 * dangling-membership-row spec arm in
 * `admin-groups-server-cards-hydration.spec.ts`.
 *
 * The whole point of that spec arm is to prove the admin Server
 * Groups page's INNER JOIN (added in #1406) silently drops orphaned
 * `:prefix_servers_groups` rows — which means the test setup needs
 * to LEAVE the orphaned `:prefix_servers_groups` row in place AFTER
 * the server delete lands. The dispatcher's cleanup cascade would
 * make the orphan impossible to produce.
 *
 * Same e2e-only guardrails as `seed-server-group-e2e.php` /
 * `seed-comms-e2e.php` / `set-setting-e2e.php`: refuses any DB other
 * than the e2e schema (default `sourcebans_e2e`).
 *
 * Idempotent: deleting an already-deleted sid is a no-op (the
 * `DELETE` matches zero rows). The shim exits 0 either way and
 * surfaces the actual rowcount on stdout so the caller can sanity-
 * check whether the row existed when expected.
 *
 * Usage (inside the web container):
 *
 *   echo '{"sid":7}' | php delete-server-e2e.php
 *
 * Output on stdout (single JSON line):
 *
 *   {"sid":7,"deleted":1}
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "delete-server-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to delete server against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "delete-server-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "delete-server-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$sid = (int)($decoded['sid'] ?? 0);
if ($sid <= 0) {
    fwrite(STDERR, "delete-server-e2e.php: missing or invalid `sid` in payload.\n");
    exit(2);
}

// Raw DELETE — NO cascade. The whole point of this shim is to
// produce a `:prefix_servers_groups` row that points at a sid which
// no longer exists in `:prefix_servers`, so the panel handler's
// INNER JOIN can be exercised against an actual orphan. Using
// `api_servers_remove` here would silently clean up the
// membership row in the same transaction and the spec arm would
// have nothing to test.
$GLOBALS['PDO']->query("DELETE FROM `:prefix_servers` WHERE sid = ?");
$GLOBALS['PDO']->execute([$sid]);

$result = [
    'sid'     => $sid,
    'deleted' => $GLOBALS['PDO']->rowCount(),
];

fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . "\n");
