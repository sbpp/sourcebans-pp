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
*************************************************************************/

global $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
define('IN_HOME', true);

$res          = $GLOBALS['db']->Execute("SELECT count(name) FROM " . DB_PREFIX . "_banlog");
$totalstopped = (int) $res->fields[0];

$res = $GLOBALS['db']->Execute("SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip
								FROM " . DB_PREFIX . "_banlog AS bl
								LEFT JOIN " . DB_PREFIX . "_bans AS b ON b.bid = bl.bid
								ORDER BY time DESC LIMIT 10");

$GLOBALS['server_qry'] = "";
$stopped               = [];
$blcount               = 0;
while (!$res->EOF) {
    $info               = [];
    $info['date']       = Config::time($res->fields[1]);
    $info['name']       = stripslashes(filter_var($res->fields[0], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES));
    $info['short_name'] = trunc($info['name'], 40);
    $info['auth']       = $res->fields['authid'];
    $info['ip']         = $res->fields['ip'];
    $info['server']     = "block_" . $res->fields['sid'] . "_$blcount";

    if ($res->fields['type'] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = "index.php?p=banlist&advSearch=$info[ip]&advType=ip&Submit";
        else
            $info['search_link'] = "index.php?p=banlist&advSearch=$info[name]&advType=name";
    } else {
        $info['search_link'] = "index.php?p=banlist&advSearch=" . $info['auth'] . "&advType=steamid&Submit";
    }
    $info['link_url'] = "window.location = '" . $info['search_link'] . "';";
    $info['name']     = htmlspecialchars(addslashes($info['name']), ENT_QUOTES, 'UTF-8');
    $info['popup']    = "ShowBox('Blocked player: " . $info['name'] . "', '" . $info['name'] . " tried to enter<br />' + document.getElementById('" . $info['server'] . "').title + '<br />at " . $info['date'] . "<br /><div align=middle><a href=" . $info['search_link'] . ">Click here for ban details.</a></div>', 'red', '', true);";

    $GLOBALS['server_qry'] .= "xajax_ServerHostProperty(" . $res->fields['sid'] . ", 'block_" . $res->fields['sid'] . "_$blcount', 'title', 100);";

    $stopped []= $info;
    $res->MoveNext();
    ++$blcount;
}

$res      = $GLOBALS['db']->Execute("SELECT count(bid) FROM " . DB_PREFIX . "_bans");
$BanCount = (int) $res->fields[0];

$res  = $GLOBALS['db']->Execute("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, CONCAT(se.ip,':',se.port), se.sid, mo.icon, ba.RemoveType, ba.type
			    				FROM " . DB_PREFIX . "_bans AS ba
			    				LEFT JOIN " . DB_PREFIX . "_admins AS ad ON ba.aid = ad.aid
			    				LEFT JOIN " . DB_PREFIX . "_servers AS se ON se.sid = ba.sid
			    				LEFT JOIN " . DB_PREFIX . "_mods AS mo ON mo.mid = se.modid
			    				ORDER BY created DESC LIMIT 10");
$bans = [];
while (!$res->EOF) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;
    if ($res->fields['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $info['name']    = stripslashes($res->fields[3]);
    $info['created'] = Config::time($res->fields['created']);
    $ltemp           = explode(",", $res->fields[6] == 0 ? 'Permanent' : SecondsToString(intval($res->fields[6])));
    $info['length']  = $ltemp[0];
    $info['icon']    = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
    $info['authid']  = $res->fields[2];
    $info['ip']      = $res->fields[1];
    if ($res->fields[15] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = "index.php?p=banlist&advSearch=$info[ip]&advType=ip&Submit";
        else
            $info['search_link'] = "index.php?p=banlist&advSearch=$info[name]&advType=name";
    } else {
        $info['search_link'] = "index.php?p=banlist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
    }
    $info['link_url']   = "window.location = '" . $info['search_link'] . "';";
    $info['short_name'] = trunc($info['name'], 40);

    if ($res->fields[14] == 'D' || $res->fields[14] == 'U' || $res->fields[14] == 'E' || ($res->fields[6] && $res->fields[5] < time())) {
        $info['unbanned'] = true;

        if ($res->fields[14] == 'D') {
            $info['ub_reason'] = 'D';
        } elseif ($res->fields[14] == 'U') {
            $info['ub_reason'] = 'U';
        } else {
            $info['ub_reason'] = 'E';
        }
    } else {
        $info['unbanned'] = false;
    }

    array_push($bans, $info);
    $res->MoveNext();
}

$res       = $GLOBALS['db']->Execute("SELECT count(bid) FROM " . DB_PREFIX . "_comms");
$CommCount = (int) $res->fields[0];

$res   = $GLOBALS['db']->Execute("SELECT bid, ba.authid, ba.type, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, CONCAT(se.ip,':',se.port), se.sid, mo.icon, ba.RemoveType, ba.type
				    				FROM " . DB_PREFIX . "_comms AS ba
				    				LEFT JOIN " . DB_PREFIX . "_admins AS ad ON ba.aid = ad.aid
				    				LEFT JOIN " . DB_PREFIX . "_servers AS se ON se.sid = ba.sid
				    				LEFT JOIN " . DB_PREFIX . "_mods AS mo ON mo.mid = se.modid
				    				ORDER BY created DESC LIMIT 10");
$comms = [];
while (!$res->EOF) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;

    if ($res->fields['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $info['name']        = stripslashes($res->fields[3]);
    $info['created']     = Config::time($res->fields['created']);
    $ltemp               = explode(",", $res->fields[6] == 0 ? 'Permanent' : SecondsToString(intval($res->fields[6])));
    $info['length']      = $ltemp[0];
    $info['icon']        = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
    $info['authid']      = $res->fields['authid'];
    $info['search_link'] = "index.php?p=commslist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
    $info['link_url']    = "window.location = '" . $info['search_link'] . "';";
    $info['short_name']  = trunc($info['name'], 40);
    $info['type']        = $res->fields['type'] == 2 ? "fas fa-comment-slash fa-lg" : "fas fa-microphone-slash fa-lg";

    if ($res->fields[14] == 'D' || $res->fields[14] == 'U' || $res->fields[14] == 'E' || ($res->fields[6] && $res->fields[5] < time())) {
        $info['unbanned'] = true;

        if ($res->fields[14] == 'D') {
            $info['ub_reason'] = 'D';
        } elseif ($res->fields[14] == 'U') {
            $info['ub_reason'] = 'U';
        } else {
            $info['ub_reason'] = 'E';
        }
    } else {
        $info['unbanned'] = false;
    }

    array_push($comms, $info);
    $res->MoveNext();
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
