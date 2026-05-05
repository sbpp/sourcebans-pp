<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Advanced search" panel for the public ban list — binds to
 * `box_admin_bans_search.tpl`. Card layout that dispatches one
 * `sb.api.call(Actions.ServersHostPlayers, …)` per server option
 * directly from inline `{literal}<script>…{/literal}` to populate
 * each `<option id="ssSID">Loading…</option>`.
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
     *     per option to populate the `<option id="ssSID">` text. The
     *     template inlines its own per-option script and carries an
     *     `{if false}…{/if}` parity reference so SmartyTemplateRule's
     *     "every declared property is referenced" check stays green
     *     while this server-built blob is still available for any
     *     third-party theme that copies the legacy emit pattern.
     * @param bool $hideplayerips
     *     `Config::getBool('banlist.hideplayerips')` for non-admins.
     *     Hides the "Search by IP" row.
     * @param bool $hideadminname
     *     `Config::getBool('banlist.hideadminname')` for non-admins.
     *     Hides the "Search by admin" row.
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
