<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E server-group + bound-servers seeder.
 *
 * Some E2E specs need a `:prefix_groups WHERE type = 3` row with
 * N bound `:prefix_servers` rows so the admin Server Groups list
 * (`?p=admin&c=groups`) actually has cards to render. The dispatcher
 * actions cover this in principle (`Actions.GroupsAdd` with
 * `type=3`, then per-server `Actions.ServersAdd`, then... but there
 * is no JSON action to wire a server into a server-group's
 * `:prefix_servers_groups` membership; that's a master-detail UI
 * write path the panel exposes only through legacy form posts), so
 * driving the seed through the API alone leaves the
 * `:prefix_servers_groups` join table empty.
 *
 * This shim writes:
 *
 *   1. One `:prefix_groups (type=3, name=…)` row — the server group
 *      itself. Returns its `gid`.
 *   2. N `:prefix_servers (ip, port, ...)` rows — the bound servers.
 *      Returns the per-server `sid`s.
 *   3. N `:prefix_servers_groups (group_id, server_id)` rows wiring
 *      each seeded server into the seeded group.
 *
 * Returns the seeded `{gid, sids[], servers[]}` JSON on stdout so
 * the spec can stub the right `Actions.ServersHostPlayers` route
 * keyed on each sid.
 *
 * Same e2e-only guardrails as the sister `set-setting-e2e.php` /
 * `seed-comms-e2e.php` shims: refuses any DB other than the e2e
 * schema (default `sourcebans_e2e`).
 *
 * Usage (inside the web container):
 *
 *   echo '{"group":{"name":"alpha"},
 *          "servers":[{"ip":"203.0.113.1","port":27015},
 *                     {"ip":"203.0.113.2","port":27016,"enabled":false}]}' \
 *     | php seed-server-group-e2e.php
 *
 * Output on stdout (single JSON line):
 *
 *   {"gid":42,"sids":[7,8],
 *    "servers":[{"sid":7,"ip":"203.0.113.1","port":27015,"enabled":true},
 *               {"sid":8,"ip":"203.0.113.2","port":27016,"enabled":false}]}
 *
 * `enabled` defaults to `true` per server (matches the schema's
 * `:prefix_servers.enabled TINYINT NOT NULL DEFAULT '1'`). Pass
 * `"enabled": false` to seed a server that the admin Server Groups
 * card stack will tag with `data-server-skip="1"` + the visible
 * "Disabled" pill (#1406 post-review).
 *
 * IPs SHOULD be in the RFC 5737 documentation range (203.0.113.0/24,
 * 198.51.100.0/24, 192.0.2.0/24) so no real Source server can ever
 * answer the A2S probe — the spec stubs `Actions.ServersHostPlayers`
 * via `page.route` anyway, but the IPs are visible in the SSR
 * fallback and using a documentation range keeps the rendered
 * surface unambiguous.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-server-group-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed server-group against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-server-group-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "seed-server-group-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$group   = (array)($decoded['group']   ?? []);
$servers = (array)($decoded['servers'] ?? []);

$groupName = (string)($group['name'] ?? '');
if ($groupName === '') {
    fwrite(STDERR, "seed-server-group-e2e.php: missing group.name in payload.\n");
    exit(2);
}

if (!is_array($servers) || $servers === []) {
    fwrite(STDERR, "seed-server-group-e2e.php: empty `servers` array — pass at least one server.\n");
    exit(2);
}

// ---------------------------------------------------------------
// 1. Insert the server group. Mirrors `api_groups_add`'s type=3
//    branch (`INSERT INTO :prefix_groups (gid, type, name, flags)
//    VALUES (?, '3', ?, '0')`) including the "next gid is MAX+1"
//    computation, so the seeded row is shape-equivalent to one
//    produced by the dispatcher.
// ---------------------------------------------------------------
$nextGid = (int)($GLOBALS['PDO']->query(
    "SELECT MAX(gid) AS next_gid FROM `:prefix_groups`"
)->single()['next_gid'] ?? 0) + 1;

$GLOBALS['PDO']->query(
    "INSERT INTO `:prefix_groups` (`gid`, `type`, `name`, `flags`) VALUES (?, '3', ?, '0')"
)->execute([$nextGid, $groupName]);

// ---------------------------------------------------------------
// 2. Insert the servers. `:prefix_servers` has a UNIQUE KEY on
//    (ip, port); we let the INSERT fail-if-duplicate so spec
//    authors get a loud error on collision instead of silently
//    binding the seeded group to whatever sid happened to already
//    own the (ip, port) tuple.
// ---------------------------------------------------------------
$seededServers = [];
foreach ($servers as $i => $s) {
    if (!is_array($s)) {
        fwrite(STDERR, "seed-server-group-e2e.php: servers[$i] is not an object.\n");
        exit(2);
    }
    $ip   = (string)($s['ip']   ?? '');
    $port = (int)   ($s['port'] ?? 0);
    // `enabled` defaults to true (matches the schema default); pass
    // `false` to seed a server the admin Server Groups card stack
    // surfaces with `data-server-skip="1"` + the "Disabled" pill so
    // server-tile-hydrate.js short-circuits the per-tile probe.
    $enabled = !array_key_exists('enabled', $s) || (bool) $s['enabled'];

    if ($ip === '' || $port <= 0) {
        fwrite(STDERR, "seed-server-group-e2e.php: servers[$i] missing ip/port.\n");
        exit(2);
    }

    // The `:prefix_servers` schema doesn't carry a DEFAULT for
    // `rcon` / `modid`; we set both to empty / 1 (TF2 is the
    // canonical first mod in `data.sql`) so the INSERT lands
    // without surfacing the NOT NULL constraints in the test
    // output.
    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_servers` (`ip`, `port`, `rcon`, `modid`, `enabled`) VALUES (?, ?, ?, ?, ?)"
    )->execute([$ip, $port, '', 1, $enabled ? 1 : 0]);

    // PDO::lastInsertId() against the panel's `Database` wrapper —
    // the wrapper exposes the underlying lastInsertId via the
    // `lastInsertId()` method.
    $sid = (int) $GLOBALS['PDO']->lastInsertId();
    if ($sid <= 0) {
        fwrite(STDERR, "seed-server-group-e2e.php: lastInsertId returned 0 for servers[$i]; "
            . "the INSERT may have silently coalesced against the UNIQUE(ip,port) constraint.\n");
        exit(2);
    }

    $seededServers[] = ['sid' => $sid, 'ip' => $ip, 'port' => $port, 'enabled' => $enabled];
}

// ---------------------------------------------------------------
// 3. Wire each seeded server into the seeded group's membership.
//    `:prefix_servers_groups` has PRIMARY KEY (server_id, group_id)
//    so a duplicate insert would fail loudly; we don't expect
//    duplicates here because each seeded sid is brand-new from
//    step 2.
// ---------------------------------------------------------------
foreach ($seededServers as $row) {
    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_servers_groups` (`group_id`, `server_id`) VALUES (?, ?)"
    )->execute([$nextGid, $row['sid']]);
}

$result = [
    'gid'     => $nextGid,
    'sids'    => array_map(static fn (array $r): int => $r['sid'], $seededServers),
    'servers' => $seededServers,
];

fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . "\n");
