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

SourceComms 0.9.266
Copyright (C) 2013-2014 Alexandr Duplishchev
Licensed under GNU GPL version 3, or later.
Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
*************************************************************************/

use SteamID\SteamID;

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
if (!isset($_SESSION['banlist_postkey']) || strlen((string) $_SESSION['banlist_postkey']) < 4) {
    setPostKey();
}

$page     = 1;
$pagelink = "";

PruneComms();

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page     = (int) $_GET['page'];
    $pagelink = "&page=" . $page;
}

if (isset($_GET['a']) && $_GET['a'] == "ungag" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    // #1301: see page.banlist.php for the full rationale. Both the
    // legacy GET path and the JSON twin (api_comms_unblock) now
    // require a non-empty `ureason` so the audit log carries the
    // *why* behind every block lift, restoring v1.x parity.
    $unbanReasonRaw = trim((string) ($_GET['ureason'] ?? ''));
    if ($unbanReasonRaw === '') {
        echo "<script>ShowBox('Unblock Reason Required', 'You must supply a reason when ungagging a player.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }
    //we have a multiple unban asking
    $bid = (int) $_GET['id'];
    $GLOBALS['PDO']->query("SELECT a.aid, a.gid FROM `:prefix_comms` c INNER JOIN `:prefix_admins` a ON a.aid = c.aid WHERE bid = :bid AND c.type = 2");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $res = $GLOBALS['PDO']->single();
    if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban)) && !($userbank->HasAccess(WebPermission::UnbanOwnBans) && $res['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(WebPermission::UnbanGroupBans) && $res['gid'] == $userbank->GetProperty('gid'))) {
        die("You don't have access to this");
    }

    $GLOBALS['PDO']->query("SELECT b.authid, b.name, b.created, b.sid, UNIX_TIMESTAMP() as now
										FROM `:prefix_comms` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										WHERE b.bid = :bid AND b.RemoveType IS NULL AND b.type = 2 AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP())");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $row = $GLOBALS['PDO']->single();
    if (empty($row) || !$row) {
        echo "<script>ShowBox('Player Not UnGagged', 'The player was not ungagged, either already ungagged or not a valid block.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $unbanReason = htmlspecialchars($unbanReasonRaw);
    $GLOBALS['PDO']->query("UPDATE `:prefix_comms` SET
										`RemovedBy` = :removedby,
										`RemoveType` = :rtype,
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = :ureason
										WHERE `bid` = :bid");
    $GLOBALS['PDO']->bindMultiple([
        ':removedby' => $userbank->GetAid(),
        ':rtype'     => BanRemoval::Unbanned->value,
        ':ureason'   => $unbanReason,
        ':bid'       => $bid,
    ]);
    $GLOBALS['PDO']->execute();

    $blocked = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE `enabled`=1")->resultset();
    foreach ($blocked as $tempban) {
        rcon(("sc_fw_ungag " . $row['authid']), $tempban['sid']);
    }

    if ($res) {
        echo "<script>ShowBox('Player UnGagged', '" . $row['name'] . " (" . $row['authid'] . ") has been ungagged from SourceBans.', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add(LogType::Message, "Player UnGagged", "$row[name] ($row[authid]) has been ungagged. Reason: $unbanReason");
    } else {
        echo "<script>ShowBox('Player NOT UnGagged', 'There was an error ungagging " . $row['name'] . "', 'red', 'index.php?p=commsist$pagelink', true);</script>";
    }
} else if (isset($_GET['a']) && $_GET['a'] == "unmute" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    // #1301: see ungag branch above for rationale.
    $unbanReasonRaw = trim((string) ($_GET['ureason'] ?? ''));
    if ($unbanReasonRaw === '') {
        echo "<script>ShowBox('Unblock Reason Required', 'You must supply a reason when unmuting a player.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }
    //we have a multiple unban asking
    $bid = (int) $_GET['id'];
    $GLOBALS['PDO']->query("SELECT a.aid, a.gid FROM `:prefix_comms` c INNER JOIN `:prefix_admins` a ON a.aid = c.aid WHERE bid = :bid AND c.type = 1");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $res = $GLOBALS['PDO']->single();
    if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban)) && !($userbank->HasAccess(WebPermission::UnbanOwnBans) && $res['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(WebPermission::UnbanGroupBans) && $res['gid'] == $userbank->GetProperty('gid'))) {
        die("You don't have access to this");
    }

    $GLOBALS['PDO']->query("SELECT b.authid, b.name, b.created, b.sid, UNIX_TIMESTAMP() as now
										FROM `:prefix_comms` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										WHERE b.bid = :bid AND b.RemoveType IS NULL AND b.type = 1 AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP())");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $row = $GLOBALS['PDO']->single();
    if (empty($row) || !$row) {
        echo "<script>ShowBox('Player Not UnGagged', 'The player was not unmuted, either already unmuted or not a valid block.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $unbanReason = htmlspecialchars($unbanReasonRaw);
    $GLOBALS['PDO']->query("UPDATE `:prefix_comms` SET
										`RemovedBy` = :removedby,
										`RemoveType` = :rtype,
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = :ureason
										WHERE `bid` = :bid");
    $GLOBALS['PDO']->bindMultiple([
        ':removedby' => $userbank->GetAid(),
        ':rtype'     => BanRemoval::Unbanned->value,
        ':ureason'   => $unbanReason,
        ':bid'       => $bid,
    ]);
    $GLOBALS['PDO']->execute();

    $blocked = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE `enabled`=1")->resultset();
    foreach ($blocked as $tempban) {
        rcon(("sc_fw_unmute " . $row['authid']), $tempban['sid']);
    }

    if ($res) {
        echo "<script>ShowBox('Player UnMuted', '" . $row['name'] . " (" . $row['authid'] . ") has been unmuted from SourceBans.', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add(LogType::Message, "Player UnMuted", "$row[name] ($row[authid]) has been unmuted. Reason: $unbanReason");
    } else {
        echo "<script>ShowBox('Player NOT UnGagged', 'There was an error unmuted " . $row['name'] . "', 'red', 'index.php?p=commsist$pagelink', true);</script>";
    }
} else if (isset($_GET['a']) && $_GET['a'] == "delete") {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }

    if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan))) {
        echo "<script>ShowBox('Error', 'You do not have access to this.', 'red', 'index.php?p=commslist$pagelink');</script>";
        PageDie();
    }

    $bid = (int) $_GET['id'];

    $GLOBALS['PDO']->query("SELECT name, authid, ends, length, RemoveType, type, UNIX_TIMESTAMP() AS now
									FROM `:prefix_comms` WHERE bid = :bid");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $steam = $GLOBALS['PDO']->single();
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

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_comms` WHERE `bid` = :bid");
    $GLOBALS['PDO']->bind(':bid', $bid);
    $res = $GLOBALS['PDO']->execute();

    if (empty($steam['RemoveType']) && ($length == 0 || $end > $now)) {
        $blocked = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE `enabled`=1")->resultset();
        foreach ($blocked as $tempban) {
            rcon(($cmd . " " . $steam['authid']), $tempban['sid']);
        }
    }

    if ($res) {
        echo "<script>ShowBox('Block Deleted', 'The block for \'" . $steam['name'] . "\' (" . $steam['authid'] . ") has been deleted from SourceBans', 'green', 'index.php?p=commslist$pagelink');</script>";
        Log::add(LogType::Message, "Block Deleted", "Block $steam[name] ($steam[authid]) has been deleted.");
    } else {
        echo "<script>ShowBox('Ban NOT Deleted', 'The ban for \'" . $steam['name'] . "\' had an error while being removed.', 'red', 'index.php?p=commslist$pagelink', true);</script>";
    }
}

// LIMIT для SQL запроса - по номеру страницы и числу банов на страницу
$BansStart = (int) (($page - 1) * $BansPerPage);
$BansEnd   = (int) ($BansStart + $BansPerPage);

// hide inactive bans feature
if (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "true") { // hide
    $_SESSION["hideinactive"] = true;
    //ShowBox('Hide inactive bans', 'Inactive bans will be hidden from the banlist.', 'green', 'index.php?p=banlist', true);
} elseif (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "false") { // show
    unset($_SESSION["hideinactive"]);
    //ShowBox('Show inactive bans', 'Inactive bans will be shown in the banlist.', 'green', 'index.php?p=banlist', true);
}

// #1274: filter chip URL state. The chip strip in page_comms.tpl
// submits ?type=<mute|gag|silence> for type chips and ?state=active
// for the Active chip; both have to compose with the legacy
// ?searchText / ?advSearch / ?advType paths AND with the existing
// session-based "hide inactive" toggle. The lifecycle:
//
//   1. Sanitise chip params here (validated against a small allowlist
//      so a stray ?type=DROP TABLE never reaches the SQL layer).
//   2. Treat `state=active` and the session-based hideinactive flag
//      as the SAME effect — both narrow to "active rows only" via
//      the predicate below. The chip's `aria-pressed` and the
//      toggle button's `aria-pressed` both light up when EITHER
//      surface is on (see page_comms.tpl).
//   3. The Active predicate is single-sourced as the local
//      $activePredicateCo / $activePredicateBare strings: identical
//      semantics, only the column alias differs. After PruneComms()
//      runs at the top of this file, every length-bounded expired
//      row already has RemoveType='E', so the second clause is a
//      belt-and-suspenders guard against a race window where the
//      prune hasn't yet swept the latest expired row.
$chipType  = (string)($_GET['type']  ?? '');
$chipType  = in_array($chipType, ['mute', 'gag', 'silence'], true) ? $chipType : '';
$chipStateActive = (((string)($_GET['state'] ?? '')) === 'active');

$activePredicateCo   = '(CO.RemoveType IS NULL AND (CO.length = 0 OR CO.ends > UNIX_TIMESTAMP()))';
$activePredicateBare = '(RemoveType IS NULL AND (length = 0 OR ends > UNIX_TIMESTAMP()))';

$isActiveOnly = isset($_SESSION["hideinactive"]) || $chipStateActive;
if ($isActiveOnly) {
    $hidetext      = "Show";
    $hideinactive  = " AND " . $activePredicateCo;
    $hideinactiven = " WHERE " . $activePredicateBare;
} else {
    $hidetext      = "Hide";
    $hideinactive  = "";
    $hideinactiven = "";
}

// Chip TYPE filter — additional WHERE fragment + bind. `:prefix_comms.type`
// is `1=mute, 2=gag, 3=silence` per the existing render logic at
// ~line 750 (`$typeLabel` switch). `_Co` is for queries that alias
// the table as CO; `_Bare` is for the count query that uses the
// unaliased table name.
$typeMap            = ['mute' => 1, 'gag' => 2, 'silence' => 3];
$chipTypeWhereCo    = '';
$chipTypeWhereBare  = '';
$chipTypeBind       = [];
if ($chipType !== '') {
    $chipTypeWhereCo   = ' AND CO.type = ?';
    $chipTypeWhereBare = ' AND type = ?';
    $chipTypeBind      = [$typeMap[$chipType]];
}

if (isset($_GET['searchText'])) {
    $searchText = trim((string) $_GET['searchText']);

    // #1130: when the input parses as any Steam-ID format, match `authid`
    // via REGEXP so both STEAM_0:Y:Z and STEAM_1:Y:Z stored variants hit
    // (the SourceMod plugin can write either depending on the game).
    $authidPattern = SteamID::toSearchPattern($searchText);

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

    if ($authidPattern !== null) {
        $authidClause = "CO.authid REGEXP ?";
        $authidParam  = $authidPattern;
    } else {
        $authidClause = "CO.authid LIKE ?";
        $authidParam  = $search;
    }

    // #1274: wrap the OR-search clauses in parens before appending
    // any AND-predicate (hideinactive, chip type). Without the parens
    // SQL precedence makes `WHERE A OR B OR C AND D` parse as
    // `WHERE A OR B OR (C AND D)` — the AND only restricts the last
    // OR term, so a chip-type filter would silently let inactive or
    // wrong-type rows leak through whenever they matched authid or
    // name. The parens lock the AND-predicates onto the entire OR
    // group.
    $res = $GLOBALS['PDO']->query("SELECT bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
		SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
		CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
		(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
		(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
		UNIX_TIMESTAMP() as c_time
		FROM `:prefix_comms` AS CO FORCE INDEX (created)
		LEFT JOIN `:prefix_servers` AS SE ON SE.sid = CO.sid
		LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
		LEFT JOIN `:prefix_admins` AS AD ON CO.aid = AD.aid
      	WHERE (" . $authidClause . " or CO.name LIKE ? or CO.reason LIKE ?)" . $hideinactive . $chipTypeWhereCo . "
   		ORDER BY CO.created DESC LIMIT ?,?")->resultset(array_merge([
        $authidParam,
        $search,
        $search,
    ], $chipTypeBind, [
        (int) $BansStart,
        (int) $BansPerPage
    ]));


    $res_count  = $GLOBALS['PDO']->query("SELECT count(CO.bid) AS cnt FROM `:prefix_comms` AS CO WHERE (" . $authidClause . " OR CO.name LIKE ? OR CO.reason LIKE ?)" . $hideinactive . $chipTypeWhereCo)->resultset(array_merge([
        $authidParam,
        $search,
        $search,
    ], $chipTypeBind));
    $searchlink = "&searchText=" . urlencode($_GET["searchText"]);
} elseif (!isset($_GET['advSearch'])) {
    // #1274: this branch has no upper-level WHERE; if hideinactive
    // already opened one ($hideinactiven = " WHERE …") we append the
    // chip type filter as " AND CO.type = ?", otherwise the chip
    // opens the WHERE itself. Mirrors the $publicFilterAnd /
    // $publicFilterWheren shape in page.banlist.php.
    if ($hideinactiven !== '') {
        $branchWhereSuffix = $hideinactiven . $chipTypeWhereCo;
        $branchCountSuffix = $hideinactiven . $chipTypeWhereBare;
    } elseif ($chipType !== '') {
        $branchWhereSuffix = ' WHERE CO.type = ?';
        $branchCountSuffix = ' WHERE type = ?';
    } else {
        $branchWhereSuffix = '';
        $branchCountSuffix = '';
    }

    $res = $GLOBALS['PDO']->query("SELECT bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
		SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
		CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
		(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
		(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
		UNIX_TIMESTAMP() as c_time
		FROM `:prefix_comms` AS CO FORCE INDEX (created)
		LEFT JOIN `:prefix_servers` AS SE ON SE.sid = CO.sid
		LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
		LEFT JOIN `:prefix_admins` AS AD ON CO.aid = AD.aid
		" . $branchWhereSuffix . "
		ORDER BY created DESC
		LIMIT ?,?")->resultset(array_merge($chipTypeBind, [
        (int) $BansStart,
        (int) $BansPerPage
    ]));

    $res_count  = $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_comms`" . $branchCountSuffix)->resultset($chipTypeBind);
    $searchlink = "";
}

$advcrit = [];
if (isset($_GET['advSearch'])) {
    $value = trim((string) $_GET['advSearch']);

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
            $where   = "WHERE CO.name LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "banid":
            $where   = "WHERE CO.bid = ?";
            $advcrit = [$value];
            break;
        case "steamid":
            // #1130: match both STEAM_0:Y:Z and STEAM_1:Y:Z stored variants;
            // see SteamID::toSearchPattern() for rationale.
            $authidPattern = SteamID::toSearchPattern($value);
            if ($authidPattern !== null) {
                $where   = "WHERE CO.authid REGEXP ?";
                $advcrit = [$authidPattern];
            } else {
                $where   = "WHERE CO.authid = ?";
                $advcrit = [$value];
            }
            break;
        case "steam":
            $where   = "WHERE CO.authid LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "reason":
            $where   = "WHERE CO.reason LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "date":
            $date    = explode(",", $value);
            $time    = mktime(0, 0, 0, (int)$date[1], (int)$date[0], (int)$date[2]);
            $time2   = mktime(23, 59, 59, (int)$date[1], (int)$date[0], (int)$date[2]);
            $where   = "WHERE CO.created > ? AND CO.created < ?";
            $advcrit = [$time, $time2];
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
            $advcrit = [$length];
            break;
        case "btype":
            $where   = "WHERE CO.type = ?";
            $advcrit = [$value];
            break;
        case "admin":
            if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE CO.aid=?";
                $advcrit = [$value];
            }
            break;
        case "where_banned":
            $where   = "WHERE CO.sid=?";
            $advcrit = [$value];
            break;
        case "bid":
            $where   = "WHERE CO.bid = ?";
            $advcrit = [$value];
            break;
        case "comment":
            if ($userbank->is_admin()) {
                $where   = "WHERE CM.type ='C' AND CM.commenttxt LIKE ?";
                $advcrit = ["%$value%"];
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

    // #1274: chip type composes onto the advSearch WHERE. If $where
    // is empty (advType resolved to a no-op) AND hideinactive is on,
    // promote $hideinactive to its WHERE form so the trailing chip
    // predicate can append cleanly. Otherwise we may be left with
    // " AND CO.type = ?" as the only WHERE clause, which is invalid
    // SQL ("AND" with no leading WHERE). Mirrors the
    // `if (empty($where) && hide_inactive) $hideinactive = $hideinactiven`
    // shape page.banlist.php uses for the same composition.
    if (empty($where) && $hideinactive === '' && $chipType !== '') {
        $advChipType = ' WHERE CO.type = ?';
    } else {
        $advChipType = $chipTypeWhereCo;
    }

    $res = $GLOBALS['PDO']->query("SELECT CO.bid ban_id, CO.type, CO.authid, CO.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, CO.ureason unban_reason, CO.aid, AD.gid AS gid, adminIp, CO.sid ban_server, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, MO.icon as mod_icon,
			CAST(MID(CO.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(CO.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 1)) as mute_count,
			(SELECT count(*) FROM `:prefix_comms` as BH WHERE (BH.authid = CO.authid AND BH.authid != '' AND BH.authid IS NOT NULL AND BH.type = 2)) as gag_count,
			UNIX_TIMESTAMP() as c_time
			FROM `:prefix_comms` AS CO FORCE INDEX (created)
			LEFT JOIN `:prefix_servers` AS SE ON SE.sid = CO.sid
			LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
			LEFT JOIN `:prefix_admins` AS AD ON CO.aid = AD.aid
  			" . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CM ON CO.bid = CM.bid" : "") . "
      " . $where . $hideinactive . $advChipType . "
   ORDER BY CO.created DESC
   LIMIT ?,?")->resultset(array_merge($advcrit, $chipTypeBind, [
        (int) $BansStart,
        (int) $BansPerPage
    ]));

    $res_count  = $GLOBALS['PDO']->query("SELECT count(CO.bid) AS cnt FROM `:prefix_comms` AS CO
										  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CM ON CO.bid = CM.bid" : "") . " " . $where . $hideinactive . $advChipType)->resultset(array_merge($advcrit, $chipTypeBind));
    $searchlink = "&advSearch=" . urlencode($_GET['advSearch']) . "&advType=" . urlencode($_GET['advType']);
}

