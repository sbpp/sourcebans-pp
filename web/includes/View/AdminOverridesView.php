<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * SourceMod command/group overrides editor — binds to
 * `page_admin_overrides.tpl`. Overrides let admins flip the required
 * flags on any in-game command without editing plugin source.
 *
 * Until #1123 D1 cuts over, the same DTO has to satisfy both
 * `web/themes/default/page_admin_overrides.tpl` (legacy markup) and
 * `web/themes/sbpp2026/page_admin_overrides.tpl` (new redesign), so:
 *
 *   - The permission gate is exposed as `$permission_addadmin` (the
 *     legacy name) rather than the canonical `can_add_admins` from
 *     {@see Perms::for()}; renaming it would require editing the
 *     legacy default template, which is out of scope for B17.
 *   - Each row in `$overrides_list` carries BOTH the legacy
 *     `id` / `name` keys (read by the default template) AND the
 *     handoff-style `oid` / `command_or_group` aliases (read by the
 *     sbpp2026 template). They point at the same underlying DB
 *     columns; the SmartyTemplateRule only checks top-level vars, so
 *     row-level alias keys cost nothing.
 *
 * The wiring lives in `web/pages/admin.overrides.php`. The legacy
 * `web/pages/admin.admins.php` `require`s that file at the bottom so
 * the existing `?p=admin&c=admins` URL keeps its three-tab layout.
 */
final class AdminOverridesView extends View
{
    public const TEMPLATE = 'page_admin_overrides.tpl';

    /**
     * @param list<array<string,mixed>> $overrides_list Each entry is a
     *     row from `:prefix_overrides` augmented with the handoff-style
     *     aliases. Effective row shape:
     *     `id` (int), `oid` (int, alias of `id`),
     *     `type` ('command'|'group'),
     *     `name` (string), `command_or_group` (string, alias of `name`),
     *     `flags` (string).
     */
    public function __construct(
        public readonly bool $permission_addadmin,
        public readonly string $overrides_error,
        public readonly bool $overrides_save_success,
        public readonly array $overrides_list,
    ) {
    }
}
