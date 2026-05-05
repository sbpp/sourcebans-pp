<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Home dashboard page — binds to `page_dashboard.tpl`.
 *
 * Two themes consume this view side-by-side during the v2.0.0 rollout
 * (#1123): the legacy `web/themes/default/page_dashboard.tpl` (which
 * `{include file='page_servers.tpl'}`s the server list) and the new
 * `web/themes/sbpp2026/page_dashboard.tpl` (which renders inline server
 * tiles + a stats card grid). Because `SmartyTemplateRule` enforces
 * one-to-one parity between properties and template references for the
 * theme it scans, this View declares the *union* of variables both
 * templates need:
 *
 *   - Legacy-only fields (`access_bans`, `IN_SERVERS_PAGE`,
 *     `opened_server`) stay so the legacy template + transitively
 *     included `page_servers.tpl` continue to type-check on the
 *     `default` PHPStan leg.
 *   - New stat-grid fields (`active_bans`, `total_servers`) are
 *     consumed by the sbpp2026 template's stat cards. They have no
 *     legacy use; the legacy template ignores extra Smarty assignments.
 *
 * D1's hard cutover deletes the legacy template and renames the
 * sbpp2026 directory into `default/`; at that point this View loses the
 * legacy-only fields in a follow-up cleanup PR (tracked by the D2 docs
 * sweep).
 */
final class HomeDashboardView extends View
{
    public const TEMPLATE = 'page_dashboard.tpl';

    /**
     * @param list<array<string,mixed>> $players_blocked
     * @param list<array<string,mixed>> $players_banned
     * @param list<array<string,mixed>> $players_commed
     * @param list<array<string,mixed>> $server_list
     */
    public function __construct(
        public readonly string $dashboard_title,
        public readonly string $dashboard_text,
        public readonly bool $dashboard_lognopopup,
        public readonly array $players_blocked,
        public readonly int $total_blocked,
        public readonly array $players_banned,
        public readonly int $total_bans,
        public readonly int $active_bans,
        public readonly array $players_commed,
        public readonly int $total_comms,
        public readonly bool $access_bans,
        public readonly array $server_list,
        public readonly int $total_servers,
        public readonly bool $IN_SERVERS_PAGE,
        public readonly int $opened_server,
    ) {
    }
}
