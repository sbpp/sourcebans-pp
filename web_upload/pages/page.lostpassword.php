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

global $theme, $userbank;

if(isset($_GET['validation'],$_GET['email']) && !empty($_GET['email']) && !empty($_GET['validation']))
{  
	$email = $_GET['email'];
	$validation = $_GET['validation'];

	preg_match("/[\w\.]*/", $_SERVER['HTTP_HOST'], $match);

	if($match[0] != $_SERVER['HTTP_HOST']) 
	{ 
		echo '<div id="msg-red" style="">
			<i><img src="./images/warning.png" alt="Warning" /></i>
			<b>Error</b>
			<br />			
			An unknown error occured.
			</div>';
		$log = new CSystemLog("w", "Hacking Attempt", "Attempted password reset email injection. Using: " . $_SERVER['HTTP_HOST']);
		exit();
	}

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