$BanCount = isset($res_count[0]['cnt']) ? (int) $res_count[0]['cnt'] : 0;
if ($BansEnd > $BanCount) {
    $BansEnd = $BanCount;
}
// Mirrors page.banlist.php: the redesigned page_comms.tpl renders its
// own "No comm blocks match those filters." empty state inside the
// table, so we let the template handle the empty case rather than
// short-circuiting with PageDie() and dropping the marquee chrome.
// PDO error mode is EXCEPTION, so a real SQL failure throws before
// reaching this point.

$view_comments = false;
$bans          = [];
foreach ($res as $row) {
    $data = [];

    $data['ban_id'] = $row['ban_id'];
    $data['type']   = $row['type'];
    $data['c_time'] = $row['c_time'];

    $mute_count    = (int) $row['mute_count'];
    $gag_count     = (int) $row['gag_count'];
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

    $data['ban_date']    = Config::time($row['ban_created']);

    // Fix #1008 - bug: Player Names Contain Unwanted Non-Standard Characters
    $raw_name = $row['player_name'];
    $cleaned_name = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);

    $data['player']      = addslashes($cleaned_name);
    $data['steamid']     = $row['authid'];
    // Fix #906 - Bad SteamID Format broke the page view, so give them an null SteamID.
    if (!\SteamID\SteamID::isValidID($data['steamid'])) {
		$data['steamid'] = 'STEAM_0:0:00000000';
	}
    $data['communityid'] = $row['community_id'];
    $steam2id            = $data['steamid'];
    $steam3parts         = explode(':', $steam2id);
    $data['steamid3']    = \SteamID\SteamID::toSteam3($data['steamid']);

    if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
        $data['admin'] = false;
    } else {
        // Same LEFT JOIN nullability as page.banlist.php:773 — see
        // the matching comment there for the rationale (deleted-
        // admin scenario, #1273 null-into-scalar discipline).
        $data['admin'] = stripslashes((string) $row['admin_name']);
    }
    $data['reason'] = stripslashes($row['ban_reason']);

    if ($row['ban_length'] > 0) {
        $data['ban_length'] = SecondsToString((int) $row['ban_length']);
        $data['expires']    = Config::time($row['ban_ends']);
    } else if ($row['ban_length'] == 0) {
        $data['ban_length'] = 'Permanent';
        $data['expires']    = 'never';
    } else {
        $data['ban_length'] = 'Session';
        $data['expires']    = 'n/a';
    }

    // Что за тип разбана - D? Я такой не видел, но оставлю так и быть.. for feature use...
    $rowRemoval = BanRemoval::tryFrom((string) ($row['row_type'] ?? ''));
    if ($rowRemoval !== null || ($row['ban_length'] && $row['ban_ends'] < $data['c_time'])) {
        $data['unbanned'] = true;
        $data['class']    = "listtable_1_unbanned";

        $data['ub_reason'] = match ($rowRemoval) {
            BanRemoval::Deleted  => "(Deleted)",
            BanRemoval::Unbanned => "(Unbanned)",
            default              => "(Expired)",
        };

        if (isset($row['unban_reason']))
            $data['ureason'] = stripslashes($row['unban_reason']);

        $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':aid', $row['RemovedBy']);
        $removedby         = $GLOBALS['PDO']->single();
        $data['removedby'] = "";
        if (!empty($removedby['user']) && $data['admin']) {
            $data['removedby'] = $removedby['user'];
        }
    } else if ($data['ban_length'] == 'Permanent') {
        $data['class'] = "listtable_1_permanent";
    } else {
        $data['unbanned']  = false;
        $data['class']     = "listtable_1_banned";
        $data['ub_reason'] = "";
    }

    $data['layer_id'] = 'layer_' . $row['ban_id'];
    // Запрос текущего статуса игрока для рисования ссылки на мьют или гаг
    $GLOBALS['PDO']->query("SELECT count(bid) as count FROM `:prefix_comms` WHERE authid = :authid AND RemovedBy IS NULL AND type = :type AND (length = 0 OR ends > UNIX_TIMESTAMP())");
    $GLOBALS['PDO']->bindMultiple([
        ':authid' => $data['steamid'],
        ':type'   => $data['type'],
    ]);
    $alrdybnd         = $GLOBALS['PDO']->single();
    if ($alrdybnd['count'] == 0) {
        // #1275 — admin-comms is single-section Pattern A; the legacy
        // `#^0` fragment targeted the old page-toc add-block anchor
        // (long since dead). Drop it.
        switch ($data['type']) {
            case 1:
                $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> ReMute', "index.php?p=admin&c=comms" . $pagelink . "&rebanid=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);
                break;
            case 2:
                $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> ReGag', "index.php?p=admin&c=comms" . $pagelink . "&rebanid=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);
                break;
            default:
                break;
        }
    } else {
        $data['reban_link'] = false;
    }


    $data['edit_link'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i> Edit Details', "index.php?p=admin&c=comms&o=edit" . $pagelink . "&id=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);

    switch ($data['type']) {
        case 2:
            $data['unban_link'] = CreateLinkR('<i class="fas fa-undo fa-lg"></i> UnGag', "#", "", "_self", false, "UnGag('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1);return false;");
            break;
        case 1:
            $data['unban_link'] = CreateLinkR('<i class="fas fa-undo fa-lg"></i> UnMute', "#", "", "_self", false, "UnMute('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1);return false;");
            break;
        default:
            break;
    }

    $data['delete_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Delete Block', "#", "", "_self", false, "RemoveBlock('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 0);return false;");

    $data['server_id'] = $row['ban_server'];

    if (empty($row['mod_icon'])) {
        $modicon = "web.png";
    } else {
        $modicon = $row['mod_icon'];
    }

    $data['mod_icon'] = '<img src="images/games/' . $modicon . '" alt="MOD" border="0" align="absmiddle" />&nbsp;' . $data['type_icon'];

    if ($history_count > 1) {
        $data['prevoff_link'] = $history_count . " " . CreateLinkR("&nbsp;(search)", "index.php?p=commslist&searchText=" . urlencode($data['steamid']) . "&Submit");
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

    $data['server_id'] = $row['ban_server'];

    //COMMENT STUFF
    //-----------------------------------
    if (Config::getBool('config.enablepubliccomments') || $userbank->is_admin()) {
        $view_comments = true;
        $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE C.type = 'C' AND bid = :bid ORDER BY added desc");
        $GLOBALS['PDO']->bind(':bid', $data['ban_id']);
        $commentres    = $GLOBALS['PDO']->resultset();

        if (count($commentres) > 0) {
            if ($mute_count > 0 || $gag_count > 0) {
                $delimiter = "&ensp;";
            }
            $comment = [];
            $morecom = 0;
            foreach ($commentres as $crow) {
                $cdata            = [];
                $cdata['morecom'] = ($morecom == 1 ? true : false);
                if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(WebPermission::Owner)) {
                    $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=commslist&comment=' . $data['ban_id'] . '&ctype=C&cid=' . $crow['cid'] . $pagelink, 'Edit Comment');
                    if ($userbank->HasAccess(WebPermission::Owner)) {
                        $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'C'," . (isset($_GET["page"]) ? $_GET["page"] : -1) . ");\"><i class='fas fa-trash fa-lg'></i></a>";
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

    $data['addcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=commslist&comment=' . $data['ban_id'] . '&ctype=C' . $pagelink);
    //-----------------------------------
    $data['counts']     = $delimiter . $mutes . $gags;

    $data['ub_reason']   = (isset($data['ub_reason']) ? $data['ub_reason'] : "");
    $data['banlength']   = $data['ban_length'] . " " . $data['ub_reason'];
    $data['view_edit']   = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans)) || ($userbank->HasAccess(WebPermission::EditOwnBans) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(WebPermission::EditGroupBans) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_unban']  = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban)) || ($userbank->HasAccess(WebPermission::UnbanOwnBans) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(WebPermission::UnbanGroupBans) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_delete'] = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan)));

    // === Row augmentation (#1123 B4) ======================================
    // Layer the keys the v2.0.0 template consumes ($comm.cid / .name /
    // .steam / .type / .length_human / .started_human / .started_iso /
    // .state / .sname / .admin / .reason / .{edit,unmute,delete}_url)
    // onto the same $data row that any third-party theme that forked
    // the pre-v2.0.0 default also consumes. Doing it inside the
    // original loop is what lets us read raw SQL columns (row_type,
    // ban_server, server_ip, ban_created) that don't survive into
    // $data otherwise.
    $rowRemovalState = BanRemoval::tryFrom((string) ($row['row_type'] ?? ''));
    $banLengthInt    = (int) $row['ban_length'];
    $banEndsInt      = (int) $row['ban_ends'];
    $cTimeInt        = (int) $row['c_time'];
    $stateLabel = match (true) {
        $rowRemovalState === BanRemoval::Deleted, $rowRemovalState === BanRemoval::Unbanned => 'unmuted',
        $rowRemovalState === BanRemoval::Expired || ($banLengthInt > 0 && $banEndsInt < $cTimeInt) => 'expired',
        $banLengthInt === 0                       => 'permanent',
        default                                   => 'active',
    };

    $typeInt = (int) $row['type'];
    switch ($typeInt) {
        case 1:
            $typeLabel = 'mute';
            break;
        case 2:
            $typeLabel = 'gag';
            break;
        case 3:
            // SourceComms forks sometimes assign 3 to the combined
            // "silence" (mute+gag); display it the same way regardless
            // of whether a write path actually emits the row.
            $typeLabel = 'silence';
            break;
        default:
            $typeLabel = 'unknown';
    }

    $banServerInt = (int) $row['ban_server'];
    if ($banServerInt === 0) {
        $snameLabel = 'Web Block';
    } else {
        $snameLabel = !empty($row['server_ip'])
            ? (string) $row['server_ip']
            : 'Server #' . $banServerInt;
    }

    $startedTs = (int) $row['ban_created'];
    $key       = (string) ($_SESSION['banlist_postkey'] ?? '');

    $data['cid']           = (int) $row['ban_id'];
    $data['name']          = stripslashes((string) $data['player']);
    $data['steam']         = (string) $data['steamid'];
    // Stable per-name hue for the row's avatar tile. crc32 keeps
    // identically-named players coloured the same across sessions
    // without any storage; the modulo into 360 gives an HSL hue.
    $data['avatar_hue']    = (int) (sprintf('%u', crc32($data['name'] . $data['steam'])) % 360);
    // Kept as a string label for the new theme's pill/icon switch. The
    // legacy default theme only reads `type_icon` (HTML-rendered above)
    // so overwriting `type` is safe — see the dual-theme audit in the
    // CommsListView class docblock.
    $data['type']          = $typeLabel;
    $data['length_human']  = (string) $data['ban_length'];
    $data['started_human'] = (string) $data['ban_date'];
    // ISO 8601 (`c`) so the new theme's <time datetime="…"> hooks give
    // the browser a parseable absolute timestamp regardless of the
    // admin-configured `config.dateformat`.
    $data['started_iso']   = date('c', $startedTs);
    $data['state']         = $stateLabel;
    $data['sname']         = $snameLabel;
    $data['edit_url']      = 'index.php?p=admin&c=comms&o=edit&id=' . (int) $row['ban_id'] . '&key=' . urlencode($key);
    $data['delete_url']    = 'index.php?p=commslist&a=delete&id=' . (int) $row['ban_id'] . '&key=' . urlencode($key);
    if ($stateLabel === 'active' || $stateLabel === 'permanent') {
        $verb              = $typeInt === 1 ? 'unmute' : ($typeInt === 2 ? 'ungag' : null);
        $data['unmute_url'] = $verb !== null
            ? 'index.php?p=commslist&a=' . $verb . '&id=' . (int) $row['ban_id'] . '&key=' . urlencode($key)
            : null;
    } else {
        $data['unmute_url'] = null;
    }

    array_push($bans, $data);
}

