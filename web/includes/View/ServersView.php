<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Server list page — binds to `page_servers.tpl`, which is also pulled in
 * transitively by the dashboard (see {@see HomeDashboardView}). This view
 * only covers the standalone-display case from `page.servers.php`.
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
