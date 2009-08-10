<?php 
/**
 * =============================================================================
 * RCON window
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.rcon.php 165 2008-09-27 14:36:57Z peace-maker $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 

global $theme, $userbank;

$theme->assign('id', $_GET['id']);
$theme->assign('permission_rcon', $userbank->HasAccess(SM_RCON . SM_ROOT));
$theme->left_delimiter = '-{';
$theme->right_delimiter = '}-';

$theme->display('page_admin_servers_rcon.tpl');

$theme->left_delimiter = '{';
$theme->right_delimiter = '}';
?>

