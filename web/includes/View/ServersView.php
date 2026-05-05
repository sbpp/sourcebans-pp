<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Server list page — binds to `page_servers.tpl`. Card grid that
 * progressively populates each card via inline
 * `sb.api.call(Actions.ServersHostPlayers, …)`.
 *
 * The dashboard page (`page.home.php`) `require`s `page.servers.php`
 * to reuse the server list data and reads the populated `$serversView`'s
 * public properties when constructing its own {@see HomeDashboardView}.
 * The dashboard renders its own server tile inline and does not
 * `{include}` `page_servers.tpl`, so adding a new property here only
 * needs to be wired into HomeDashboardView if the dashboard wants it.
 *
 * `$server_list` row shape (set by `page.servers.php`):
 *   - sid    int     Stable server id (DB primary key).
 *   - ip     string  Hostname or numeric IP, as configured.
 *   - port   int     Game port.
 *   - dns    string  Resolved IPv4 (gethostbyname($ip)) for the
 *                    `steam://connect/{dns}:{port}` URL.
 *   - icon   string  Filename in `web/images/games/` for the mod icon.
 *   - mod    string  Mod display name from `:prefix_mods.name`. Empty
 *                    string when the server's modid doesn't resolve
 *                    (deleted/renamed mod row).
 *   - index  int     0-based offset into the unsorted result set used
 *                    by the dashboard `?p=servers&s={index}` deep links
 *                    (matches `opened_server` semantics).
 *   - evOnClick string Inline JS navigating to ?p=servers&s={index};
 *                    only set when `IN_SERVERS_PAGE` is false (i.e.
 *                    the dashboard is rendering this row). Preserved
 *                    for any third-party theme that forked the
 *                    pre-v2.0.0 default and expects the legacy hook.
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
