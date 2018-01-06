<div id="admin-page-content">
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
global $userbank, $ui;

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}

$AdminsStart = intval(($page - 1) * $AdminsPerPage);
$AdminsEnd   = intval($AdminsStart + $AdminsPerPage);
if ($AdminsEnd > $admin_count) {
    $AdminsEnd = $admin_count;
}

// List Page
$admin_list = array();
foreach ($admins as $admin) {
    $admin['immunity']     = $userbank->GetProperty("srv_immunity", $admin['aid']);
    $admin['web_group']    = $userbank->GetProperty("group_name", $admin['aid']);
    $admin['server_group'] = $userbank->GetProperty("srv_groups", $admin['aid']);
    if (empty($admin['web_group']) || $admin['web_group'] == " ") {
        $admin['web_group'] = "No Group/Individual Permissions";
    }
    if (empty($admin['server_group']) || $admin['server_group'] == " ") {
        $admin['server_group'] = "No Group/Individual Permissions";
    }
    $num               = $GLOBALS['db']->GetRow("SELECT count(authid) AS num FROM `" . DB_PREFIX . "_bans` WHERE aid = '" . $admin['aid'] . "'");
    $admin['bancount'] = $num['num'];

    $nodem                = $GLOBALS['db']->GetRow("SELECT count(B.bid) AS num FROM `" . DB_PREFIX . "_bans` AS B WHERE aid = '" . $admin['aid'] . "' AND NOT EXISTS (SELECT D.demid FROM `" . DB_PREFIX . "_demos` AS D WHERE D.demid = B.bid)");
    $admin['aid']         = $admin['aid'];
    $admin['nodemocount'] = $nodem['num'];

    $admin['name']               = stripslashes($admin['user']);
    $admin['server_flag_string'] = SmFlagsToSb($userbank->GetProperty("srv_flags", $admin['aid']));
    $admin['web_flag_string']    = BitToString($userbank->GetProperty("extraflags", $admin['aid']));

    $lastvisit = $userbank->GetProperty("lastvisit", $admin['aid']);
    if (!$lastvisit) {
        $admin['lastvisit'] = "Never";
    } else {
        $admin['lastvisit'] = date($dateformat, $userbank->GetProperty("lastvisit", $admin['aid']));
    }
    array_push($admin_list, $admin);
}

if ($page > 1) {
    $prev = CreateLinkR('<img border="0" alt="prev" src="images/left.png" style="vertical-align:middle;" /> prev', "index.php?p=admin&c=admins&page=" . ($page - 1) . $advSearchString);
} else {
    $prev = "";
}
if ($AdminsEnd < $admin_count) {
    $next = CreateLinkR('next <img border="0" alt="prev" src="images/right.png" style="vertical-align:middle;" />', "index.php?p=admin&c=admins&page=" . ($page + 1) . $advSearchString);
} else {
    $next = "";
}

//=================[ Start Layout ]==================================
$admin_nav = 'displaying&nbsp;' . $AdminsStart . '&nbsp;-&nbsp;' . $AdminsEnd . '&nbsp;of&nbsp;' . $admin_count . '&nbsp;results';

if (strlen($prev) > 0) {
    $admin_nav .= ' | <b>' . $prev . '</b>';
}
if (strlen($next) > 0) {
    $admin_nav .= ' | <b>' . $next . '</b>';
}

$pages = ceil($admin_count / $AdminsPerPage);
if ($pages > 1) {
    $admin_nav .= '&nbsp;<select onchange="changePage(this,\'A\',\'' . $_GET['advSearch'] . '\',\'' . $_GET['advType'] . '\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $_GET["page"]) {
            $admin_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $admin_nav .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $admin_nav .= '</select>';
}

