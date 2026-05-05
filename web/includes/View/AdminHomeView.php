<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Admin landing page (`?p=admin` with no `c=…`) — binds to
 * `page_admin.tpl`. 8-card landing grid. The route enforces
 * `CheckAdminAccess(ALL_WEB)` in {@see route()}
 * (web/includes/page-builder.php), so the only people who hit this
 * view are admins holding *some* web flag; this View's job is then
 * to gate the per-area cards so an admin only sees cards leading to
 * areas they actually have rights inside.
 *
 * ### Card list
 *
 * The set of cards (and therefore the list of `can_<area>` props) is
 * the canonical 8-card admin grid:
 *   - admins, groups, servers, bans, mods, overrides, settings, audit
 *
 * Each `can_<area>` is a composite `OR` over the underlying `can_<flag>`
 * keys produced by {@see Perms::for()}, mirroring the masks the router
 * uses in `page-builder.php` so a card visible on the landing implies
 * the router will let the user through.
 *
 * Comms intentionally folds into the sidebar nav (`admin/comms`) and
 * does not surface a separate Comms card on the landing.
 *
 * `access_*`, `dev`, `demosize`, `total_*`, and `archived_*` are
 * preserved on this View (and referenced by an unreachable
 * `{if false}` parity block in the template) so any third-party theme
 * that forked the pre-v2.0.0 default keeps rendering off the same
 * variable surface.
 */
final class AdminHomeView extends View
{
    public const TEMPLATE = 'page_admin.tpl';

    public function __construct(
        // composite per-area gates for the landing card grid.
        public readonly bool $can_admins,
        public readonly bool $can_groups,
        public readonly bool $can_servers,
        public readonly bool $can_bans,
        public readonly bool $can_mods,
        public readonly bool $can_overrides,
        public readonly bool $can_settings,
        public readonly bool $can_audit,
        // legacy compatibility surface — preserved for third-party themes
        // that forked the pre-v2.0.0 default; the shipped template
        // references them only inside an unreachable `{if false}` block
        // so the SmartyTemplateRule parity check stays green.
        public readonly bool $access_admins,
        public readonly bool $access_servers,
        public readonly bool $access_bans,
        public readonly bool $access_groups,
        public readonly bool $access_settings,
        public readonly bool $access_mods,
        public readonly bool $dev,
        public readonly string $demosize,
        public readonly int $total_admins,
        public readonly int $total_bans,
        public readonly int $total_comms,
        public readonly int $total_blocks,
        public readonly int $total_servers,
        public readonly int $total_protests,
        public readonly int $archived_protests,
        public readonly int $total_submissions,
        public readonly int $archived_submissions,
    ) {
    }
}