if (isset($_GET['advSearch'])) {
    $advSearchString = "&advSearch=" . urlencode(isset($_GET['advSearch']) ? $_GET['advSearch'] : '') . "&advType=" . urlencode(isset($_GET['advType']) ? $_GET['advType'] : '');
} else {
    $advSearchString = '';
}

// #1225: when the result count is zero we short-circuit to an empty
// string. Mirrors page.banlist.php — the legacy theme reads $ban_nav
// inside its `{if !empty($ban_nav)}` guard, and the new theme reads
// $pagination separately (page_comms.tpl gates that block on
// `$pagination.total > 0`). With no rows AND no filter, the empty
// state owns the surface alone instead of being shadowed by a
// "displaying 0 - 0 of 0 results" pagination shell.
if ($BanCount === 0) {
    $ban_nav = '';
} else {
    if ($page > 1) {
        if (isset($_GET['c']) && $_GET['c'] == "comms") {
            $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "javascript:void(0);", "", "_self", false, $prev);
        } else {
            $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=commslist&page=" . ($page - 1) . (isset($_GET['searchText']) > 0 ? "&searchText=" . urlencode($_GET['searchText']) : '' . $advSearchString));
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
            $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=commslist&page=" . ($page + 1) . (isset($_GET['searchText']) ? "&searchText=" . urlencode($_GET['searchText']) : '' . $advSearchString));
        }
    } else {
        $next = "";
    }

    //=================[ Start Layout ]==================================
    $ban_nav = 'displaying&nbsp;' . $BansStart . '&nbsp;-&nbsp;' . $BansEnd . '&nbsp;of&nbsp;' . $BanCount . '&nbsp;results';

    if ($prev !== '') {
        $ban_nav .= ' | <b>' . $prev . '</b>';
    }
    if ($next !== '') {
        $ban_nav .= ' | <b>' . $next . '</b>';
    }
    $pages = ceil($BanCount / $BansPerPage);
    if ($pages > 1) {
        // Issue #1113: see page.banlist.php for the layered-escape rationale.
        $advSearchJs = htmlspecialchars(addslashes((string)($_GET['advSearch'] ?? '')), ENT_QUOTES, 'UTF-8');
        $advTypeJs   = htmlspecialchars(addslashes((string)($_GET['advType']   ?? '')), ENT_QUOTES, 'UTF-8');
        $ban_nav .= '&nbsp;<select onchange="changePage(this,\'C\',\'' . $advSearchJs . '\',\'' . $advTypeJs . '\');">';
        for ($i = 1; $i <= $pages; $i++) {
            if (isset($_GET["page"]) && $i == $_GET["page"]) {
                $ban_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
                continue;
            }
            $ban_nav .= '<option value="' . $i . '">' . $i . '</option>';
        }
        $ban_nav .= '</select>';
    }
}

