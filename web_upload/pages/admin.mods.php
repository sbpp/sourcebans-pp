<?php  
/**
 * =============================================================================
 * Mods page
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.mods.php 165 2008-09-27 14:36:57Z peace-maker $
 * =============================================================================
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
