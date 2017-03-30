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

global $userbank, $theme;

//serverlist
$server_list  = $GLOBALS['db']->Execute("SELECT sid, ip, port FROM `" . DB_PREFIX . "_servers` WHERE enabled = 1");
$servers      = array();
$serverscript = "<script type=\"text/javascript\">";
while (!$server_list->EOF) {
    $info = array();
    $serverscript .= "xajax_ServerHostPlayers('" . $server_list->fields[0] . "', 'id', 'ss" . $server_list->fields[0] . "', '', '', false, 200);";
    $info['sid']  = $server_list->fields[0];
    $info['ip']   = $server_list->fields[1];
    $info['port'] = $server_list->fields[2];
    array_push($servers, $info);
    $server_list->MoveNext();
}
$serverscript .= "</script>";

//webgrouplist
$webgroup_list = $GLOBALS['db']->Execute("SELECT gid, name FROM " . DB_PREFIX . "_groups WHERE type = '1'");
$webgroups     = array();
while (!$webgroup_list->EOF) {
    $data         = array();
    $data['gid']  = $webgroup_list->fields['gid'];
    $data['name'] = $webgroup_list->fields['name'];

    array_push($webgroups, $data);
    $webgroup_list->MoveNext();
}

//serveradmingrouplist
$srvadmgroup_list = $GLOBALS['db']->Execute("SELECT name FROM " . DB_PREFIX . "_srvgroups ORDER BY name ASC");
$srvadmgroups     = array();
while (!$srvadmgroup_list->EOF) {
    $data         = array();
    $data['name'] = $srvadmgroup_list->fields['name'];

    array_push($srvadmgroups, $data);
    $srvadmgroup_list->MoveNext();
}

//servergroup
$srvgroup_list = $GLOBALS['db']->Execute("SELECT gid, name FROM " . DB_PREFIX . "_groups WHERE type = '3'");
$srvgroups     = array();
while (!$srvgroup_list->EOF) {
    $data         = array();
    $data['gid']  = $srvgroup_list->fields['gid'];
    $data['name'] = $srvgroup_list->fields['name'];

    array_push($srvgroups, $data);
    $srvgroup_list->MoveNext();
}

//webpermissions
$webflag[] = array(
    "name" => "Root Admin",
    "flag" => "ADMIN_OWNER"
);
$webflag[] = array(
    "name" => "View admins",
    "flag" => "ADMIN_LIST_ADMINS"
);
$webflag[] = array(
    "name" => "Add admins",
    "flag" => "ADMIN_ADD_ADMINS"
);
$webflag[] = array(
    "name" => "Edit admins",
    "flag" => "ADMIN_EDIT_ADMINS"
);
$webflag[] = array(
    "name" => "Delete admins",
    "flag" => "ADMIN_DELETE_ADMINS"
);
$webflag[] = array(
    "name" => "View servers",
    "flag" => "ADMIN_LIST_SERVERS"
);
$webflag[] = array(
    "name" => "Add servers",
    "flag" => "ADMIN_ADD_SERVER"
);
$webflag[] = array(
    "name" => "Edit servers",
    "flag" => "ADMIN_EDIT_SERVERS"
);
$webflag[] = array(
    "name" => "Delete servers",
    "flag" => "ADMIN_DELETE_SERVERS"
);
$webflag[] = array(
    "name" => "Add bans",
    "flag" => "ADMIN_ADD_BAN"
);
$webflag[] = array(
    "name" => "Edit own bans",
    "flag" => "ADMIN_EDIT_OWN_BANS"
);
$webflag[] = array(
    "name" => "Edit groups bans",
    "flag" => "ADMIN_EDIT_GROUP_BANS"
);
$webflag[] = array(
    "name" => "Edit all bans",
    "flag" => "ADMIN_EDIT_ALL_BANS"
);
$webflag[] = array(
    "name" => "Ban protests",
    "flag" => "ADMIN_BAN_PROTESTS"
);
$webflag[] = array(
    "name" => "Ban submissions",
    "flag" => "ADMIN_BAN_SUBMISSIONS"
);
$webflag[] = array(
    "name" => "Delete bans",
    "flag" => "ADMIN_DELETE_BAN"
);
$webflag[] = array(
    "name" => "Unban own bans",
    "flag" => "ADMIN_UNBAN_OWN_BANS"
);
$webflag[] = array(
    "name" => "Unban group bans",
    "flag" => "ADMIN_UNBAN_GROUP_BANS"
);
$webflag[] = array(
    "name" => "Unban all bans",
    "flag" => "ADMIN_UNBAN"
);
$webflag[] = array(
    "name" => "Import bans",
    "flag" => "ADMIN_BAN_IMPORT"
);
$webflag[] = array(
    "name" => "Submission email notifying",
    "flag" => "ADMIN_NOTIFY_SUB"
);
$webflag[] = array(
    "name" => "Protest email notifying",
    "flag" => "ADMIN_NOTIFY_PROTEST"
);
$webflag[] = array(
    "name" => "List groups",
    "flag" => "ADMIN_LIST_GROUPS"
);
$webflag[] = array(
    "name" => "Add groups",
    "flag" => "ADMIN_ADD_GROUP"
);
$webflag[] = array(
    "name" => "Edit groups",
    "flag" => "ADMIN_EDIT_GROUPS"
);
$webflag[] = array(
    "name" => "Delete groups",
    "flag" => "ADMIN_DELETE_GROUPS"
);
$webflag[] = array(
    "name" => "Web settings",
    "flag" => "ADMIN_WEB_SETTINGS"
);
$webflag[] = array(
    "name" => "List mods",
    "flag" => "ADMIN_LIST_MODS"
);
$webflag[] = array(
    "name" => "Add mods",
    "flag" => "ADMIN_ADD_MODS"
);
$webflag[] = array(
    "name" => "Edit mods",
    "flag" => "ADMIN_EDIT_MODS"
);
$webflag[] = array(
    "name" => "Delete mods",
    "flag" => "ADMIN_DELETE_MODS"
);
$webflags  = array();
foreach ($webflag as $flag) {
    $data['name'] = $flag["name"];
    $data['flag'] = $flag["flag"];

    array_push($webflags, $data);
}

