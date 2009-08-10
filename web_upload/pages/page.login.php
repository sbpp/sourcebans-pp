<?php 
/**
 * =============================================================================
 * Login page
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: page.login.php 219 2009-02-24 21:09:11Z peace-maker $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
RewritePageTitle("Admin Login");

global $userbank, $theme;
$submenu = array( array( "title" => 'Lost Your Password?', "url" => 'index.php?p=lostpassword' ) );
SubMenu( $submenu );
if(isset($_GET['m']) && $_GET['m'] == "no_access")
	echo "<script>ShowBox('Error - No Access', 'You dont have permission to access this page.<br />Please login with an account that has access.', 'red', '', false);</script>";
	
	
$theme->assign('redir', "DoLogin('".(isset($_SESSION['q'])?$_SESSION['q']:'')."');");
$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_login.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
?>


