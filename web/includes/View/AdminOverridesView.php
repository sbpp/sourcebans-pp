<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * SourceMod command/group overrides editor — binds to
 * `page_admin_overrides.tpl`. Overrides let admins flip the required
 * flags on any in-game command without editing plugin source.
 *
 *   - The permission gate is exposed as `$permission_addadmin` (rather
 *     than the canonical `can_add_admins` from {@see Perms::for()}) to
 *     preserve the historical Smarty variable name.
 *   - Each row in `$overrides_list` carries the `oid` /
 *     `command_or_group` aliases used by the template alongside the
 *     legacy `id` / `name` keys (preserved for any third-party theme
 *     that still references them). They point at the same underlying
 *     DB columns; the SmartyTemplateRule only checks top-level vars,
 *     so row-level alias keys cost nothing.
 *
 * The wiring lives in `web/pages/admin.overrides.php`.
 * `web/pages/admin.admins.php` `require`s that file at the bottom so
 * the existing `?p=admin&c=admins` URL keeps its three-tab layout.
 */
final class AdminOverridesView extends View
{
    public const TEMPLATE = 'page_admin_overrides.tpl';

    /**
     * @param list<array<string,mixed>> $overrides_list Each entry is a
     *     row from `:prefix_overrides` augmented with the row aliases
     *     described in the class docblock. Effective row shape:
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
