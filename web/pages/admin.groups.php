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

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme;

new AdminTabs([
    ['name' => 'List groups', 'permission' => ADMIN_OWNER|ADMIN_LIST_GROUPS],
    ['name' => 'Add a group', 'permission' => ADMIN_OWNER|ADMIN_ADD_GROUP]
], $userbank, $theme);

// ------------------------------------------------------------------
// Web admin groups (`:prefix_groups` WHERE type != 3).
//
// `web_admins` / `web_admins_list` are kept (indexed by foreach
// position) as a compatibility shape for any third-party theme that
// forked the pre-v2.0.0 default; the shipped template reads
// `member_count` inlined on each row instead. Both shapes derive from
// the same per-group queries below.
// ------------------------------------------------------------------
$web_group_rows = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type != '3'")->resultset();
$web_group_list          = [];
$web_admins              = [];
$web_admins_list         = [];
foreach ($web_group_rows as $row) {
    $row['gid']         = (int) $row['gid'];
    $row['flags']       = (int) $row['flags'];
    $row['permissions'] = BitToString($row['flags']);

    $cnt = $GLOBALS['PDO']->query("SELECT COUNT(gid) AS cnt FROM `:prefix_admins` WHERE gid = :gid");
    $GLOBALS['PDO']->bind(':gid', $row['gid']);
    $cnt = $GLOBALS['PDO']->single();
    $row['member_count'] = (int) $cnt['cnt'];

    $GLOBALS['PDO']->query("SELECT aid, user, authid FROM `:prefix_admins` WHERE gid = :gid");
    $GLOBALS['PDO']->bind(':gid', $row['gid']);
    $members = $GLOBALS['PDO']->resultset();

    $web_group_list[]  = $row;
    $web_admins[]      = $row['member_count'];
    $web_admins_list[] = $members;
}
$web_group_count = count($web_group_list);

