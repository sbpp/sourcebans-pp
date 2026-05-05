<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Server list page — binds to `page_servers.tpl`.
 *
 * Two themes consume this view side-by-side during the v2.0.0 rollout
 * (#1123): the legacy `web/themes/default/page_servers.tpl` (table +
 * accordion + xajax-style live polling helpers from sourcebans.js) and
 * the new `web/themes/sbpp2026/page_servers.tpl` (card grid that
 * progressively populates each card via inline `sb.api.call(
 * Actions.ServersHostPlayers, …)`). Both legs of the dual-theme PHPStan
 * matrix scan this view; the SmartyTemplateRule baseline carries any
 * legacy-only properties so the matrix stays clean.
 *
 * The dashboard page (`page.home.php`) `require`s `page.servers.php` to
 * reuse the server list data and reads the populated $serversView's
 * public properties when constructing its own {@see HomeDashboardView}.
 * Adding a new property here means HomeDashboardView either has to
 * forward it (legacy default theme transitively `{include}`s
 * page_servers.tpl from page_dashboard.tpl) or ignore it (sbpp2026's
 * dashboard renders its own server tile inline and never includes
 * page_servers.tpl). Removing or renaming an existing property would
 * break either the dashboard or the legacy template — only ADD here.
 *
 * `$server_list` row shape (set by `page.servers.php`):
 *   - sid    int     Stable server id (DB primary key).
 *   - ip     string  Hostname or numeric IP, as configured.
 *   - port   int     Game port.
 *   - dns    string  Resolved IPv4 (gethostbyname($ip)) for the
 *                    `steam://connect/{dns}:{port}` URL — the legacy
 *                    template uses it; the sbpp2026 template uses it for
 *                    the same Connect button.
 *   - icon   string  Filename in `web/images/games/` for the mod icon.
 *   - mod    string  Mod display name from `:prefix_mods.name`. Empty
 *                    string when the server's modid doesn't resolve
 *                    (deleted/renamed mod row). Added in #1123 B5 for
 *                    the sbpp2026 card label; the legacy template
 *                    ignores extra row keys.
 *   - index  int     0-based offset into the unsorted result set used
 *                    by the dashboard `?p=servers&s={index}` deep links
 *                    (matches `opened_server` semantics).
 *   - evOnClick string Inline JS navigating to ?p=servers&s={index};
 *                    only set when `IN_SERVERS_PAGE` is false (i.e.
 *                    the dashboard is rendering this row). Legacy-only.
 */
final class ServersView extends View
{
    public const TEMPLATE = 'page_servers.tpl';

    /**
     * @param list<array<string,mixed>> $server_list
     */
    public function __construct(
        public readonly bool $access_bans,
        public readonly array $server_list,
        public readonly bool $IN_SERVERS_PAGE,
        public readonly int $opened_server,
    ) {
    }
}
