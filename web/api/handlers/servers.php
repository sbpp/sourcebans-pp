<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use xPaw\SourceQuery\SourceQuery;

function api_servers_add(array $params): array
{
    $ip      = (string)($params['ip'] ?? '');
    $port    = (string)($params['port'] ?? '');
    $rcon    = (string)($params['rcon'] ?? '');
    $rcon2   = (string)($params['rcon2'] ?? '');
    $mod     = (int)($params['mod'] ?? -2);
    $enabledRaw = $params['enabled'] ?? null;
    $enabled    = $enabledRaw === true || $enabledRaw === 'true';
    $group   = (string)($params['group'] ?? '');

    if ($ip === '') {
        throw new ApiError('validation', 'You must type the server address.', 'address');
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new ApiError('validation', 'You must type a valid IP.', 'address');
    }
    if ($port === '') {
        throw new ApiError('validation', 'You must type the server port.', 'port');
    }
    if (!is_numeric($port)) {
        throw new ApiError('validation', 'You must type a valid port number.', 'port');
    }
    if (!empty($rcon) && $rcon !== $rcon2) {
        throw new ApiError('validation', "The passwords don't match.", 'rcon2');
    }
    if ($mod === -2) {
        throw new ApiError('validation', 'You must select the mod your server runs.', 'mod');
    }
    if ($group === '-2') {
        throw new ApiError('validation', 'You must select an option.', 'group');
    }

    $chk = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE ip = ? AND port = ?;")
        ->single([$ip, (int)$port]);
    if ($chk) {
        throw new ApiError('duplicate', 'There already is a server with that IP:Port combination.');
    }

    $sid = nextSid();
    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_servers` (`sid`, `ip`, `port`, `rcon`, `modid`, `enabled`)
        VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$sid, $ip, (int)$port, $rcon, $mod, $enabled ? 1 : 0]);

    foreach (explode(',', $group) as $g) {
        if ($g !== '') {
            $GLOBALS['PDO']->query(
                "INSERT INTO `:prefix_servers_groups` (`server_id`, `group_id`) VALUES (?, ?)"
            )->execute([$sid, $g]);
        }
    }

    Log::add('m', 'Server Added', "Server ($ip:$port) has been added.");

    return [
        'sid'     => $sid,
        'reload'  => true,
        'message' => [
            'title' => 'Server Added',
            'body'  => 'Your server has been successfully created.',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=servers',
        ],
    ];
}

function api_servers_remove(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $info = $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $info = $GLOBALS['PDO']->single();

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $ok = $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers_groups` WHERE server_id = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins_servers_groups` SET server_id = -1 WHERE server_id = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $GLOBALS['PDO']->execute();

    $cnt = (int)($GLOBALS['PDO']->query("SELECT count(sid) AS cnt FROM `:prefix_servers`")->single()['cnt'] ?? 0);

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was a problem deleting the server from the database. Check the logs for more info');
    }

    Log::add('m', 'Server Deleted', "Server ({$info['ip']}:{$info['port']}) has been deleted.");

    return [
        'remove'  => "sid_$sid",
        'counter' => ['srvcount' => $cnt],
        'message' => [
            'title' => 'Server Deleted',
            'body'  => 'The selected server has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=servers',
        ],
    ];
}

function api_servers_setup_edit(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $server = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $server = $GLOBALS['PDO']->single();
    if (!$server) {
        throw new ApiError('not_found', 'Server not found');
    }

    return [
        'sid'   => (int)$server['sid'],
        'ip'    => $server['ip'],
        'port'  => $server['port'],
        'rcon'  => $server['rcon'],
        'mod'   => $server['modid'],
        'group' => $server['gid'] ?? 0,
    ];
}

function api_servers_refresh(array $params): array
{
    return ['sid' => (int)($params['sid'] ?? 0)];
}

/**
 * Returns server info + player list for the requested server. Pure data —
 * the client decides how to render it.
 */
function api_servers_host_players(array $params): array
{
    global $userbank;
    $sid           = (int)($params['sid'] ?? 0);
    $trunchostname = (int)($params['trunchostname'] ?? 48);

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['ip']) || empty($server['port'])) {
        throw new ApiError('not_found', 'Server not found');
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $info = $query->GetInfo();
        $info['HostName'] = preg_replace('/[\x00-\x1f]/', '', htmlspecialchars($info['HostName']));
        $players = $query->GetPlayers();
    } catch (\Throwable $e) {
        return [
            'sid'     => $sid,
            'ip'      => $server['ip'],
            'port'    => $server['port'],
            'error'   => 'connect',
            'is_owner' => $userbank->HasAccess(ADMIN_OWNER),
        ];
    } finally {
        $query->Disconnect();
    }

    $os = match ($info['Os']) {
        'w'     => 'fab fa-windows',
        'l'     => 'fab fa-linux',
        default => 'fas fa-server',
    };

    return [
        'sid'      => $sid,
        'ip'       => $server['ip'],
        'port'     => $server['port'],
        'hostname' => trunc($info['HostName'], $trunchostname),
        'players'  => (int)$info['Players'],
        'maxplayers' => (int)$info['MaxPlayers'],
        'map'      => basename($info['Map']),
        'mapfull'  => $info['Map'],
        'mapimg'   => GetMapImage($info['Map']),
        'os_class' => $os,
        'secure'   => (bool)$info['Secure'],
        'player_list' => array_map(fn($p) => [
            'id'     => $p['Id'],
            'name'   => $p['Name'],
            'frags'  => (int)$p['Frags'],
            'time'   => $p['Time'],
            'time_f' => $p['TimeF'],
        ], $players ?: []),
        'can_ban' => $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN),
    ];
}

function api_servers_host_property(array $params): array
{
    $sid           = (int)($params['sid'] ?? 0);
    $trunchostname = (int)($params['trunchostname'] ?? 48);

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();
    if (empty($server['ip']) || empty($server['port'])) {
        throw new ApiError('not_found', 'Server not found');
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $info = $query->GetInfo();
        $info['HostName'] = preg_replace('/[\x00-\x1f]/', '', htmlspecialchars($info['HostName']));
    } catch (\Throwable $e) {
        return ['ip' => $server['ip'], 'port' => $server['port'], 'error' => 'connect'];
    } finally {
        $query->Disconnect();
    }

    return ['hostname' => trunc($info['HostName'] ?: '', $trunchostname), 'ip' => $server['ip'], 'port' => $server['port']];
}

function api_servers_host_players_list(array $params): array
{
    $idsStr = (string)($params['sids'] ?? '');
    $ids = array_filter(explode(';', $idsStr));
    if (count($ids) < 1) {
        return ['lines' => []];
    }

    $lines = [];
    foreach ($ids as $sid) {
        $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
        $GLOBALS['PDO']->bind(':sid', (int)$sid, PDO::PARAM_INT);
        $server = $GLOBALS['PDO']->single();
        if (empty($server['ip']) || empty($server['port'])) {
            continue;
        }
        $query = new SourceQuery();
        try {
            $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
            $info = $query->GetInfo();
            $info['HostName'] = preg_replace('/[\x00-\x1f]/', '', htmlspecialchars($info['HostName']));
            $lines[] = trunc($info['HostName'] ?: '', 48);
        } catch (\Throwable $e) {
            $lines[] = "ERROR " . $server['ip'] . ':' . $server['port'];
        } finally {
            $query->Disconnect();
        }
    }
    return ['lines' => $lines];
}

function api_servers_players(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();
    if (empty($server['ip']) || empty($server['port'])) {
        return ['sid' => $sid, 'players' => []];
    }

    $query = new SourceQuery();
    try {
        $query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $players = $query->GetPlayers();
    } catch (\Throwable $e) {
        return ['sid' => $sid, 'players' => []];
    } finally {
        $query->Disconnect();
    }

    return [
        'sid' => $sid,
        'players' => array_map(fn($p) => [
            'name'  => $p['Name'],
            'frags' => (int)$p['Frags'],
            'time'  => $p['Time'],
        ], $players ?: []),
    ];
}

function api_servers_send_rcon(array $params): array
{
    $sid     = (int)($params['sid'] ?? 0);
    $command = (string)($params['command'] ?? '');
    $output  = (bool)($params['output'] ?? true);

    if ($command === '') {
        return ['kind' => 'noop'];
    }
    if ($command === 'clr') {
        return ['kind' => 'clear'];
    }
    if (stripos($command, 'rcon_password') !== false) {
        return ['kind' => 'append', 'text' => "> Error: You have to use this console. Don't try to cheat the rcon password!"];
    }

    $command = html_entity_decode($command, ENT_QUOTES);
    $ret = rcon($command, $sid);

    if (!$ret) {
        return ['kind' => 'append', 'text' => '> Error: Can\'t connect to server!'];
    }

    if (!$output) {
        return ['kind' => 'noop'];
    }

    $ret = str_replace("\n", '<br />', $ret);
    return ['kind' => 'append', 'text' => '-> ' . $command . '<br />' . $ret];
}
