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

use SteamID\SteamID;

global $theme;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
$BansPerPage = SB_BANS_PER_PAGE;
$servers     = [];
global $userbank;
function setPostKey()
{
    if (isset($_SERVER['REMOTE_IP'])) {
        $_SESSION['banlist_postkey'] = md5($_SERVER['REMOTE_IP'] . time() . rand(0, 100000));
    } else {
        $_SESSION['banlist_postkey'] = md5(time() . rand(0, 100000));
    }
}
if (!isset($_SESSION['banlist_postkey']) || strlen($_SESSION['banlist_postkey']) < 4) {
    setPostKey();
}

$page     = 1;
$pagelink = "";

PruneBans();

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page     = intval($_GET['page']);
    $pagelink = "&page=" . $page;
}
if (isset($_GET['a']) && $_GET['a'] == "unban" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    //we have a multiple unban asking
    if (isset($_GET['bulk'])) {
        $bids = explode(",", $_GET['id']);
    } else {
        $bids = array(
            $_GET['id']
        );
    }
    $ucount = 0;
    $fail   = 0;
    foreach ($bids as $bid) {
        $bid = intval($bid);
        $GLOBALS['PDO']->query("SELECT a.aid, a.gid FROM `:prefix_bans` b INNER JOIN `:prefix_admins` a ON a.aid = b.aid WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $res = $GLOBALS['PDO']->single();
        if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN) && !($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res['gid'] == $userbank->GetProperty('gid'))) {
            $fail++;
            if (!isset($_GET['bulk'])) {
                die("You don't have access to this");
            }
            continue;
        }

        $GLOBALS['PDO']->query("SELECT b.ip, b.authid,
										b.name, b.created, b.sid, b.type, m.steam_universe, UNIX_TIMESTAMP() as now
										FROM `:prefix_bans` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										LEFT JOIN `:prefix_mods` m ON m.mid = s.modid
										WHERE b.bid = :bid AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP()) AND b.RemoveType IS NULL");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $row = $GLOBALS['PDO']->single();
        if (empty($row) || !$row) {
            $fail++;
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player Not Unbanned', 'The player was not unbanned, either already unbanned or not a valid ban.', 'red', 'index.php?p=banlist$pagelink');</script>";
                PageDie();
            }
            continue;
        }
        $unbanReason = htmlspecialchars(trim($_GET['ureason']));
        $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET
										`RemovedBy` = :removedby,
										`RemoveType` = 'U',
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = :ureason
										WHERE `bid` = :bid");
        $GLOBALS['PDO']->bindMultiple([
            ':removedby' => $userbank->GetAid(),
            ':ureason'   => $unbanReason,
            ':bid'       => $bid,
        ]);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '4' WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("SELECT s.sid, m.steam_universe FROM `:prefix_banlog` bl INNER JOIN `:prefix_servers` s ON s.sid = bl.sid INNER JOIN `:prefix_mods` m ON m.mid = s.modid WHERE bl.bid = :bid AND (UNIX_TIMESTAMP() - bl.time <= 300)");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $blocked = $GLOBALS['PDO']->resultset();
        foreach ($blocked as $tempban) {
            rcon(($row['type'] == 0 ? "removeid STEAM_" . $tempban['steam_universe'] . substr($row['authid'], 7) : "removeip " . $row['ip']), $tempban['sid']);
        }
        if (((int) $row['now'] - (int) $row['created']) <= 300 && $row['sid'] != "0" && !in_array($row['sid'], $blocked)) {
            rcon(($row['type'] == 0 ? "removeid STEAM_" . $row['steam_universe'] . substr($row['authid'], 7) : "removeip " . $row['ip']), $row['sid']);
        }

        if ($res) {
            $type = $row['type'] == 0 ? $row['authid'] : $row['ip'];
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player Unbanned', '" . $row['name'] . " ($type) has been unbanned from SourceBans.', 'green', 'index.php?p=banlist$pagelink');</script>";
            }
            Log::add("m", "Player Unbanned", "$row[name] ($type) has been unbanned.");
            $ucount++;
        } else {
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player NOT Unbanned', 'There was an error unbanning " . $row['name'] . "', 'red', 'index.php?p=banlist$pagelink', true);</script>";
            }
            $fail++;
        }
    }
    if (isset($_GET['bulk'])) {
        echo "<script>ShowBox('Players Unbanned', '$ucount players has been unbanned from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
    }
} elseif (isset($_GET['a']) && $_GET['a'] == "delete") {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }

    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_BAN)) {
        echo "<script>ShowBox('Error', 'You do not have access to this.', 'red', 'index.php?p=banlist$pagelink');</script>";
        PageDie();
    }
    //we have a multiple ban delete asking
    if (isset($_GET['bulk'])) {
        $bids = explode(",", $_GET['id']);
    } else {
        $bids = array(
            $_GET['id']
        );
    }
    $dcount = 0;
    $fail   = 0;
    foreach ($bids as $bid) {
        $bid    = intval($bid);
        $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE `demid` = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $demres = $GLOBALS['PDO']->single();
        if ($demres) {
            @unlink(SB_DEMOS . "/" . $demres["filename"]);
        }
        $GLOBALS['PDO']->query("SELECT s.sid, m.steam_universe FROM `:prefix_banlog` bl INNER JOIN `:prefix_servers` s ON s.sid = bl.sid INNER JOIN `:prefix_mods` m ON m.mid = s.modid WHERE bl.bid = :bid AND (UNIX_TIMESTAMP() - bl.time <= 300)");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $blocked = $GLOBALS['PDO']->resultset();
        $GLOBALS['PDO']->query("SELECT b.name, b.authid, b.created, b.sid, b.RemoveType, b.ip, b.type, m.steam_universe, UNIX_TIMESTAMP() AS now
										FROM `:prefix_bans` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										LEFT JOIN `:prefix_mods` m ON m.mid = s.modid
										WHERE b.bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $steam = $GLOBALS['PDO']->single();
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_banlog` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $GLOBALS['PDO']->execute();
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_bans` WHERE `bid` = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $res = $GLOBALS['PDO']->execute();
        if (empty($steam['RemoveType'])) {
            foreach ($blocked as $tempban) {
                rcon(($steam['type'] == 0 ? "removeid STEAM_" . $tempban['steam_universe'] . substr($steam['authid'], 7) : "removeip " . $steam['ip']), $tempban['sid']);
            }
            if (((int) $steam['now'] - (int) $steam['created']) <= 300 && $steam['sid'] != "0" && !in_array($steam['sid'], $blocked)) {
                rcon(($steam['type'] == 0 ? "removeid STEAM_" . $steam['steam_universe'] . substr($steam['authid'], 7) : "removeip " . $steam['ip']), $steam['sid']);
            }
        }

        if ($res) {
            $type = $steam['type'] == 0 ? $steam['authid'] : $steam['ip'];
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Ban Deleted', 'The ban for \'" . $steam['name'] . "\' ($type) has been deleted from SourceBans', 'green', 'index.php?p=banlist$pagelink');</script>";
            }
            Log::add("m", "Ban Deleted", "Ban $steam[name] ($type) has been deleted.");
            $dcount++;
        } else {
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Ban NOT Deleted', 'The ban for \'" . $steam['name'] . "\' had an error while being removed.', 'red', 'index.php?p=banlist$pagelink', true);</script>";
            }
            $fail++;
        }
    }
    if (isset($_GET['bulk'])) {
        echo "<script>ShowBox('Players Deleted', '$dcount players has been deleted from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
    }
}

