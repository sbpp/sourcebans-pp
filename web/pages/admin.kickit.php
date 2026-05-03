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

include_once '../init.php';

global $userbank, $theme;

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN)) {
    echo "No Access";
    die();
}

$servers = $GLOBALS['PDO']->query("SELECT ip, port, rcon FROM `:prefix_servers` WHERE enabled = 1 ORDER BY modid, sid")->resultset();
$theme->assign('total', count($servers));
$serverlinks = [];
$num         = 0;
foreach ($servers as $server) {
    $serverlinks[] = ['num' => $num, 'ip' => $server['ip'], 'port' => $server['port']];
    $num++;
}
$theme->assign('servers', $serverlinks);
$theme->assign('check', $_GET['check'] ?? '');
$theme->assign('type', $_GET['type'] ?? 0);

$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');
$theme->display('page_kickit.tpl');
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
