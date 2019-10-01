<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2019 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	GNU GENERAL PUBLIC LICENSE Version 3.

	You should have received a copy of the license along with this
	work.  If not, see <https://www.gnu.org/licenses/gpl-3.0.txt>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s):
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under GPLv3
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/
use xPaw\SourceQuery\SourceQuery;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return string URL
 */
function CreateLinkR($title, $url, $tooltip="", $target="_self", $wide=false, $onclick="")
{
    if ($wide) {
        $class = "perm";
    } else {
        $class = "tip";
    }
    if (strlen($tooltip) == 0) {
        return "<a href='{$url}' onclick='{$onclick}' target='{$target}'> {$title} </a>";
    } else {
        return "<a href='{$url}' class='{$class}' title='{$title} :: {$tooltip }' target='{$target}'> {$title} </a>";
    }
}

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

    return $out;
}


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

    return $out;
}

function NextSid()
{
    $sid = $GLOBALS['db']->GetRow("SELECT MAX(sid) AS next_sid FROM `" . DB_PREFIX . "_servers`");
    return ($sid['next_sid']+1);
}
function NextAid()
{
    $aid = $GLOBALS['db']->GetRow("SELECT MAX(aid) AS next_aid FROM `" . DB_PREFIX . "_admins`");
    return ($aid['next_aid']+1);
}

function trunc(string $text, int $len)
{
    return (strlen($text) > $len) ? substr($text, 0, $len).'...' : $text;
}

function CheckAdminAccess($mask)
{
    global $userbank;
    if (!$userbank->HasAccess($mask)) {
        header("Location: index.php?p=login&m=no_access");
        die();
    }
}

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

function FetchIp($ip)
{
    $ip = sprintf('%u', ip2long($ip));
    if (!isset($_SESSION['CountryFetchHndl']) || !is_resource($_SESSION['CountryFetchHndl'])) {
        $handle = fopen(INCLUDES_PATH.'/IpToCountry.csv', "r");
        $_SESSION['CountryFetchHndl'] = $handle;
    } else {
        $handle = $_SESSION['CountryFetchHndl'];
        rewind($handle);
    }

    if (!$handle) {
        return "zz";
    }

    while (($ipdata = fgetcsv($handle, 4096)) !== false) {
        // If line is comment or IP is out of range
        if ($ipdata[0][0] == '#' || $ip < $ipdata[0] || $ip > $ipdata[1]) {
            continue;
        }

        if (empty($ipdata[4])) {
            return "zz";
        }
        return $ipdata[4];
    }

	return "zz";
}

function PageDie()
{
    require_once(TEMPLATES_PATH.'/core/footer.php');
    die();
}

function GetMapImage($map)
{
    $map = (@file_exists(SB_MAP_LOCATION."/$map.jpg")) ? $map : 'nomap';
    return SB_MAP_LOCATION."/$map.jpg";
}

function checkExtension($file, array $validExts)
{
    $file = pathinfo($file, PATHINFO_EXTENSION);
    return in_array(strtolower($file), $validExts);
}

function PruneBans()
{
    global $userbank;
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_bans` SET `RemovedBy` = 0, `RemoveType` = 'E', `RemovedOn` = UNIX_TIMESTAMP()
        WHERE `length` != 0 AND `ends` < UNIX_TIMESTAMP() AND `RemoveType` IS NULL"
    );
    $GLOBALS['PDO']->execute();
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_protests` SET `archiv` = 3, `archivedby` = :id
        WHERE `archiv` = 0 AND bid IN(SELECT bid FROM `:prefix_bans` WHERE `RemoveType` = 'E')"
    );
    $GLOBALS['PDO']->bind(':id', $userbank->GetAid() < 0 ? 0 : $userbank->GetAid());
    $GLOBALS['PDO']->execute();
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_submissions` SET `archiv` = 3, `archivedby` = :id
        WHERE `archiv` = 0 AND
        (SteamId IN(SELECT authid FROM `:prefix_bans` WHERE type = 0 AND RemoveType IS NULL)
        OR sip IN(SELECT ip FROM `:prefix_bans` WHERE type = 1 AND RemoveType IS NULL))"
    );
    $GLOBALS['PDO']->bind(':id', $userbank->GetAid() < 0 ? 0 : $userbank->GetAid());
    $GLOBALS['PDO']->execute();
}

function PruneComms()
{
    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comms` SET `RemovedBy` = 0, `RemoveType` = 'E', `RemovedOn` = UNIX_TIMESTAMP()
        WHERE `length` != 0 AND `ends` < UNIX_TIMESTAMP() AND `RemoveType` IS NULL"
    );
    $GLOBALS['PDO']->execute();
}

function getDirSize($dir)
{
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $object) {
        $size += is_file($object) ? filesize($object) : getDirSize($object);
    }
    return sizeFormat((int)$size);
}

function sizeFormat($bytes)
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), [0, 0, 2, 2, 3][$i]).[' B', ' kB', ' MB', ' GB', ' TB'][$i];
}

//function to check for multiple steamids on one server.
// param $steamids needs to be an array of steamids.
//returns array('STEAM_ID_1' => array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping), 'STEAM_ID_2' => array()....)
function checkMultiplePlayers(int $sid, $steamids)
{
    $ret = rcon('status', $sid);

    if (!$ret)
        return false;

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

function GetCommunityName($steamid)
{
    $endpoint = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".STEAMAPIKEY.'&steamids='.\SteamID\SteamID::toSteam64($steamid);
    $data = json_decode(file_get_contents($endpoint), true);
    return (isset($data['response']['players'][0]['personaname'])) ? $data['response']['players'][0]['personaname'] : '';
}

function rcon(string $cmd, int $sid)
{
    $GLOBALS['PDO']->query("SELECT ip, port, rcon FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['rcon']))
        return false;

    $output = "";
    $rcon = new SourceQuery();
    try {
        $rcon->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
        $rcon->setRconPassword($server['rcon']);

        $output = $rcon->Rcon($cmd);
    } catch (AuthenticationException $e) {
        $GLOBALS['PDO']->query("UPDATE `:prefix_servers` SET rcon = '' WHERE sid = :sid");
        $GLOBALS['PDO']->bind(':sid', $sid);
        $GLOBALS['PDO']->execute();

        Log::add('e', "Rcon Password Error [ServerID: $sid]", $e->getMessage);
        return false;
    } catch (Exception $e) {
        Log::add('e', "Rcon Error [ServerID: $sid]", $e->getMessage);
        return false;
    } finally {
        $rcon->Disconnect();
    }
    return $output;
}

function parseRconStatus(string $status)
{
    $regex = '/#\s*(\d+)(?>\s|\d)*"(.*)"\s*(STEAM_[01]:[01]:\d+|\[U:1:\d+\])(?>\s|:|\d)*[a-zA-Z]*\s*\d*\s*([0-9.]+)/';
    $players = [];

    foreach (preg_match_all($regex, $ret) as $player) {
        $players[] = [
            'id' => $player[1],
            'name' => $player[2],
            'steamid' => $player[3],
            'ip' => $player[4]
        ];
    }

    return $players;
}

function compareSanitizedString(string $str1, string $str2)
{
    return (bool)strcmp(filter_var($str1, FILTER_SANITIZE_STRING), filter_var($str2, FILTER_SANITIZE_STRING) === 0);
}
