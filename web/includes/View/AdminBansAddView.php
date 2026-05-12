<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a ban" tab on the admin bans page — binds to `page_admin_bans_add.tpl`.
 *
 * `prefill_steam` / `prefill_type` carry the smart-default shape used
 * by the `?p=admin&c=bans&section=add-ban&steam=<STEAMID>` deep link
 * (used by the public servers list's right-click context menu — see
 * `web/scripts/server-context-menu.js`). Both values are server-side
 * pre-fills threaded through the View so the form's `<input id="steam">`
 * renders with the value baked in; we don't reach for JS to populate
 * the field because the form has to be usable without JS on the same
 * path. Empty strings on a bare `?p=admin&c=bans&section=add-ban`
 * (the default load) so the existing tests don't have to thread
 * smart-default args through every fixture.
 */
final class AdminBansAddView extends View
{
    public const TEMPLATE = 'page_admin_bans_add.tpl';

    /**
     * @param false|list<string> $customreason `false` when custom reasons
     *     are disabled, otherwise the list of reason strings.
     */
    public function __construct(
        public readonly bool $permission_addban,
        public readonly false|array $customreason,
        public readonly string $prefill_steam = '',
        public readonly int $prefill_type = 0,
    ) {
    }
}
