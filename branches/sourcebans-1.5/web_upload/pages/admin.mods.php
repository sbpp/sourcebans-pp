<?php  
/**
 * Mods page
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 
global $userbank,$theme;
?>

<div id="admin-page-content">
	<!-- List Mods -->
	<div id="0" style="display:none;">
		<?php 
		$theme->assign('mod_count', $mod_count);
		$theme->assign('permission_listmods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_MODS));
		$theme->assign('permission_editmods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_MODS));
		$theme->assign('permission_deletemods', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_MODS));
		$theme->assign('mod_list', $mod_list);
		
		$theme->display('page_admin_mods_list.tpl');
		?>
	</div>
	
	<!-- Add Mods -->
	<div id="1" style="display:none;">
		<?php
		$theme->assign('permission_add', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_MODS));
		
		$theme->display('page_admin_mods_add.tpl');
		?>
	</div>
</div>
