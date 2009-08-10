<?php 
/**
 * =============================================================================
 * Edit a mod
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.edit.mod.php 182 2008-12-18 19:12:19Z smithxxl $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 
global $theme, $userbank;
if(!isset($_GET['id']))
{
	echo '<script>ShowBox("Error", "No mod ID set. Only follow links", "red", "", true);</script>';	
	PageDie();
}
if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_MODS))
{
	$log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit a mod, but doesnt have access.");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	You are not allowed to edit mods.
</div>';
	PageDie();
}

$_GET['id'] = (int)$_GET['id'];
$res = $GLOBALS['db']->GetRow("
    				SELECT name, modfolder, icon, enabled
    				FROM ".DB_PREFIX."_mods
    				WHERE mid = {$_GET['id']}");
if(isset($_POST['name']))
{
	$enabled = ($_POST['enabled'] == '1' ? 1 : 0);
	
	if($res['icon']!=$_POST['icon_hid'])
		@unlink(SB_ICONS."/".$res['icon']);
		
	$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_mods SET
									`name` = ?, `modfolder` = ?, `icon` = ?, `enabled` = ?
									WHERE `mid` = ?", array($_POST['name'], $_POST['folder'], $_POST['icon_hid'], $enabled, (int)$_GET['id']));
	echo '<script>ShowBox("Mod updated", "The mod has been updated successfully", "green", "index.php?p=admin&c=mods");</script>';
}
if(!$res)
	echo '<script>ShowBox("Error", "There was an error getting details. Maybe the mod has been deleted?", "red", "index.php?p=admin&c=mod");</script>';

$theme->assign('mod_icon', $res['icon']);
$theme->assign('folder', $res['modfolder']);
$theme->assign('name', $res['name']);
?>


<div id="admin-page-content">
<div id="1">
<?php $theme->display('page_admin_edit_mod.tpl'); ?>
<script>
$('enabled').checked = <?php echo $res['enabled'] ?>;
</script>
</div>
</div>

