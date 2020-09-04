<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
 *************************************************************************/

use MaxMind\Db\Reader;
use xPaw\SourceQuery\SourceQuery;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param  string $title   The title of the tooltip/text to link
 * @param  string $url     The link
 * @param  string $tooltip The tooltip message
 * @param  string $target  The new links target
 * @param  bool   $wide
 * @param  string $onclick
 * @return string URL
 */
function CreateLinkR($title, $url, $tooltip="", $target="_self", $wide=false, $onclick="")
{
    $class = ($wide) ? "perm" : "tip";

    if (strlen($tooltip) == 0) {
        return "<a href='{$url}' onclick=\"{$onclick}\" target='{$target}'> {$title} </a>";
    }
    return "<a href='{$url}' class='{$class}' title='{$tooltip}' target='{$target}'> {$title} </a>";
}

/**
 * @param  $mask
 * @return array|false
 */
function BitToString($mask)
{
    $perms = json_decode(file_get_contents(ROOT.'/configs/permissions/web.json'), true);

    if ($mask == 0) {
        return false;
    }

    foreach ($perms as $perm) {
        if (($mask & $perm['value']) != 0 || ($mask & ADMIN_OWNER) != 0) {
            if ($perm['value'] != ALL_WEB) {
                $out[] = $perm['display'];
            }
        }
    }

    return isset($out) ? $out : false;
}

/**
 * @param  $flagstring
 * @return array|false
 */
function SmFlagsToSb($flagstring)
{
    $flags = json_decode(file_get_contents(ROOT.'/configs/permissions/sourcemod.json'), true);

    if (empty($flagstring)) {
        return false;
    }

    foreach ($flags as $flag) {
        if (strstr($flagstring, $flag['value']) || strstr($flagstring, 'z')) {
            $out[] = $flag['display'];
        }
    }

    return isset($out) ? $out : false;
}

/**
 * @return int
 */
function NextSid()
{
    $sid = $GLOBALS['db']->GetRow("SELECT MAX(sid) AS next_sid FROM `" . DB_PREFIX . "_servers`");
    return ($sid['next_sid'] + 1);
}

/**
 * @return int
 */
function NextAid()
{
    $aid = $GLOBALS['db']->GetRow("SELECT MAX(aid) AS next_aid FROM `" . DB_PREFIX . "_admins`");
    return ($aid['next_aid'] + 1);
}

/**
 * @param  string $text
 * @param  int    $len
 * @return string
 */
function trunc(string $text, int $len)
{
    return (strlen($text) > $len) ? substr($text, 0, $len).'...' : $text;
}

/**
 * @param int $mask
 */
function CheckAdminAccess($mask)
{
    global $userbank;
    if (!$userbank->HasAccess($mask)) {
        header("Location: index.php?p=login&m=no_access");
        die();
    }
}

/**
 * @param  int  $sec
 * @param  bool $textual
 * @return false|string
 */
function SecondsToString($sec, $textual=true)
{
    if ($sec < 0) {
        return 'Session';
    }
    if ($textual) {
        $div = array( 2592000, 604800, 86400, 3600, 60, 1 );
        $desc = array('mo','wk','d','hr','min','sec');
        $ret = null;
        foreach ($div as $index => $value) {
            $quotent = floor($sec / $value); //greatest whole integer
            if ($quotent > 0) {
                $ret .= "$quotent {$desc[$index]}, ";
                $sec %= $value;
            }
        }
        return substr($ret, 0, -2);
    } else {
        $hours = floor($sec / 3600);
        $sec -= $hours * 3600;
        $mins = floor($sec / 60);
        $secs = $sec % 60;
        return "$hours:$mins:$secs";
    }
}

/**
 * @param  string $ip
 * @return mixed|string
 */
function FetchIp($ip)
{
    try {
        $reader = new Reader(MMDB_PATH);
        return $reader->get($ip)["country"]["iso_code"];
    }catch (Exception $e){
        return "zz";
    }
}

function PageDie()
{
    include_once TEMPLATES_PATH.'/core/footer.php';
    die();
}

/**
 * @param  string $map
 * @return string
 */
function GetMapImage($map)
{
    $map = (@file_exists(SB_MAP_LOCATION."/$map.jpg")) ? $map : 'nomap';
    return SB_MAP_LOCATION."/$map.jpg";
}

/**
 * @param  string $file
 * @param  array  $validExts
 * @return bool
 */
function checkExtension($file, array $validExts)
{
    $file = pathinfo($file, PATHINFO_EXTENSION);
    return in_array(strtolower($file), $validExts);
}


