<?php 
/**
 * =============================================================================
 * Server database display page
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.servers.db.php 190 2008-12-30 02:06:27Z peace-maker $
 * =============================================================================
 */

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

