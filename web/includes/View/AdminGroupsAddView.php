<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a group" tab on the admin groups page — binds to
 * `page_admin_groups_add.tpl`.
 *
 * The form posts via `sb.api.call(Actions.GroupsAdd, …)` and is
 * intentionally a name + type + (type-dependent) `srvflags` field —
 * permission flag editing is the marquee surface of the **list** tab's
 * master-detail editor (which `AdminGroupsListView` populates via
 * `all_flags`), so this View carries only the gating boolean.
 */
final class AdminGroupsAddView extends View
{
    public const TEMPLATE = 'page_admin_groups_add.tpl';

    public function __construct(
        public readonly bool $permission_addgroup,
    ) {
    }
}
