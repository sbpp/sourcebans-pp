<?php
/**
 * Log search box
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
 
 global $theme;
 
 $admin_list = $GLOBALS['db']->GetAll("SELECT * FROM " . DB_PREFIX . "_admins ORDER BY user ASC");
 $theme->assign('admin_list', $admin_list);
 
 $theme->display('box_admin_log_search.tpl');
 
?>