<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme;

/*
 * Section routing (#1239 — Pattern A, settings-page shape).
 *
 * Mirrors `admin.servers.php`: read `?section=list|add`, render one
 * View per request. #1259 unified the chrome on the Settings-style
 * vertical sidebar (`core/admin_sidebar.tpl`).
 *
 * Note: legacy callers reach this page with `?gid=<n>` to focus the
 * master-detail editor on a specific group; that's a *list* concern,
 * so it always lands on the list section.
 */
$canList = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListGroups));
$canAdd  = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddGroup));

/** @var list<array{slug: string, name: string, permission: int, url: string, icon: string}> $sections */
$sections = [
    [
        'slug'       => 'list',
        'name'       => 'List groups',
        'permission' => ADMIN_OWNER | ADMIN_LIST_GROUPS,
        'url'        => 'index.php?p=admin&c=groups&section=list',
        'icon'       => 'users',
    ],
    [
        'slug'       => 'add',
        'name'       => 'Add a group',
        'permission' => ADMIN_OWNER | ADMIN_ADD_GROUP,
        'url'        => 'index.php?p=admin&c=groups&section=add',
        'icon'       => 'plus',
    ],
];

$validSlugs = ['list', 'add'];
$section    = (string) ($_GET['section'] ?? '');
if (!in_array($section, $validSlugs, true)) {
    if ($canList) {
        $section = 'list';
    } elseif ($canAdd) {
        $section = 'add';
    } else {
        $section = 'list';
    }
}

// AdminTabs opens the sidebar shell + emits the <aside> + opens the
// content column. Closing tags live at the bottom of this file. The
// PHP block here is followed by a `<script>` HTML island in the
// default theme — the closing divs are emitted via PHP `echo` BEFORE
// the file's closing PHP delimiter so the markup nests correctly.
new AdminTabs($sections, $userbank, $theme, $section, 'Group sections');

if ($section === 'add') {
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminGroupsAddView(
        permission_addgroup: $canAdd,
    ));
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell — opened by new AdminTabs(...) above -->';
    return;
}

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
// #1404 dropped the pre-fix `<script>LoadServerHostPlayersList(...)`
// echo + the literal "Servers populate via the legacy ... hook."
// placeholder + the `<div id="servers_{gid}">` slot. #1406 is the
// additive replacement: the per-group `servers` array (sid / ip /
// port) we load here drives a vertical stack of
// `[data-testid="server-tile"]` cards in the template, hydrated
// client-side via the shared `web/scripts/server-tile-hydrate.js`
// helper that already powers the public Server List + admin Server
// Management + dashboard widget. The bare `IP:port` per row stays
// as the SSR / cache-cold / no-JS fallback inside
// `[data-testid="server-host"]`. The hydration helper auto-runs on
// first paint for every `[data-server-hydrate="auto"]` container
// and fires `Actions.ServersHostPlayers` per tile, so no new JSON
// action is registered for this surface.
// ------------------------------------------------------------------
$server_group_rows = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_groups` WHERE type = '3'")->resultset();
$server_list   = [];
$server_counts = [];
foreach ($server_group_rows as $row) {
    $row['gid'] = (int) $row['gid'];

    // INNER JOIN against `:prefix_servers` so groups that retain
    // dangling `:prefix_servers_groups` rows from a deleted server
    // (the schema has no cascade) silently drop those rows from
    // the card list — there's nothing useful to render for a sid
    // that no longer exists, and the hydration helper would hit
    // the "server not found" arm of `api_servers_host_players` on
    // every page load. ORDER BY S.sid keeps the render order stable
    // across page loads so a refresh doesn't shuffle the cards.
    //
    // `S.enabled` rides the projection (post-review): disabled
    // servers should STILL surface ("this group is bound to N
    // servers, here are their addresses" stays useful even when
    // some are disabled — silently filtering them out would hide
    // the bound-but-disabled relationship from the admin), but
    // the template tags each `<li>` with `data-server-skip="1"`
    // so `server-tile-hydrate.js` short-circuits before firing
    // `Actions.ServersHostPlayers` against a server the panel
    // already knows is offline by config. Mirrors the sibling
    // contract in `page_admin_servers_list.tpl`.
    $GLOBALS['PDO']->query(
        "SELECT S.sid, S.ip, S.port, S.enabled
         FROM `:prefix_servers_groups` AS SG
         INNER JOIN `:prefix_servers` AS S ON S.sid = SG.server_id
         WHERE SG.group_id = :gid
         ORDER BY S.sid ASC"
    );
    $GLOBALS['PDO']->bind(':gid', $row['gid']);
    $serverRows = $GLOBALS['PDO']->resultset();

    $row['servers'] = array_map(static fn (array $s): array => [
        'sid'     => (int)    $s['sid'],
        'ip'      => (string) $s['ip'],
        'port'    => (int)    $s['port'],
        // `:prefix_servers.enabled` is `TINYINT NOT NULL DEFAULT '1'`
        // — cast to bool here so the template's `{if !$server.enabled}`
        // gate doesn't have to know the on-disk shape.
        'enabled' => (bool)   $s['enabled'],
    ], $serverRows);
    $row['server_count'] = count($row['servers']);

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

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminGroupsListView(
    permission_listgroups:    $canList,
    permission_editgroup:     $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditGroups)),
    permission_deletegroup:   $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteGroups)),
    permission_editadmin:     $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAdmins)),
    permission_addgroup:      $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddGroup)),
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

echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell — opened by new AdminTabs(...) above -->';
?>
<script>
// sb.accordion (sb.js) is the actual implementation, so call it directly.
// The v1.x InitAccordion helper also stashed the controller in a global
// `accordion` variable, but no template reads it back, so we drop that
// side effect.
sb.ready(function () { sb.accordion('tr.opener', 'div.opener', 'mainwrapper', -1); });
</script>