// ------------------------------------------------------------------
// Server admin groups (`:prefix_srvgroups`).
// ------------------------------------------------------------------
$server_admin_group_rows = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_srvgroups`")->resultset();
$server_group_list       = [];
$server_admins           = [];
$server_admins_list      = [];
$server_overrides_list   = [];
foreach ($server_admin_group_rows as $row) {
    $row['id']          = (int) $row['id'];
    $row['immunity']    = (int) ($row['immunity'] ?? 0);
    $row['permissions'] = SmFlagsToSb($row['flags']);

    $GLOBALS['PDO']->query("SELECT COUNT(aid) AS cnt FROM `:prefix_admins` WHERE srv_group = :srv_group");
    $GLOBALS['PDO']->bind(':srv_group', $row['name']);
    $cnt = $GLOBALS['PDO']->single();
    $row['member_count'] = (int) $cnt['cnt'];

    $GLOBALS['PDO']->query("SELECT aid, user, authid FROM `:prefix_admins` WHERE srv_group = :srv_group");
    $GLOBALS['PDO']->bind(':srv_group', $row['name']);
    $members = $GLOBALS['PDO']->resultset();

    $GLOBALS['PDO']->query("SELECT type, name, access FROM `:prefix_srvgroups_overrides` WHERE group_id = :gid");
    $GLOBALS['PDO']->bind(':gid', $row['id']);
    $overrides = $GLOBALS['PDO']->resultset();

    $server_group_list[]     = $row;
    $server_admins[]         = $row['member_count'];
    $server_admins_list[]    = $members;
    $server_overrides_list[] = $overrides;
}
$server_admin_group_count = count($server_group_list);

// ------------------------------------------------------------------
// Server groups (`:prefix_groups` WHERE type = 3).
//
// `LoadServerHostPlayersList(...)` inline scripts are still emitted
// for any third-party theme that forked the pre-v2.0.0 default and
// renders an accordion-revealed server list per row. The shipped
// template omits the accordion entirely (the marquee surface is the
// master-detail flag grid above, not these server-of-servers
// groupings) and ignores the script tags.
// ------------------------------------------------------------------
$server_group_rows = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type = '3'")->resultset();
$server_list   = [];
$server_counts = [];
foreach ($server_group_rows as $row) {
    $row['gid'] = (int) $row['gid'];

    $GLOBALS['PDO']->query("SELECT COUNT(server_id) AS cnt FROM `:prefix_servers_groups` WHERE `group_id` = :gid");
    $GLOBALS['PDO']->bind(':gid', $row['gid']);
    $cnt = $GLOBALS['PDO']->single();
    $row['server_count'] = (int) $cnt['cnt'];

    $GLOBALS['PDO']->query("SELECT server_id FROM `:prefix_servers_groups` WHERE group_id = :gid");
    $GLOBALS['PDO']->bind(':gid', $row['gid']);
    $servers_in_group = $GLOBALS['PDO']->resultset();

    $server_arr = "";
    foreach ($servers_in_group as $server) {
        $server_arr .= $server['server_id'] . ";";
    }
    echo "<script>";
    echo "LoadServerHostPlayersList('" . $server_arr . "', 'id', 'servers_" . $row['gid'] . "');";
    echo "</script>";

    $server_list[]   = $row;
    $server_counts[] = $row['server_count'];
}
$server_group_count = count($server_list);

// ------------------------------------------------------------------
// Web flag definitions (drives the master-detail flag grid). Sourced
// from `web/configs/permissions/web.json`; we strip the meta entries
// (`ALL_WEB`, `ADMIN_OWNER`) since they're not assignable per-group.
// ------------------------------------------------------------------
$flagDefsRaw = json_decode((string) file_get_contents(ROOT . '/configs/permissions/web.json'), true);
$all_flags = [];
if (is_array($flagDefsRaw)) {
    foreach ($flagDefsRaw as $constName => $info) {
        if (!is_string($constName) || !str_starts_with($constName, 'ADMIN_')) {
            continue;
        }
        if ($constName === 'ADMIN_OWNER') {
            continue;
        }
        if (!is_array($info) || !isset($info['value'], $info['display'])) {
            continue;
        }
        $all_flags[] = [
            'name'  => strtolower(substr($constName, strlen('ADMIN_'))),
            'value' => (int) $info['value'],
            'label' => (string) $info['display'],
        ];
    }
}

// ------------------------------------------------------------------
// Selected group: ?gid=<n> falls back to the first row so the
// master-detail panel always has something to render when the
// directory is non-empty.
// ------------------------------------------------------------------
$selected_group = null;
if (!empty($web_group_list)) {
    $requestedGid = isset($_GET['gid']) ? (int) $_GET['gid'] : 0;
    $match = null;
    foreach ($web_group_list as $g) {
        if ($g['gid'] === $requestedGid) {
            $match = $g;
            break;
        }
    }
    if ($match === null) {
        $match = $web_group_list[0];
    }
    $selected_group = [
        'gid'          => (int) $match['gid'],
        'name'         => (string) $match['name'],
        'flags'        => (int) $match['flags'],
        'member_count' => (int) $match['member_count'],
    ];
}

echo '<div id="admin-page-content">';

// List groups tab.
echo '<div class="tabcontent" id="List groups">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminGroupsListView(
    permission_listgroups:    $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_GROUPS),
    permission_editgroup:     $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_GROUPS),
    permission_deletegroup:   $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_GROUPS),
    permission_editadmin:     $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS),
    permission_addgroup:      $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_GROUP),
    web_group_count:          $web_group_count,
    web_admins:               $web_admins,
    web_admins_list:          $web_admins_list,
    web_group_list:           $web_group_list,
    server_admin_group_count: $server_admin_group_count,
    server_admins:            $server_admins,
    server_admins_list:       $server_admins_list,
    server_overrides_list:    $server_overrides_list,
    server_group_list:        $server_group_list,
    server_group_count:       $server_group_count,
    server_counts:            $server_counts,
    server_list:              $server_list,
    all_flags:                $all_flags,
    selected_group:           $selected_group,
));
echo '</div>';

// Add a group tab.
echo '<div class="tabcontent" id="Add a group">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminGroupsAddView(
    permission_addgroup: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_GROUP),
));
echo '</div>';
?>
<script>
// sb.accordion (sb.js) is the actual implementation, so call it directly.
// The v1.x InitAccordion helper also stashed the controller in a global
// `accordion` variable, but no template reads it back, so we drop that
// side effect.
sb.ready(function () { sb.accordion('tr.opener', 'div.opener', 'mainwrapper', -1); });
</script>
</div>
