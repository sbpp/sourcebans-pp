<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

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

global $userbank, $theme;

new AdminTabs([], $userbank, $theme);

$admsteam = [];
$admins = [];

// Issue #1314: under native prepares (`PDO::ATTR_EMULATE_PREPARES =>
// false`, the panel's default since #1124 / motivated by #1167's
// `LIMIT '0','30'` MariaDB regression), the MySQL driver expands every
// `:name` occurrence into its own positional `?` slot in the prepared
// statement. A name reused inside one query therefore needs as many
// bind() calls as occurrences — `bind(':sid', …)` ONCE on a query
// that mentions `:sid` twice leaves the second slot unbound and
// `execute()` raises `SQLSTATE[HY093] Invalid parameter number`. Pre
// #1124 the duplicate-name pattern was masked by emulated prepares
// substituting the literal value client-side at every occurrence.
// The inner subquery's placeholder is renamed to `:sid_inner` so each
// position is bound separately. See AGENTS.md Anti-patterns ("Reusing
// a `:name` placeholder ...").
$sid = (int) ($_GET['id'] ?? 0);
$GLOBALS['PDO']->query("SELECT authid, user
    FROM `:prefix_admins_servers_groups` AS asg
    LEFT JOIN `:prefix_admins` AS a ON a.aid = asg.admin_id
    WHERE (server_id = :sid OR srv_group_id = ANY
    (
            SELECT group_id
            FROM `:prefix_servers_groups`
            WHERE server_id = :sid_inner)
    )
    GROUP BY aid, authid, srv_password, srv_group, srv_flags, user ");
$GLOBALS['PDO']->bind(':sid', $sid);
$GLOBALS['PDO']->bind(':sid_inner', $sid);
$srv_admins = $GLOBALS['PDO']->resultset();
$i = 0;
foreach ($srv_admins as $admin) {
    if ($admin['authid'] !== null) {
        $admsteam[] = $admin['authid'];
    }
}
if (count($admsteam) > 0 && $serverdata = checkMultiplePlayers($sid, $admsteam)) {
    $noproblem = true;
}
foreach ($srv_admins as $admin) {
    $admins[$i]['user']   = $admin['user'];
    $admins[$i]['authid'] = $admin['authid'];
    if (isset($noproblem) && isset($serverdata[$admin['authid']])) {
        $admins[$i]['ingame'] = true;
        $admins[$i]['iname']  = $serverdata[$admin['authid']]['name'];
        $admins[$i]['iip']    = $serverdata[$admin['authid']]['ip'];
    } else {
        $admins[$i]['ingame'] = false;
    }
    $i++;
}
$theme->assign('admin_count', count($srv_admins));
$theme->assign('admin_list', $admins);
?>
<div id="admin-page-content">
    <div class="tabcontent">
        <?php $theme->display('page_admin_servers_adminlist.tpl');?>
    </div>
</div>
