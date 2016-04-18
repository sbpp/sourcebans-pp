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
global $userbank, $theme;

if(!$userbank->HasAccess(ADMIN_OWNER))
{
	echo "Access Denied!";
}
else
{
	
$srv_cfg = '"Databases"
{
	"driver_default"		"mysql"
	
	"sourcebans"
	{
		"driver"			"mysql"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		//"timeout"			"0"
		"port"			"{port}"
	}
	
	"storage-local"
	{
		"driver"			"sqlite"
		"database"			"sourcemod-local"
	}
}
';
$srv_cfg = str_replace("{server}", DB_HOST, $srv_cfg);
$srv_cfg = str_replace("{user}", DB_USER, $srv_cfg);
$srv_cfg = str_replace("{pass}", DB_PASS, $srv_cfg);
$srv_cfg = str_replace("{db}", DB_NAME, $srv_cfg);
$srv_cfg = str_replace("{prefix}", DB_PREFIX, $srv_cfg);
$srv_cfg = str_replace("{port}", DB_PORT, $srv_cfg);	
	
if(strtolower(DB_HOST) == "localhost")
{
	ShowBox("Local server warning", "You have said your MySQL server is running on the same box as the webserver, this is fine, but you may need to alter the following config to set the remote domain/ip of your MySQL server. Unless your gameserver is on the same box as your webserver." , "blue", "", true);
}

$theme->assign('conf', $srv_cfg);
?>
<div id="admin-page-content">
	<div id="0">
	<?php $theme->display('page_admin_servers_db.tpl'); ?>
	</div>
</div>
<?php } ?>