//COMMENT STUFF
//----------------------------------------
// Comment-drawer locals. Computed into PHP locals here (instead of
// the older `$theme->assign(...)` calls) so they can be threaded
// into the View constructor below — Renderer copies every public
// property onto $theme. The shipped v2.0.0 default theme defers the
// editor UI to a follow-up; any third-party theme that forked the
// pre-v2.0.0 default still renders it when `{if $comment}` is truthy.
$ceditType  = '';
$ceditText  = '';
$ceditCtype        = '';
$ceditCid          = '';
$ceditPage         = -1;
$ceditOthers = [];
if (isset($_GET["comment"])) {
    $ceditType = isset($_GET["cid"]) ? "Edit" : "Add";
    if (isset($_GET["cid"])) {
        $GLOBALS['PDO']->query("SELECT * FROM `:prefix_comments` WHERE cid = :cid");
        $GLOBALS['PDO']->bind(':cid', (int) $_GET["cid"]);
        $ceditdata      = $GLOBALS['PDO']->single();
        $ceditText = html_entity_decode($ceditdata['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cotherdataedit = " AND cid != '" . (int) $_GET["cid"] . "'";
    } else {
        $cotherdataedit = "";
    }
    $cotherdata = $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE type = ? AND bid = ?" . $cotherdataedit . " ORDER BY added desc")->resultset([
        $_GET["ctype"],
        $_GET["comment"],
    ]);

    foreach ($cotherdata as $cdrow) {
        $coment               = [];
        $coment['comname']    = $cdrow['comname'];
        $coment['added']      = Config::time($cdrow['added']);
        $commentText          = html_entity_decode($cdrow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $commentText          = encodePreservingBr($commentText);
        $commentText          = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
        $coment['commenttxt'] = $commentText;
        if ($cdrow['editname'] != "") {
            $coment['edittime'] = Config::time($cdrow['edittime']);
            $coment['editname'] = $cdrow['editname'];
        } else {
            $coment['editname'] = "";
            $coment['edittime'] = "";
        }
        array_push($ceditOthers, $coment);
    }

    $ceditPage = isset($_GET["page"]) ? (int) $_GET["page"] : -1;
    $ceditCtype = (string) $_GET["ctype"];
    $ceditCid   = isset($_GET["cid"]) ? (int) $_GET["cid"] : '';
}
$ceditBid = (isset($_GET["comment"]) && $view_comments) ? (int) $_GET["comment"] : false;
//----------------------------------------

unset($_SESSION['CountryFetchHndl']);

// `searchlink` is conditionally assigned in the search/advSearch/no-search
// branches above. Normalise it before threading it into the View so we
// don't widen the existing `Variable $searchlink might not be defined`
// baseline entry that catches the older `$theme->assign('searchlink', …)`
// reference in the original code path.
$searchlink = $searchlink ?? '';

// #1274: extend $searchlink with the chip TYPE (so toggle URL +
// pagination preserve a Mute/Gag/Silence selection across navigations).
// NOTE: chip STATE (`?state=active`) is intentionally NOT folded into
// $searchlink — the toggle URL needs to be able to drop it cleanly
// when the user clicks "Show inactive". $paginationLink below adds
// state on top so paginated pages still preserve the active filter.
if ($chipType !== '') {
    $searchlink .= '&type=' . urlencode($chipType);
}
$paginationLink = $searchlink . ($chipStateActive ? '&state=active' : '');

$theme->assign('admin_nick', $userbank->GetProperty("user"));
$theme->assign('admin_postkey', $_SESSION['banlist_postkey']);
$theme->assign('active_bans', $BanCount);
$theme->assign('general_unban', $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban, WebPermission::UnbanOwnBans, WebPermission::UnbanGroupBans)));
$theme->assign('can_delete', $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan)));