//server permissions
$serverflag[] = array(
    "name" => "Full Admin",
    "flag" => "SM_ROOT"
);
$serverflag[] = array(
    "name" => "Reserved slot",
    "flag" => "SM_RESERVED_SLOT"
);
$serverflag[] = array(
    "name" => "Generic admin",
    "flag" => "SM_GENERIC"
);
$serverflag[] = array(
    "name" => "Kick",
    "flag" => "SM_KICK"
);
$serverflag[] = array(
    "name" => "Ban",
    "flag" => "SM_BAN"
);
$serverflag[] = array(
    "name" => "Un-ban",
    "flag" => "SM_UNBAN"
);
$serverflag[] = array(
    "name" => "Slay",
    "flag" => "SM_SLAY"
);
$serverflag[] = array(
    "name" => "Map change",
    "flag" => "SM_MAP"
);
$serverflag[] = array(
    "name" => "Change cvars",
    "flag" => "SM_CVAR"
);
$serverflag[] = array(
    "name" => "Run configs",
    "flag" => "SM_CONFIG"
);
$serverflag[] = array(
    "name" => "Admin chat",
    "flag" => "SM_CHAT"
);
$serverflag[] = array(
    "name" => "Start votes",
    "flag" => "SM_VOTE"
);
$serverflag[] = array(
    "name" => "Password server",
    "flag" => "SM_PASSWORD"
);
$serverflag[] = array(
    "name" => "RCON",
    "flag" => "SM_RCON"
);
$serverflag[] = array(
    "name" => "Enable Cheats",
    "flag" => "SM_CHEATS"
);
$serverflag[] = array(
    "name" => "Custom flag 1",
    "flag" => "SM_CUSTOM1"
);
$serverflag[] = array(
    "name" => "Custom flag 2",
    "flag" => "SM_CUSTOM2"
);
$serverflag[] = array(
    "name" => "Custom flag 3",
    "flag" => "SM_CUSTOM3"
);
$serverflag[] = array(
    "name" => "Custom flag 4",
    "flag" => "SM_CUSTOM4"
);
$serverflag[] = array(
    "name" => "Custom flag 5",
    "flag" => "SM_CUSTOM5"
);
$serverflag[] = array(
    "name" => "Custom flag 6",
    "flag" => "SM_CUSTOM6"
);
$serverflags  = array();
foreach ($serverflag as $flag) {
    $data['name'] = $flag["name"];
    $data['flag'] = $flag["flag"];

    array_push($serverflags, $data);
}


$theme->assign('server_list', $servers);
$theme->assign('server_script', $serverscript);
$theme->assign('webgroup_list', $webgroups);
$theme->assign('srvadmgroup_list', $srvadmgroups);
$theme->assign('srvgroup_list', $srvgroups);
$theme->assign('admwebflag_list', $webflags);
$theme->assign('admsrvflag_list', $serverflags);
$theme->assign('can_editadmin', $userbank->HasAccess(ADMIN_EDIT_ADMINS | ADMIN_OWNER));

$theme->display('box_admin_admins_search.tpl');
