<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Second tab of the admin "Mods" page (add new mod) — binds to
 * `page_admin_mods_add.tpl`. The form is wired to `Actions.ModsAdd`
 * via the legacy `ProcessMod()` helper in `web/scripts/sourcebans.js`;
 * the icon picker still pops `pages/admin.uploadicon.php` and writes
 * the resulting filename back into the form via `window.opener.icon()`.
 *
 * `permission_add` keeps its legacy name because the default theme's
 * `page_admin_mods_add.tpl` references `{if NOT $permission_add}`. The
 * dual-theme PHPStan matrix (#1123 A2) cross-checks both templates
 * against this View; renaming would force a paired edit to the
 * default theme, which is out of this PR's scope.
 */
final class AdminModsAddView extends View
{
    public const TEMPLATE = 'page_admin_mods_add.tpl';

    public function __construct(
        public readonly bool $permission_add,
    ) {
    }
}
