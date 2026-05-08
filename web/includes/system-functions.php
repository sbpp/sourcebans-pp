<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

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
 * Creates an anchor tag, and adds tooltip code if needed.
 */
function CreateLinkR(string $title, string $url, string $tooltip = "", string $target = "_self", bool $wide = false, string $onclick = ""): string
{
    $class = ($wide) ? "perm" : "tip";

    if (strlen((string) $tooltip) == 0) {
        return "<a href='{$url}' onclick=\"{$onclick}\" target='{$target}'> {$title} </a>";
    }
    return "<a href='{$url}' class='{$class}' title='{$tooltip}' target='{$target}'> {$title} </a>";
}

function BitToString(int $mask): array|false
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

function SmFlagsToSb(string $flagstring): array|false
{
    $flags = json_decode(file_get_contents(ROOT.'/configs/permissions/sourcemod.json'), true);

    if (empty($flagstring)) {
        return false;
    }

    foreach ($flags as $flag) {
        if (str_contains($flagstring, $flag['value']) || str_contains($flagstring, 'z')) {
            $out[] = $flag['display'];
        }
    }

    return isset($out) ? $out : false;
}

function NextSid(): int
{
    $sid = $GLOBALS['PDO']->query("SELECT MAX(sid) AS next_sid FROM `:prefix_servers`")->single();
    return ($sid['next_sid'] + 1);
}

function NextAid(): int
{
    $aid = $GLOBALS['PDO']->query("SELECT MAX(aid) AS next_aid FROM `:prefix_admins`")->single();
    return ($aid['next_aid'] + 1);
}

function trunc(string $text, int $len): string
{
    return (strlen($text) > $len) ? substr($text, 0, $len).'...' : $text;
}

function CheckAdminAccess(int $mask): void
{
    global $userbank;
    if (!$userbank->HasAccess($mask)) {
        header("Location: index.php?p=login&m=no_access");
        die();
    }
}

function SecondsToString(int $sec, bool $textual = true): string
{
    if ($sec < 0) {
        return 'Session';
    }
    if ($textual) {
        $div = [2592000, 604800, 86400, 3600, 60, 1];
        $desc = ['mo', 'wk', 'd', 'hr', 'min', 'sec'];
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

function FetchIp(string $ip): mixed
{
    try {
        $reader = new Reader(MMDB_PATH);
        return $reader->get($ip)["country"]["iso_code"];
    }catch (Exception $e){
        return "zz";
    }
}

function PageDie(): never
{
    include_once TEMPLATES_PATH.'/core/footer.php';
    die();
}

function GetMapImage(string $map): string
{
    $map = (@file_exists(SB_MAP_LOCATION."/$map.jpg")) ? $map : 'nomap';
    return SB_MAP_LOCATION."/$map.jpg";
}

function checkExtension(string $file, array $validExts): bool
{
    $file = pathinfo($file, PATHINFO_EXTENSION);
    return in_array(strtolower($file), $validExts);
}


function PruneBans(): void
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


function PruneComms(): void
{
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comms` SET `RemovedBy` = 0, `RemoveType` = 'E', `RemovedOn` = UNIX_TIMESTAMP()
        WHERE `length` != 0 AND `ends` < UNIX_TIMESTAMP() AND `RemoveType` IS NULL"
    );
    $GLOBALS['PDO']->execute();
}

/**
 * Human-readable size of every file under `$dir`, recursively.
 *
 * The actual byte-count traversal lives in {@see getDirSizeBytes()};
 * this thin wrapper formats the total via {@see sizeFormat()}. Keeping
 * the recursion in a typed-int helper avoids the legacy bug where the
 * inner recursive call returned a `sizeFormat()` string and `+=`'d it
 * back into `$size` (PHP 8 warned "non-numeric value encountered"
 * and undercounted any tree with nested subdirectories — e.g. a
 * `web/demos/<server>/<demo>.dem` layout would lose the per-server
 * subtotals).
 */
function getDirSize(string $dir): string
{
    return sizeFormat(getDirSizeBytes($dir));
}

/**
 * Recursive byte-count of every file under `$dir`. Returns a strict
 * int so callers can `+=` it without tripping PHP 8's
 * "non-numeric value encountered" warning. {@see getDirSize()} wraps
 * this with {@see sizeFormat()} for the user-visible string.
 */
function getDirSizeBytes(string $dir): int
{
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $object) {
        $size += is_file($object) ? (int) filesize($object) : getDirSizeBytes($object);
    }
    return $size;
}

function sizeFormat(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), [0, 0, 2, 2, 3][$i]).[' B', ' kB', ' MB', ' GB', ' TB'][$i];
}

/**
 * Check for multiple steamids on one server. The returned array is keyed by
 * STEAM_ID and each row carries `name`, `steam`, `ip` (and historically
 * `time`, `ping` — only the first three are populated by the rcon parser).
 *
 * @return array<string, array{name: string, steam: string, ip: string}> Keyed by Steam2 id, e.g. ['STEAM_0:0:1' => ['name' => …, 'steam' => …, 'ip' => …], …].
 */
function checkMultiplePlayers(int $sid, array $steamids): array
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

function GetCommunityName(string $steamid): string
{
    $endpoint = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".STEAMAPIKEY.'&steamids='.\SteamID\SteamID::toSteam64($steamid);
    $data = json_decode(file_get_contents($endpoint), true);
    return (isset($data['response']['players'][0]['personaname'])) ? $data['response']['players'][0]['personaname'] : '';
}

function rcon(string $cmd, int $sid): false|string
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
        Log::add("m", "RCON Sent", sprintf("RCON Command (%s) was sent to server (%s:%d)", $cmd, $server['ip'], $server['port']));
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

function parseRconStatus(string $status): array
{
    $regex = '/#\s*(\d+)(?>\s|\d)*"(.*)"\s*(STEAM_[01]:[01]:\d+|\[U:1:\d+\])(?>\s|:|\d)*[a-zA-Z]*\s*\d*\s([0-9.]+)/';
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

function encodePreservingBr(string $text): string {
    // Split the text at <br> tags, preserving the tags in the result
    $parts = preg_split('/(<br\s*\/?>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = '';
    
    foreach ($parts as $part) {
        if (preg_match('/^<br\s*\/?>$/i', $part)) {
            $result .= "\n"; // Replace <br /> with newline
        } else {
            $result .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8'); // Encode the rest
        }
    }
    
    return nl2br($result);  // Convert newlines back to <br /> for HTML
}