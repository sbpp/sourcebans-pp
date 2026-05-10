<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Home dashboard page — binds to `page_dashboard.tpl`. Renders inline
 * server tiles + a stats card grid (`active_bans`, `total_servers`).
 *
 * `access_bans` is consumed by the shipped `page_dashboard.tpl` to
 * gate the "Open the servers page to manage players in real time."
 * admin hint above the server tiles.
 *
 * `IN_SERVERS_PAGE` is preserved as a parity reference (the dashboard
 * template carries an unreachable `{if $IN_SERVERS_PAGE}` block that
 * keeps SmartyTemplateRule's "unused property" check green); always
 * `false` on the dashboard. The pre-#1306 rationale ("the transitively
 * included `page_servers.tpl` keeps type-checking") no longer applies
 * because #1306 dropped `IN_SERVERS_PAGE` from `page_servers.tpl`
 * along with the right-click hint it gated.
 *
 * `opened_server` is unused by the shipped dashboard template and
 * sits in the PHPStan baseline as such; preserved for any third-party
 * theme that forked the pre-v2.0.0 default and expects the legacy hook.
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
        // #1207 PUB-5: gate per-card empty-state CTAs in
        // page_dashboard.tpl. `can_add_ban` is reused for the comm
        // blocks card too — both flow through the same admin
        // surface (?p=admin&c=bans / ?p=admin&c=comms) which is
        // gated on ADMIN_ADD_BAN. Splatted from
        // `Perms::for($userbank)` in `web/pages/page.home.php`.
        public readonly bool $can_add_ban,
        public readonly bool $can_add_server,
    ) {
    }
}
