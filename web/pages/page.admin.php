<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

global $userbank, $theme;

/*
 * Admin landing page (`?p=admin` with no `c=…`). The route gates on
 * `CheckAdminAccess(ALL_WEB)` upstream in
 * `web/includes/page-builder.php`, so anyone reaching this handler
 * already holds *some* web flag.
 *
 * The job here is to compute one composite `$can_<area>` boolean per
 * landing-grid card from `Sbpp\View\Perms::for($userbank)`. Each
 * composite OR's the per-flag booleans the legacy router gates on for
 * that sub-route — a card visible on the landing implies the router
 * will let the user through. Owner bypass is already baked into every
 * per-flag bool by `Perms::for()`, so a user holding `ADMIN_OWNER`
 * lights up every card without an extra `|| $can_owner` here.
 *
 * Pre-#5 this handler also computed a 9-subquery composite COUNT over
 * `:prefix_banlog` etc. plus a recursive `getDirSize(SB_DEMOS)` walk
 * for the legacy v1.x stat-counts row. The v2.0 8-card grid never
 * displayed those values, and the compute was gated behind a
 * `Sbpp\Theme::wantsLegacyAdminCounts()` opt-in for theme forks that
 * still rendered them. The v2.0 rewrite deletes both halves: the gate, the
 * `access_*` / `total_*` / `archived_*` / `demosize` properties on
 * `AdminHomeView`, and the matching `{if false}` parity block in
 * `page_admin.tpl` that kept SmartyTemplateRule green. Theme forks
 * still on the legacy stat-counts surface need to migrate to the
 * 8-card grid (the canonical v2.0 admin landing) or carry the COUNT
 * + getDirSize compute themselves.
 */
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
));
