<?php 
/**
 * Draw a tab
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
global $theme;
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$theme->assign('active', (bool)$tabs['active']);
$theme->assign('tab_link', CreateLinkR($tabs['title'], $tabs['url'], $tabs['desc']));
$theme->display('tab.tpl');
?>
