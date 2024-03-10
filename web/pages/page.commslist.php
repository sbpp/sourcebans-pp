<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2023 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>

SourceComms 0.9.266
Copyright (C) 2013-2014 Alexandr Duplishchev
Licensed under GNU GPL version 3, or later.
Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
*************************************************************************/

global $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if (!Config::getBool('config.enablecomms')) {
    print "<script>ShowBox('Error', 'This page is disabled. You should not be here.', 'red');</script>";
    PageDie();
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

PruneComms();

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page     = intval($_GET['page']);
    $pagelink = "&page=" . $page;
}

if (isset($_GET['a']) && $_GET['a'] == "ungag" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    //we have a multiple unban asking
    $bid = intval($_GET['id']);
    $res = $GLOBALS['db']->Execute("SELECT a.aid, a.gid FROM `" . DB_PREFIX . "_comms` c INNER JOIN " . DB_PREFIX . "_admins a ON a.aid = c.aid WHERE bid = '" . $bid . "' AND c.type = 2;");
    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN) && !($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res->fields['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res->fields['gid'] == $userbank->GetProperty('gid'))) {
        die("You don't have access to this");
    }

    $row = $GLOBALS['db']->GetRow("SELECT b.authid, b.name, b.created, b.sid, UNIX_TIMESTAMP() as now
										FROM " . DB_PREFIX . "_comms b
										LEFT JOIN " . DB_PREFIX . "_servers s ON s.sid = b.sid
										WHERE b.bid = ? AND b.RemoveType IS NULL AND b.type = 2 AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP())", array(
        $bid
    ));
    if (empty($row) || !$row) {
        echo "<script>ShowBox('Player Not UnGagged', 'The player was not ungagged, either already ungagged or not a valid block.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $unbanReason = htmlspecialchars(trim($_GET['ureason']));
    $ins         = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_comms` SET
										`RemovedBy` = ?,
										`RemoveType` = 'U',
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = ?
										WHERE `bid` = ?;", array(
        $userbank->GetAid(),
        $unbanReason,
        $bid
    ));

    $blocked = $GLOBALS['db']->GetAll("SELECT sid FROM `" . DB_PREFIX . "_servers` WHERE `enabled`=1");
    foreach ($blocked as $tempban) {
        rcon(("sc_fw_ungag " . $row['authid']), $tempban['sid']);
    }

    if ($res) {
        echo "<script>ShowBox('Player UnGagged', '" . $row['name'] . " (" . $row['authid'] . ") has been ungagged from SourceBans.', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add("m", "Player UnGagged", "$row[name] ($row[authid]) has been ungagged.");
    } else {
        echo "<script>ShowBox('Player NOT UnGagged', 'There was an error ungagging " . $row['name'] . "', 'red', 'index.php?p=commsist$pagelink', true);</script>";
    }
} else if (isset($_GET['a']) && $_GET['a'] == "unmute" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    //we have a multiple unban asking
    $bid = intval($_GET['id']);
    $res = $GLOBALS['db']->Execute("SELECT a.aid, a.gid FROM `" . DB_PREFIX . "_comms` c INNER JOIN " . DB_PREFIX . "_admins a ON a.aid = c.aid WHERE bid = '" . $bid . "' AND c.type = 1;");
    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN) && !($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res->fields['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res->fields['gid'] == $userbank->GetProperty('gid'))) {
        die("You don't have access to this");
    }

    $row = $GLOBALS['db']->GetRow("SELECT b.authid, b.name, b.created, b.sid, UNIX_TIMESTAMP() as now
										FROM " . DB_PREFIX . "_comms b
										LEFT JOIN " . DB_PREFIX . "_servers s ON s.sid = b.sid
										WHERE b.bid = ? AND b.RemoveType IS NULL AND b.type = 1 AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP())", array(
        $bid
    ));
    if (empty($row) || !$row) {
        echo "<script>ShowBox('Player Not UnGagged', 'The player was not unmuted, either already unmuted or not a valid block.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $unbanReason = htmlspecialchars(trim($_GET['ureason']));
    $ins         = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_comms` SET
										`RemovedBy` = ?,
										`RemoveType` = 'U',
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = ?
										WHERE `bid` = ?;", array(
        $userbank->GetAid(),
        $unbanReason,
        $bid
    ));

    $blocked = $GLOBALS['db']->GetAll("SELECT sid FROM `" . DB_PREFIX . "_servers` WHERE `enabled`=1");
    foreach ($blocked as $tempban) {
        rcon(("sc_fw_unmute " . $row['authid']), $tempban['sid']);
    }

    if ($res) {
        echo "<script>ShowBox('Player UnMuted', '" . $row['name'] . " (" . $row['authid'] . ") has been unmuted from SourceBans.', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add("m", "Player UnMuted", "$row[name] ($row[authid]) has been unmuted.");
    } else {
        echo "<script>ShowBox('Player NOT UnGagged', 'There was an error unmuted " . $row['name'] . "', 'red', 'index.php?p=commsist$pagelink', true);</script>";
    }
} else if (isset($_GET['a']) && $_GET['a'] == "delete") {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }

    if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_BAN)) {
        echo "<script>ShowBox('Error', 'You do not have access to this.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $bid = intval($_GET['id']);

    $steam  = $GLOBALS['db']->GetRow("SELECT name, authid, ends, length, RemoveType, type, UNIX_TIMESTAMP() AS now
									FROM " . DB_PREFIX . "_comms WHERE bid=?", array(
        $bid
    ));
    $end    = (int) $steam['ends'];
    $length = (int) $steam['length'];
    $now    = (int) $steam['now'];

    $cmd = "";

    switch ($steam['type']) {
        case 1:
            $cmd = "sc_fw_unmute";
            break;
        case 2:
            $cmd = "sc_fw_ungag";
            break;
        default:
            break;
    }

    $res = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_comms` WHERE `bid` = ?", array(
        $bid
    ));

    if (empty($steam['RemoveType']) && ($length == 0 || $end > $now)) {
        $blocked = $GLOBALS['db']->GetAll("SELECT sid FROM `" . DB_PREFIX . "_servers` WHERE `enabled`=1");
        foreach ($blocked as $tempban) {
            rcon(($cmd . " " . $steam['authid']), $tempban['sid']);
        }
    }

    if ($res) {
        echo "<script>ShowBox('Block Deleted', 'The block for \'" . $steam['name'] . "\' (" . $steam['authid'] . ") has been deleted from SourceBans', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add("m", "Block Deleted", "Block $steam[name] ($steam[authid]) has been deleted.");
    } else {
        echo "<script>ShowBox('Ban NOT Deleted', 'The ban for \'" . $steam['name'] . "\' had an error while being removed.', 'red', 'index.php?p=commslist$pagelink', true);</script>";
    }
}

// LIMIT для SQL запроса - по номеру страницы и числу банов на страницу
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
    $search = '%' . trim($_GET['searchText']) . '%';

    $res = $GLOBALS['db']->Execute("SELECT bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
		SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
		CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
		(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
		(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
		UNIX_TIMESTAMP() as c_time
		FROM " . DB_PREFIX . "_comms AS CO FORCE INDEX (created)
		LEFT JOIN " . DB_PREFIX . "_servers AS SE ON SE.sid = CO.sid
		LEFT JOIN " . DB_PREFIX . "_mods AS MO on SE.modid = MO.mid
		LEFT JOIN " . DB_PREFIX . "_admins AS AD ON CO.aid = AD.aid
      	WHERE CO.authid LIKE ? or CO.name LIKE ? or CO.reason LIKE ?" . $hideinactive . "
   		ORDER BY CO.created DESC LIMIT ?,?", array(
        $search,
        $search,
        $search,
        intval($BansStart),
        intval($BansPerPage)
    ));


    $res_count  = $GLOBALS['db']->Execute("SELECT count(CO.bid) FROM " . DB_PREFIX . "_comms AS CO WHERE CO.authid LIKE ? OR CO.name LIKE ? OR CO.reason LIKE ?" . $hideinactive, array(
        $search,
        $search,
        $search
    ));
    $searchlink = "&searchText=" . $_GET["searchText"];
} elseif (!isset($_GET['advSearch'])) {
    $res = $GLOBALS['db']->Execute("SELECT bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
		SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
		CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
		(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
		(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
		UNIX_TIMESTAMP() as c_time
		FROM " . DB_PREFIX . "_comms AS CO FORCE INDEX (created)
		LEFT JOIN " . DB_PREFIX . "_servers AS SE ON SE.sid = CO.sid
		LEFT JOIN " . DB_PREFIX . "_mods AS MO on SE.modid = MO.mid
		LEFT JOIN " . DB_PREFIX . "_admins AS AD ON CO.aid = AD.aid
		" . $hideinactiven . "
		ORDER BY created DESC
		LIMIT ?,?", array(
        intval($BansStart),
        intval($BansPerPage)
    ));

    $res_count  = $GLOBALS['db']->Execute("SELECT count(bid) FROM " . DB_PREFIX . "_comms" . $hideinactiven);
    $searchlink = "";
}

$advcrit = [];
if (isset($_GET['advSearch'])) {
    $value = trim($_GET['advSearch']);
    $type  = $_GET['advType'];
    switch ($type) {
        case "name":
            $where   = "WHERE CO.name LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "banid":
            $where   = "WHERE CO.bid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "steamid":
            $where   = "WHERE CO.authid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "steam":
            $where   = "WHERE CO.authid LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "reason":
            $where   = "WHERE CO.reason LIKE ?";
            $advcrit = array(
                "%$value%"
            );
            break;
        case "date":
            $date    = explode(",", $value);
            $time    = mktime(0, 0, 0, (int)$date[1], (int)$date[0], (int)$date[2]);
            $time2   = mktime(23, 59, 59, (int)$date[1], (int)$date[0], (int)$date[2]);
            $where   = "WHERE CO.created > ? AND CO.created < ?";
            $advcrit = array(
                $time,
                $time2
            );
            break;
        case "length":
            $len         = explode(",", $value);
            $length_type = $len[0];
            $length      = (int)$len[1] * 60;
            $where       = "WHERE CO.length ";
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
            $where   = "WHERE CO.type = ?";
            $advcrit = array(
                $value
            );
            break;
        case "admin":
            if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE CO.aid=?";
                $advcrit = array(
                    $value
                );
            }
            break;
        case "where_banned":
            $where   = "WHERE CO.sid=?";
            $advcrit = array(
                $value
            );
            break;
        case "bid":
            $where   = "WHERE CO.bid = ?";
            $advcrit = array(
                $value
            );
            break;
        case "comment":
            if ($userbank->is_admin()) {
                $where   = "WHERE CM.type ='C' AND CM.commenttxt LIKE ?";
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

    $res = $GLOBALS['db']->Execute("SELECT CO.bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
			CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
			(SELECT count(*) FROM " . DB_PREFIX . "_comms as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
			UNIX_TIMESTAMP() as c_time
			FROM " . DB_PREFIX . "_comms AS CO FORCE INDEX (created)
			LEFT JOIN " . DB_PREFIX . "_servers AS SE ON SE.sid = CO.sid
			LEFT JOIN " . DB_PREFIX . "_mods AS MO on SE.modid = MO.mid
			LEFT JOIN " . DB_PREFIX . "_admins AS AD ON CO.aid = AD.aid
  			" . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN " . DB_PREFIX . "_comments AS CM ON CO.bid = CM.bid" : "") . "
      " . $where . $hideinactive . "
   ORDER BY CO.created DESC
   LIMIT ?,?", array_merge($advcrit, array(
        intval($BansStart),
        intval($BansPerPage)
    )));

    $res_count  = $GLOBALS['db']->Execute("SELECT count(CO.bid) FROM " . DB_PREFIX . "_comms AS CO
										  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN " . DB_PREFIX . "_comments AS CM ON CO.bid = CM.bid" : "") . " " . $where . $hideinactive, $advcrit);
    $searchlink = "&advSearch=" . $_GET['advSearch'] . "&advType=" . $_GET['advType'];
}

$BanCount = $res_count->fields[0];
if ($BansEnd > $BanCount) {
    $BansEnd = $BanCount;
}
if (!$res) {
    echo "No Blocks Found.";
    PageDie();
}

$view_comments = false;
$bans          = [];
while (!$res->EOF) {
    $data = [];

    $data['ban_id'] = $res->fields['ban_id'];
    $data['type']   = $res->fields['type'];
    $data['c_time'] = $res->fields['c_time'];

    $mute_count    = (int) $res->fields['mute_count'];
    $gag_count     = (int) $res->fields['gag_count'];
    $history_count = $mute_count + $gag_count;

    $delimiter = "";

    switch ((int) $data['type']) {
        case 1:
            $data['type_icon'] = '<i class="fas fa-microphone-slash fa-lg"></i>';
            $mute_count        = $mute_count - 1;
            break;
        case 2:
            $data['type_icon'] = '<i class="fas fa-comment-slash fa-lg"></i>';
            $gag_count         = $gag_count - 1;
            break;
        default:
            $data['type_icon'] = '<img src="images/country/zz.png" alt="Unknown block type" border="0" align="absmiddle" />';
            break;
    }

    $data['ban_date']    = Config::time($res->fields['ban_created']);
    $data['player']      = addslashes($res->fields['player_name']);
    $data['steamid']     = $res->fields['authid'];
    // Fix #906 - Bad SteamID Format broke the page view, so give them an null SteamID.
    if (!\SteamID\SteamID::isValidID($data['steamid'])) {
		$data['steamid'] = 'STEAM_0:0:00000000';
	}
    $data['communityid'] = $res->fields['community_id'];
    $steam2id            = $data['steamid'];
    $steam3parts         = explode(':', $steam2id);
    $data['steamid3']    = \SteamID\SteamID::toSteam3($data['steamid']);

    if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
        $data['admin'] = false;
    } else {
        $data['admin'] = stripslashes($res->fields['admin_name']);
    }
    $data['reason'] = stripslashes($res->fields['ban_reason']);

    if ($res->fields['ban_length'] > 0) {
        $data['ban_length'] = SecondsToString(intval($res->fields['ban_length']));
        $data['expires']    = Config::time($res->fields['ban_ends']);
    } else if ($res->fields['ban_length'] == 0) {
        $data['ban_length'] = 'Permanent';
        $data['expires']    = 'never';
    } else {
        $data['ban_length'] = 'Session';
        $data['expires']    = 'n/a';
    }

    // Что за тип разбана - D? Я такой не видел, но оставлю так и быть.. for feature use...
    if ($res->fields['row_type'] == 'D' || $res->fields['row_type'] == 'U' || $res->fields['row_type'] == 'E' || ($res->fields['ban_length'] && $res->fields['ban_ends'] < $data['c_time'])) {
        $data['unbanned'] = true;
        $data['class']    = "listtable_1_unbanned";

        if ($res->fields['row_type'] == "D") {
            $data['ub_reason'] = "(Deleted)";
        } elseif ($res->fields['row_type'] == "U") {
            $data['ub_reason'] = "(Unbanned)";
        } else {
            $data['ub_reason'] = "(Expired)";
        }

        if (isset($res->fields['unban_reason']))
            $data['ureason'] = stripslashes($res->fields['unban_reason']);

        $removedby         = $GLOBALS['db']->GetRow("SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = '" . $res->fields['RemovedBy'] . "'");
        $data['removedby'] = "";
        if (isset($removedby[0]) && $data['admin']) {
            $data['removedby'] = $removedby[0];
        }
    } else if ($data['ban_length'] == 'Permanent') {
        $data['class'] = "listtable_1_permanent";
    } else {
        $data['unbanned']  = false;
        $data['class']     = "listtable_1_banned";
        $data['ub_reason'] = "";
    }

    $data['layer_id'] = 'layer_' . $res->fields['ban_id'];
    // Запрос текущего статуса игрока для рисования ссылки на мьют или гаг
    $alrdybnd         = $GLOBALS['db']->Execute("SELECT count(bid) as count FROM `" . DB_PREFIX . "_comms` WHERE authid = '" . $data['steamid'] . "' AND RemovedBy IS NULL AND type = '" . $data['type'] . "' AND (length = 0 OR ends > UNIX_TIMESTAMP());");
    if ($alrdybnd->fields['count'] == 0) {
        switch ($data['type']) {
            case 1:
                $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> ReMute', "index.php?p=admin&c=comms" . $pagelink . "&rebanid=" . $res->fields['ban_id'] . "&key=" . $_SESSION['banlist_postkey'] . "#^0");
                break;
            case 2:
                $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> ReGag', "index.php?p=admin&c=comms" . $pagelink . "&rebanid=" . $res->fields['ban_id'] . "&key=" . $_SESSION['banlist_postkey'] . "#^0");
                break;
            default:
                break;
        }
    } else {
        $data['reban_link'] = false;
    }


    $data['edit_link'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i> Edit Details', "index.php?p=admin&c=comms&o=edit" . $pagelink . "&id=" . $res->fields['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);

    switch ($data['type']) {
        case 2:
            $data['unban_link'] = CreateLinkR('<i class="fas fa-undo fa-lg"></i> UnGag', "#", "", "_self", false, "UnGag('" . $res->fields['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1);return false;");
            break;
        case 1:
            $data['unban_link'] = CreateLinkR('<i class="fas fa-undo fa-lg"></i> UnMute', "#", "", "_self", false, "UnMute('" . $res->fields['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1);return false;");
            break;
        default:
            break;
    }

    $data['delete_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Delete Block', "#", "", "_self", false, "RemoveBlock('" . $res->fields['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 0);return false;");

    $data['server_id'] = $res->fields['ban_server'];

    if (empty($res->fields['mod_icon'])) {
        $modicon = "web.png";
    } else {
        $modicon = $res->fields['mod_icon'];
    }

    $data['mod_icon'] = '<img src="images/games/' . $modicon . '" alt="MOD" border="0" align="absmiddle" />&nbsp;' . $data['type_icon'];

    if ($history_count > 1) {
        $data['prevoff_link'] = $history_count . " " . CreateLinkR("&nbsp;(search)", "index.php?p=commslist&searchText=" . $data['steamid'] . "&Submit");
    } else {
        $data['prevoff_link'] = "No previous blocks";
    }

    $mutes = "";
    $gags  = "";
    if ($mute_count > 0) {
        $mutes = $mute_count . '<i class="fas fa-microphone-slash fa-lg"></i>';
        if ($gag_count > 0) {
            $mutes = $mutes . "&ensp;";
        }
    }
    if ($gag_count > 0) {
        $gags = $gag_count . '<i class="fas fa-comment-slash fa-lg"></i>';
    }

    $data['server_id'] = $res->fields['ban_server'];

    //COMMENT STUFF
    //-----------------------------------
    if (Config::getBool('config.enablepubliccomments') || $userbank->is_admin()) {
        $view_comments = true;
        $commentres    = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
											FROM `" . DB_PREFIX . "_comments` AS C
											WHERE C.type = 'C' AND bid = '" . $data['ban_id'] . "' ORDER BY added desc");

        if ($commentres->RecordCount() > 0) {
            if ($mute_count > 0 || $gag_count > 0) {
                $delimiter = "&ensp;";
            }
            $comment = [];
            $morecom = 0;
            while (!$commentres->EOF) {
                $cdata            = [];
                $cdata['morecom'] = ($morecom == 1 ? true : false);
                if ($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
                    $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=commslist&comment=' . $data['ban_id'] . '&ctype=C&cid=' . $commentres->fields['cid'] . $pagelink, 'Edit Comment');
                    if ($userbank->HasAccess(ADMIN_OWNER)) {
                        $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $commentres->fields['cid'] . ",'C'," . (isset($_GET["page"]) ? $_GET["page"] : -1) . ");\"><i class='fas fa-trash fa-lg'></i></a>";
                    }
                } else {
                    $cdata['editcomlink'] = "";
                    $cdata['delcomlink']  = "";
                }

                $cdata['comname']    = $commentres->fields['comname'];
                $cdata['added']      = Config::time($commentres->fields['added']);
                $cdata['commenttxt'] = $commentres->fields['commenttxt'];
                $cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);
                // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
                $cdata['commenttxt'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $cdata['commenttxt']);

                if (!empty($commentres->fields['edittime'])) {
                    $cdata['edittime'] = Config::time($commentres->fields['edittime']);
                    $cdata['editname'] = $commentres->fields['editname'];
                } else {
                    $cdata['edittime'] = "";
                    $cdata['editname'] = "";
                }

                $morecom = 1;
                array_push($comment, $cdata);
                $commentres->MoveNext();
            }
        } else {
            $comment = "None";
        }

        $data['commentdata'] = $comment;
    }

    $data['addcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=commslist&comment=' . $data['ban_id'] . '&ctype=C' . $pagelink);
    //-----------------------------------
    $data['counts']     = $delimiter . $mutes . $gags;

    $data['ub_reason']   = (isset($data['ub_reason']) ? $data['ub_reason'] : "");
    $data['banlength']   = $data['ban_length'] . " " . $data['ub_reason'];
    $data['view_edit']   = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS) || ($userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res->fields['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res->fields['gid'] == $userbank->GetProperty('gid')));
    $data['view_unban']  = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN) || ($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res->fields['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res->fields['gid'] == $userbank->GetProperty('gid')));
    $data['view_delete'] = ($userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_BAN));
    array_push($bans, $data);
    $res->MoveNext();
}

if (isset($_GET['advSearch'])) {
    $advSearchString = "&advSearch=" . (isset($_GET['advSearch']) ? $_GET['advSearch'] : '') . "&advType=" . (isset($_GET['advType']) ? $_GET['advType'] : '');
} else {
    $advSearchString = '';
}

if ($page > 1) {
    if (isset($_GET['c']) && $_GET['c'] == "comms") {
        $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "javascript:void(0);", "", "_self", false, $prev);
    } else {
        $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=commslist&page=" . ($page - 1) . (isset($_GET['searchText']) > 0 ? "&searchText=" . $_GET['searchText'] : '' . $advSearchString));
    }
} else {
    $prev = "";
}
if ($BansEnd < $BanCount) {
    if (isset($_GET['c']) && $_GET['c'] == "comms") {
        if (!isset($nxt)) {
            $nxt = "";
        }
        $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "javascript:void(0);", "", "_self", false, $nxt);
    } else {
        $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=commslist&page=" . ($page + 1) . (isset($_GET['searchText']) ? "&searchText=" . $_GET['searchText'] : '' . $advSearchString));
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
    $ban_nav .= '&nbsp;<select onchange="changePage(this,\'C\',\'' . (isset($_GET['advSearch']) ? $_GET['advSearch'] : '') . '\',\'' . (isset($_GET['advType']) ? $_GET['advType'] : '') . '\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if (isset($_GET["page"]) && $i == $_GET["page"]) {
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
    $theme->assign('commenttype', (isset($_GET["cid"]) ? "Edit" : "Add"));
    if (isset($_GET["cid"])) {
        $ceditdata      = $GLOBALS['db']->GetRow("SELECT * FROM " . DB_PREFIX . "_comments WHERE cid = '" . (int) $_GET["cid"] . "'");
        $ctext          = $ceditdata['commenttxt'];
        $cotherdataedit = " AND cid != '" . (int) $_GET["cid"] . "'";
    } else {
        $cotherdataedit = "";
        $ctext          = "";
    }
    $cotherdata = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = C.editaid) AS editname
											FROM `" . DB_PREFIX . "_comments` AS C
											WHERE type = ? AND bid = ?" . $cotherdataedit . " ORDER BY added desc", array(
        $_GET["ctype"],
        $_GET["comment"]
    ));

    $ocomments = [];
    while (!$cotherdata->EOF) {
        $coment               = [];
        $coment['comname']    = $cotherdata->fields['comname'];
        $coment['added']      = Config::time($cotherdata->fields['added']);
        $coment['commenttxt'] = str_replace("\n", "<br />", $cotherdata->fields['commenttxt']);
        // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
        $coment['commenttxt'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $coment['commenttxt']);
        if ($cotherdata->fields['editname'] != "") {
            $coment['edittime'] = Config::time($cotherdata->fields['edittime']);
            $coment['editname'] = $cotherdata->fields['editname'];
        } else {
            $coment['editname'] = "";
            $coment['edittime'] = "";
        }
        array_push($ocomments, $coment);
        $cotherdata->MoveNext();
    }

    $theme->assign('page', (isset($_GET["page"]) ? $_GET["page"] : -1));
    $theme->assign('othercomments', $ocomments);
    $theme->assign('commenttext', (isset($ctext) ? $ctext : ""));
    $theme->assign('ctype', $_GET["ctype"]);
    $theme->assign('cid', (isset($_GET["cid"]) ? $_GET["cid"] : ""));
    $theme->assign('canedit', $userbank->is_admin());
}
$theme->assign('view_comments', $view_comments);
$theme->assign('comment', (isset($_GET["comment"]) && $view_comments ? $_GET["comment"] : false));
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
$theme->assign('hideadminname', (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()));
$theme->assign('general_unban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS));
$theme->assign('can_delete', $userbank->HasAccess(ADMIN_DELETE_BAN));
$theme->assign('view_bans', ($userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ALL_BANS | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS | ADMIN_DELETE_BAN)));
$theme->display('page_comms.tpl');
