<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
//
// Endpoints used by the admin.blockit.php iframe.

use SteamID\SteamID;

function api_blockit_load_servers(array $params): array
{
    $servers = $GLOBALS['PDO']
        ->query("SELECT sid, rcon FROM `:prefix_servers` WHERE enabled = 1 ORDER BY modid, sid")
        ->resultset();

    $out = [];
    $id  = 0;
    foreach ($servers as $s) {
        $out[] = [
            'num'      => $id,
            'sid'      => (int)$s['sid'],
            'has_rcon' => !empty($s['rcon']),
        ];
        $id++;
    }
    return ['servers' => $out];
}

function api_blockit_block_player(array $params): array
{
    $check  = (string)($params['check']  ?? '');
    $sid    = (int)($params['sid']    ?? 0);
    $num    = (int)($params['num']    ?? 0);
    $type   = (int)($params['type']   ?? 0);
    $length = (int)($params['length'] ?? 0);

    // #1423 follow-up #4 — gate `$check` shape BEFORE the
    // `SteamID::compare()` call below; the comms-block flow is always
    // Steam-ID-keyed (there is no "block by IP" path) so the gate is
    // unconditional. `compare()` routes through `toSteam64()` which
    // throws on any non-isValidID input; without this gate a hostile
    // caller posting `?check=garbage` 500s the handler instead of
    // getting the `not_found` envelope the iframe loop expects.
    if (!SteamID::isValidID($check)) {
        return [
            'status'   => 'not_found',
            'sid'      => $sid,
            'num'      => $num,
            'hostname' => '',
            'ip'       => '',
            'port'     => '',
        ];
    }

    $serverInfo = $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $sdata = $GLOBALS['PDO']->single() ?: ['ip' => '', 'port' => ''];

    $ret = rcon('status', $sid);
    if (!$ret) {
        return [
            'status'   => 'no_connect',
            'sid'      => $sid,
            'num'      => $num,
            'hostname' => '',
            'ip'       => $sdata['ip'],
            'port'     => $sdata['port'],
        ];
    }

    if (preg_match('/hostname:[ ]*(.+)/', $ret, $hostname)) {
        $hostname = trunc(htmlspecialchars($hostname[1]), 25);
    } else {
        $hostname = '';
    }

    foreach (parseRconStatus($ret) as $player) {
        if (SteamID::compare($player['steamid'], $check)) {
            $GLOBALS['PDO']->query("UPDATE `:prefix_comms` SET sid = :sid WHERE authid = :authid AND RemovedBy IS NULL");
            $GLOBALS['PDO']->bind(':sid', $sid);
            $GLOBALS['PDO']->bind(':authid', $check);
            $GLOBALS['PDO']->execute();

            rcon("sc_fw_block $type $length {$player['steamid']}", $sid);

            return ['status' => 'blocked', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
        }
    }

    return ['status' => 'not_found', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
}
