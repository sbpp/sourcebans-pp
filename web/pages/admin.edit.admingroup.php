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
global $userbank, $theme;

new AdminTabs([], $userbank);

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	No admin id specified. Please only follow links
</div>';
    PageDie();
}

$_GET['id'] = (int) $_GET['id'];
if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
    Log::add("w", "Hacking Attempt", $userbank->GetProperty("user")." tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s groups, but doesn't have access.");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	You are not allowed to edit other admin\'s groups.
</div>';
    PageDie();
}

if (!$userbank->GetProperty("user", $_GET['id'])) {
    Log::add("e", "Getting admin data failed", "Can't find data for admin with id $_GET[id].");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
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
            $GLOBALS['PDO']->query(
                "UPDATE `:prefix_admins` SET gid = :gid WHERE aid = :aid"
            );
            $GLOBALS['PDO']->bind(':gid', $_POST['wg']);
            $GLOBALS['PDO']->bind(':aid', $_GET['id']);
            $GLOBALS['PDO']->execute();
        }

        if (isset($_POST['sg']) && $_POST['sg'] != "-2") {
            // Edit the server admin group
            $group = "";
            if ($_POST['sg'] != -1) {
                $GLOBALS['PDO']->query("SELECT name FROM `:prefix_srvgroups` WHERE id = :id");
                $GLOBALS['PDO']->bind(':id', $_POST['sg']);
                $grps = $GLOBALS['PDO']->single();
                if ($grps) {
                    $group = $grps['name'];
                }
            }

            $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_group = :srvg WHERE aid = :aid");
            $GLOBALS['PDO']->bind(':srvg', $group);
            $GLOBALS['PDO']->bind(':aid', $_GET['id']);
            $edit = $GLOBALS['PDO']->execute();

            $GLOBALS['PDO']->query("UPDATE `:prefix_admins_servers_groups` SET group_id = :gid WHERE admin_id = :aid");
            $GLOBALS['PDO']->bind(':gid', $_POST['sg']);
            $GLOBALS['PDO']->bind(':aid', $_GET['id']);
            $edit = $GLOBALS['PDO']->execute();
        }
        if (Config::getBool('config.enableadminrehashing')) {
            // rehash the admins on the servers
            $GLOBALS['PDO']->query(
                "SELECT s.sid FROM `:prefix_servers` s
                LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = :aid
                LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
                WHERE ((asg.server_id != -1 AND asg.srv_group_id = -1) OR (asg.srv_group_id != -1 AND asg.server_id = -1))
                AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1"
            );
            $serveraccessq = $GLOBALS['PDO']->resultset();
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

        $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':aid', $_GET['id']);
        $admname = $GLOBALS['PDO']->single();
        Log::add("m", "Admin's Groups Updated", "Admin ($admname[user]) groups has been updated.");
    }
}

$GLOBALS['PDO']->query("SELECT gid, name FROM `:prefix_groups` WHERE type != 3");
$wgroups = $GLOBALS['PDO']->resultset();

$GLOBALS['PDO']->query("SELECT id, name FROM `:prefix_srvgroups`");
$sgroups = $GLOBALS['PDO']->resultset();

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
