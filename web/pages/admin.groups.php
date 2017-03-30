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
global $userbank, $theme;
?>
<div id="admin-page-content">
<?php
// web groups
$web_group_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != '3'");
for ($i = 0; $i < count($web_group_list); $i++) {
    $web_group_list[$i]['permissions'] = BitToString($web_group_list[$i]['flags'], $web_group_list[$i]['type']);
    $query                             = $GLOBALS['db']->GetRow("SELECT COUNT(gid) AS cnt FROM `" . DB_PREFIX . "_admins` WHERE gid = '" . $web_group_list[$i]['gid'] . "'");
    $web_group_count[$i]               = $query['cnt'];
    $web_group_admins[$i]              = $GLOBALS['db']->GetAll("SELECT aid, user, authid FROM `" . DB_PREFIX . "_admins` WHERE gid = '" . $web_group_list[$i]['gid'] . "'");
}

// Server admin groups
$server_admin_group_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
for ($i = 0; $i < count($server_admin_group_list); $i++) {
    $server_admin_group_list[$i]['permissions'] = SmFlagsToSb($server_admin_group_list[$i]['flags']);
    $srvGroup                                   = $GLOBALS['db']->qstr($server_admin_group_list[$i]['name']);
    $query                                      = $GLOBALS['db']->GetRow("SELECT COUNT(aid) AS cnt FROM `" . DB_PREFIX . "_admins` WHERE srv_group = $srvGroup;");
    $server_admin_group_count[$i]               = $query['cnt'];
    $server_admin_group_admins[$i]              = $GLOBALS['db']->GetAll("SELECT aid, user, authid FROM `" . DB_PREFIX . "_admins` WHERE srv_group = $srvGroup;");
    $server_admin_group_overrides[$i]           = $GLOBALS['db']->GetAll("SELECT type, name, access FROM `" . DB_PREFIX . "_srvgroups_overrides` WHERE group_id = ?", array(
        $server_admin_group_list[$i]['id']
    ));
}
// server groups
$server_group_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type = '3'");
for ($i = 0; $i < count($server_group_list); $i++) {
    $query                  = $GLOBALS['db']->GetRow("SELECT COUNT(server_id) AS cnt FROM `" . DB_PREFIX . "_servers_groups` WHERE `group_id` = " . $server_group_list[$i]['gid']);
    $server_group_count[$i] = $query['cnt'];
    $servers_in_group       = $GLOBALS['db']->GetAll("SELECT server_id FROM `" . DB_PREFIX . "_servers_groups` WHERE group_id = " . $server_group_list[$i]['gid']);
    $server_arr             = "";
    foreach ($servers_in_group as $server) {
        $server_arr .= $server['server_id'] . ";";
    }
    echo "<script>";
    echo "xajax_ServerHostPlayers_list('" . $server_arr . "', 'id', 'servers_" . $server_group_list[$i]['gid'] . "');";
    echo "</script>";
}
// List Group
?>
<div id="0" style="display:none;">
<?php
$theme->assign('permission_listgroups', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_GROUPS));
$theme->assign('permission_editgroup', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_GROUPS));
$theme->assign('permission_deletegroup', $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_GROUPS));
$theme->assign('permission_editadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS));
$theme->assign('web_group_count', count($web_group_list));
$theme->assign('web_admins', (isset($web_group_count) ? $web_group_count : '0'));
$theme->assign('web_admins_list', $web_group_admins);
$theme->assign('web_group_list', $web_group_list);
$theme->assign('server_admin_group_count', count($server_admin_group_list));
$theme->assign('server_admins', (isset($server_admin_group_count) ? $server_admin_group_count : '0'));
$theme->assign('server_admins_list', $server_admin_group_admins);
$theme->assign('server_overrides_list', $server_admin_group_overrides);
$theme->assign('server_group_list', $server_admin_group_list);
$theme->assign('server_group_count', count($server_group_list));
$theme->assign('server_counts', (isset($server_group_count) ? $server_group_count : '0'));
$theme->assign('server_list', $server_group_list);
$theme->display('page_admin_groups_list.tpl');
// Add Groups
?>
</div>
    <div id="1" style="display:none;">
<?php
$theme->assign('permission_addgroup', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_GROUP));
$theme->display('page_admin_groups_add.tpl');
?>
    </div>
    <script>InitAccordion('tr.opener', 'div.opener', 'mainwrapper');</script>
</div>