$BansStart = intval(($page - 1) * $BansPerPage);
$BansEnd   = intval($BansStart + $BansPerPage);

// hide inactive bans feature
if (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "true") { // hide
    $_SESSION["hideinactive"] = true;
    //ShowBox('Hide inactive bans', 'Inactive bans will be hidden from the banlist.', 'green', 'index.php?p=banlist', true);
} elseif (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "false") { // show
    unset($_SESSION["hideinactive"]);
    //ShowBox('Show inactive bans', 'Inactive bans will be shown in the banlist.', 'green', 'index.php?p=banlist', true);
}
if (isset($_SESSION["hideinactive"])) {
    $hidetext      = "Show";
    $hideinactive  = " AND RemoveType IS NULL";
    $hideinactiven = " WHERE RemoveType IS NULL";
} else {
    $hidetext      = "Hide";
    $hideinactive  = "";
    $hideinactiven = "";
}


if (isset($_GET['searchText'])) {
    $searchText = trim($_GET['searchText']);

    try {
        SteamID::init();
        if (SteamID::isValidID($searchText)) {
            $conversionResult = SteamID::toSteam2($searchText);

            if ($conversionResult) {
                $searchText = $conversionResult;
            }
        }
    } catch (Exception $e) { }

    $search = "%{$searchText}%";

    // disable ip search if hiding player ips
    $search_ips   = "";
    $search_array = [];
    if (!Config::getBool('banlist.hideplayerips') || $userbank->is_admin()) {
        $search_ips     = "BA.ip LIKE ? OR ";
        $search_array[] = $search;
    }

    $res = $GLOBALS['PDO']->query("SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
            (SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
      WHERE " . $search_ips . "BA.authid LIKE ? or BA.name LIKE ? or BA.reason LIKE ?" . $hideinactive . "
   ORDER BY BA.created DESC
   LIMIT ?,?")->resultset(array_merge($search_array, [
        $search,
        $search,
        $search,
        intval($BansStart),
        intval($BansPerPage),
    ]));


    $res_count  = $GLOBALS['PDO']->query("SELECT count(BA.bid) AS cnt FROM `:prefix_bans` AS BA WHERE " . $search_ips . "BA.authid LIKE ? OR BA.name LIKE ? OR BA.reason LIKE ?" . $hideinactive)->resultset(array_merge($search_array, [
        $search,
        $search,
        $search,
    ]));
    $searchlink = "&searchText=" . urlencode($_GET["searchText"]);
} elseif (!isset($_GET['advSearch'])) {
    $res = $GLOBALS['PDO']->query("SELECT bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
			(SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
  " . $hideinactiven . "
   ORDER BY created DESC
   LIMIT ?,?")->resultset([
        intval($BansStart),
        intval($BansPerPage),
    ]);

    $res_count  = $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_bans`" . $hideinactiven)->resultset();
    $searchlink = "";
}

$advcrit = [];
if (isset($_GET['advSearch'])) {
    $value = trim($_GET['advSearch']);

    try {
        SteamID::init();
        if (SteamID::isValidID($value)) {
            $conversionResult = SteamID::toSteam2($value);

            if ($conversionResult) {
                $value = $conversionResult;
            }
        }
    } catch (Exception $e) { }

    $type  = $_GET['advType'];
    switch ($type) {
        case "name":
            $where   = "WHERE BA.name LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "banid":
            $where   = "WHERE BA.bid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "steamid":
            $where   = "WHERE BA.authid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "steam":
            $where   = "WHERE BA.authid LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "ip":
            // disable ip search if hiding player ips
            if (Config::getBool('banlist.hideplayerips') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE BA.ip LIKE ?";
                $advcrit = array(
                    "%$value%"
                );
            }
            break;
        case "reason":
            $where   = "WHERE BA.reason LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "date":
            $date    = explode(",", $value);
            $time    = mktime(0, 0, 0, (int)$date[1], (int)$date[0], (int)$date[2]);
            $time2   = mktime(23, 59, 59, (int)$date[1], (int)$date[0], (int)$date[2]);
            $where   = "WHERE BA.created > ? AND BA.created < ?";
            $advcrit = array(
                $time,
                $time2
            );
            break;
        case "length":
            $len         = explode(",", $value);
            $length_type = $len[0];
            $length      = (int)$len[1] * 60;
            $where       = "WHERE BA.length ";
            switch ($length_type) {
                case "e":
                    $where .= "=";
                    break;
                case "h":
                    $where .= ">";
                    break;
                case "l":
                    $where .= "<";
                    break;
                case "eh":
                    $where .= ">=";
                    break;
                case "el":
                    $where .= "<=";
                    break;
            }
            $where .= " ?";
            $advcrit = array(
                $length
            );
            break;
        case "btype":
            $where   = "WHERE BA.type = ?";
            $advcrit = array(
                $value
            );
            break;
        case "admin":
            if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE BA.aid=?";
                $advcrit = array(
                    $value
                );
            }
            break;
        case "where_banned":
            $where   = "WHERE BA.sid=?";
            $advcrit = array(
                $value
            );
            break;
        case "nodemo":
            $where   = "WHERE BA.aid = ? AND NOT EXISTS (SELECT DM.demid FROM " . DB_PREFIX . "_demos AS DM WHERE DM.demid = BA.bid)";
            $advcrit = array(
                $value
            );
            break;
        case "bid":
            $where   = "WHERE BA.bid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "comment":
            if ($userbank->is_admin()) {
                $where   = "WHERE CO.type = 'B' AND CO.commenttxt LIKE ?";
                $advcrit = array(
                    "%$value%"
                );
            } else {
                $where   = "";
                $advcrit = [];
            }
            break;
        default:
            $where             = "";
            $_GET['advType']   = "";
            $_GET['advSearch'] = "";
            $advcrit           = [];
            break;
    }

    // Make sure we got a "WHERE" clause there, if we add the hide inactive condition
    if (empty($where) && isset($_SESSION["hideinactive"])) {
        $hideinactive = $hideinactiven;
    }

    $res = $GLOBALS['PDO']->query("SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
            (SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CO ON BA.bid = CO.bid" : "") . "
      " . $where . $hideinactive . "
   ORDER BY BA.created DESC
   LIMIT ?,?")->resultset(array_merge($advcrit, [
        intval($BansStart),
        intval($BansPerPage),
    ]));

    $res_count  = $GLOBALS['PDO']->query("SELECT count(BA.bid) AS cnt FROM `:prefix_bans` AS BA
										  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CO ON BA.bid = CO.bid" : "") . " " . $where . $hideinactive)->resultset($advcrit);
    $searchlink = "&advSearch=" . urlencode($_GET['advSearch']) . "&advType=" . urlencode($_GET['advType']);
}

$BanCount = isset($res_count[0]['cnt']) ? (int) $res_count[0]['cnt'] : 0;
if ($BansEnd > $BanCount) {
    $BansEnd = $BanCount;
}
if (!$res) {
    echo "No Bans Found.";
    PageDie();
}

$canEditComment = false;
$view_comments = false;
$bans          = [];
foreach ($res as $row) {
    $data = [];

    $data['ban_id'] = $row['ban_id'];

    if (!empty($row['ban_ip']) && !Config::getBool('banlist.nocountryfetch')) {
        if (!empty($row['ban_country']) && $row['ban_country'] != ' ') {
            $data['country'] = '<img src="images/country/' . strtolower($row['ban_country']) . '.png" alt="' . $row['ban_country'] . '" border="0" align="absmiddle" />';
        } elseif (!Config::getBool('banlist.nocountryfetch')) {
            $country = FetchIp($row['ban_ip']);
            $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET country = ?
				                            WHERE bid = ?")->execute([
                $country,
                $row['ban_id'],
            ]);

            $countryFlag = empty($country) ? 'zz' : strtolower($country);
            $data['country'] = '<img src="images/country/' . $countryFlag . '.png" alt="' . $country . '" border="0" align="absmiddle" />';
        } else {
            $data['country'] = '<img src="images/country/zz.png" alt="Unknown Country" border="0" align="absmiddle" />';
        }
    } else {
        $data['country'] = '<img src="images/country/zz.png" alt="Unknown Country" border="0" align="absmiddle" />';
    }

    $data['ban_date']    = Config::time($row['ban_created']);

    // Fix #1008 - bug: Player Names Contain Unwanted Non-Standard Characters
    $raw_name = $row['player_name'];
    $cleaned_name = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);

    $data['player'] = addslashes($cleaned_name);
    $data['type']        = $row['type'];
    $data['steamid']     = $row['authid'];
    // Fix #900 - Bad SteamID Format broke the page view, so give them an null SteamID.
	if (isset($data['steamid']) && !empty($data['steamid']) && !\SteamID\SteamID::isValidID($data['steamid'])) {
		$data['steamid'] = 'STEAM_0:0:00000000';
	}
    $data['communityid'] = $row['community_id'];
    $data['steamid3']    = \SteamID\SteamID::toSteam3($data['steamid']);

    if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
        $data['admin'] = false;
    } else {
        $data['admin'] = stripslashes($row['admin_name']);
    }
    $data['reason']     = stripslashes($row['ban_reason']);
    $data['ban_length'] = $row['ban_length'] == 0 ? 'Permanent' : SecondsToString(intval($row['ban_length']));

    // Custom "listtable_1_banned" & "listtable_1_permanent" addition entries
    // Comment the 14 lines below out if they cause issues
    if ($row['ban_length'] == 0) {
        $data['expires']   = 'never';
        $data['class']     = "listtable_1_permanent";
        $data['ub_reason'] = "";
        $data['unbanned']  = false;
    } else {
        $data['expires']   = Config::time($row['ban_ends']);
        $data['class']     = "listtable_1_banned";
        $data['ub_reason'] = "";
        $data['unbanned']  = false;
    }
    // End custom entries

    if ($row['row_type'] == 'D' || $row['row_type'] == 'U' || $row['row_type'] == 'E' || ($row['ban_length'] && $row['ban_ends'] < time())) {
        $data['unbanned'] = true;
        $data['class']    = "listtable_1_unbanned";

        if ($row['row_type'] == "D") {
            $data['ub_reason'] = "(Deleted)";
        } elseif ($row['row_type'] == "U") {
            $data['ub_reason'] = "(Unbanned)";
        } else {
            $data['ub_reason'] = "(Expired)";
        }

        $data['ureason'] = stripslashes($row['unban_reason'] ?? '');

        $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':aid', $row['RemovedBy']);
        $removedby         = $GLOBALS['PDO']->single();
        $data['removedby'] = "";
        if (!empty($removedby['user']) && $data['admin']) {
            $data['removedby'] = $removedby['user'];
        }
    }
    // Don't need this stuff.
    // Uncomment below if the modifications above cause issues
    //	else
    //	{
    //		$data['unbanned'] = false;
    //		$data['class'] = "listtable_1";
    //		$data['ub_reason'] = "";
    //	}

    $data['layer_id'] = 'layer_' . $row['ban_id'];
    if ($data['type'] == "0") {
        $GLOBALS['PDO']->query("SELECT count(bid) as count FROM `:prefix_bans` WHERE authid = :authid AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0'");
        $GLOBALS['PDO']->bind(':authid', $data['steamid']);
        $alrdybnd = $GLOBALS['PDO']->single();
    } else {
        $GLOBALS['PDO']->query("SELECT count(bid) as count FROM `:prefix_bans` WHERE ip = :ip AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1'");
        $GLOBALS['PDO']->bind(':ip', $row['ban_ip']);
        $alrdybnd = $GLOBALS['PDO']->single();
    }
    if ($alrdybnd['count'] == 0) {
        $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> Reban', "index.php?p=admin&c=bans" . $pagelink . "&rebanid=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey'] . "#^0");
    } else {
        $data['reban_link'] = false;
    }
    $data['blockcomm_link']  = CreateLinkR('<i class="fas fa-ban fa-lg"></i> Block Comms', "index.php?p=admin&c=comms" . $pagelink . "&blockfromban=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey'] . "#^0");
    $data['details_link']    = CreateLinkR('click', 'getdemo.php?type=B&id=' . urlencode($row['ban_id']));
    $data['groups_link']     = CreateLinkR('<i class="fas fa-users fa-lg"></i> Show Groups', "index.php?p=admin&c=bans&fid=" . urlencode($data['communityid']) . "#^4");
    $data['friend_ban_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Ban Friends', '#', '', '_self', false, "BanFriendsProcess('" . $data['communityid'] . "','" . $data['player'] . "');return false;");
    $data['edit_link']       = CreateLinkR('<i class="fas fa-edit fa-lg"></i> Edit Details', "index.php?p=admin&c=bans&o=edit" . $pagelink . "&id=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);

    $data['unban_link']  = CreateLinkR('<i class="fas fa-undo fa-lg"></i> Unban', "#", "", "_self", false, "UnbanBan('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1, false);return false;");
    $data['delete_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Delete Ban', "#", "", "_self", false, "RemoveBan('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 0, false);return false;");


    $data['server_id'] = $row['ban_server'];

    if (empty($row['mod_icon'])) {
        $modicon = "web.png";
    } else {
        $modicon = $row['mod_icon'];
    }

    $data['mod_icon'] = '<img src="images/games/' . $modicon . '" alt="MOD" border="0" align="absmiddle" />&nbsp;' . $data['country'];

    if ($row['history_count'] > 1) {
        $data['prevoff_link'] = $row['history_count'] . " " . CreateLinkR("&nbsp;(search)", "index.php?p=banlist&searchText=" . urlencode($data['type'] == 0 ? $data['steamid'] : $row['ban_ip']) . "&Submit");
    } else {
        $data['prevoff_link'] = "No previous bans";
    }



    if (strlen($row['ban_ip']) < 7) {
        $data['ip'] = 'none';
    } else {
        $data['ip'] = $data['country'] . '&nbsp;' . $row['ban_ip'];
    }

    if ($row['ban_length'] == 0) {
        $data['expires'] = 'never';
    } else {
        $data['expires'] = Config::time($row['ban_ends']);
    }


    if ($row['demo_count'] == 0) {
        $data['demo_available'] = false;
        $data['demo_quick']     = 'N/A';
        $data['demo_link']      = CreateLinkR('<i class="fas fa-video-slash fa-lg"></i> No Demos', "#");
    } else {
        $data['demo_available'] = true;
        $data['demo_quick']     = CreateLinkR('Demo', "getdemo.php?type=B&id=" . urlencode($data['ban_id']));
        $data['demo_link']      = CreateLinkR('<i class="fas fa-video fa-lg"></i> Review Demo', "getdemo.php?type=B&id=" . urlencode($data['ban_id']));
    }



    $data['server_id'] = $row['ban_server'];

    $GLOBALS['PDO']->query("SELECT bl.time, bl.name, s.ip, s.port FROM `:prefix_banlog` AS bl LEFT JOIN `:prefix_servers` AS s ON s.sid = bl.sid WHERE bid = :bid");
    $GLOBALS['PDO']->bind(':bid', $data['ban_id']);
    $banlog             = $GLOBALS['PDO']->resultset();
    $data['blockcount'] = sizeof($banlog);
    $logstring          = "";
    foreach ($banlog as $logged) {
        if (!empty($logstring)) {
            $logstring .= ", ";
        }
        $logstring .= '<span title="Server: ' . $logged["ip"] . ':' . $logged["port"] . ', Date: ' . Config::time($logged["time"]) . '">' . ($logged["name"] != "" ? htmlspecialchars($logged["name"]) : "<i>no name</i>") . '</span>';
    }
    $data['banlog'] = $logstring;

    //COMMENT STUFF
    //-----------------------------------
    if (Config::getBool('config.enablepubliccomments') || $userbank->is_admin()) {
        $view_comments = true;
        $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE type = 'B' AND bid = :bid ORDER BY added desc");
        $GLOBALS['PDO']->bind(':bid', $data['ban_id']);
        $commentres    = $GLOBALS['PDO']->resultset();

        if (count($commentres) > 0) {
            $comment = [];
            $morecom = 0;
            foreach ($commentres as $crow) {
                $cdata            = [];
                $cdata['morecom'] = ($morecom == 1 ? true : false);
                if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . $data['ban_id'] . '&ctype=B&cid=' . $crow['cid'] . $pagelink, 'Edit Comment');
                    if ($userbank->HasAccess(ADMIN_OWNER)) {
                        $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'B'," . (isset($_GET["page"]) ? $page : -1) . ");\"><i class='fas fa-trash fa-lg'></i></a>";
                    }
                } else {
                    $cdata['editcomlink'] = "";
                    $cdata['delcomlink']  = "";
                }

                $cdata['comname']    = $crow['comname'];
                $cdata['added']      = Config::time($crow['added']);
                $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $commentText         = encodePreservingBr($commentText);
                // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
                $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
                $cdata['commenttxt'] = $commentText;

                if (!empty($crow['edittime'])) {
                    $cdata['edittime'] = Config::time($crow['edittime']);
                    $cdata['editname'] = $crow['editname'];
                } else {
                    $cdata['edittime'] = "";
                    $cdata['editname'] = "";
                }

                $morecom = 1;
                array_push($comment, $cdata);
            }
        } else {
            $comment = "None";
        }

        $data['commentdata'] = $comment;
    }


    $data['addcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . $data['ban_id'] . '&ctype=B' . $pagelink);
    //-----------------------------------

    $data['ub_reason']   = (isset($data['ub_reason']) ? $data['ub_reason'] : "");
    $data['banlength']   = $data['ban_length'] . " " . $data['ub_reason'];
    $data['view_edit']   = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS) || ($userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_unban']  = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN) || ($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_delete'] = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_BAN));
    array_push($bans, $data);
}

if (isset($_GET['advSearch'])) {
    $advSearchString = "&advSearch=" . urlencode(isset($_GET['advSearch']) ? $_GET['advSearch'] : '') . "&advType=" . urlencode(isset($_GET['advType']) ? $_GET['advType'] : '');
} else {
    $advSearchString = '';
}

if ($page > 1) {
    if (isset($_GET['c']) && $_GET['c'] == "bans") {
        $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "javascript:void(0);", "", "_self", false, $prev);
    } else {
        $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=banlist&page=" . ($page - 1) . (isset($_GET['searchText']) > 0 ? "&searchText=" . urlencode($_GET['searchText']) : '' . $advSearchString));
    }
} else {
    $prev = "";
}
if ($BansEnd < $BanCount) {
    if (isset($_GET['c']) && $_GET['c'] == "bans") {
        if (!isset($nxt)) {
            $nxt = "";
        }
        $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "javascript:void(0);", "", "_self", false, $nxt);
    } else {
        $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=banlist&page=" . ($page + 1) . (isset($_GET['searchText']) ? "&searchText=" . urlencode($_GET['searchText']) : '' . $advSearchString));
    }
} else {
    $next = "";
}

//=================[ Start Layout ]==================================
$ban_nav = 'displaying&nbsp;' . $BansStart . '&nbsp;-&nbsp;' . $BansEnd . '&nbsp;of&nbsp;' . $BanCount . '&nbsp;results';

if (strlen($prev) > 0) {
    $ban_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $ban_nav .= ' | <b>' . $next . '</b>';
}
$pages = ceil($BanCount / $BansPerPage);
if ($pages > 1) {
    // Issue #1113: $_GET['advSearch']/['advType'] used to be interpolated
    // raw into the JS-string-inside-HTML-attribute below, so e.g.
    // ?advSearch=x');alert(1);// closed the JS string and ran arbitrary
    // script on every banlist visit. addslashes escapes the `'`/`\` for
    // the JS-string layer, htmlspecialchars(ENT_QUOTES) escapes the
    // `<`/`>`/`&`/`'`/`"` for the HTML-attribute layer; both layers are
    // necessary because the browser unescapes attribute entities before
    // the JS engine sees the string.
    $advSearchJs = htmlspecialchars(addslashes((string)($_GET['advSearch'] ?? '')), ENT_QUOTES, 'UTF-8');
    $advTypeJs   = htmlspecialchars(addslashes((string)($_GET['advType']   ?? '')), ENT_QUOTES, 'UTF-8');
    $ban_nav .= '&nbsp;<select onchange="changePage(this,\'B\',\'' . $advSearchJs . '\',\'' . $advTypeJs . '\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if (isset($_GET["page"]) && $i == $page) {
            $ban_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $ban_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $ban_nav .= '</select>';
}

