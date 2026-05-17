<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a block" tab on the admin comms page — binds to
 * `page_admin_comms_add.tpl`.
 *
 * SourceComms reuses the bans permission set: there is no separate
 * `ADMIN_ADD_COMM` flag, so `permission_addban` (precomputed via
 * {@see Perms::for()} as `can_add_ban`) gates the form. The naming
 * matches the legacy `default/page_admin_comms_add.tpl` so a single
 * View instance satisfies both themes during the v2.0.0 rollout window
 * (#1123): SmartyTemplateRule scans whichever theme is active and the
 * property name has to line up on both legs of the matrix.
 *
 * `prefill_steam` / `prefill_type` carry the smart-default shape used
 * by the `?p=admin&c=comms&steam=<STEAMID>` deep link (used by the
 * public servers list's right-click context menu — see
 * `web/scripts/server-context-menu.js`). Mirrors the
 * `Sbpp\View\AdminBansAddView` pair for the sibling "Ban player"
 * affordance. The property NAMES match; the `prefill_type` SEMANTICS
 * diverge by surface — for comms the valid values are 1=Mute,
 * 2=Gag, 3=Silence (the `:prefix_comms.type` column), with 0
 * meaning "no pre-selection". Bans uses 0=Steam ID, 1=IP for the
 * sibling field. The form's `<select id="type">` lands on the
 * native first-option default (Mute) when `prefill_type === 0` —
 * no `selected` attribute fires anywhere on the option list. (#1395)
 */
final class AdminCommsAddView extends View
{
    public const TEMPLATE = 'page_admin_comms_add.tpl';

    public function __construct(
        public readonly bool $permission_addban,
        public readonly string $prefill_steam = '',
        public readonly int $prefill_type = 0,
    ) {
    }
}
