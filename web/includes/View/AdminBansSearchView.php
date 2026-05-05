<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Advanced search" panel for the public ban list — binds to
 * `box_admin_bans_search.tpl`.
 *
 * Two themes consume this view side-by-side during the v2.0.0 rollout
 * (#1123): the legacy `web/themes/default/box_admin_bans_search.tpl`
 * (table layout + xajax-style live polling driven by sourcebans.js'
 * `LoadServerHost` helper) and the new
 * `web/themes/sbpp2026/box_admin_bans_search.tpl` (card layout that
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
 * The form submits as a plain `GET` to
 * `?p=banlist&advSearch=…&advType=…`, which is the wire format
 * `web/pages/page.banlist.php` already parses. No CSRF field — search
 * is read-only.
 */
final class AdminBansSearchView extends View
{
    public const TEMPLATE = 'box_admin_bans_search.tpl';

    /**
     * @param list<array<string,mixed>> $admin_list  Each entry is a row
     *     from `:prefix_admins` with at minimum `aid` and `user`. Only
     *     consumed when `$hideadminname` is false.
     * @param list<array{sid: int, ip: string, port: int}> $server_list
     *     Per-server entries for the `Server` dropdown.
     * @param string $server_script Server-built `<script>` blob that
     *     fires one `sb.api.call(Actions.ServersHostPlayers, {sid})`
     *     per option to populate the `<option id="ssSID">` text — the
     *     vanilla replacement for the legacy `LoadServerHost('SID', …)`
     *     calls (sourcebans.js helper, deleted at #1123 D1). Consumed
     *     by the legacy default-theme template only; the new sbpp2026
     *     template inlines its own per-option script and carries an
     *     `{if false}…{/if}` parity reference so SmartyTemplateRule
     *     stays green.
     * @param bool $hideplayerips
     *     `Config::getBool('banlist.hideplayerips')` for non-admins.
     *     Mirrors the legacy default-theme gate on the "Search by IP" row.
     * @param bool $hideadminname
     *     `Config::getBool('banlist.hideadminname')` for non-admins.
     *     Mirrors the legacy gate on the "Search by admin" row.
     * @param bool $is_admin
     *     `$userbank->is_admin()`. Gates the "Search by comment" row
     *     (admin notes are not surfaced to the public).
     */
    public function __construct(
        public readonly array $admin_list,
        public readonly array $server_list,
        public readonly string $server_script,
        public readonly bool $hideplayerips,
        public readonly bool $hideadminname,
        public readonly bool $is_admin,
    ) {
    }
}
