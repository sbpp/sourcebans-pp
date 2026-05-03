<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Endpoints used by the admin.kickit.php iframe — the page-by-page
"is the player still on this server, then kick them" loop.
*************************************************************************/

use SteamID\SteamID;

/**
 * Lists every enabled server with its rcon status. Replaces the legacy
 * LoadServers() that emitted addScript() calls per row.
 *
 * @return array{servers: array<int, array{num:int, sid:int, has_rcon:bool}>}
 */
function api_kickit_load_servers(array $params): array
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

/**
 * Try to kick a single player on a single server. Used per-row by the
 * iframe's per-server JS loop.
 *
 * @return array{
 *   status: 'kicked'|'not_found'|'no_connect',
 *   hostname: string,
 *   ip?: string,
 *   port?: string,
 *   sid: int,
 *   num: int,
 * }
 */
function api_kickit_kick_player(array $params): array
{
    $check = (string)($params['check'] ?? '');
    $sid   = (int)($params['sid']   ?? 0);
    $num   = (int)($params['num']   ?? 0);
    $type  = (int)($params['type']  ?? 0);

    $serverInfo = $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $sdata = $GLOBALS['PDO']->single() ?: ['ip' => '', 'port' => ''];

    $ret = rcon('status', $sid);
    if (!$ret) {
        return [
            'status' => 'no_connect',
            'sid'    => $sid,
            'num'    => $num,
            'ip'     => $sdata['ip'],
            'port'   => $sdata['port'],
            'hostname' => '',
        ];
    }

    if (preg_match('/hostname:[ ]*(.+)/', $ret, $hostname)) {
        $hostname = trunc(htmlspecialchars($hostname[1]), 25);
    } else {
        $hostname = '';
    }

    foreach (parseRconStatus($ret) as $player) {
        if ($type === 0) {
            if (SteamID::compare($player['steamid'], $check)) {
                $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET sid = :sid WHERE authid = :authid AND RemovedBy IS NULL");
                $GLOBALS['PDO']->bind(':sid', $sid);
                $GLOBALS['PDO']->bind(':authid', $check);
                $GLOBALS['PDO']->execute();

                $domain = Host::complete();
                rcon("kickid {$player['id']} \"You have been banned by this server, check $domain for more info\"", $sid);

                return ['status' => 'kicked', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
            }
        } elseif ($type === 1) {
            if (($player['ip'] ?? null) === $check) {
                $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET sid = :sid WHERE ip = :ip AND RemovedBy IS NULL");
                $GLOBALS['PDO']->bind(':sid', $sid);
                $GLOBALS['PDO']->bind(':ip', $check);
                $GLOBALS['PDO']->execute();

                $domain = Host::complete();
                rcon("kickid {$player['id']} \"You have been banned by this server, check $domain for more info\"", $sid);

                return ['status' => 'kicked', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
            }
        }
    }

    return ['status' => 'not_found', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
}
