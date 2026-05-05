<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Edit admin server access" page — binds to
 * `page_admin_edit_admins_servers.tpl`.
 *
 * The handler (`admin.edit.adminservers.php`) gates entry on
 * `ADMIN_OWNER | ADMIN_EDIT_ADMINS`, so the View doesn't carry an access
 * boolean of its own.
 *
 * Property set matches the legacy handler's `$theme->assign(...)` calls
 * verbatim so the existing `$theme->display(...)` path keeps rendering the
 * redesigned template unchanged. See {@see EditAdminDetailsView} for why
 * the per-tab edit handlers are out of B11's scope.
 */
final class EditAdminServersView extends View
{
    public const TEMPLATE = 'page_admin_edit_admins_servers.tpl';

    /**
     * @param list<array<string,mixed>> $group_list       Server groups
     *     (`:prefix_groups` rows where type = 3) selectable for the admin.
     * @param list<array<string,mixed>> $server_list      Per-server entries
     *     (`:prefix_servers` rows) for the per-server access checkboxes.
     * @param list<array{server_id:string|int,srv_group_id:string|int}> $assigned_servers
     *     Existing assignments for the admin from
     *     `:prefix_admins_servers_groups`, used to pre-check the boxes.
     */
    public function __construct(
        public readonly int $row_count,
        public readonly array $group_list,
        public readonly array $server_list,
        public readonly array $assigned_servers,
    ) {
    }
}
