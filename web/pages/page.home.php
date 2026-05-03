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

global $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
define('IN_HOME', true);

$totalstopped = (int) $GLOBALS['PDO']->query("SELECT count(name) AS cnt FROM `:prefix_banlog`")->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip
								FROM `:prefix_banlog` AS bl
								LEFT JOIN `:prefix_bans` AS b ON b.bid = bl.bid
								ORDER BY time DESC LIMIT 10")->resultset();

$GLOBALS['server_qry'] = "";
$stopped               = [];
$blcount               = 0;
foreach ($rows as $row) {
    $info               = [];
    $info['date']       = Config::time($row['time']);
    $raw_name           = htmlspecialchars(stripslashes((string) $row['name']), ENT_NOQUOTES, 'UTF-8');
    $cleaned_name       = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']       = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['short_name'] = trunc($cleaned_name, 40);
    $info['auth']       = $row['authid'];
    $info['ip']         = $row['ip'];
    $info['server']     = "block_" . $row['sid'] . "_$blcount";

    if ($row['type'] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['ip']) . '&advType=ip&Submit';
        else
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['name']) . '&advType=name';
    } else {
        $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['auth']) . '&advType=steamid&Submit';
    }
    $info['link_url'] = "window.location = '" . $info['search_link'] . "';";

    // To print a name in the popup instead an empty string
    if (empty($cleaned_name)) {
        $cleaned_name = "<i>No nickname present</i>";
    }
    $info['popup']    = "ShowBox('Blocked player: " . $cleaned_name . "', '" . $cleaned_name . " tried to enter<br />' + document.getElementById('" . $info['server'] . "').title + '<br />at " . $info['date'] . "<br /><div align=middle><a href=" . $info['search_link'] . ">Click here for ban details.</a></div>', 'red', '', true);";

    $GLOBALS['server_qry'] .= "xajax_ServerHostProperty(" . $row['sid'] . ", 'block_" . $row['sid'] . "_$blcount', 'title', 100);";

    $stopped []= $info;
    ++$blcount;
}

$BanCount = (int) $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_bans`")->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid, mo.icon, ba.RemoveType, ba.type
			    				FROM `:prefix_bans` AS ba
			    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
			    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
			    				LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid
			    				ORDER BY created DESC LIMIT 10")->resultset();
$bans = [];
foreach ($rows as $row) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;
    if ($row['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $raw_name         = stripslashes($row['name']);
    $cleaned_name     = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']    = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['created'] = Config::time($row['created']);
    $ltemp           = explode(",", $row['length'] == 0 ? 'Permanent' : SecondsToString(intval($row['length'])));
    $info['length']  = $ltemp[0];
    $info['icon']    = empty($row['icon']) ? 'web.png' : $row['icon'];
    $info['authid']  = $row['authid'];
    $info['ip']      = $row['ip'];
    if ($row['type'] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['ip']) . '&advType=ip&Submit';
        else
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['name']) . '&advType=name';
    } else {
        $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['authid']) . '&advType=steamid&Submit';
    }
    $info['link_url']   = "window.location = '" . $info['search_link'] . "';";
    $info['short_name'] = trunc($cleaned_name, 40);

    if ($row['RemoveType'] == 'D' || $row['RemoveType'] == 'U' || $row['RemoveType'] == 'E' || ($row['length'] && $row['ends'] < time())) {
        $info['unbanned'] = true;

        if ($row['RemoveType'] == 'D') {
            $info['ub_reason'] = 'D';
        } elseif ($row['RemoveType'] == 'U') {
            $info['ub_reason'] = 'U';
        } else {
            $info['ub_reason'] = 'E';
        }
    } else {
        $info['unbanned'] = false;
    }

    array_push($bans, $info);
}

$CommCount = (int) $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_comms`")->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bid, ba.authid, ba.type, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid, mo.icon, ba.RemoveType
				    				FROM `:prefix_comms` AS ba
				    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
				    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
				    				LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid
				    				ORDER BY created DESC LIMIT 10")->resultset();
$comms = [];
foreach ($rows as $row) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;

    if ($row['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $raw_name             = stripslashes($row['name']);
    $cleaned_name         = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences   = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']        = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['created']     = Config::time($row['created']);
    $ltemp               = explode(",", $row['length'] == 0 ? 'Permanent' : SecondsToString(intval($row['length'])));
    $info['length']      = $ltemp[0];
    $info['icon']        = empty($row['icon']) ? 'web.png' : $row['icon'];
    $info['authid']      = $row['authid'];
    $info['search_link'] = 'index.php?p=commslist&advSearch=' . urlencode($info['authid']) . '&advType=steamid&Submit';
    $info['link_url']    = "window.location = '" . $info['search_link'] . "';";
    $info['short_name']  = trunc($cleaned_name, 40);
    $info['type']        = $row['type'] == 2 ? "fas fa-comment-slash fa-lg" : "fas fa-microphone-slash fa-lg";

    if ($row['RemoveType'] == 'D' || $row['RemoveType'] == 'U' || $row['RemoveType'] == 'E' || ($row['length'] && $row['ends'] < time())) {
        $info['unbanned'] = true;

        if ($row['RemoveType'] == 'D') {
            $info['ub_reason'] = 'D';
        } elseif ($row['RemoveType'] == 'U') {
            $info['ub_reason'] = 'U';
        } else {
            $info['ub_reason'] = 'E';
        }
    } else {
        $info['unbanned'] = false;
    }

    array_push($comms, $info);
}


require(TEMPLATES_PATH . "/page.servers.php"); //Set theme vars from servers page

$theme->assign('dashboard_lognopopup', Config::getBool('dash.lognopopup'));
$theme->assign('dashboard_title', Config::get('dash.intro.title'));
$theme->assign('dashboard_text', Config::get('dash.intro.text'));
$theme->assign('players_blocked', $stopped);
$theme->assign('total_blocked', $totalstopped);

$theme->assign('players_banned', $bans);
$theme->assign('total_bans', $BanCount);

$theme->assign('total_comms', $CommCount);
$theme->assign('players_commed', $comms);

$theme->display('page_dashboard.tpl');
