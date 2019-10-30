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
global $theme;

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

$aid = (int) $_GET['id'];
if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
    Log::add("w", "Hacking Attempt", $userbank->GetProperty("user")." tried to edit ".$userbank->GetProperty('user', $_GET['id'])."'s server access, but doesnt have access.");
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	You are not allowed to edit admins server access.
</div>';
    PageDie();
}

$GLOBALS['PDO']->query("SELECT server_id, srv_group_id FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
$GLOBALS['PDO']->bind(':aid', $aid);
$servers    = $GLOBALS['PDO']->resultset();

$GLOBALS['PDO']->query("SELECT id FROM `:prefix_srvgroups` sg, `:prefix_admins` a WHERE sg.name = a.srv_group AND a.aid :aid LIMIT 1");
$GLOBALS['PDO']->bind(':aid', $aid);
$adminGroup = $GLOBALS['PDO']->single();

$server_grp = isset($adminGroup[0]['id']) ? $adminGroup[0]['id'] : 0;


if (isset($_POST['editadminserver'])) {
    // clear old stuffs
    $GLOBALS['PDO']->query("DELETE FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();
    if (isset($_POST['servers']) && is_array($_POST['servers']) && count($_POST['servers']) > 0) {
        foreach ($_POST['servers'] as $s) {
            $GLOBALS['PDO']->query("INSERT INTO `:prefix_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id) VALUES (:aid, :gid, -1, :sid)");
            $GLOBALS['PDO']->bindMultiple([
                ':aid' => $aid,
                ':gid' => $server_grp,
                ':sid' => substr($s, 1)
            ]);
            $GLOBALS['PDO']->execute();
        }
    }
    if (isset($_POST['group']) && is_array($_POST['group']) && count($_POST['group']) > 0) {
        foreach ($_POST['group'] as $g) {
            $GLOBALS['PDO']->query("INSERT INTO `:prefix_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id) VALUES (:aid, :gid, :srvid, -1)");
            $GLOBALS['PDO']->bindMultiple([
                ':aid' => $aid,
                ':gid' => $server_grp,
                ':srvid' => substr($g, 1)
            ]);
            $GLOBALS['PDO']->execute();
        }
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
        $GLOBALS['PDO']->bind(':aid', $aid);
        $serveraccessq = $GLOBALS['PDO']->resultset();

        $allservers = array();
        foreach ($serveraccessq as $access) {
            if (!in_array($access['sid'], $allservers)) {
                $allservers[] = $access['sid'];
            }
        }

        // Add all servers, he's been admin on before
        foreach ($servers as $server) {
            if ($server['server_id'] != "-1" && !in_array((int) $server['server_id'], $allservers)) {
                $allservers[] = (int) $server['server_id'];
            }

            // old server groups
            $GLOBALS['PDO']->query("SELECT server_id FROM `:prefix_servers_groups` WHERE group_id = :gid");
            $GLOBALS['PDO']->bind(':gid', $server['srv_group_id']);
            $serv_in_grp = $GLOBALS['PDO']->resultset();
            foreach ($serv_in_grp as $srg) {
                if ($srg['server_id'] != "-1" && !in_array((int) $srg['server_id'], $allservers)) {
                    $allservers[] = (int) $srg['server_id'];
                }
            }
        }

        echo '<script>ShowRehashBox("' . implode(",", $allservers) . '", "Admin server access updated", "The admin server access has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
    } else {
        echo '<script>ShowBox("Admin server access updated", "The admin server access has been updated successfully", "green", "index.php?p=admin&c=admins");TabToReload();</script>';
    }

    $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admname = $GLOBALS['PDO']->single();
    Log::add("m", "Admin Servers Updated", "Admin ($admname[user]) server access has been changed.");
}


$GLOBALS['PDO']->query("SELECT * FROM `:prefix_servers`");
$server_list = $GLOBALS['PDO']->resultset();

$GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type = 3");
$group_list  = $GLOBALS['PDO']->resultset();
$rowcount    = (count($server_list) + count($group_list));

$theme->assign('row_count', $rowcount);
$theme->assign('group_list', $group_list);
$theme->assign('server_list', $server_list);
$theme->assign('assigned_servers', $servers);

$theme->display('page_admin_edit_admins_servers.tpl');
