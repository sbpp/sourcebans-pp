<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank;

new AdminTabs([], $userbank, $theme);

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
    PageDie();
}
$admin = $GLOBALS['db']->GetRow("SELECT * FROM " . DB_PREFIX . "_admins WHERE aid = \"" . $_GET['id'] . "\"");


if (!$userbank->GetProperty("user", $_GET['id'])) {
    Log::add("e", "Getting admin data failed", "Can't find data for admin with id $_GET[id].");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	Error getting current data.
</div>';
    PageDie();
}

$_GET['id'] = (int) $_GET['id'];
if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
    Log::add("w", "Hacking Attempt", $userbank->GetProperty("user")." tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s permissions, but doesn't have access.");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	You are not allowed to edit other permissions.
</div>';
    PageDie();
}

$web_root  = $userbank->HasAccess(ADMIN_OWNER, $_GET['id']);
$steam     = trim($userbank->GetProperty("authid", $_GET['id']));
$web_flags = intval($userbank->GetProperty("extraflags", $_GET['id']));
$name      = $userbank->GetProperty("user", $_GET['id'])?>
<div id="admin-page-content">
<div id="add-group">
<h3>Web Admin Permissions</h3>
<input type="hidden" id="admin_id" value=<?=$_GET['id']?>>
<?=str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php"))?>
<br />
<h3>Server Admin Permissions</h3>

<?=str_replace("{title}", $name, file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php"))?>

<table width="100%">
<tr><td>&nbsp;</td>
</tr>
<tr align="center">
    <td>&nbsp;</td>
    <td>
    <div align="center">
        <input type='button' onclick="ProcessEditAdminPermissions();" name='editadmingroup' class='btn ok' onmouseover='ButtonOver("editadmingroup")' onmouseout='ButtonOver("editadmingroup")' id='editadmingroup' value='Save Changes' />
        <input type='button' onclick="history.go(-1);" name='back' class='btn cancel' onmouseover='ButtonOver("back")' onmouseout='ButtonOver("back")' id='back' value='Back' />
    </div>
    </td>
  </tr>
</table>




<script>
<?php
if (!$userbank->HasAccess(ADMIN_OWNER)) {
?>
    if($("wrootcheckbox")) {
        $("wrootcheckbox").setStyle('display', 'none');
    }
    if($("srootcheckbox")) {
        $("srootcheckbox").setStyle('display', 'none');
    }
<?php
}
?>
$('p2').checked = <?=$userbank->HasAccess(ADMIN_OWNER, $_GET['id']) ? "true" : "false"?>;

$('p4').checked = <?=$userbank->HasAccess(ADMIN_LIST_ADMINS, $_GET['id']) ? "true" : "false"?>;
$('p5').checked = <?=$userbank->HasAccess(ADMIN_ADD_ADMINS, $_GET['id']) ? "true" : "false"?>;
$('p6').checked = <?=$userbank->HasAccess(ADMIN_EDIT_ADMINS, $_GET['id']) ? "true" : "false"?>;
$('p7').checked = <?=$userbank->HasAccess(ADMIN_DELETE_ADMINS, $_GET['id']) ? "true" : "false"?>;

$('p9').checked = <?=$userbank->HasAccess(ADMIN_LIST_SERVERS, $_GET['id']) ? "true" : "false"?>;
$('p10').checked = <?=$userbank->HasAccess(ADMIN_ADD_SERVER, $_GET['id']) ? "true" : "false"?>;
$('p11').checked = <?=$userbank->HasAccess(ADMIN_EDIT_SERVERS, $_GET['id']) ? "true" : "false"?>;
$('p12').checked = <?=$userbank->HasAccess(ADMIN_DELETE_SERVERS, $_GET['id']) ? "true" : "false"?>;

$('p14').checked = <?=$userbank->HasAccess(ADMIN_ADD_BAN, $_GET['id']) ? "true" : "false"?>;
$('p16').checked = <?=$userbank->HasAccess(ADMIN_EDIT_OWN_BANS, $_GET['id']) ? "true" : "false"?>;
$('p17').checked = <?=$userbank->HasAccess(ADMIN_EDIT_GROUP_BANS, $_GET['id']) ? "true" : "false"?>;
$('p18').checked = <?=$userbank->HasAccess(ADMIN_EDIT_ALL_BANS, $_GET['id']) ? "true" : "false"?>;
$('p19').checked = <?=$userbank->HasAccess(ADMIN_BAN_PROTESTS, $_GET['id']) ? "true" : "false"?>;
$('p20').checked = <?=$userbank->HasAccess(ADMIN_BAN_SUBMISSIONS, $_GET['id']) ? "true" : "false"?>;
$('p33').checked = <?=$userbank->HasAccess(ADMIN_DELETE_BAN, $_GET['id']) ? "true" : "false"?>;
$('p32').checked = <?=$userbank->HasAccess(ADMIN_UNBAN, $_GET['id']) ? "true" : "false"?>;
$('p34').checked = <?=$userbank->HasAccess(ADMIN_BAN_IMPORT, $_GET['id']) ? "true" : "false"?>;
$('p38').checked = <?=$userbank->HasAccess(ADMIN_UNBAN_OWN_BANS, $_GET['id']) ? "true" : "false"?>;
$('p39').checked = <?=$userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS, $_GET['id']) ? "true" : "false"?>;

