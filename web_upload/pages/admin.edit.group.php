<?php  
/**
 * =============================================================================
 * Edit a group
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.edit.group.php 195 2008-12-30 17:26:40Z peace-maker $
 * =============================================================================
 */

if(!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
} 


if(!isset($_GET['id']))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No server id specified. Please only follow links
</div>';
	die();
}

if(!isset($_GET['type']) || ($_GET['type'] != 'web' && $_GET['type'] != 'srv' && $_GET['type'] != 'server'))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No valid group type specified. Please only follow links
</div>';
	die();
}

$_GET['id'] = (int)$_GET['id'];

$web_group = $GLOBALS['db']->GetRow("SELECT flags, name FROM ".DB_PREFIX."_groups WHERE gid = {$_GET['id']}");
$srv_group = $GLOBALS['db']->GetRow("SELECT flags, name, immunity FROM ".DB_PREFIX."_srvgroups WHERE id = {$_GET['id']}");


$web_flags = intval($web_group[0]);
$srv_flags = isset($srv_group[0]) ? $srv_group[0] : '';

$name = $userbank->GetProperty("user", $_GET['id']);
?>
<div id="admin-page-content">
<div id="add-group">
<h3> Edit Group</h3><br />
<input type="hidden" id="group_id" value="<?= $_GET['id']?>">
<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?= HelpIcon("Group Name", "Type the name of the new group you want to create.");?>Group Name </div></td>
    <td><div align="left">
      <input type="text" TABINDEX=1 class="inputbox" id="groupname" name="groupname" />
    </div><div id="groupname.msg" style="color:#CC0000;"></div></td>
  </tr>
</table>
<?php if($_GET['type'] == "web")
{?>
<h3>Web Admin Permissions</h3>
<?= str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
<br /><?php }elseif($_GET['type'] == "srv"){?>
<h3>Server Admin Permissions</h3>
<?php  $permissions = str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;
echo $permissions;

// Group overrides
// ALERT >>> GROSS CODE MIX <<<
// I'm far to lazy to rewrite this to use smarty right now.
$overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = ?", array($_GET['id']));

?>
<br />
<form action="" method="post" name="group_overrides_form">
<div class="rowdesc">Group Overrides</div>
Group Overrides allow specific commands or groups of commands to be completely allowed or denied to members of the group.<br />
<i>Read about <b><a href="http://wiki.alliedmods.net/Adding_Groups_%28SourceMod%29" title="Adding Groups (SourceMod)" target="_blank">group overrides</a></b> in the AlliedModders Wiki!</i><br /><br />
Blanking out an overrides' name will delete it.<br /><br />
<table align="center" cellspacing="0" cellpadding="4" id="overrides" width="90%">
	<tr>
		<td class="tablerow4">Type</td>
		<td class="tablerow4">Name</td>
		<td class="tablerow4">Access</td>
	</tr>
<?php
	foreach($overrides_list as $override) {
?>
	<tr>
		<td class="tablerow1">
			<select name="override_type[]">
				<option value="command" <?= $override['type']=="command"?"selected=\"selected\"":""; ?>>Command</option>
				<option value="group"<?= $override['type']=="group"?"selected=\"selected\"":""; ?>>Group</option>
			</select>
			<input type="hidden" name="override_id[]" value="<?= $override['id']; ?>" />
		</td>
		<td class="tablerow1"><input name="override_name[]" value="<?= htmlspecialchars($override['name']); ?>" /></td>
		<td class="tablerow1">
			<select name="override_access[]">
				<option value="allow"<?= $override['access']=="allow"?"selected=\"selected\"":""; ?>>Allow</option>
				<option value="deny"<?= $override['access']=="deny"?"selected=\"selected\"":""; ?>>Deny</option>
			</select>
		</td>
	</tr>
<?php } ?>	
	<tr>
		<td class="tablerow1">
			<select id="new_override_type">
				<option value="command">Command</option>
				<option value="group">Group</option>
			</select>
		</td>
		<td class="tablerow1"><input id="new_override_name" /></td>
		<td class="tablerow1">
			<select id="new_override_access">
				<option value="allow">Allow</option>
				<option value="deny">Deny</option>
			</select>
		</td>
	</tr>
</table>
</form>
<?php } ?>
<table width="100%">
<tr><td>&nbsp;</td>
</tr>
<tr align="center">
    <td>&nbsp;</td>
    <td>
    <div align="center">
      <?= $ui->drawButton("Save Changes", "ProcessEditGroup('".$_GET['type']."', $('groupname').get('value'));", "ok", "editgroup", true);?>
      &nbsp;<?= $ui->drawButton("Back", "history.go(-1)", "cancel", "back");?>  
      </div>	</td>
  </tr>
