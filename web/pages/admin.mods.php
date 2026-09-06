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
 * Section routing (#1239 — Pattern A). Read `?section=list|add`,
 * render one View per request. Section chrome lives in the main
 * sidebar accordion (`AdminNavCatalog` + `core/navbar.tpl`, #1490).
 */
$canList = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListMods));
$canAdd  = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddMods));

$sections = \Sbpp\View\AdminNavCatalog::sectionsFor('mods');
$validSlugs = array_column($sections, 'slug');
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

if ($section === 'add') {
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsAddView(
        permission_add: $canAdd,
    ));
    return;
}

// mid=0 is the reserved Web pseudo-mod, not a configurable game mod.
$mod_list  = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_mods` WHERE mid > 0 ORDER BY name ASC")->resultset();
$mod_count = (int) $GLOBALS['PDO']->query("SELECT COUNT(mid) AS cnt FROM `:prefix_mods` WHERE mid > 0")->single()['cnt'];

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsListView(
    permission_listmods:   $canList,
    permission_addmods:    $canAdd,
    permission_editmods:   $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditMods)),
    permission_deletemods: $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteMods)),
    mod_count:             $mod_count,
    mod_list:              $mod_list,
));

