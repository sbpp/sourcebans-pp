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
$srv_admins = $GLOBALS['db']->GetAll("SELECT authid, user
										FROM " . DB_PREFIX . "_admins_servers_groups AS asg						
										LEFT JOIN " . DB_PREFIX . "_admins AS a ON a.aid = asg.admin_id			
										WHERE (server_id = " . (int)$_GET['id'] . " OR srv_group_id = ANY					
										(															
			   								SELECT group_id											
			   								FROM " . DB_PREFIX . "_servers_groups									
			   								WHERE server_id = " . (int)$_GET['id'] . ")									
										)															
										GROUP BY aid, authid, srv_password, srv_group, srv_flags, user ");
$i = 0;
foreach($srv_admins as $admin) {
	$admsteam[] = $admin['authid'];
}
if(sizeof($admsteam)>0 && $serverdata = checkMultiplePlayers((int)$_GET['id'], $admsteam))
	$noproblem = true;
foreach($srv_admins as $admin) {
	$admins[$i]['user'] = $admin['user'];
	$admins[$i]['authid'] = $admin['authid'];
	if(isset($noproblem) && isset($serverdata[$admin['authid']])) {
	$admins[$i]['ingame'] = true;
	$admins[$i]['iname'] = $serverdata[$admin['authid']]['name'];
	$admins[$i]['iip'] = $serverdata[$admin['authid']]['ip'];
	$admins[$i]['iping'] = $serverdata[$admin['authid']]['ping'];
	$admins[$i]['itime'] = $serverdata[$admin['authid']]['time'];
	} else
		$admins[$i]['ingame'] = false;
	$i++;
}
										
$theme->assign('admin_count', count($srv_admins));
$theme->assign('admin_list', $admins);
?>


<div id="admin-page-content">
<div id="0" style="display:none;">

<?php $theme->display('page_admin_servers_adminlist.tpl'); ?>

</div>
</div>