$hideAdminName = Config::getBool('banlist.hideadminname') && !$userbank->is_admin();
$viewBans      = (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans, WebPermission::EditOwnBans, WebPermission::EditGroupBans, WebPermission::Unban, WebPermission::UnbanOwnBans, WebPermission::UnbanGroupBans, WebPermission::DeleteBan));

// === View bag (#1123 B4) ==============================================
// Server filter dropdown — small list (one row per enabled server). The
// schema has no hostname column; the dropdown shows ip:port and a
// follow-up ticket can swap in a sb.api.call-driven resolver.
$serverRows = $GLOBALS['PDO']->query(
    "SELECT sid, ip, port FROM `:prefix_servers` WHERE enabled = 1 ORDER BY ip"
)->resultset();
$serversFilter = [];
foreach ($serverRows as $sr) {
    $serversFilter[] = [
        'sid'  => (int) $sr['sid'],
        'name' => (string) $sr['ip'] . ':' . (int) $sr['port'],
    ];
}

$filters = [
    'search' => isset($_GET['searchText']) ? (string) $_GET['searchText'] : '',
    'server' => isset($_GET['server']) ? (string) $_GET['server'] : '',
    'time'   => isset($_GET['time']) ? (string) $_GET['time'] : '',
    'state'  => $chipStateActive ? 'active' : '',
    'type'   => $chipType,
];

