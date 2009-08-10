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
<input type="hidden" id="admin_id" value=<?php echo $_GET['id']?>>
<?php echo str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
<br />
<h3>Server Admin Permissions</h3>

<?php echo str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;?>

<table width="100%">
<tr><td>&nbsp;</td>
</tr>
<tr align="center">
    <td>&nbsp;</td>
    <td>
    <div align="center">
       <?php echo $ui->drawButton("Save Changes", "ProcessEditAdminPermissions();", "ok", "editadmingroup");?>
      &nbsp;<?php echo $ui->drawButton("Back", "history.go(-1)", "cancel", "back");?>
      
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
$('p2').checked = <?php echo check_flag($web_flags, ADMIN_OWNER) ? "true" : "false"?>;

$('p4').checked = <?php echo check_flag($web_flags, ADMIN_LIST_ADMINS) ? "true" : "false"?>;
$('p5').checked = <?php echo check_flag($web_flags, ADMIN_ADD_ADMINS) ? "true" : "false"?>;
$('p6').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_ADMINS) ? "true" : "false"?>;
$('p7').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_ADMINS) ? "true" : "false"?>;

$('p9').checked = <?php echo check_flag($web_flags, ADMIN_LIST_SERVERS) ? "true" : "false"?>;
$('p10').checked = <?php echo check_flag($web_flags, ADMIN_ADD_SERVER) ? "true" : "false"?>;
$('p11').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_SERVERS) ? "true" : "false"?>;
$('p12').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_SERVERS) ? "true" : "false"?>;

$('p14').checked = <?php echo check_flag($web_flags, ADMIN_ADD_BAN) ? "true" : "false"?>;
$('p16').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_OWN_BANS) ? "true" : "false"?>;
$('p17').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_GROUP_BANS) ? "true" : "false"?>;
$('p18').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_ALL_BANS) ? "true" : "false"?>;
$('p19').checked = <?php echo check_flag($web_flags, ADMIN_BAN_PROTESTS) ? "true" : "false"?>;
$('p20').checked = <?php echo check_flag($web_flags, ADMIN_BAN_SUBMISSIONS) ? "true" : "false"?>;
$('p33').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_BAN) ? "true" : "false"?>;
$('p32').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN) ? "true" : "false"?>;
$('p34').checked = <?php echo check_flag($web_flags, ADMIN_BAN_IMPORT) ? "true" : "false"?>;
$('p38').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN_OWN_BANS) ? "true" : "false"?>;
$('p39').checked = <?php echo check_flag($web_flags, ADMIN_UNBAN_GROUP_BANS) ? "true" : "false"?>;

$('p36').checked = <?php echo check_flag($web_flags, ADMIN_NOTIFY_SUB) ? "true" : "false"?>;
$('p37').checked = <?php echo check_flag($web_flags, ADMIN_NOTIFY_PROTEST) ? "true" : "false"?>;

$('p22').checked = <?php echo check_flag($web_flags, ADMIN_LIST_GROUPS) ? "true" : "false"?>;
$('p23').checked = <?php echo check_flag($web_flags, ADMIN_ADD_GROUP) ? "true" : "false"?>;
$('p24').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_GROUPS) ? "true" : "false"?>;
$('p25').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_GROUPS) ? "true" : "false"?>;

$('p26').checked = <?php echo check_flag($web_flags, ADMIN_WEB_SETTINGS) ? "true" : "false"?>;

$('p28').checked = <?php echo check_flag($web_flags, ADMIN_LIST_MODS) ? "true" : "false"?>;
$('p29').checked = <?php echo check_flag($web_flags, ADMIN_ADD_MODS) ? "true" : "false"?>;
$('p30').checked = <?php echo check_flag($web_flags, ADMIN_EDIT_MODS) ? "true" : "false"?>;
$('p31').checked = <?php echo check_flag($web_flags, ADMIN_DELETE_MODS) ? "true" : "false"?>;


$('s14').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_ROOT) ? "true" : "false"?>;
$('s1').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_RESERVED_SLOT) ? "true" : "false"?>;
$('s23').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_GENERIC) ? "true" : "false"?>;
$('s2').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_KICK) ? "true" : "false"?>;
$('s3').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_BAN) ? "true" : "false"?>;
$('s4').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_UNBAN) ? "true" : "false"?>;
$('s5').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_SLAY) ? "true" : "false"?>;
$('s6').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_MAP) ? "true" : "false"?>;
$('s7').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CVAR) ? "true" : "false"?>;
$('s8').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CONFIG) ? "true" : "false"?>;
$('s9').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CHAT) ? "true" : "false"?>;
$('s10').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_VOTE) ? "true" : "false"?>;
$('s11').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_PASSWORD) ? "true" : "false"?>;
$('s12').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_RCON) ? "true" : "false"?>;
$('s13').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CHEATS) ? "true" : "false"?>;

$('s17').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM1) ? "true" : "false"?>;
$('s18').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM2) ? "true" : "false"?>;
$('s19').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM3) ? "true" : "false"?>;
$('s20').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM4) ? "true" : "false"?>;
$('s21').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM5) ? "true" : "false"?>;
$('s22').checked = <?php echo strstr(get_non_inherited_admin($admin['authid']), SM_CUSTOM6) ? "true" : "false"?>;

$('immunity').value = <?php echo $admin['immunity'] ? $admin['immunity'] : "0"?>;
</script>
</div></div>