</table>


<script>
<?php if($_GET['type'] == "web" || $_GET['type'] == "server"){?>
		$('groupname').set('value', '<?= $web_group['name']?>');
<?php }?>
<?php if(!$userbank->HasAccess(ADMIN_OWNER)) { ?>
	if($("wrootcheckbox")) { 
		$("wrootcheckbox").setStyle('display', 'none');
	}
	if($("srootcheckbox")) { 
		$("srootcheckbox").setStyle('display', 'none');
	}
<?php } ?>
<?php if($_GET['type'] == "web"){?>	
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

<?php }elseif($_GET['type'] == "srv"){?>
$('groupname').set('value', '<?= $srv_group['name']?>');
$('s14').set('checked', <?= mb_strstr($srv_flags, SM_ROOT) ? "true" : "false"?>);
$('s1').set('checked', <?= mb_strstr($srv_flags, SM_RESERVED_SLOT) ? "true" : "false"?>);
$('s23').set('checked', <?= mb_strstr($srv_flags, SM_GENERIC) ? "true" : "false"?>);
$('s2').set('checked', <?= mb_strstr($srv_flags, SM_KICK) ? "true" : "false"?>);
$('s3').set('checked', <?= mb_strstr($srv_flags, SM_BAN) ? "true" : "false"?>);
$('s4').set('checked', <?= mb_strstr($srv_flags, SM_UNBAN) ? "true" : "false"?>);
$('s5').set('checked', <?= mb_strstr($srv_flags, SM_SLAY) ? "true" : "false"?>);
$('s6').set('checked', <?= mb_strstr($srv_flags, SM_MAP) ? "true" : "false"?>);
$('s7').set('checked', <?= mb_strstr($srv_flags, SM_CVAR) ? "true" : "false"?>);
$('s8').set('checked', <?= mb_strstr($srv_flags, SM_CONFIG) ? "true" : "false"?>);
$('s9').set('checked', <?= mb_strstr($srv_flags, SM_CHAT) ? "true" : "false"?>);
$('s10').set('checked', <?= mb_strstr($srv_flags, SM_VOTE) ? "true" : "false"?>);
$('s11').set('checked', <?= mb_strstr($srv_flags, SM_PASSWORD) ? "true" : "false"?>);
$('s12').set('checked', <?= mb_strstr($srv_flags, SM_RCON) ? "true" : "false"?>);
$('s13').set('checked', <?= mb_strstr($srv_flags, SM_CHEATS) ? "true" : "false"?>);

$('s17').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM1) ? "true" : "false"?>);
$('s18').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM2) ? "true" : "false"?>);
$('s19').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM3) ? "true" : "false"?>);
$('s20').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM4) ? "true" : "false"?>);
$('s21').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM5) ? "true" : "false"?>);
$('s22').set('checked', <?= mb_strstr($srv_flags, SM_CUSTOM6) ? "true" : "false"?>);

$('immunity').set('value', '<?= $srv_group['immunity'] ? (int)$srv_group['immunity'] : "0"?>');
<?php }?>
</script>
</div></div>

