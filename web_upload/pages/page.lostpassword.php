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

global $theme, $userbank;

if(isset($_GET['validation'],$_GET['email']) && !empty($_GET['email']) && !empty($_GET['validation']))
{  
	$email = $_GET['email'];
	$validation = $_GET['validation'];
	
	if(strlen($validation) < 60)
	{
		echo '<div id="msg-red" style="">
			<i><img src="./images/warning.png" alt="Warning" /></i>
			<b>Error</b>
			<br />
			The validation string is too short.
			</div>';
		exit();
	}
	
	$q = $GLOBALS['db']->GetRow("SELECT aid, user FROM `" . DB_PREFIX . "_admins` WHERE `email` = ? && `validate` IS NOT NULL && `validate` = ?", array($email, $validation));
	if($q)
	{
		$newpass = generate_salt(MIN_PASS_LENGTH+8);
		$query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `password` = '" . $userbank->encrypt_password($newpass) . "', validate = NULL WHERE `aid` = ?", array($q['aid']));
		$message = "Hello " . $q['user'] . ",\n\n";
		$message .= "Your password reset was successful.\n";
		$message .= "Your password was changed to: ".$newpass."\n\n";
		$message .= "Login to your SourceBans account and change your password in Your Account.\n";

		$headers = 'From: lostpwd@' . $_SERVER['HTTP_HOST'] . "\n" .
		'X-Mailer: PHP/' . phpversion();
		$m = mail($email, "SourceBans Password Reset", $message, $headers);
		
		echo '<div id="msg-blue" style="">
			<i><img src="./images/info.png" alt="Info" /></i>
			<b>Password Reset</b>
			<br />
			Your password has been reset and sent to your email.<br />Please check your spam folder too.<br />Please login using this password, <br />then use the change password link in Your Account.
			</div>';
	}
	else 
	{
		echo '<div id="msg-red" style="">
			<i><img src="./images/warning.png" alt="Warning" /></i>
			<b>Error</b>
			<br />
			The validation string does not match the email for this reset request.
			</div>';
	}
}else 
{
	$theme->display('page_lostpassword.tpl');
}
?>