$pagination = [
    'from'     => $BanCount === 0 ? 0 : $BansStart + 1,
    'to'       => $BansEnd,
    'total'    => $BanCount,
    'prev_url' => $page > 1
        ? 'index.php?p=commslist&page=' . ($page - 1) . $paginationLink
        : null,
    'next_url' => $BansEnd < $BanCount
        ? 'index.php?p=commslist&page=' . ($page + 1) . $paginationLink
        : null,
];

$hideInactive = isset($_SESSION['hideinactive']);
// #1274: the toggle button is the single OFF-switch for active-only mode.
// When the chip's `?state=active` is on the URL, the toggle's "Show
// inactive" path must clear BOTH the session AND the URL state — else
// clicking the toggle would clear the session but leave the chip
// pressed, leaving us in a weird half-on state. $searchlink omits
// `state=active` precisely so this URL composes cleanly.
$hideInactiveToggle = 'index.php?p=commslist&hideinactive=' . ($isActiveOnly ? 'false' : 'true') . $searchlink;

// #1207: filter-aware empty state. Any filter chip / search / time /
// hide-inactive flips this; the template branches the empty-state
// copy + CTA on the result (first-run vs filtered).
$commsIsFiltered =
    $filters['search'] !== ''
    || $filters['server'] !== ''
    || $filters['time']   !== ''
    || $filters['state']  !== ''
    || $filters['type']   !== ''
    || $hideInactive;

