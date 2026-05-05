<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Advanced search" panel on the admin/admins list — binds to
 * `box_admin_admins_search.tpl`.
 *
 * Two themes consume this view side-by-side during the v2.0.0 rollout
 * (#1123): the legacy `web/themes/default/box_admin_admins_search.tpl`
 * (table layout + xajax-style live polling driven by sourcebans.js'
 * `LoadServerHost` helper) and the new
 * `web/themes/sbpp2026/box_admin_admins_search.tpl` (card layout that
 * dispatches one `sb.api.call(Actions.ServersHostPlayers, …)` per
 * server option directly from inline `{literal}<script>…{/literal}`).
 * Both legs of the dual-theme PHPStan matrix scan this view, so it
 * declares the union of variables both templates reference; the new
 * template carries an `{if false}…{/if}` parity block for the legacy-
 * only `$server_script` so SmartyTemplateRule's "unused property"
 * check stays green for the sbpp2026 leg without bespoke baseline
 * entries. D1's hard cutover deletes the legacy template + the
 * legacy-only `$server_script` property here in lockstep.
 *
 * Permission name shape note: the boolean is `$can_editadmin` (no
 * underscore between "edit" and "admin", and singular) to match the
 * legacy template's literal `{if $can_editadmin}` reference. The new
 * sbpp2026 template uses the same name. We deviate from the
 * `Perms::for()` `can_<lowercase ADMIN_*>` convention here only to
 * keep the dual-theme matrix happy — see {@see AdminAdminsListView}
 * for the canonical naming on Views that don't need to share a
 * template name across themes.
 *
 * Rendered inline from `web/pages/admin.admins.search.php`, which is
 * pulled in by `page_admin_admins_list.tpl` via the
 * `{load_template file="admin.admins.search"}` Smarty plugin.
 *
 * The form submits as a plain `GET` to
 * `?p=admin&c=admins&advSearch=…&advType=…`, which is the wire format
 * `web/pages/admin.admins.php` already parses. No CSRF field — search
 * is read-only.
 */
final class AdminAdminsSearchView extends View
{
    public const TEMPLATE = 'box_admin_admins_search.tpl';

    /**
     * @param list<array{sid: int, ip: string, port: int}> $server_list
     *     Per-server entries for the "Search by server" dropdown.
     * @param string $server_script Server-built `<script>` blob that
     *     fires one `sb.api.call(Actions.ServersHostPlayers, {sid})`
     *     per option to populate the `<option id="ssSID">` text — the
     *     vanilla replacement for the legacy `LoadServerHost('SID', …)`
     *     calls (sourcebans.js helper, deleted at #1123 D1). Consumed
     *     by the legacy default-theme template only; the new sbpp2026
     *     template inlines its own per-tile script and carries an
     *     `{if false}…{/if}` parity reference so SmartyTemplateRule
     *     stays green.
     * @param list<array{gid: int|string, name: string}>  $webgroup_list
     *     `:prefix_groups` rows where `type=1` (web groups).
     * @param list<array{name: string}>                   $srvadmgroup_list
     *     `:prefix_srvgroups` rows (SourceMod admin groups).
     * @param list<array{gid: int|string, name: string}>  $srvgroup_list
     *     `:prefix_groups` rows where `type=3` (server groups).
     * @param list<array{name: string, flag: string}>     $admwebflag_list
     *     Web permission flags as {label, ADMIN_* constant name} pairs
     *     for the "Search by web permission" multi-select. Submitted as
     *     a comma-joined `ADMIN_*` constant-name string the consumer
     *     handler resolves with `constant()`.
     * @param list<array{name: string, flag: string}>     $admsrvflag_list
     *     SourceMod permission flags as {label, SM_* constant name}
     *     pairs for the "Search by server permission" multi-select.
     */
    public function __construct(
        public readonly bool $can_editadmin,
        public readonly array $server_list,
        public readonly string $server_script,
        public readonly array $webgroup_list,
        public readonly array $srvadmgroup_list,
        public readonly array $srvgroup_list,
        public readonly array $admwebflag_list,
        public readonly array $admsrvflag_list,
    ) {
    }
}
