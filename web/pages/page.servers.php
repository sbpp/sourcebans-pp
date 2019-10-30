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

$GLOBALS['PDO']->query(
    "SELECT se.sid, se.ip, se.port, se.modid, se.rcon, md.icon FROM `:prefix_servers` se
    LEFT JOIN `:prefix_mods` md ON md.mid = se.modid WHERE se.enabled = 1
    ORDER BY se.modid, se.sid");

$servers = $GLOBALS['PDO']->resultset();

$home = defined('IN_HOME');
foreach($servers as $key => $serv) {
    if ($home) {
        $servers[$key]['evOnClick'] = "window.location = 'index.php?p=servers&s=$key';";
    }

    $GLOBALS['server_qry'] .= "xajax_ServerHostPlayers($serv[sid], 'servers', ''. '$key', '$number', '$home', 70);";
}

$theme->assign('access_bans', ($userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN) ? true : false));
$theme->assign('server_list', $servers);
$theme->assign('IN_SERVERS_PAGE', !$home);
$theme->assign('opened_server', $number);

if (!defined('IN_HOME')) {
    $theme->display('page_servers.tpl');
}
