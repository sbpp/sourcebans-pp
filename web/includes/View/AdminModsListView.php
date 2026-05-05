<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * First tab of the admin "Mods" page — binds to
 * `page_admin_mods_list.tpl`.
 *
 * Each `mod_list` row is the raw `:prefix_mods` SELECT projection (mid,
 * name, modfolder, icon, steam_universe, enabled). The View only cares
 * that the array has the columns the template iterates; the
 * SmartyTemplateRule check is on the View → template variable parity,
 * not on the inner array shape.
 *
 * Property names keep the historical `permission_*` shape (rather than
 * the newer `can_*` convention `Sbpp\View\Perms::for()` produces) because
 * the template references `{$permission_listmods}` /
 * `{$permission_editmods}` / `{$permission_deletemods}`. See
 * `AdminServersListView` / `AdminBansAddView` for the canonical
 * `can_*` convention on Views that don't have to honour a historical
 * variable name.
 */
final class AdminModsListView extends View
{
    public const TEMPLATE = 'page_admin_mods_list.tpl';

    /**
     * @param list<array<string,mixed>> $mod_list
     */
    public function __construct(
        public readonly bool $permission_listmods,
        public readonly bool $permission_editmods,
        public readonly bool $permission_deletemods,
        public readonly int $mod_count,
        public readonly array $mod_list,
    ) {
    }
}