function PruneBans()
{
    global $userbank;
    $adminId = $userbank->GetAid() < 0 ? 0 : $userbank->GetAid();
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_bans` SET `RemovedBy` = 0, `RemoveType` = 'E', `RemovedOn` = UNIX_TIMESTAMP()
        WHERE `length` != 0 AND `ends` < UNIX_TIMESTAMP() AND `RemoveType` IS NULL"
    );
    $GLOBALS['PDO']->execute();
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_protests` SET `archiv` = 3, `archivedby` = :id
        WHERE `archiv` = 0 AND bid IN(SELECT bid FROM `:prefix_bans` WHERE `RemoveType` = 'E')"
    );
    $GLOBALS['PDO']->bind(':id', $adminId);
    $GLOBALS['PDO']->execute();

    // Break subqueries into individual selects to improve speed.
    $steamIDs = $GLOBALS['PDO']
        ->query('SELECT DISTINCT authid FROM `:prefix_bans` WHERE `type` = 0 AND `RemoveType` IS NULL')
        ->resultset(null, PDO::FETCH_COLUMN);
    $banIPs = $GLOBALS['PDO']
        ->query('SELECT ip FROM `:prefix_bans` WHERE type = 1 AND RemoveType IS NULL')
        ->resultset(null, PDO::FETCH_COLUMN);

    // If we have active steamid bans or ip bans, see if any non-archived submissions exist that
    // we can expire due to the user having been banned.
    if ($steamIDs || $banIPs) {
        $subsets = [];
        // Only include IN() statements if there are values
        if ($steamIDs) {
            $subsets[] = "SteamId IN(" . implode(',', array_fill(0, count($steamIDs), '?')) . ")";
        }
        if ($banIPs) {
            $subsets[] = "sip IN(" . implode(',', array_fill(0, count($banIPs), '?')) . ")";
        }
        // We don't actually want to run the UPDATE on this data, because UPDATE WHERE locks every row
        // it encounters during the WHERE check, not just the rows it needs to update.  Instead,
        // let's select a list of IDs to update.
        $query = "SELECT `subid` FROM `:prefix_submissions` WHERE `archiv` = 0 AND (" . implode(" OR ", $subsets) . ")";
        $subIds = $GLOBALS['PDO']->query($query)->resultset(array_merge($steamIDs, $banIPs), PDO::FETCH_COLUMN);

        if ($subIds) {
            // This can lock the whole table only if we have more results than the mysql query optimizer decides
            // it's worth using the index for.  From my experience, anything under 15000 results is never an issue.
            $query = "UPDATE `:prefix_submissions` SET `archiv` = 3, `archivedby` = ? WHERE `subid` IN("
                . implode(',', array_fill(0, count($subIds), '?'))
                . ")";

            $GLOBALS['PDO']->query($query)->execute(array_merge([$adminId], $subIds));
        }
    }
}


function PruneComms()
{
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comms` SET `RemovedBy` = 0, `RemoveType` = 'E', `RemovedOn` = UNIX_TIMESTAMP()
        WHERE `length` != 0 AND `ends` < UNIX_TIMESTAMP() AND `RemoveType` IS NULL"
    );
    $GLOBALS['PDO']->execute();
}

/**
 * @param  string $dir
 * @return string
 */
function getDirSize($dir)
{
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $object) {
        $size += is_file($object) ? filesize($object) : getDirSize($object);
    }
    return sizeFormat((int)$size);
}

/**
 * @param  int $bytes
 * @return string
 */
function sizeFormat($bytes)
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), [0, 0, 2, 2, 3][$i]).[' B', ' kB', ' MB', ' GB', ' TB'][$i];
}

/**
 * Check for multiple steamids on one server
 *
 * @param  int   $sid
 * @param  array $steamids
 * @return array array('STEAM_ID_1' => array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping), 'STEAM_ID_2' => array()....)
 */
function checkMultiplePlayers(int $sid, $steamids)
{
    $ret = rcon('status', $sid);

    if (!$ret) {
        return [];
    }

    $players = [];
    foreach (parseRconStatus($ret) as $player) {
        foreach ($steamids as $steam) {
            if (\SteamID\SteamID::compare($player['steamid'], $steam)) {
                $steamid = \SteamID\SteamID::toSteam2($player['steamid']);
                $players[$steamid] = [
                    'name' => $player['name'],
                    'steam' => $steamid,
                    'ip' => $player['ip']
                ];
            }
        }
    }
    return $players;
}

/**
 * @param  string $steamid
 * @return mixed|string
 */
function GetCommunityName($steamid)
{
    $endpoint = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".STEAMAPIKEY.'&steamids='.\SteamID\SteamID::toSteam64($steamid);
    $data = json_decode(file_get_contents($endpoint), true);
    return (isset($data['response']['players'][0]['personaname'])) ? $data['response']['players'][0]['personaname'] : '';
}

/**
 * @param  string $cmd
 * @param  int    $sid
 * @return false|string
 */
function rcon(string $cmd, int $sid)
{
    $GLOBALS['PDO']->query("SELECT ip, port, rcon FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['rcon'])) {
        return false;
    }

    $output = "";
    $rcon = new SourceQuery();
    try {
        $rcon->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $rcon->setRconPassword($server['rcon']);

        $output = $rcon->Rcon($cmd);
    } catch (\xPaw\SourceQuery\Exception\AuthenticationException $e) {
        $GLOBALS['PDO']->query("UPDATE `:prefix_servers` SET rcon = '' WHERE sid = :sid");
        $GLOBALS['PDO']->bind(':sid', $sid);
        $GLOBALS['PDO']->execute();

        Log::add('e', "Rcon Password Error [ServerID: $sid]", $e->getMessage());
        return false;
    } catch (Exception $e) {
        Log::add('e', "Rcon Error [ServerID: $sid]", $e->getMessage());
        return false;
    } finally {
        $rcon->Disconnect();
    }
    return $output;
}

/**
 * @param  string $status
 * @return array
 */
function parseRconStatus(string $status)
{
    $regex = '/#\s*(\d+)(?>\s|\d)*"(.*)"\s*(STEAM_[01]:[01]:\d+|\[U:1:\d+\])(?>\s|:|\d)*[a-zA-Z]*\s*\d*\s*([0-9.]+)/';
    $players = [];

    $result = [];
    preg_match_all($regex, $status, $result, PREG_SET_ORDER);

    foreach ($result as $player) {
        $players[] = [
            'id' => $player[1],
            'name' => $player[2],
            'steamid' => $player[3],
            'ip' => $player[4]
        ];
    }

    return $players;
}

/**
 * @param  string $str1
 * @param  string $str2
 * @return bool
 */
function compareSanitizedString(string $str1, string $str2)
{
    return (bool)(strcmp(filter_var($str1, FILTER_SANITIZE_STRING), filter_var($str2, FILTER_SANITIZE_STRING)) === 0);
}
