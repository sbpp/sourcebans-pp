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

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 


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
<input type="hidden" id="group_id" value=<?php echo $_GET['id']?>>
<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Group Name", "Type the name of the new group you want to create.");?>Group Name </div></td>
    <td><div align="left">
      <input type="text" TABINDEX=1 class="inputbox" id="groupname" name="groupname" />
    </div><div id="name.msg" style="color:#CC0000;"></div></td>
  </tr>
  </table>
<?php if($_GET['type'] == "web")
{?>
<h3>Web Admin Permissions</h3>
<?php echo str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php")) ;?>
<br /><?php }elseif($_GET['type'] == "srv"){?>
<h3>Server Admin Permissions</h3>
<?php  $permissions = str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php")) ;
$ig = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
		$html = "";
		foreach($ig AS $g)
			$html .= '<option value="' . $g['id'] . '">' . $g['name'] . '</option>';
			
		$permissions = str_replace("{GROUPS}", $html, $permissions);
echo $permissions;
?>
<?php }?>
<table width="100%">
<tr><td>&nbsp;</td>
</tr>
<tr align="center">
    <td>&nbsp;</td>
    <td>
    <div align="center">
      <?php echo $ui->drawButton("Save Changes", "ProcessEditGroup('".$_GET['type']."', $('groupname').value);", "ok", "editgroup", true);?>
      &nbsp;<?php echo $ui->drawButton("Back", "history.go(-1)", "cancel", "back");?>  
      </div>	</td>
  </tr>
</table>


<script>
<?php if($_GET['type'] == "web" || $_GET['type'] == "server"){?>
		$('groupname').value = "<?php echo $web_group['name']?>";
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

<?php }elseif($_GET['type'] == "srv"){?>
$('groupname').value = "<?php echo $srv_group['name']?>";
$('s14').checked = <?php echo strstr($srv_flags, SM_ROOT) ? "true" : "false"?>;
$('s1').checked = <?php echo strstr($srv_flags, SM_RESERVED_SLOT) ? "true" : "false"?>;
$('s23').checked = <?php echo strstr($srv_flags, SM_GENERIC) ? "true" : "false"?>;
$('s2').checked = <?php echo strstr($srv_flags, SM_KICK) ? "true" : "false"?>;
$('s3').checked = <?php echo strstr($srv_flags, SM_BAN) ? "true" : "false"?>;
$('s4').checked = <?php echo strstr($srv_flags, SM_UNBAN) ? "true" : "false"?>;
$('s5').checked = <?php echo strstr($srv_flags, SM_SLAY) ? "true" : "false"?>;
$('s6').checked = <?php echo strstr($srv_flags, SM_MAP) ? "true" : "false"?>;
$('s7').checked = <?php echo strstr($srv_flags, SM_CVAR) ? "true" : "false"?>;
$('s8').checked = <?php echo strstr($srv_flags, SM_CONFIG) ? "true" : "false"?>;
$('s9').checked = <?php echo strstr($srv_flags, SM_CHAT) ? "true" : "false"?>;
$('s10').checked = <?php echo strstr($srv_flags, SM_VOTE) ? "true" : "false"?>;
$('s11').checked = <?php echo strstr($srv_flags, SM_PASSWORD) ? "true" : "false"?>;
$('s12').checked = <?php echo strstr($srv_flags, SM_RCON) ? "true" : "false"?>;
$('s13').checked = <?php echo strstr($srv_flags, SM_CHEATS) ? "true" : "false"?>;

$('s17').checked = <?php echo strstr($srv_flags, SM_CUSTOM1) ? "true" : "false"?>;
$('s18').checked = <?php echo strstr($srv_flags, SM_CUSTOM2) ? "true" : "false"?>;
$('s19').checked = <?php echo strstr($srv_flags, SM_CUSTOM3) ? "true" : "false"?>;
$('s20').checked = <?php echo strstr($srv_flags, SM_CUSTOM4) ? "true" : "false"?>;
$('s21').checked = <?php echo strstr($srv_flags, SM_CUSTOM5) ? "true" : "false"?>;
$('s22').checked = <?php echo strstr($srv_flags, SM_CUSTOM6) ? "true" : "false"?>;

$('immunity').value = <?php echo $srv_group['immunity'] ? $srv_group['immunity'] : "0"?>;
<?php }?>
</script>
</div></div>

