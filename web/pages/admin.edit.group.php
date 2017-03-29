<?php
/*************************************************************************
This file is part of SourceBans++

Copyright � 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

SourceBans++ is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright � 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No server id specified. Please only follow links
</div>';
    die();
}

if (!isset($_GET['type']) || ($_GET['type'] != 'web' && $_GET['type'] != 'srv' && $_GET['type'] != 'server')) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No valid group type specified. Please only follow links
</div>';
    die();
}

$_GET['id'] = (int) $_GET['id'];

$web_group = $GLOBALS['db']->GetRow("SELECT flags, name FROM " . DB_PREFIX . "_groups WHERE gid = {$_GET['id']}");
$srv_group = $GLOBALS['db']->GetRow("SELECT flags, name, immunity FROM " . DB_PREFIX . "_srvgroups WHERE id = {$_GET['id']}");

$web_flags = intval($web_group[0]);
$srv_flags = isset($srv_group[0]) ? $srv_group[0] : '';

$name = $userbank->GetProperty("user", $_GET['id'])?>
<div id="admin-page-content">
<div id="add-group">
<h3> Edit Group</h3><br />
<input type="hidden" id="group_id" value=<?=$_GET['id']?>>
<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?=HelpIcon("Group Name", "Type the name of the new group you want to create.")?>Group Name </div></td>
    <td><div align="left">
      <input type="text" TABINDEX=1 class="inputbox" id="groupname" name="groupname" />
    </div><div id="groupname.msg" style="color:#CC0000;"></div></td>
  </tr>
</table>
<?php
if ($_GET['type'] == "web") {
?>
<h3>Web Admin Permissions</h3>
<?=str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.web.perm.php"))?>
<br /><?php
} elseif ($_GET['type'] == "srv") {
?>
<h3>Server Admin Permissions</h3>
<?php
    $permissions = str_replace("{title}", $name, @file_get_contents(TEMPLATES_PATH . "/groups.server.perm.php"));
    echo $permissions;
    // Group overrides
    // ALERT >>> GROSS CODE MIX <<<
    // I'm far to lazy to rewrite this to use smarty right now.
    $overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = ?", array(
        $_GET['id']
    ));
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
foreach ($overrides_list as $override) {
?>
    <tr>
        <td class="tablerow1">
            <select name="override_type[]">
                <option value="command" <?=$override['type'] == "command" ? "selected=\"selected\"" : ""?>>Command</option>
                <option value="group"<?=$override['type'] == "group" ? "selected=\"selected\"" : ""?>>Group</option>
            </select>
                    <input type="hidden" name="override_id[]" value="<?=$override['id']?>" />
            </td>
            <td class="tablerow1"><input name="override_name[]" value="<?=htmlspecialchars($override['name'])?>" /></td>
            <td class="tablerow1">
                <select name="override_access[]">
                    <option value="allow"<?=$override['access'] == "allow" ? "selected=\"selected\"" : ""?>>Allow</option>
                    <option value="deny"<?=$override['access'] == "deny" ? "selected=\"selected\"" : ""?>>Deny</option>
                </select>
            </td>
    </tr>
<?php
}
?>
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
<?php
}
?>
<table width="100%">
    <tr><td>&nbsp;</td>
    </tr>
    <tr align="center">
        <td>&nbsp;</td>
        <td>
            <div align="center">
        <?=$ui->drawButton("Save Changes", "ProcessEditGroup('" . $_GET['type'] . "', $('groupname').value);", "ok", "editgroup", true)?>
        &nbsp;<?=$ui->drawButton("Back", "history.go(-1)", "cancel", "back")?>
            </div>
        </td>
    </tr>
</table>
<script>
<?php
if ($_GET['type'] == "web" || $_GET['type'] == "server") {
?>
    $('groupname').value = "<?=$web_group['name']?>";
<?php
}
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
if ($_GET['type'] == "web") {
?>
$('p2').checked = <?=check_flag($web_flags, ADMIN_OWNER) ? "true" : "false"?>;

$('p4').checked = <?=check_flag($web_flags, ADMIN_LIST_ADMINS) ? "true" : "false"?>;
$('p5').checked = <?=check_flag($web_flags, ADMIN_ADD_ADMINS) ? "true" : "false"?>;
$('p6').checked = <?=check_flag($web_flags, ADMIN_EDIT_ADMINS) ? "true" : "false"?>;
$('p7').checked = <?=check_flag($web_flags, ADMIN_DELETE_ADMINS) ? "true" : "false"?>;

$('p9').checked = <?=check_flag($web_flags, ADMIN_LIST_SERVERS) ? "true" : "false"?>;
$('p10').checked = <?=check_flag($web_flags, ADMIN_ADD_SERVER) ? "true" : "false"?>;
$('p11').checked = <?=check_flag($web_flags, ADMIN_EDIT_SERVERS) ? "true" : "false"?>;
$('p12').checked = <?=check_flag($web_flags, ADMIN_DELETE_SERVERS) ? "true" : "false"?>;

$('p14').checked = <?=check_flag($web_flags, ADMIN_ADD_BAN) ? "true" : "false"?>;
$('p16').checked = <?=check_flag($web_flags, ADMIN_EDIT_OWN_BANS) ? "true" : "false"?>;
$('p17').checked = <?=check_flag($web_flags, ADMIN_EDIT_GROUP_BANS) ? "true" : "false"?>;
$('p18').checked = <?=check_flag($web_flags, ADMIN_EDIT_ALL_BANS) ? "true" : "false"?>;
$('p19').checked = <?=check_flag($web_flags, ADMIN_BAN_PROTESTS) ? "true" : "false"?>;
$('p20').checked = <?=check_flag($web_flags, ADMIN_BAN_SUBMISSIONS) ? "true" : "false"?>;
$('p33').checked = <?=check_flag($web_flags, ADMIN_DELETE_BAN) ? "true" : "false"?>;
$('p32').checked = <?=check_flag($web_flags, ADMIN_UNBAN) ? "true" : "false"?>;
$('p34').checked = <?=check_flag($web_flags, ADMIN_BAN_IMPORT) ? "true" : "false"?>;
$('p38').checked = <?=check_flag($web_flags, ADMIN_UNBAN_OWN_BANS) ? "true" : "false"?>;
$('p39').checked = <?=check_flag($web_flags, ADMIN_UNBAN_GROUP_BANS) ? "true" : "false"?>;

$('p36').checked = <?=check_flag($web_flags, ADMIN_NOTIFY_SUB) ? "true" : "false"?>;
$('p37').checked = <?=check_flag($web_flags, ADMIN_NOTIFY_PROTEST) ? "true" : "false"?>;

$('p22').checked = <?=check_flag($web_flags, ADMIN_LIST_GROUPS) ? "true" : "false"?>;
$('p23').checked = <?=check_flag($web_flags, ADMIN_ADD_GROUP) ? "true" : "false"?>;
$('p24').checked = <?=check_flag($web_flags, ADMIN_EDIT_GROUPS) ? "true" : "false"?>;
$('p25').checked = <?=check_flag($web_flags, ADMIN_DELETE_GROUPS) ? "true" : "false"?>;

$('p26').checked = <?=check_flag($web_flags, ADMIN_WEB_SETTINGS) ? "true" : "false"?>;

$('p28').checked = <?=check_flag($web_flags, ADMIN_LIST_MODS) ? "true" : "false"?>;
$('p29').checked = <?=check_flag($web_flags, ADMIN_ADD_MODS) ? "true" : "false"?>;
$('p30').checked = <?=check_flag($web_flags, ADMIN_EDIT_MODS) ? "true" : "false"?>;
$('p31').checked = <?=check_flag($web_flags, ADMIN_DELETE_MODS) ? "true" : "false"?>;

<?php
} elseif ($_GET['type'] == "srv") {
?>
$('groupname').value = "<?=$srv_group['name']?>";
$('s14').checked = <?=strstr($srv_flags, SM_ROOT) ? "true" : "false"?>;
$('s1').checked = <?=strstr($srv_flags, SM_RESERVED_SLOT) ? "true" : "false"?>;
$('s23').checked = <?=strstr($srv_flags, SM_GENERIC) ? "true" : "false"?>;
$('s2').checked = <?=strstr($srv_flags, SM_KICK) ? "true" : "false"?>;
$('s3').checked = <?=strstr($srv_flags, SM_BAN) ? "true" : "false"?>;
$('s4').checked = <?=strstr($srv_flags, SM_UNBAN) ? "true" : "false"?>;
$('s5').checked = <?=strstr($srv_flags, SM_SLAY) ? "true" : "false"?>;
$('s6').checked = <?=strstr($srv_flags, SM_MAP) ? "true" : "false"?>;
$('s7').checked = <?=strstr($srv_flags, SM_CVAR) ? "true" : "false"?>;
$('s8').checked = <?=strstr($srv_flags, SM_CONFIG) ? "true" : "false"?>;
$('s9').checked = <?=strstr($srv_flags, SM_CHAT) ? "true" : "false"?>;
$('s10').checked = <?=strstr($srv_flags, SM_VOTE) ? "true" : "false"?>;
$('s11').checked = <?=strstr($srv_flags, SM_PASSWORD) ? "true" : "false"?>;
$('s12').checked = <?=strstr($srv_flags, SM_RCON) ? "true" : "false"?>;
$('s13').checked = <?=strstr($srv_flags, SM_CHEATS) ? "true" : "false"?>;

$('s17').checked = <?=strstr($srv_flags, SM_CUSTOM1) ? "true" : "false"?>;
$('s18').checked = <?=strstr($srv_flags, SM_CUSTOM2) ? "true" : "false"?>;
$('s19').checked = <?=strstr($srv_flags, SM_CUSTOM3) ? "true" : "false"?>;
$('s20').checked = <?=strstr($srv_flags, SM_CUSTOM4) ? "true" : "false"?>;
$('s21').checked = <?=strstr($srv_flags, SM_CUSTOM5) ? "true" : "false"?>;
$('s22').checked = <?=strstr($srv_flags, SM_CUSTOM6) ? "true" : "false"?>;

$('immunity').value = <?=$srv_group['immunity'] ? (int) $srv_group['immunity'] : "0"?>;
<?php
}
?>
</script>
</div></div>