// #1315: auto-open the advanced-search disclosure on a post-submit
// paint. Bare `?p=commslist` and simple-bar filters
// (`?searchText=` / `?server=` / `?time=` / `?type=` / `?state=`)
// leave it closed so the unfiltered list reaches above the fold. The
// legacy ?advSearch shim is the only surface that re-opens it,
// mirroring v1.x behaviour where the form was always-open below the
// row table — the v2.0 disclosure is the post-#1303 collapsed shape
// with the same post-submit affordance the admin-admins page uses.
$commsAdvancedOpen =
    isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== '';

// Aggregate permission flags. Each precomputed via Perms::for($userbank)
// so the template's {if $can_*} reads stay opinion-free about the bit
// math — see the AGENTS.md "Permissions" section + Perms::for() docblock.
$perms             = \Sbpp\View\Perms::for($userbank);
$can_add_comm     = $perms['can_owner'] || $perms['can_add_ban'];
$can_edit_comm    = $perms['can_owner']
    || $perms['can_edit_all_bans']
    || $perms['can_edit_own_bans']
    || $perms['can_edit_group_bans'];
$can_unmute_gag   = $perms['can_owner']
    || $perms['can_unban']
    || $perms['can_unban_own_bans']
    || $perms['can_unban_group_bans'];
$can_delete_comm  = $perms['can_owner'] || $perms['can_delete_ban'];

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\CommsListView(
    total_bans:               $BanCount,
    searchlink:               $searchlink,
    ban_list:                 $bans,
    filters:                  $filters,
    servers:                  $serversFilter,
    pagination:               $pagination,
    hide_inactive:            $hideInactive,
    is_active_only:           $isActiveOnly,
    hide_inactive_toggle_url: $hideInactiveToggle,
    can_add_comm:             $can_add_comm,
    can_edit_comm:            $can_edit_comm,
    can_unmute_gag:           $can_unmute_gag,
    can_delete_comm:          $can_delete_comm,
    is_filtered:              $commsIsFiltered,
    comment:                  $ceditBid,
    commenttype:              $ceditType,
    canedit:                  $userbank->is_admin(),
    commenttext:              $ceditText,
    ctype:                    $ceditCtype,
    cid:                      $ceditCid,
    page:                     $ceditPage,
    othercomments:            $ceditOthers,
    ban_nav:                  $ban_nav,
    hidetext:                 $hidetext,
    hideadminname:            $hideAdminName,
    view_comments:            (bool) $view_comments,
    view_bans:                $viewBans,
    is_advanced_search_open:  $commsAdvancedOpen,
));
