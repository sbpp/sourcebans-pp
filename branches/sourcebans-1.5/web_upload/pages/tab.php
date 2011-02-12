<?php 
/**
 * =============================================================================
 * Draw a tab
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */
global $theme;
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$theme->assign('active', (bool)$tabs['active']);
$theme->assign('tab_link', CreateLinkR($tabs['title'], $tabs['url'], $tabs['desc']));
$theme->display('tab.tpl');
?>
