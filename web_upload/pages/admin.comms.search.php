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

global $userbank, $theme;
$admin_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_admins` ORDER BY user ASC");
$server_list = $GLOBALS['db']->Execute("SELECT sid, ip, port FROM `" . DB_PREFIX . "_servers` WHERE enabled = 1");
$servers = array();
$serverscript = "<script type=\"text/javascript\">";
while (!$server_list->EOF)
{
	$info = array();
    $serverscript .= "xajax_ServerHostPlayers('".$server_list->fields[0]."', 'id', 'ss".$server_list->fields[0]."', '', '', false, 200);";
	$info['sid'] = $server_list->fields[0];
	$info['ip'] = $server_list->fields[1];
	$info['port'] = $server_list->fields[2];
	array_push($servers,$info);
	$server_list->MoveNext();
}
$serverscript .= "</script>";
$page = isset($_GET['page'])?$_GET['page']:1;

$theme->assign('hideplayerips', (isset($GLOBALS['config']['banlist.hideplayerips']) && $GLOBALS['config']['banlist.hideplayerips'] == "1" && !$userbank->is_admin()));
$theme->assign('is_admin', $userbank->is_admin());
$theme->assign('admin_list', $admin_list);
$theme->assign('server_list', $servers);
$theme->assign('server_script', $serverscript);

$theme->display('box_admin_comms_search.tpl');
?>
<script type="text/javascript">
function switch_length(opt)
{
	if(opt.options[opt.selectedIndex].value=='other')
	{
		$('other_length').setStyle('display', 'block');
		$('other_length').focus();
		$('length').setStyle('width', '20px');
	} else { 
		$('other_length').setStyle('display', 'none');
		$('length').setStyle('width', '210px');
	}
}
</script>
