<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme;

/*
 * Section routing (#1239 — Pattern A, settings-page shape).
 *
 * Each section is its own page request keyed on `?section=list|add`;
 * the sub-nav at the top carries `aria-current="page"` on the active
 * link. Pre-#1239 the page emitted a broken `<button onclick="openTab(...)">`
 * strip (the JS handler was deleted with sourcebans.js at #1123 D1)
 * and rendered BOTH panes back-to-back below it, so the tab strip
 * lied about being a tab control. The fix follows the long-standing
 * `admin.settings.php` convention: read `?section=…`, render one View
 * per request, list every section pill as an anchor link.
 */
$canList = $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_SERVERS);
$canAdd  = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_SERVER);

/** @var list<array{slug: string, name: string, permission: int, url: string}> $sections */
$sections = [
    [
        'slug'       => 'list',
        'name'       => 'List servers',
        'permission' => ADMIN_OWNER | ADMIN_LIST_SERVERS,
        'url'        => 'index.php?p=admin&c=servers&section=list',
    ],
    [
        'slug'       => 'add',
        'name'       => 'Add new server',
        'permission' => ADMIN_OWNER | ADMIN_ADD_SERVER,
        'url'        => 'index.php?p=admin&c=servers&section=add',
    ],
];

// Default to the first accessible section so the page doesn't render
// a blank body when the URL omits ?section= or carries an unknown
// value. List > Add for users who have both, falling back to whichever
// permission they hold otherwise.
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

new AdminTabs($sections, $userbank, $theme, $section);

if ($section === 'add') {
    // List mods (drives the mod <select> in the add form).
    $modlist = $GLOBALS['PDO']->query("SELECT mid, name FROM `:prefix_mods` WHERE `mid` > 0 AND `enabled` = 1 ORDER BY name ASC")->resultset();
    // List groups (drives the server-group <select>).
    $grouplist = $GLOBALS['PDO']->query("SELECT gid, name FROM `:prefix_groups` WHERE type = 3 ORDER BY name ASC")->resultset();

    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminServersAddView(
        permission_addserver: $canAdd,
        edit_server: false,
        ip: '',
        port: '',
        rcon: '',
        modid: '',
        modlist: $modlist,
        grouplist: $grouplist,
        submit_text: 'Add Server',
    ));
    return;
}

// `mod_name` (mod display name) was added in #1123 B15 so the card
// grid can render the mod label without a second per-card query.
$servers = $GLOBALS['PDO']->query("SELECT srv.ip ip, srv.port port, srv.sid sid, mo.icon icon, mo.name mod_name, srv.enabled enabled FROM `:prefix_servers` AS srv
   LEFT JOIN `:prefix_mods` AS mo ON mo.mid = srv.modid
   ORDER BY modid, sid")->resultset();
$server_count = $GLOBALS['PDO']->query("SELECT COUNT(sid) AS cnt FROM `:prefix_servers`")->single();

$server_access = [];
if ($userbank->HasAccess(SM_RCON . SM_ROOT)) {
    // Get all servers the admin has access to
    $GLOBALS['PDO']->query("SELECT `server_id`, `srv_group_id` FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
    $GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
    $servers2 = $GLOBALS['PDO']->resultset();
    foreach ($servers2 as $server) {
        $server_access[] = $server['server_id'];
        if ($server['srv_group_id'] > 0) {
            $GLOBALS['PDO']->query("SELECT `server_id` FROM `:prefix_servers_groups` WHERE group_id = :gid");
            $GLOBALS['PDO']->bind(':gid', (int) $server['srv_group_id']);
            $servers_in_group = $GLOBALS['PDO']->resultset();
            foreach ($servers_in_group as $servig) {
                $server_access[] = $servig['server_id'];
            }
        }
    }
}

// Only show the RCON link for servers he's access to
foreach ($servers as &$server) {
    if (in_array($server['sid'], $server_access)) {
        $server['rcon_access'] = true;
    } else {
        $server['rcon_access'] = false;
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminServersListView(
    permission_list: $canList,
    permission_editserver: $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_SERVERS),
    pemission_delserver: $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_SERVERS),
    permission_addserver: $canAdd,
    server_count: (int) $server_count['cnt'],
    server_list: $servers,
));
