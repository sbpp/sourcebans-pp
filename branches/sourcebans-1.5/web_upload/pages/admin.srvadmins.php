<?php
/**
 * List server admins
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */

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
