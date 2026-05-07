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

/*
 * Section routing (#1239 — Pattern A, settings-page shape).
 *
 * Mirrors `admin.servers.php`: read `?section=list|add`, render one
 * View per request, the AdminTabs strip is now anchor links instead
 * of the broken `<button onclick="openTab(...)">` chrome (sourcebans.js
 * was dropped at #1123 D1 and the click handler with it).
 */
$canList = $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_MODS);
$canAdd  = $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_MODS);

/** @var list<array{slug: string, name: string, permission: int, url: string}> $sections */
$sections = [
    [
        'slug'       => 'list',
        'name'       => 'List MODs',
        'permission' => ADMIN_OWNER | ADMIN_LIST_MODS,
        'url'        => 'index.php?p=admin&c=mods&section=list',
    ],
    [
        'slug'       => 'add',
        'name'       => 'Add new MOD',
        'permission' => ADMIN_OWNER | ADMIN_ADD_MODS,
        'url'        => 'index.php?p=admin&c=mods&section=add',
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

new AdminTabs($sections, $userbank, $theme, $section);

if ($section === 'add') {
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsAddView(
        permission_add: $canAdd,
    ));
    return;
}

$mod_list  = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_mods` WHERE mid > 0 ORDER BY name ASC")->resultset();
$mod_count = (int) $GLOBALS['PDO']->query("SELECT COUNT(mid) AS cnt FROM `:prefix_mods`")->single()['cnt'];

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminModsListView(
    permission_listmods:   $canList,
    permission_editmods:   $userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_MODS),
    permission_deletemods: $userbank->HasAccess(ADMIN_OWNER | ADMIN_DELETE_MODS),
    mod_count:             $mod_count,
    mod_list:              $mod_list,
));
