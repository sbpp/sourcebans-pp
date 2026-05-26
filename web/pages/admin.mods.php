<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

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
 * vertical sidebar (`core/admin_sidebar.tpl`), so the page handler
 * just builds `$sections` (with an `icon` per row for the Lucide
 * glyph) and lets `AdminTabs.php` open the shell + render the
 * <aside> + open the content column.
 */
$canList = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListMods));
$canAdd  = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddMods));

/** @var list<array{slug: string, name: string, permission: int, url: string, icon: string}> $sections */
$sections = [
    [
        'slug'       => 'list',
        'name'       => 'List MODs',
        'permission' => ADMIN_OWNER | ADMIN_LIST_MODS,
        'url'        => 'index.php?p=admin&c=mods&section=list',
        'icon'       => 'puzzle',
    ],
    [
        'slug'       => 'add',
        'name'       => 'Add new MOD',
        'permission' => ADMIN_OWNER | ADMIN_ADD_MODS,
        'url'        => 'index.php?p=admin&c=mods&section=add',
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
// content column. Closing tags live at the bottom of this file.
new AdminTabs($sections, $userbank, $theme, $section, 'MOD sections');

if ($section === 'add') {
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsAddView(
        permission_add: $canAdd,
    ));
    echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell — opened by new AdminTabs(...) above -->';
    return;
}

$mod_list  = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_mods` WHERE mid > 0 ORDER BY name ASC")->resultset();
$mod_count = (int) $GLOBALS['PDO']->query("SELECT COUNT(mid) AS cnt FROM `:prefix_mods`")->single()['cnt'];

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsListView(
    permission_listmods:   $canList,
    permission_editmods:   $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditMods)),
    permission_deletemods: $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteMods)),
    mod_count:             $mod_count,
    mod_list:              $mod_list,
));

echo '</div></div><!-- /.admin-sidebar-content + /.admin-sidebar-shell — opened by new AdminTabs(...) above -->';