//COMMENT STUFF
//----------------------------------------
if (isset($_GET["comment"])) {
    $_GET["comment"] = (int) $_GET["comment"];
    $theme->assign('commenttype', (isset($_GET["cid"]) ? "Edit" : "Add"));
    if (isset($_GET["cid"])) {
        $_GET["cid"]    = (int) $_GET["cid"];
        $GLOBALS['PDO']->query("SELECT * FROM `:prefix_comments` WHERE cid = :cid");
        $GLOBALS['PDO']->bind(':cid', $_GET["cid"]);
        $ceditdata      = $GLOBALS['PDO']->single();
        $ctext          = html_entity_decode($ceditdata['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cotherdataedit = " AND cid != '" . $_GET["cid"] . "'";
    } else {
        $cotherdataedit = "";
        $ctext          = "";
    }

    $_GET["ctype"] = substr($_GET["ctype"], 0, 1);

    $cotherdata = $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE type = ? AND bid = ?" . $cotherdataedit . " ORDER BY added desc")->resultset([
        $_GET["ctype"],
        $_GET["comment"],
    ]);

    $ocomments = [];
    foreach ($cotherdata as $cdrow) {
        $coment               = [];
        $coment['comname']    = $cdrow['comname'];
        $coment['added']      = Config::time($cdrow['added']);
        $commentText          = html_entity_decode($cdrow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $commentText          = encodePreservingBr($commentText);
        // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
        $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
        $coment['commenttxt'] = $commentText;

        if ($cdrow['editname'] != "") {
            $coment['edittime'] = Config::time($cdrow['edittime']);
            $coment['editname'] = $cdrow['editname'];
        } else {
            $coment['editname'] = "";
            $coment['edittime'] = "";
        }
        array_push($ocomments, $coment);
    }

    $theme->assign('page', (isset($_GET["page"]) ? $page : -1));
    $theme->assign('othercomments', $ocomments);
    $theme->assign('commenttext', (isset($ctext) ? $ctext : ""));
    $theme->assign('ctype', $_GET["ctype"]);
    $theme->assign('cid', (isset($_GET["cid"]) ? $_GET["cid"] : ""));
    $theme->assign('canedit', $userbank->is_admin());
}
$theme->assign('view_comments', $view_comments);
$theme->assign('comment', (isset($_GET["comment"]) && $view_comments ? $_GET["comment"] : false));
$theme->assign('canEditComment', $canEditComment);
//----------------------------------------

unset($_SESSION['CountryFetchHndl']);

$theme->assign('searchlink', $searchlink);
$theme->assign('hidetext', $hidetext);
$theme->assign('total_bans', $BanCount);
$theme->assign('active_bans', $BanCount);

$theme->assign('ban_nav', $ban_nav);
$theme->assign('ban_list', $bans);
$theme->assign('admin_nick', $userbank->GetProperty("user"));

$theme->assign('admin_postkey', $_SESSION['banlist_postkey']);
$theme->assign('hideplayerips', (Config::getBool('banlist.hideplayerips') && !$userbank->is_admin()));
$theme->assign('hideadminname', (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()));
$theme->assign('groupban', (Config::getBool('config.enablegroupbanning') && $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)));
$theme->assign('friendsban', (Config::getBool('config.enablefriendsbanning') && $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)));
$theme->assign('general_unban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS));
$theme->assign('can_delete', $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_BAN));
$theme->assign('view_bans', ($userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS | ADMIN_DELETE_BAN)));
$theme->assign('can_export', ($userbank->HasAccess(ADMIN_OWNER) || Config::getBool('config.exportpublic')));
$theme->display('page_bans.tpl');