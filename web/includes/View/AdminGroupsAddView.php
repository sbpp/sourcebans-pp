<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a group" tab on the admin groups page — binds to
 * `page_admin_groups_add.tpl`.
 *
 * The form posts via `sb.api.call(Actions.GroupsAdd, …)` so the View
 * itself is intentionally minimal — just the gating boolean. The
 * template lazy-loads its flag selector via the `groups.update_perms`
 * action so the View doesn't need the flag definitions inlined.
 * (`AdminGroupsListView` already exposes `all_flags` for the
 * master-detail editor on the list tab, which is the marquee surface
 * for the flag grid.)
 */
final class AdminGroupsAddView extends View
{
    public const TEMPLATE = 'page_admin_groups_add.tpl';

    public function __construct(
        public readonly bool $permission_addgroup,
    ) {
    }
}
