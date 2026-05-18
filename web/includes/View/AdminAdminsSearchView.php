<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Advanced search" panel on the admin/admins list — binds to
 * `box_admin_admins_search.tpl`. Card layout that dispatches one
 * `sb.api.call(Actions.ServersHostPlayers, …)` per server option
 * directly from inline `{literal}<script>…{/literal}` to populate
 * each `<option id="ssSID">Loading…</option>`.
 *
 * Permission name shape note: the boolean is `$can_editadmin` (no
 * underscore between "edit" and "admin", and singular) to match the
 * template's literal `{if $can_editadmin}` reference. We deviate from
 * the `Perms::for()` `can_<lowercase ADMIN_*>` convention to preserve
 * the historical name; see {@see AdminAdminsListView} for the canonical
 * naming on newer Views.
 *
 * Rendered inline from `web/pages/admin.admins.search.php`, which is
 * pulled in by `page_admin_admins_list.tpl` via the
 * `{load_template file="admin.admins.search"}` Smarty plugin.
 *
 * The form submits as a plain `GET` to `?p=admin&c=admins` with one
 * parameter per populated filter (`name`, `name_match`, `steamid`,
 * `steam_match`, `admemail`, `admemail_match`, `webgroup`,
 * `srvadmgroup`, `srvgroup`, `admwebflag[]`, `admsrvflag[]`,
 * `server`). admin.admins.php AND-combines every non-empty filter —
 * see #1207 ADM-4. No CSRF field — search is read-only.
 *
 * `name_match` / `admemail_match` were added in #1231 so Login and
 * E-mail can be flipped between exact / partial mode the way SteamID
 * already could; defaults are partial ('1') to preserve pre-#1231
 * substring behaviour for legacy URLs.
 *
 * `$active_filter_*` mirror the corresponding $_GET keys so the
 * template can pre-fill the form without splattering
 * `$smarty.get.X|default:''` everywhere; the page handler
 * (admin.admins.search.php) is the only place that knows whether a
 * given $_GET shape came from a modern submit, a legacy
 * `advType=…&advSearch=…` URL, or nothing at all.
 *
 * #1303 — collapsible disclosure
 * ------------------------------
 * The form is wrapped in a `<details class="card filters-details">`
 * default-collapsed disclosure so the unfiltered admin list paints
 * above the fold. `$has_active_filters` (derived from the nine
 * `active_filter_*` value slots — match-mode toggles don't count
 * because they always carry a default) drives the `[open]` attribute,
 * so any post-submit page paints with the form expanded. The chrome
 * mirrors `core/admin_sidebar.tpl`'s mobile `<details open>` pattern
 * (chevron + label + `prefers-reduced-motion: reduce` override). The
 * count badge ("Filters · N active") rides `$active_filter_count`.
 */
final class AdminAdminsSearchView extends View
{
    public const TEMPLATE = 'box_admin_admins_search.tpl';

    /**
     * @param list<array{sid: int, ip: string, port: int}> $server_list
     *     Per-server entries for the "Search by server" dropdown.
     * @param string $server_script Server-built `<script>` blob that
     *     fires one `sb.api.call(Actions.ServersHostPlayers, {sid})`
     *     per option to populate the `<option id="ssSID">` text. The
     *     template inlines its own per-tile script and carries an
     *     `{if false}…{/if}` parity reference so SmartyTemplateRule's
     *     "every declared property is referenced" check stays green
     *     while this server-built blob is still available for any
     *     third-party theme that copies the legacy emit pattern.
     * @param list<array{gid: int|string, name: string}>  $webgroup_list
     *     `:prefix_groups` rows where `type=1` (web groups).
     * @param list<array{name: string}>                   $srvadmgroup_list
     *     `:prefix_srvgroups` rows (SourceMod admin groups).
     * @param list<array{gid: int|string, name: string}>  $srvgroup_list
     *     `:prefix_groups` rows where `type=3` (server groups).
     * @param list<array{name: string, flag: string}>     $admwebflag_list
     *     Web permission flags as {label, ADMIN_* constant name} pairs
     *     for the "Search by web permission" multi-select. Submitted as
     *     repeated `admwebflag[]=ADMIN_*` parameters; the consumer
     *     handler resolves each via `constant()`.
     * @param list<array{name: string, flag: string}>     $admsrvflag_list
     *     SourceMod permission flags as {label, SM_* constant name}
     *     pairs for the "Search by server permission" multi-select.
     * @param list<string> $active_filter_admwebflag Pre-filled
     *     `admwebflag[]` values; the template `in_array`s against this
     *     to mark the matching `<option selected>` rows.
     * @param list<string> $active_filter_admsrvflag Pre-filled
     *     `admsrvflag[]` values.
     * @param int $active_filter_count Number of non-empty filter
     *     value slots — drives the `<summary>` count badge ("Filters
     *     · N active") and `$has_active_filters`. Match-mode toggles
     *     (`name_match` / `steam_match` / `admemail_match`) are NOT
     *     counted: they always carry a default ('0' or '1') and only
     *     refine the matching filter, they don't filter on their own.
     * @param bool $has_active_filters Convenience boolean derived from
     *     `$active_filter_count > 0`. The template uses it to decide
     *     whether the disclosure paints `<details open>` (post-submit
     *     paint) vs default-collapsed (first-paint).
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
        public readonly string $active_filter_name = '',
        public readonly string $active_filter_name_match = '1',
        public readonly string $active_filter_steamid = '',
        public readonly string $active_filter_steam_match = '0',
        public readonly string $active_filter_admemail = '',
        public readonly string $active_filter_admemail_match = '1',
        public readonly string $active_filter_webgroup = '',
        public readonly string $active_filter_srvadmgroup = '',
        public readonly string $active_filter_srvgroup = '',
        public readonly string $active_filter_server = '',
        public readonly array $active_filter_admwebflag = [],
        public readonly array $active_filter_admsrvflag = [],
        public readonly int $active_filter_count = 0,
        public readonly bool $has_active_filters = false,
    ) {
    }
}
