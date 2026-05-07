<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2026 by SourceBans++ Dev Team

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

// #1270: the legacy v1.x default-theme rendered the stat-counts row
// + a getDirSize(SB_DEMOS) "demos on disk" badge on the admin
// landing. The v2.0.0 redesign (#1146) replaced both with the
// 8-card grid that doesn't reference any of these values; the
// matching $demosize / $total_* / $archived_* fields on
// AdminHomeView are kept assignable so theme forks of the
// pre-v2.0.0 default keep rendering off the same DTO surface, and
// the `{if false}` parity block in page_admin.tpl keeps
// SmartyTemplateRule's "every assigned property is referenced
// somewhere in the template tree" cross-check green.
//
// Computing the values for every request — a 9-subquery composite
// COUNT over `:prefix_banlog` (worst case on production: every
// block ever logged) plus a recursive glob() walk over
// `web/demos/` — was the dominant cost of this page handler on
// installs with months of demo retention. Sbpp\Theme gates it
// behind `wantsLegacyAdminCounts()`: third-party forks opt back in
// by defining `theme_legacy_admin_counts` in their `theme.conf.php`;
// the shipped default doesn't, so the counts are placeholders and
// the work is skipped. D1's hard cutover drops both the parity
// block and the legacy fields together; this gate goes with them.
if (\Sbpp\Theme::wantsLegacyAdminCounts()) {
    \Sbpp\Theme::recordLegacyComputePass();

    $counts = $GLOBALS['PDO']->query("SELECT
                                     (SELECT COUNT(bid) FROM `:prefix_banlog`)                       AS blocks,
                                     (SELECT COUNT(bid) FROM `:prefix_bans`)                         AS bans,
                                     (SELECT COUNT(bid) FROM `:prefix_comms`)                        AS comms,
                                     (SELECT COUNT(aid) FROM `:prefix_admins`  WHERE aid > 0)        AS admins,
                                     (SELECT COUNT(subid) FROM `:prefix_submissions` WHERE archiv = '0') AS subs,
                                     (SELECT COUNT(subid) FROM `:prefix_submissions` WHERE archiv > 0)   AS archiv_subs,
                                     (SELECT COUNT(pid) FROM `:prefix_protests`    WHERE archiv = '0') AS protests,
                                     (SELECT COUNT(pid) FROM `:prefix_protests`    WHERE archiv > 0)   AS archiv_protests,
                                     (SELECT COUNT(sid) FROM `:prefix_servers`)                      AS servers")->single();
    $demosize = getDirSize(SB_DEMOS);
} else {
    // Placeholders that satisfy AdminHomeView's typed properties.
    // The default template references these fields only inside an
    // unreachable `{if false}` parity block, so the values are
    // never visible to the user; theme forks that opt back in (see
    // above) get the real numbers.
    $counts = [
        'blocks' => 0, 'bans'             => 0, 'comms'    => 0, 'admins'   => 0,
        'subs'   => 0, 'archiv_subs'      => 0, 'protests' => 0, 'archiv_protests' => 0,
        'servers' => 0,
    ];
    $demosize = '0 B';
}

// AdminHomeView precomputes one composite `$can_<area>` boolean per
// landing-grid card from Sbpp\View\Perms::for($userbank). Each composite
// OR's the per-flag booleans the legacy router gates on for that
// sub-route in web/includes/page-builder.php — a card visible on the
// landing implies the router will let the user through. Owner bypass is
// already baked into every per-flag bool by Perms::for(), so a user
// holding ADMIN_OWNER lights up every card without an extra `||
// $can_owner` here.
$perms = \Sbpp\View\Perms::for($userbank);
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminHomeView(
    can_admins:    $perms['can_list_admins']  || $perms['can_add_admins']  || $perms['can_edit_admins']  || $perms['can_delete_admins'],
    can_groups:    $perms['can_list_groups']  || $perms['can_add_group']   || $perms['can_edit_groups']  || $perms['can_delete_groups'],
    can_servers:   $perms['can_list_servers'] || $perms['can_add_server']  || $perms['can_edit_servers'] || $perms['can_delete_servers'],
    can_bans:      $perms['can_add_ban'] || $perms['can_edit_own_bans'] || $perms['can_edit_group_bans'] || $perms['can_edit_all_bans'] || $perms['can_ban_protests'] || $perms['can_ban_submissions'],
    can_mods:      $perms['can_list_mods']    || $perms['can_add_mods']    || $perms['can_edit_mods']    || $perms['can_delete_mods'],
    can_overrides: $perms['can_add_admins'],
    can_settings:  $perms['can_web_settings'],
    can_audit:     $perms['can_owner'],
    // Legacy default-theme bindings; see View docblock + D1 cutover note.
    access_admins:        $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS  | ADMIN_ADD_ADMINS  | ADMIN_EDIT_ADMINS  | ADMIN_DELETE_ADMINS),
    access_servers:       $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_SERVERS | ADMIN_ADD_SERVER  | ADMIN_EDIT_SERVERS | ADMIN_DELETE_SERVERS),
    access_bans:          $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS),
    access_groups:        $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_GROUPS  | ADMIN_ADD_GROUP   | ADMIN_EDIT_GROUPS  | ADMIN_DELETE_GROUPS),
    access_settings:      $userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS),
    access_mods:          $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_MODS    | ADMIN_ADD_MODS    | ADMIN_EDIT_MODS    | ADMIN_DELETE_MODS),
    demosize:             $demosize,
    total_admins:         (int) $counts['admins'],
    total_bans:           (int) $counts['bans'],
    total_comms:          (int) $counts['comms'],
    total_blocks:         (int) $counts['blocks'],
    total_servers:        (int) $counts['servers'],
    total_protests:       (int) $counts['protests'],
    archived_protests:    (int) $counts['archiv_protests'],
    total_submissions:    (int) $counts['subs'],
    archived_submissions: (int) $counts['archiv_subs'],
));
