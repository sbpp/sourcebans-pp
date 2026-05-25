<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a ban" tab on the admin bans page — binds to `page_admin_bans_add.tpl`.
 *
 * `prefill_steam` / `prefill_type` / `prefill_name` carry the smart-default
 * shape used by the
 * `?p=admin&c=bans&section=add-ban&steam=<STEAMID>&name=<player>` deep
 * link (used by the public servers list's right-click context menu —
 * see `web/scripts/server-context-menu.js`). All three values are
 * server-side pre-fills threaded through the View so the form's
 * `<input id="steam">` / `<input id="nickname">` render with the
 * values baked in; we don't reach for JS to populate the fields
 * because the form has to be usable without JS on the same path.
 * Empty strings on a bare `?p=admin&c=bans&section=add-ban` (the
 * default load) so the existing tests don't have to thread
 * smart-default args through every fixture.
 *
 * `prefill_name` (issue #1440) is the player's display name as
 * surfaced by the context menu's `data-name` attribute. The page
 * handler strips control characters and caps the value at 128
 * codepoints (matching `:prefix_bans.name`'s `varchar(128)` width
 * — keeps the form-pre-fill round-trip safe against the eventual
 * INSERT on submit) before it reaches this DTO. Smarty's global
 * auto-escape handles the `value="…"` HTML attribute escape at
 * render time, so a Steam name containing `<` / `&` / `"` lands
 * as the entity-escaped form and never breaks out of the
 * attribute. The strict-allowlist shape used for `prefill_steam`
 * doesn't apply here: player names are intrinsically freeform
 * (Unicode / emoji / punctuation), so the contract is "scrub
 * dangerous bytes, trust the escape layer for everything else".
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
        public readonly string $prefill_name = '',
    ) {
    }
}
