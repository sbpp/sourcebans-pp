<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Second tab of the admin "Mods" page (add new mod) — binds to
 * `page_admin_mods_add.tpl`. The form posts via
 * `sb.api.call(Actions.ModsAdd, …)`; the icon picker pops
 * `pages/admin.uploadicon.php` and writes the resulting filename back
 * into the form via `window.opener.icon()`.
 *
 * `permission_add` keeps its historical name to match the template's
 * `{if NOT $permission_add}` reference.
 */
final class AdminModsAddView extends View
{
    public const TEMPLATE = 'page_admin_mods_add.tpl';

    public function __construct(
        public readonly bool $permission_add,
    ) {
    }
}
