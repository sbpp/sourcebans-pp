<?php
/**
 * =============================================================================
 * Edit admin details
 *
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: admin.edit.admindetails.php 249 2009-03-25 22:26:22Z peace-maker $
 * =============================================================================
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

if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Getting admin data failed", "Can't find data for admin with id '".$_GET['id']."'");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Error getting current data.
</div>';
	PageDie();
}


// Skip all checks if root
if(!$userbank->HasAccess(ADMIN_OWNER))
{
	if(!$userbank->HasAccess(ADMIN_EDIT_ADMINS) || ($userbank->HasAccess(ADMIN_OWNER, $_GET['id']) && $_GET['id'] != $userbank->GetAid()))
	{
		$log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s details, but doesnt have access.");
		echo '<div id="msg-red" >
		<i><img src="./images/warning.png" alt="Warning" /></i>
		<b>Error</b>
		<br />
		You are not allowed to edit other profiles.
	</div>';
		PageDie();
	}
}

if(isset($_POST['adminname']))
{
	if(!empty($_POST["a_spass"]) && empty($_POST['password'])) 
	{
		$changeerrors .= '* Can\'t enable the server admin password. You need to change the password.';
		$validchange = false;
	}


	if(empty($_POST['password']) || !$userbank->HasAccess(ADMIN_OWNER))
		{

			if($_POST['a_spass'] != "on")
				$srvpw = NULL;
			else
				$srvpw = $userbank->GetProperty("srv_password", $_GET['id']);

			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
										`user` = ?, `authid` = ?, `email` = ?, `srv_password` = ?
										WHERE `aid` = ?", array(RemoveCode($_POST['adminname']), trim(RemoveCode($_POST['steam'])), RemoveCode($_POST['email']), $srvpw, (int)$_GET['id']));
		}
		else
		{
			if($_POST['a_spass'] == "on")
				$srvpw = $_POST['password'];
			else
				$srvpw = NULL;
			
			// to prevent rehash window to error with "no access", cause pw doesn't match
			$ownpwchanged = false;
			if($_GET['id']==$userbank->GetAid() && $userbank->encrypt_password($_POST['password'])!=$userbank->GetProperty("password"))
				$ownpwchanged = true;
			
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_admins SET
										`user` = ?,
										`authid` = ?,
										`email` = ?,
										`password` = ?,
										`srv_password` = ?
										WHERE `aid` = ". (int)$_GET['id'], array(RemoveCode($_POST['adminname']), trim(RemoveCode($_POST['steam'])), RemoveCode($_POST['email']), $userbank->encrypt_password($_POST['password']), $srvpw));
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
			$rehashing = true;
		}
		$admname = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = ?", array((int)$_GET['id']));
		$log = new CSystemLog("m", "Admin Servers Updated", "Admin (" . $admname['user'] . ") details has been changed");
		if($ownpwchanged)
			echo '<script>ShowBox("Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=login");TabToReload();</script>';
		else if(isset($rehashing))
			echo '<script>ShowRehashBox("'.$allservers.'", "Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
		else
			echo '<script>ShowBox("Admin details updated", "The admin details has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
}



$theme->assign('change_pass', ($userbank->HasAccess(ADMIN_OWNER) ||  $_GET['id'] == $userbank->GetAid()));
$theme->assign('user', $userbank->GetProperty("user", $_GET['id']));
$theme->assign('authid', trim($userbank->GetProperty("authid", $_GET['id'])));
$theme->assign('email', $userbank->GetProperty("email", $_GET['id']));
$theme->assign('a_spass', $userbank->GetProperty("srv_password", $_GET['id']));

$theme->display('page_admin_edit_admins_details.tpl');
?>
