<?php
/**
 * =============================================================================
 * Log search box
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.log.search.php 94 2008-07-19 18:55:09Z olly $
 * =============================================================================
 */
 
 global $theme;
 
 $admin_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_admins` ORDER BY user ASC");
 $theme->assign('admin_list', $admin_list);
 
 $theme->display('box_admin_log_search.tpl');
 
?>