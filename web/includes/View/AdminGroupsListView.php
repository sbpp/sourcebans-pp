<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "List groups" tab on the admin groups page — binds to
 * `page_admin_groups_list.tpl`.
 *
 * The marquee feature is a master-detail flag grid for web admin
 * groups: clicking a row in the left rail focuses that group in the
 * right pane, and the right pane renders one checkbox per
 * web-permission flag (sourced from
 * `web/configs/permissions/web.json`) pre-checked against the group's
 * stored bitmask. The handler precomputes `all_flags` so the template
 * is server-side rendered with no extra round-trip; the Save button
 * posts back via `sb.api.call(Actions.GroupsEdit, …)` which lives in
 * `web/api/handlers/groups.php`.
 *
 * The legacy `web_admins[]` parallel-count array is preserved for any
 * third-party theme that forked the pre-v2.0.0 default; the shipped
 * template reads the inline `member_count` per row instead.
 */
final class AdminGroupsListView extends View
{
    public const TEMPLATE = 'page_admin_groups_list.tpl';

    /**
     * @param list<array<string,mixed>>      $web_group_list           Web admin group rows
     *     (`:prefix_groups` WHERE type != 3) augmented with
     *     `permissions` (display list from `BitToString`) and
     *     `member_count` (inline count consumed by the master-detail
     *     rail, mirrors the parallel `web_admins[index]` array kept
     *     for legacy compatibility).
     * @param list<int>                      $web_admins               Per-group member counts, indexed
     *     parallel to `$web_group_list`. Preserved on the View for
     *     any third-party theme that forked the pre-v2.0.0 default.
     * @param list<list<array<string,mixed>>> $web_admins_list         Per-group member rows
     *     (`{aid, user, authid}`), indexed parallel to
     *     `$web_group_list`.
     * @param list<array<string,mixed>>      $server_group_list        Server admin group rows
     *     (`:prefix_srvgroups`). The historically misleading template
     *     name (`server_group_list` vs. the page handler's
     *     `$server_admin_group_list`) is preserved for default-theme
     *     compatibility.
     * @param list<int>                      $server_admins            Per-group admin counts for
     *     the server admin groups, indexed parallel to
     *     `$server_group_list`.
     * @param list<list<array<string,mixed>>> $server_admins_list      Per-group admin rows for the
     *     server admin groups.
     * @param list<list<array<string,mixed>>> $server_overrides_list   Per-group override rows
     *     (`{type, name, access}`) for the server admin groups.
     * @param list<array<string,mixed>>      $server_list              Server group rows
     *     (`:prefix_groups` WHERE type = 3). Same naming-clash caveat
     *     as `$server_group_list` above.
     * @param list<int>                      $server_counts            Per-group server counts, indexed
     *     parallel to `$server_list`.
     * @param list<array{name: string, value: int, label: string}> $all_flags Web-permission flag
     *     definitions sourced from `web/configs/permissions/web.json`.
     *     `name` is the lowercased ADMIN_-stripped key
     *     (e.g. `add_ban`); `value` is the bitmask; `label` is the
     *     human-readable column from the JSON. Drives the master-detail
     *     flag-grid checkboxes; rendered server-side so the page
     *     does not need a round-trip to populate the form.
     * @param array{gid: int, name: string, flags: int, member_count: int}|null $selected_group
     *     The group highlighted in the master-detail. `null` when the
     *     groups list is empty or the request didn't pin one via
     *     `?gid=…` and the handler couldn't fall back to the first
     *     row. Templates render an empty-state panel in that case.
     *     Web admin groups (`:prefix_groups`) have no `immunity` column
     *     and `api_groups_edit` ignores the field for type=web, so the
     *     master-detail editor never surfaces it; SourceMod admin groups
     *     (`:prefix_srvgroups`) keep their immunity surface elsewhere.
     */
    public function __construct(
        public readonly bool $permission_listgroups,
        public readonly bool $permission_editgroup,
        public readonly bool $permission_deletegroup,
        public readonly bool $permission_editadmin,
        public readonly int $web_group_count,
        public readonly array $web_admins,
        public readonly array $web_admins_list,
        public readonly array $web_group_list,
        public readonly int $server_admin_group_count,
        public readonly array $server_admins,
        public readonly array $server_admins_list,
        public readonly array $server_overrides_list,
        public readonly array $server_group_list,
        public readonly int $server_group_count,
        public readonly array $server_counts,
        public readonly array $server_list,
        public readonly array $all_flags,
        public readonly ?array $selected_group,
    ) {
    }
}
