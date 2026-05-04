<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Home dashboard page — binds to `page_dashboard.tpl`, which in turn
 * `{include file='page_servers.tpl'}`s the server list view. Because the
 * dashboard renders the server list without exiting and re-entering the
 * Smarty context, every variable used by `page_servers.tpl` also has to be
 * declared here (the transitive include is resolved by SmartyTemplateRule).
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
        public readonly array $players_commed,
        public readonly int $total_comms,
        public readonly bool $access_bans,
        public readonly array $server_list,
        public readonly bool $IN_SERVERS_PAGE,
        public readonly int $opened_server,
    ) {
    }
}
