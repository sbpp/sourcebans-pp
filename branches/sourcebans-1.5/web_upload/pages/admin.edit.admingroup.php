<?php 
/**
 * Edit the admins group
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 
global $userbank, $theme;

if(!isset($_GET['id']))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
	PageDie();
}

$_GET['id'] = (int)$_GET['id'];
if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
{
	$log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s groups, but doesn't have access.");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	You are not allowed to edit other groups.
</div>';
	PageDie();
}

$authId = $userbank->GetProperty('authid', $_GET['id']);

$serveradmin = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_admins WHERE authid = ?", array($authId));
$wgroups = $GLOBALS['db']->GetAll("SELECT * FROM ".DB_PREFIX."_groups WHERE type != 3");
$sgroups = $GLOBALS['db']->GetAll("SELECT * FROM ".DB_PREFIX."_srvgroups");

if(isset($_POST['wg']) || isset($_GET['wg']) || isset($_GET['sg']))
{
	if(isset($_GET['wg'])) {
		$_POST['wg'] = $_GET['wg'];
	}
	if(isset($_GET['sg'])) {
		$_POST['sg'] = $_GET['sg'];
	}
	if(isset($_POST['wg']))	{
		// Edit the web group
		$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
										`gid` = '" . (int)$_POST['wg'] . "'
										WHERE `aid` = ". (int)$_GET['id']);
	}
	
	if(isset($_POST['sg'])) {
		// Edit the server admin group
		$grps = $GLOBALS['db']->GetRow("SELECT name FROM ".DB_PREFIX."_srvgroups WHERE id = " . (int)$_POST['sg']);
		if(!$grps)
			$group = "";
		else 
			$group = $grps['name'];
			
		$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
										`srv_group` = ?
										WHERE authid = ?", array($group, $authId));
		
		$srv = $GLOBALS['db']->GetAll("SELECT * FROM ".DB_PREFIX."_admins_servers_groups WHERE admin_id = (SELECT aid FROM ".DB_PREFIX."_admins WHERE authid = ?)", array($authId));
		foreach($srv AS $s)
		{
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins_servers_groups SET
										`group_id` = " . (int)$_POST['sg'] . "
										WHERE admin_id = (SELECT aid FROM ".DB_PREFIX."_admins WHERE authid = ?)", array($authId));
			
		}
	}
	if(isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1)
	{
		// rehash the admins on the servers
		$serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `".DB_PREFIX."_servers` s
												LEFT JOIN `".DB_PREFIX."_admins_servers_groups` asg ON asg.admin_id = '".(int)$_GET['id']."'
												LEFT JOIN `".DB_PREFIX."_servers_groups` sg ON sg.group_id = asg.srv_group_id
												WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
												OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
												AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
		$allservers = "";
		foreach($serveraccessq as $access) {
			if(!strstr($allservers, $access['sid'].",")) {
				$allservers .= $access['sid'].",";
			}
		}
		echo '<script>ShowRehashBox("'.$allservers.'", "Admin updated", "The admin has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
	}
	else
		echo '<script>ShowBox("Admin updated", "The admin has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
	
	$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$_GET['id']));
	$log = new CSystemLog("m", "Admin Group Updated", "Admin (" . $admname['user'] . ") groups has been updated");
}


if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Getting admin data failed", "Can't find data for admin with id '".$_GET['id']."'");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Error getting current data.</div>';
	PageDie();
}

$sarray = array();
$lst = "";
$wlist = "";
foreach($sgroups AS $g)
{
	$grp = array($g['name'] => $g['id']);
	array_push($sarray, $grp);
	$lst .= "<option value='" . $g['id'] . "'>" . $g['name'] . "</option>";
}
foreach($wgroups AS $g)
{
	$wlist .= "<option value='" . $g['gid'] . "'>" . $g['name'] . "</option>";
}
$tmp = 0;
foreach($sarray as $idx=>$a)
{
	$tmp = isset($sarray[$idx][$serveradmin['srv_group']])?$sarray[$idx][$serveradmin['srv_group']]:false; 
	if($tmp)
		break;
}
$theme->assign('group_admin_name', $userbank->GetProperty("user", $_GET['id']));
$theme->assign('group_admin_id', $userbank->GetProperty("gid", $_GET['id']));
$theme->assign('group_lst',  $lst);
$theme->assign('web_lst',  $wlist);
$theme->assign('tmp',  $tmp);

$theme->display('page_admin_edit_admins_group.tpl');
?>
