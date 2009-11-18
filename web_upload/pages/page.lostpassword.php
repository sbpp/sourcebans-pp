<?php 
/**
 * =============================================================================
 * Lost password page
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: page.lostpassword.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

global $theme;

if(isset($_GET['validation'],$_GET['email']) && !empty($_GET['email']) && !empty($_GET['validation']))
{  
	$email = $_GET['email'];
	$validation = $_GET['validation'];
	$q = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_admins` WHERE `email` = ? && `validate` = ?", array($email, $validation));
	if($q[0])
	{
		$newpass = generate_salt(MIN_PASS_LENGTH+1);
		$query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `password` = '" . $userbank->encrypt_password($newpass) . "' WHERE `email` = ?", array($email));
		$query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `validate` = '' WHERE `email` = ?", array($email));
		echo '<div id="msg-blue" style="">
			<i><img src="./images/info.png" alt="Warning" /></i>
			<b>Password Reset</b>
			<br />
			Your password has been reset to<br /><br /><b> '.$newpass.'</b><br />Please login using this password, then use the change password link in Your Account
			</div>';
	}
	else 
	{
		echo '<div id="msg-red" style="">
			<i><img src="./images/warning.png" alt="Warning" /></i>
			<b>Error</b>
			<br />
			The validation string does not match the email for this reset request.</i>
			</div>';
	}
}else 
{
	$theme->display('page_lostpassword.tpl');
}
?>
