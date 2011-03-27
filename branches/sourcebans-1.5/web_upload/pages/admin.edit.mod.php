<?php 
/**
 * Edit a mod
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
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
    				FROM " . DB_PREFIX . "_mods
    				WHERE mid = {$_GET['id']}");

$errorScript = "";

if(isset($_POST['name']))
{
	// Form validation
	$error = 0;
	
	if(empty($_POST['name']))
	{
		$error++;
		$errorScript .= "$('name.msg').innerHTML = 'You must type a name for the mod.';";
		$errorScript .= "$('name.msg').setStyle('display', 'block');";
	}
	if(empty($_POST['folder']))
	{
		$error++;
		$errorScript .= "$('name.msg').innerHTML = 'You must enter mod\'s folder name.';";
		$errorScript .= "$('name.msg').setStyle('display', 'block');";
	}
	
	$name = htmlspecialchars(strip_tags($_POST['name']));//don't want to addslashes because execute will automatically do it
	$icon = htmlspecialchars(strip_tags($_POST['icon_hid']));
	$folder = htmlspecialchars(strip_tags($_POST['folder']));
	$enabled = ($_POST['enabled'] == '1' ? 1 : 0);
	
	if($error == 0)
	{
		if($res['icon']!=$_POST['icon_hid'])
			@unlink(SB_ICONS."/".$res['icon']);
		
		$edit = $GLOBALS['db']->Execute("UPDATE " . DB_PREFIX . "_mods SET
										name = ?, modfolder = ?, icon = ?, enabled = ?
										WHERE mid = ?", array($name, $folder, $icon, $enabled, $_GET['id']));
		echo '<script>ShowBox("Mod updated", "The mod has been updated successfully", "green", "index.php?p=admin&c=mods");</script>';
	}
	
	// put into array to display new values after submit
	$res['name'] = $name;
	$res['modfolder'] = $folder;
	$res['icon'] = $icon;
	$res['enabled'] = $enabled;
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
$('enabled').checked = <?php echo (int)$res['enabled'] ?>;
</script>
</div>
</div>
<script type="text/javascript">window.addEvent('domready', function(){
<?php echo $errorScript; ?>
});
</script>