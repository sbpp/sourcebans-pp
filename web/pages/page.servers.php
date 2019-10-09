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

global $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

$number = -1;
if (!defined('IN_HOME')) {
    $GLOBALS['server_qry'] = "";
    if (isset($_GET['s'])) {
        $number = (int) $_GET['s'];
    }
}

$res     = $GLOBALS['db']->Execute("SELECT se.sid, se.ip, se.port, se.modid, se.rcon, md.icon FROM " . DB_PREFIX . "_servers se LEFT JOIN " . DB_PREFIX . "_mods md ON md.mid=se.modid WHERE se.sid > 0 AND se.enabled = 1 ORDER BY se.modid, se.sid");
$servers = array();
$i       = 0;
while (!$res->EOF) {
    $info          = array();
    $info['sid']   = $res->fields[0];
    $info['ip']    = $res->fields[1];
    $info['port']  = $res->fields[2];
    $info['icon']  = $res->fields[5];
    $info['index'] = $i;
    if (defined('IN_HOME')) {
        $info['evOnClick'] = "window.location = 'index.php?p=servers&s=" . $info['index'] . "';";
    }

    $GLOBALS['server_qry'] .= "xajax_ServerHostPlayers({$info['sid']}, 'servers', '', '" . $i . "', '" . $number . "', '" . defined('IN_HOME') . "', 70);";
    array_push($servers, $info);
    $i++;
    $res->MoveNext();
}

$theme->assign('access_bans', ($userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN) ? true : false));
$theme->assign('server_list', $servers);
$theme->assign('IN_SERVERS_PAGE', !defined('IN_HOME'));
$theme->assign('opened_server', $number);

if (!defined('IN_HOME')) {
    $theme->display('page_servers.tpl');
}
