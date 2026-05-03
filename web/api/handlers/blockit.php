<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Endpoints used by the admin.blockit.php iframe.
*************************************************************************/

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
