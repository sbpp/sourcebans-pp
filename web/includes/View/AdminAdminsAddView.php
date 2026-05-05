<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add new admin" tab on the admin admins page — binds to
 * `page_admin_admins_add.tpl`.
 *
 * Permission booleans follow the {@see Perms::for()} `can_<flag>` naming
 * (owner bypass baked in). `admin.admins.php` passes them by name; see the
 * sibling {@see AdminAdminsListView} docblock for why we don't splat
 * `Perms::for()`.
 */
final class AdminAdminsAddView extends View
{
    public const TEMPLATE = 'page_admin_admins_add.tpl';

    /**
     * @param list<array<string,mixed>> $group_list        Web groups (type=3)
     *     selectable as the new admin's "server access" group.
     * @param list<array<string,mixed>> $server_list       Per-server entries
     *     ({sid, ip, port}) for the per-server access checkboxes.
     * @param list<array<string,mixed>> $server_admin_group_list `:prefix_srvgroups`
     *     rows for the SourceMod admin-group dropdown.
     * @param list<array<string,mixed>> $server_group_list `:prefix_groups`
     *     rows (type != 3) for the web admin-group dropdown.
     */
    public function __construct(
        public readonly bool $can_add_admins,
        public readonly array $group_list,
        public readonly array $server_list,
        public readonly array $server_admin_group_list,
        public readonly array $server_group_list,
        public readonly string $server_script,
    ) {
    }
}
