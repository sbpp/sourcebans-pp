<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Admin landing page (`?p=admin` with no `c=…`) — binds to
 * `page_admin.tpl`. The route enforces `CheckAdminAccess(ALL_WEB)` in
 * {@see route()} (web/includes/page-builder.php), so the only people
 * who hit this view are admins holding *some* web flag; this View's
 * job is then to gate the per-area cards so an admin only sees
 * cards leading to areas they actually have rights inside.
 *
 * ### Dual-theme rollout
 *
 * Two themes consume this view side-by-side during the v2.0.0 rollout
 * (#1123): the legacy `web/themes/default/page_admin.tpl` (table-y
 * stats grid + access-flag-gated card list) and the new
 * `web/themes/sbpp2026/page_admin.tpl` (8-card landing grid). Because
 * `SmartyTemplateRule` enforces one-to-one parity between properties
 * and template references for the theme it scans, this View declares
 * the *union* of variables both templates need:
 *
 *   - New fields (`can_admins`, `can_groups`, `can_servers`,
 *     `can_bans`, `can_mods`, `can_overrides`, `can_settings`,
 *     `can_audit`) drive the sbpp2026 template's per-card visibility
 *     gates. Each is a composite `OR` over the underlying `can_<flag>`
 *     keys produced by {@see Perms::for()}, mirroring the masks the
 *     legacy router uses in `page-builder.php` so a card visible on
 *     the landing implies the router will let the user through.
 *   - Legacy-only fields (`access_*`, `dev`, `demosize`, `total_*`,
 *     `archived_*`) stay so the legacy template continues to
 *     type-check on the `default` PHPStan leg of the dual-theme
 *     matrix. The sbpp2026 template references them inside an
 *     unreachable `{if false}` parity block (see the template) so the
 *     `sbpp2026` leg's "unused property" check also stays green
 *     without bespoke baseline entries — matching the precedent set
 *     by `HomeDashboardView`'s `IN_SERVERS_PAGE` parity reference.
 *
 * ### Card list
 *
 * The set of cards (and therefore the list of `can_<area>` props) is
 * the canonical 8-card admin grid for the v2.0.0 theme:
 *   - admins, groups, servers, bans, mods, overrides, settings, audit
 *
 * Comms intentionally folds into the sidebar nav (`admin/comms`) — the
 * mockup at `handoff/ui_kits/webpanel-2026/views.jsx` (`AdminPanelView`)
 * doesn't surface a separate Comms card on the landing, matching the
 * orchestrator's spec for #1123 B10. Audit is a forward-looking card
 * gated to owners until a future ticket adds the `c=audit` route +
 * page; until then the legacy router's `default:` case returns the
 * admin landing itself, so the link is a harmless self-loop rather
 * than a 404.
 *
 * D1's hard cutover deletes the legacy template and renames the
 * sbpp2026 directory into `default/`; at that point this View loses
 * the legacy-only fields in a follow-up cleanup PR (tracked by the D2
 * docs sweep).
 */
final class AdminHomeView extends View
{
    public const TEMPLATE = 'page_admin.tpl';

    public function __construct(
        // sbpp2026 — composite per-area gates for the landing card grid.
        public readonly bool $can_admins,
        public readonly bool $can_groups,
        public readonly bool $can_servers,
        public readonly bool $can_bans,
        public readonly bool $can_mods,
        public readonly bool $can_overrides,
        public readonly bool $can_settings,
        public readonly bool $can_audit,
        // legacy default theme — kept until D1 deletes the legacy template.
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
