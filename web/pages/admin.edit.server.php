<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

global $userbank, $theme;

new \Sbpp\View\AdminTabs([], $userbank, $theme);

require_once __DIR__ . '/_admin_edit_helpers.php';

$serverId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($serverId <= 0) {
    sbpp_admin_edit_die_with_toast(
        'No server id specified. Please only follow links.',
        'index.php?p=admin&c=servers',
    );
    return;
}

$pdo = $GLOBALS['PDO'];

$server = $pdo->query('SELECT * FROM `:prefix_servers` WHERE sid = :sid')
    ->single([':sid' => $serverId]);
if (!$server) {
    \Sbpp\Log::add(
        \LogType::Error,
        'Getting server data failed',
        "Can't find data for server with id {$serverId}.",
    );
    sbpp_admin_edit_die_with_toast(
        'Error getting current data.',
        'index.php?p=admin&c=servers',
    );
    return;
}

if (!$userbank->HasAccess(\WebPermission::mask(\WebPermission::Owner, \WebPermission::EditServers))) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . " tried to edit a server, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit servers.",
        'index.php?p=admin&c=servers',
    );
    return;
}

// "Don't change the rcon password" sentinel — the form pre-fills the
// rcon inputs with this so the legacy "leave it alone unless you
// retype it" behaviour survives the rewrite.
const SBPP_RCON_UNCHANGED = '+-#*_';

/** @var array<string,string> $validationErrors */
$validationErrors = [];
$postSuccess      = false;