echo '<div id="0" style="display:none;">';
$theme->assign('permission_listadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS));
$theme->assign('permission_editadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS));
$theme->assign('permission_deleteadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_ADMINS));
$theme->assign('admin_count', $admin_count);
$theme->assign('admin_nav', $admin_nav);
$theme->assign('admins', $admin_list);
$theme->display('page_admin_admins_list.tpl');
echo '</div>';

// Add Page
$group_list              = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type = '3'");
$servers                 = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_servers`");
$server_admin_group_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
$server_group_list       = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != 3");
$server_list             = array();
$serverscript            = "<script type=\"text/javascript\">";
foreach ($servers as $server) {
    $serverscript .= "xajax_ServerHostPlayers('" . $server['sid'] . "', 'id', 'sa" . $server['sid'] . "');";
    $info['sid']  = $server['sid'];
    $info['ip']   = $server['ip'];
    $info['port'] = $server['port'];
    array_push($server_list, $info);
}
$serverscript .= "</script>";

echo '<div id="1" style="display:none;">';
$theme->assign('group_list', $group_list);
$theme->assign('server_list', $server_list);
$theme->assign('server_script', $serverscript);
$theme->assign('server_admin_group_list', $server_admin_group_list);
$theme->assign('server_group_list', $server_group_list);
$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS));
$theme->display('page_admin_admins_add.tpl');
echo '</div>';
// Overrides

// Saving changed overrides
$overrides_error        = "";
$overrides_save_success = false;
try {
    if (isset($_POST['new_override_name'])) {
        // Handle old overrides, if there are any.
        if (isset($_POST['override_id'])) {
            // Apply changes first
            $edit_errors = "";
            foreach ($_POST['override_id'] as $index => $id) {
                // Skip invalid stuff?!
                if ($_POST['override_type'][$index] != "command" && $_POST['override_type'][$index] != "group") {
                    continue;
                }

                $id = (int) $id;
                // Wants to delete this override?
                if (empty($_POST['override_name'][$index])) {
                    $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_overrides` WHERE id = ?;", array(
                        $id
                    ));
                    continue;
                }

                // Check for duplicates
                $chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ? AND id != ?", array(
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    $id
                ));
                if (!empty($chk)) {
                    $edit_errors .= "&bull; There already is an override with name \\\"" . htmlspecialchars(addslashes($_POST['override_name'][$index])) . "\\\" from the selected type.<br />";
                    continue;
                }

                // Edit the override
                $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_overrides` SET name = ?, type = ?, flags = ? WHERE id = ?;", array(
                    $_POST['override_name'][$index],
                    $_POST['override_type'][$index],
                    trim($_POST['override_flags'][$index]),
                    $id
                ));
            }

            if (!empty($edit_errors)) {
                throw new Exception("There were errors applying your changes:<br /><br />" . $edit_errors);
            }
        }

        // Add a new override
        if (!empty($_POST['new_override_name'])) {
            if ($_POST['new_override_type'] != "command" && $_POST['new_override_type'] != "group") {
                throw new Exception("Invalid override type.");
            }

            // Check for duplicates
            $chk = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides` WHERE name = ? AND type = ?", array(
                $_POST['new_override_name'],
                $_POST['new_override_type']
            ));
            if (!empty($chk)) {
                throw new Exception("There already is an override with that name from the selected type.");
            }

            // Insert the new override
            $GLOBALS['db']->Execute("INSERT INTO `" . DB_PREFIX . "_overrides` (type, name, flags) VALUES (?, ?, ?);", array(
                $_POST['new_override_type'],
                $_POST['new_override_name'],
                trim($_POST['new_override_flags'])
            ));
        }

        $overrides_save_success = true;
    }
} catch (Exception $e) {
    $overrides_error = $e->getMessage();
}

$overrides_list = $GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_overrides`;");

echo '<div id="2" style="display:none;">';
$theme->assign('overrides_list', $overrides_list);
$theme->assign('overrides_error', $overrides_error);
$theme->assign('overrides_save_success', $overrides_save_success);
$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_ADMINS));
$theme->display('page_admin_overrides.tpl');
echo '</div>';
?>
</div>
