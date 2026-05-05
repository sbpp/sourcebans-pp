<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Edit admin groups" page — binds to `page_admin_edit_admins_group.tpl`.
 *
 * The handler (`admin.edit.admingroup.php`) gates entry on
 * `ADMIN_OWNER | ADMIN_EDIT_ADMINS`, so the View doesn't carry an access
 * boolean of its own.
 *
 * Property set matches the legacy handler's `$theme->assign(...)` calls
 * verbatim so the existing `$theme->display(...)` path keeps rendering the
 * redesigned template unchanged. See {@see EditAdminDetailsView} for why
 * the per-tab edit handlers are out of B11's scope.
 */
final class EditAdminGroupView extends View
{
    public const TEMPLATE = 'page_admin_edit_admins_group.tpl';

    /**
     * @param list<array<string,mixed>> $web_lst   Web groups (`:prefix_groups`
     *     rows where type != 3) selectable as the admin's web group.
     * @param list<array<string,mixed>> $group_lst SourceMod admin groups
     *     (`:prefix_srvgroups` rows) selectable as the admin's server group.
     */
    public function __construct(
        public readonly string $group_admin_name,
        public readonly int $group_admin_id,
        public readonly int $server_admin_group_id,
        public readonly array $web_lst,
        public readonly array $group_lst,
    ) {
    }
}