if (isset($_POST['address'])) {
    \CSRF::rejectIfInvalid();

    $rawAddress = trim((string) $_POST['address']);
    $rawPort    = trim((string) ($_POST['port']    ?? ''));
    $rawMod     = (int)   ($_POST['mod']     ?? 0);
    $rawRcon    =        (string) ($_POST['rcon']  ?? '');
    $rawRcon2   =        (string) ($_POST['rcon2'] ?? '');
    $enabled    = isset($_POST['enabled'])
        && in_array((string) $_POST['enabled'], ['on', '1', 'true'], true);

    if ($rawAddress === '') {
        $validationErrors['address'] = 'You must type the server address.';
    } elseif (!filter_var($rawAddress, FILTER_VALIDATE_IP)
        && !filter_var($rawAddress, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        // #1433 follow-up — keep this validator byte-for-byte symmetric
        // with `api_servers_add` (the JSON dispatcher). Pre-fix this
        // surface ran a hand-rolled `^[a-zA-Z0-9.\-]+$` regex which
        // accepted shapes the JSON handler rejects (leading hyphens,
        // double dots, etc.), so the same value would be accepted via
        // Edit but rejected via Add (and vice versa for valid hostnames
        // before the JSON handler grew its `FILTER_VALIDATE_DOMAIN`
        // arm). Both surfaces now share the IP || HOSTNAME filter pair.
        $validationErrors['address'] = 'You must type a valid IP or hostname.';
    } elseif (strlen($rawAddress) > 64) {
        // Schema width gate — see the matching comment in
        // `web/api/handlers/servers.php::api_servers_add`. The column
        // is `VARCHAR(64)` and MariaDB strict mode would otherwise
        // surface a generic 500 with no actionable copy AND skip the
        // audit-log entry below.
        $validationErrors['address'] = 'Server address must be at most 64 characters.';
    }

    if ($rawPort === '' || !ctype_digit($rawPort)
        || (int) $rawPort < 1 || (int) $rawPort > 65535) {
        $validationErrors['port'] = 'You must type a valid port number (1-65535).';
    }

    if ($rawRcon !== SBPP_RCON_UNCHANGED && $rawRcon !== $rawRcon2) {
        $validationErrors['rcon2'] = "The passwords don't match.";
    }

    if ($validationErrors === []) {
        $clash = $pdo->query(
            'SELECT sid FROM `:prefix_servers` WHERE ip = :ip AND port = :port AND sid != :sid'
        )->single([
            ':ip'   => $rawAddress,
            ':port' => (int) $rawPort,
            ':sid'  => $serverId,
        ]);
        if ($clash) {
            $validationErrors['address'] = 'There already is a server with that IP:Port combination.';
        }
    }

    $server['ip']      = $rawAddress;
    $server['port']    = (int) $rawPort;
    $server['modid']   = $rawMod;
    $server['enabled'] = $enabled ? 1 : 0;

    if ($validationErrors === []) {
        $pdo->query(
            'UPDATE `:prefix_servers`
                SET ip = :ip, port = :port, modid = :modid, enabled = :enabled
                WHERE sid = :sid'
        );
        $pdo->bindMultiple([
            ':ip'      => $rawAddress,
            ':port'    => (int) $rawPort,
            ':modid'   => $rawMod,
            ':enabled' => $enabled ? 1 : 0,
            ':sid'     => $serverId,
        ]);
        $pdo->execute();

        if ($rawRcon !== SBPP_RCON_UNCHANGED) {
            $pdo->query('UPDATE `:prefix_servers` SET rcon = :rcon WHERE sid = :sid');
            $pdo->bindMultiple([':rcon' => $rawRcon, ':sid' => $serverId]);
            $pdo->execute();
        }

        // Replace the server's `:prefix_servers_groups` membership
        // wholesale — the legacy code did the same DELETE-then-INSERT
        // sweep but row-by-row. Wrap in a transaction so a half-applied
        // state is impossible.
        $pdo->beginTransaction();
        try {
            $pdo->query('DELETE FROM `:prefix_servers_groups` WHERE server_id = :sid');
            $pdo->bind(':sid', $serverId);
            $pdo->execute();

            if (isset($_POST['groups']) && is_array($_POST['groups'])) {
                foreach ($_POST['groups'] as $g) {
                    $gid = (int) $g;
                    if ($gid <= 0) continue;
                    $pdo->query(
                        'INSERT INTO `:prefix_servers_groups` (server_id, group_id)
                            VALUES (:sid, :gid)'
                    );
                    $pdo->bindMultiple([':sid' => $serverId, ':gid' => $gid]);
                    $pdo->execute();
                }
            }
            $pdo->endTransaction();
        } catch (\Throwable $e) {
            $pdo->cancelTransaction();
            throw $e;
        }

        \Sbpp\Log::add(
            \LogType::Message,
            'Server Updated',
            "Server ({$rawAddress}:{$rawPort}) has been updated.",
        );
        $postSuccess = true;
    }
}

$assignedGroups = [];
$rows = $pdo->query('SELECT group_id FROM `:prefix_servers_groups` WHERE server_id = :sid')
    ->resultset([':sid' => $serverId]);
foreach ($rows as $row) {
    $gid = (int) ($row['group_id'] ?? 0);
    if ($gid > 0 && !in_array($gid, $assignedGroups, true)) {
        $assignedGroups[] = $gid;
    }
}

// On the post-submit re-render after a validation failure, fall back
// to the values the operator just typed instead of the row currently
// on disk.
if (isset($_POST['address']) && isset($_POST['groups']) && is_array($_POST['groups'])) {
    $assignedGroups = array_values(array_unique(array_map('intval', $_POST['groups'])));
}

$modList   = $pdo->query(
    'SELECT mid, name FROM `:prefix_mods`
        WHERE mid > 0 AND enabled = 1 ORDER BY name ASC'
)->resultset();
$groupList = $pdo->query(
    "SELECT gid, name FROM `:prefix_groups` WHERE type = 3 ORDER BY name ASC"
)->resultset();

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminServersAddView(
    permission_addserver: $userbank->HasAccess(
        \WebPermission::mask(\WebPermission::Owner, \WebPermission::AddServer)
    ),
    edit_server:          true,
    ip:                   (string) $server['ip'],
    port:                 (string) $server['port'],
    rcon:                 SBPP_RCON_UNCHANGED,
    modid:                (string) $server['modid'],
    modlist:              $modList,
    grouplist:            $groupList,
    submit_text:          'Update Server',
    enabled:              (bool) $server['enabled'],
    assigned_groups:      $assignedGroups,
));

sbpp_admin_edit_emit_tail_script(
    successTitle:    'Server updated',
    successBody:     'The server has been updated successfully.',
    successRedirect: 'index.php?p=admin&c=servers',
    postSuccess:     $postSuccess,
    rehashSids:      [],
    validationErrors:$validationErrors,
);
