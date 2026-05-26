<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * Admin landing page (`?p=admin` with no `c=…`) — binds to
 * `page_admin.tpl`. 8-card landing grid. The route enforces
 * `CheckAdminAccess(ALL_WEB)` upstream in `web/includes/page-builder.php`,
 * so the only people who hit this view are admins holding *some* web
 * flag; this View's job is then to gate the per-area cards so an
 * admin only sees cards leading to areas they actually have rights
 * inside.
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
 * Pre-#5 this DTO also carried a `access_*` / `demosize` / `total_*` /
 * `archived_*` legacy compatibility surface for theme forks of the
 * pre-v2.0.0 default. `goals#5` deletes those properties along with
 * the COUNT compute that backed them; theme forks still rendering
 * the legacy stat-counts row must migrate to the 8-card grid or
 * compute the values themselves.
 */
final class AdminHomeView extends View
{
    public const TEMPLATE = 'page_admin.tpl';

    public function __construct(
        public readonly bool $can_admins,
        public readonly bool $can_groups,
        public readonly bool $can_servers,
        public readonly bool $can_bans,
        public readonly bool $can_mods,
        public readonly bool $can_overrides,
        public readonly bool $can_settings,
        public readonly bool $can_audit,
    ) {
    }
}
