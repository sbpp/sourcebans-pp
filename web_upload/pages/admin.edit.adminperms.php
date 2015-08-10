<?php 
/**
 * =============================================================================
 * Edit the admins permissions
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.edit.adminperms.php 223 2009-03-06 13:28:13Z peace-maker $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 
global $userbank;

if(!isset($_GET['id']))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
	PageDie();
}
$admin = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_admins WHERE aid = \"". $_GET['id'] . "\"");


if(!$userbank->GetProperty("user", $_GET['id']))
{
	$log = new CSystemLog("e", "Getting admin data failed", "Can't find data for admin with id '".$_GET['id']."'");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Error getting current data.
</div>';
	PageDie();
}

$_GET['id'] = (int)$_GET['id'];
if(!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS))
{
	$log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s permissions, but doesn't have access.");
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	You are not allowed to edit other permissions.
</div>';
	PageDie();
}

$web_root = $userbank->HasAccess(ADMIN_OWNER, $_GET['id']);
$steam = trim($userbank->GetProperty("authid", $_GET['id']));
$web_flags = intval($userbank->GetProperty("extraflags", $_GET['id']));
$name = $userbank->GetProperty("user", $_GET['id']);
?>
<div id="admin-page-content">
<div id="add-group">
<h3>Web Admin Permissions</h3>
<input type="hidden" id="admin_id" value=<?= $_GET['id']?>>
<?= str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
<br />
<h3>Server Admin Permissions</h3>

<?= str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;?>

<table width="100%">
<tr><td>&nbsp;</td>
</tr>
<tr align="center">
    <td>&nbsp;</td>
    <td>
    <div align="center">
       <?= $ui->drawButton("Save Changes", "ProcessEditAdminPermissions();", "ok", "editadmingroup");?>
      &nbsp;<?= $ui->drawButton("Back", "history.go(-1)", "cancel", "back");?>
      
      </div>	</td>
  </tr>
</table>




<script>
<?php if(!$userbank->HasAccess(ADMIN_OWNER)) { ?>
	if($("wrootcheckbox")) { 
		$("wrootcheckbox").setStyle('display', 'none');
	}
	if($("srootcheckbox")) { 
		$("srootcheckbox").setStyle('display', 'none');
	}
<?php } ?>
$('p2').set('checked', <?= check_flag($web_flags, ADMIN_OWNER) ? "true" : "false"?>);

$('p4').set('checked', <?= check_flag($web_flags, ADMIN_LIST_ADMINS) ? "true" : "false"?>);
$('p5').set('checked', <?= check_flag($web_flags, ADMIN_ADD_ADMINS) ? "true" : "false"?>);
$('p6').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_ADMINS) ? "true" : "false"?>);
$('p7').set('checked', <?= check_flag($web_flags, ADMIN_DELETE_ADMINS) ? "true" : "false"?>);

$('p9').set('checked', <?= check_flag($web_flags, ADMIN_LIST_SERVERS) ? "true" : "false"?>);
$('p10').set('checked', <?= check_flag($web_flags, ADMIN_ADD_SERVER) ? "true" : "false"?>);
$('p11').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_SERVERS) ? "true" : "false"?>);
$('p12').set('checked', <?= check_flag($web_flags, ADMIN_DELETE_SERVERS) ? "true" : "false"?>);

$('p14').set('checked', <?= check_flag($web_flags, ADMIN_ADD_BAN) ? "true" : "false"?>);
$('p16').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_OWN_BANS) ? "true" : "false"?>);
$('p17').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_GROUP_BANS) ? "true" : "false"?>);
$('p18').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_ALL_BANS) ? "true" : "false"?>);
$('p19').set('checked', <?= check_flag($web_flags, ADMIN_BAN_PROTESTS) ? "true" : "false"?>);
$('p20').set('checked', <?= check_flag($web_flags, ADMIN_BAN_SUBMISSIONS) ? "true" : "false"?>);
$('p33').set('checked', <?= check_flag($web_flags, ADMIN_DELETE_BAN) ? "true" : "false"?>);
$('p32').set('checked', <?= check_flag($web_flags, ADMIN_UNBAN) ? "true" : "false"?>);
$('p34').set('checked', <?= check_flag($web_flags, ADMIN_BAN_IMPORT) ? "true" : "false"?>);
$('p38').set('checked', <?= check_flag($web_flags, ADMIN_UNBAN_OWN_BANS) ? "true" : "false"?>);
$('p39').set('checked', <?= check_flag($web_flags, ADMIN_UNBAN_GROUP_BANS) ? "true" : "false"?>);

$('p36').set('checked', <?= check_flag($web_flags, ADMIN_NOTIFY_SUB) ? "true" : "false"?>);
$('p37').set('checked', <?= check_flag($web_flags, ADMIN_NOTIFY_PROTEST) ? "true" : "false"?>);

$('p22').set('checked', <?= check_flag($web_flags, ADMIN_LIST_GROUPS) ? "true" : "false"?>);
$('p23').set('checked', <?= check_flag($web_flags, ADMIN_ADD_GROUP) ? "true" : "false"?>);
$('p24').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_GROUPS) ? "true" : "false"?>);
$('p25').set('checked', <?= check_flag($web_flags, ADMIN_DELETE_GROUPS) ? "true" : "false"?>);

$('p26').set('checked', <?= check_flag($web_flags, ADMIN_WEB_SETTINGS) ? "true" : "false"?>);

$('p28').set('checked', <?= check_flag($web_flags, ADMIN_LIST_MODS) ? "true" : "false"?>);
$('p29').set('checked', <?= check_flag($web_flags, ADMIN_ADD_MODS) ? "true" : "false"?>);
$('p30').set('checked', <?= check_flag($web_flags, ADMIN_EDIT_MODS) ? "true" : "false"?>);
$('p31').set('checked', <?= check_flag($web_flags, ADMIN_DELETE_MODS) ? "true" : "false"?>);


$('s14').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_ROOT) ? "true" : "false"?>);
$('s1').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_RESERVED_SLOT) ? "true" : "false"?>);
$('s23').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_GENERIC) ? "true" : "false"?>);
$('s2').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_KICK) ? "true" : "false"?>);
$('s3').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_BAN) ? "true" : "false"?>);
$('s4').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_UNBAN) ? "true" : "false"?>);
$('s5').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_SLAY) ? "true" : "false"?>);
$('s6').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_MAP) ? "true" : "false"?>);
$('s7').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CVAR) ? "true" : "false"?>);
$('s8').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CONFIG) ? "true" : "false"?>);
$('s9').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CHAT) ? "true" : "false"?>);
$('s10').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_VOTE) ? "true" : "false"?>);
$('s11').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_PASSWORD) ? "true" : "false"?>);
$('s12').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_RCON) ? "true" : "false"?>);
$('s13').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CHEATS) ? "true" : "false"?>);

$('s17').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM1) ? "true" : "false"?>);
$('s18').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM2) ? "true" : "false"?>);
$('s19').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM3) ? "true" : "false"?>);
$('s20').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM4) ? "true" : "false"?>);
$('s21').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM5) ? "true" : "false"?>);
$('s22').set('checked', <?= mb_strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM6) ? "true" : "false"?>);

$('immunity').set('value', '<?= $admin['immunity'] ? $admin['immunity'] : "0"?>');
</script>
</div></div>

