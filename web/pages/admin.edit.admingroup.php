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

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
    PageDie();
}

$_GET['id'] = (int) $_GET['id'];
if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
    $log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty("user") . " tried to edit " . $userbank->GetProperty('user', $_GET['id']) . "'s groups, but doesn't have access.");
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	You are not allowed to edit other admin\'s groups.
</div>';
    PageDie();
}

if (!$userbank->GetProperty("user", $_GET['id'])) {
    $log = new CSystemLog("e", "Getting admin data failed", "Can't find data for admin with id '" . $_GET['id'] . "'");
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Error getting current data.</div>';
    PageDie();
}

// Form sent
if (isset($_POST['wg']) || isset($_GET['wg']) || isset($_GET['sg'])) {
    if (isset($_GET['wg'])) {
        $_POST['wg'] = $_GET['wg'];
    }
    if (isset($_GET['sg'])) {
        $_POST['sg'] = $_GET['sg'];
    }

    $_POST['wg'] = (int) $_POST['wg'];
    $_POST['sg'] = (int) $_POST['sg'];

    // Users require a password and email to have web permissions
    $password = $GLOBALS['userbank']->GetProperty('password', $_GET['id']);
    $email    = $GLOBALS['userbank']->GetProperty('email', $_GET['id']);
    if ($_POST['wg'] > 0 && (empty($password) || empty($email))) {
        echo '<script>ShowBox("Error", "Admins have to have a password and email set in order to get web permissions.<br /><a href=\"index.php?p=admin&c=admins&o=editdetails&id=' . $_GET['id'] . '\" title=\"Edit Admin Details\">Set the details</a> first and try again.", "red");</script>';
    } else {
        if (isset($_POST['wg']) && $_POST['wg'] != "-2") {
            if ($_POST['wg'] == -1) {
                $_POST['wg'] = 0;
            }
            // Edit the web group
            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins SET
                `gid` = ?
                WHERE `aid` = ?;",
                array(
                    $_POST['wg'],
                    $_GET['id']
                )
            );
        }

        if (isset($_POST['sg']) && $_POST['sg'] != "-2") {
            // Edit the server admin group
            $group = "";
            if ($_POST['sg'] != -1) {
                $grps = $GLOBALS['db']->GetRow("SELECT name FROM " . DB_PREFIX . "_srvgroups WHERE id = ?;", array(
                    $_POST['sg']
                ));
                if ($grps) {
                    $group = $grps['name'];
                }
            }

            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins SET
                `srv_group` = ?
                WHERE aid = ?",
                array(
                    $group,
                    $_GET['id']
                )
            );

            $edit = $GLOBALS['db']->Execute(
                "UPDATE " . DB_PREFIX . "_admins_servers_groups SET
                `group_id` = ?
                WHERE admin_id = ?;",
                array(
                    $_POST['sg'],
                    $_GET['id']
                )
            );
        }
        if (isset($GLOBALS['config']['config.enableadminrehashing']) && $GLOBALS['config']['config.enableadminrehashing'] == 1) {
            // rehash the admins on the servers
            $serveraccessq = $GLOBALS['db']->GetAll("SELECT s.sid FROM `" . DB_PREFIX . "_servers` s
                LEFT JOIN `" . DB_PREFIX . "_admins_servers_groups` asg ON asg.admin_id = '" . (int) $_GET['id'] . "'
                LEFT JOIN `" . DB_PREFIX . "_servers_groups` sg ON sg.group_id = asg.srv_group_id
                WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
                OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
                AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1");
            $allservers    = array();
            foreach ($serveraccessq as $access) {
                if (!in_array($access['sid'], $allservers)) {
                    $allservers[] = $access['sid'];
                }
            }
            echo '<script>ShowRehashBox("' . implode(",", $allservers) . '", "Admin updated", "The admin has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
        } else {
            echo '<script>ShowBox("Admin updated", "The admin has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
        }

        $admname = $GLOBALS['db']->GetRow("SELECT user FROM `" . DB_PREFIX . "_admins` WHERE aid = ?", array(
            (int) $_GET['id']
        ));
        $log = new CSystemLog("m", "Admin's Groups Updated", "Admin (".$admname['user'].") groups has been updated");
    }
}

$wgroups = $GLOBALS['db']->GetAll("SELECT gid, name FROM " . DB_PREFIX . "_groups WHERE type != 3");
$sgroups = $GLOBALS['db']->GetAll("SELECT id, name FROM " . DB_PREFIX . "_srvgroups");

$server_admin_group = $userbank->GetProperty('srv_groups', $_GET['id']);
foreach ($sgroups as $sg) {
    if ($sg['name'] == $server_admin_group) {
        $server_admin_group = (int) $sg['id'];
        break;
    }
}

$theme->assign('group_admin_name', $userbank->GetProperty("user", $_GET['id']));
$theme->assign('group_admin_id', $userbank->GetProperty("gid", $_GET['id']));
$theme->assign('group_lst', $sgroups);
$theme->assign('web_lst', $wgroups);
$theme->assign('server_admin_group_id', $server_admin_group);

$theme->display('page_admin_edit_admins_group.tpl');
