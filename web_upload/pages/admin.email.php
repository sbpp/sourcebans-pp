<?php 
/**
 * =============================================================================
 * Send an email
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.email.php 175 2008-10-25 00:24:24Z peace-maker $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 

global $theme, $userbank;
$theme->assign('email_addr', strip_tags($_GET['mail']));
$theme->assign('email_js', "CheckEmail('".$_GET['mail']."')");
?>

<div id="admin-page-content">
	<div id="1">
		<?php $theme->display('page_admin_bans_email.tpl'); ?>
	</div>
</div>