$('p36').checked = <?=$userbank->HasAccess(ADMIN_NOTIFY_SUB, $_GET['id']) ? "true" : "false"?>;
$('p37').checked = <?=$userbank->HasAccess(ADMIN_NOTIFY_PROTEST, $_GET['id']) ? "true" : "false"?>;

$('p22').checked = <?=$userbank->HasAccess(ADMIN_LIST_GROUPS, $_GET['id']) ? "true" : "false"?>;
$('p23').checked = <?=$userbank->HasAccess(ADMIN_ADD_GROUP, $_GET['id']) ? "true" : "false"?>;
$('p24').checked = <?=$userbank->HasAccess(ADMIN_EDIT_GROUPS, $_GET['id']) ? "true" : "false"?>;
$('p25').checked = <?=$userbank->HasAccess(ADMIN_DELETE_GROUPS, $_GET['id']) ? "true" : "false"?>;

$('p26').checked = <?=$userbank->HasAccess(ADMIN_WEB_SETTINGS, $_GET['id']) ? "true" : "false"?>;

$('p28').checked = <?=$userbank->HasAccess(ADMIN_LIST_MODS, $_GET['id']) ? "true" : "false"?>;
$('p29').checked = <?=$userbank->HasAccess(ADMIN_ADD_MODS, $_GET['id']) ? "true" : "false"?>;
$('p30').checked = <?=$userbank->HasAccess(ADMIN_EDIT_MODS, $_GET['id']) ? "true" : "false"?>;
$('p31').checked = <?=$userbank->HasAccess(ADMIN_DELETE_MODS, $_GET['id']) ? "true" : "false"?>;


$('s14').checked = <?=$userbank->HasAccess(SM_ROOT, $_GET['id']) ? "true" : "false"?>;
$('s1').checked = <?=$userbank->HasAccess(SM_RESERVED_SLOT, $_GET['id']) ? "true" : "false"?>;
$('s23').checked = <?=$userbank->HasAccess(SM_GENERIC, $_GET['id']) ? "true" : "false"?>;
$('s2').checked = <?=$userbank->HasAccess(SM_KICK, $_GET['id']) ? "true" : "false"?>;
$('s3').checked = <?=$userbank->HasAccess(SM_BAN, $_GET['id']) ? "true" : "false"?>;
$('s4').checked = <?=$userbank->HasAccess(SM_UNBAN, $_GET['id']) ? "true" : "false"?>;
$('s5').checked = <?=$userbank->HasAccess(SM_SLAY, $_GET['id']) ? "true" : "false"?>;
$('s6').checked = <?=$userbank->HasAccess(SM_MAP, $_GET['id']) ? "true" : "false"?>;
$('s7').checked = <?=$userbank->HasAccess(SM_CVAR, $_GET['id']) ? "true" : "false"?>;
$('s8').checked = <?=$userbank->HasAccess(SM_CONFIG, $_GET['id']) ? "true" : "false"?>;
$('s9').checked = <?=$userbank->HasAccess(SM_CHAT, $_GET['id']) ? "true" : "false"?>;
$('s10').checked = <?=$userbank->HasAccess(SM_VOTE, $_GET['id']) ? "true" : "false"?>;
$('s11').checked = <?=$userbank->HasAccess(SM_PASSWORD, $_GET['id']) ? "true" : "false"?>;
$('s12').checked = <?=$userbank->HasAccess(SM_RCON, $_GET['id']) ? "true" : "false"?>;
$('s13').checked = <?=$userbank->HasAccess(SM_CHEATS, $_GET['id']) ? "true" : "false"?>;

$('s17').checked = <?=$userbank->HasAccess(SM_CUSTOM1, $_GET['id']) ? "true" : "false"?>;
$('s18').checked = <?=$userbank->HasAccess(SM_CUSTOM2, $_GET['id']) ? "true" : "false"?>;
$('s19').checked = <?=$userbank->HasAccess(SM_CUSTOM3, $_GET['id']) ? "true" : "false"?>;
$('s20').checked = <?=$userbank->HasAccess(SM_CUSTOM4, $_GET['id']) ? "true" : "false"?>;
$('s21').checked = <?=$userbank->HasAccess(SM_CUSTOM5, $_GET['id']) ? "true" : "false"?>;
$('s22').checked = <?=$userbank->HasAccess(SM_CUSTOM6, $_GET['id']) ? "true" : "false"?>;

$('immunity').value = <?=$admin['immunity'] ? $admin['immunity'] : "0"?>;
</script>
</div>
</div>
