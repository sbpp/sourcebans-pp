<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $theme;
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}

if(defined('IN_HOME'))
	$number = -1;
else
{
    $GLOBALS['server_qry'] = "";
	if(isset($_GET['s']))				
		$number = (int)$_GET['s'];
	else 
		$number = -1;
}

$res = $GLOBALS['db']->Execute("SELECT se.sid, se.ip, se.port, se.modid, se.rcon, md.icon FROM ".DB_PREFIX."_servers se LEFT JOIN ".DB_PREFIX."_mods md ON md.mid=se.modid WHERE se.sid > 0 AND se.enabled = 1 ORDER BY se.modid, se.sid");
$servers = array();
$i=0;
while (!$res->EOF)
{
	if(isset($_SESSION['getInfo.' . $res->fields[1] . '.' . $res->fields[2]]))
	{
		$_SESSION['getInfo.' . $res->fields[1] . '.' . $res->fields[2]] = "";
	}
	$info = array();
	$info['sid'] = $res->fields[0];
	$info['ip'] = $res->fields[1];
	$info['port'] = $res->fields[2];
	$info['icon'] = $res->fields[5];
	$info['index'] = $i;
	if(defined('IN_HOME'))
		$info['evOnClick'] = "window.location = 'index.php?p=servers&s=".$info['index']."';";	
	
	$GLOBALS['server_qry'] .= "xajax_ServerHostPlayers({$info['sid']}, 'servers', '', '".$i."', '".$number."', '".defined('IN_HOME')."', 70);";
	array_push($servers,$info);
	$i++;
	$res->MoveNext();
}

$theme->assign('access_bans', ($userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)?true:false));
$theme->assign('server_list', $servers);
$theme->assign('IN_SERVERS_PAGE', !defined('IN_HOME'));
$theme->assign('opened_server', $number);

if(!defined('IN_HOME'))
	$theme->display('page_servers.tpl');
?>
