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
