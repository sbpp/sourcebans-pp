<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Edit group" page (issue sbpp/goals#5, Phase 2.5f).
 *
 * Single template covers both `?type=web` and `?type=srv` (also the
 * legacy `?type=server` alias used by the admin-groups list links).
 * The split is purely UI-shape (web flags vs SourceMod char flags +
 * overrides editor) — both ride `Actions.GroupsEdit`.
 *
 * Replaces the v1.x-era inline `echo` of `groups.web.perm.php` /
 * `groups.server.perm.php` partials and the `ProcessEditGroup()` /
 * `ButtonOver()` / `$('groupname').value` JS handlers (all dead since
 * sourcebans.js was removed at #1123 D1).
 */
final class EditGroupView extends View
{
    public const TEMPLATE = 'page_admin_edit_group.tpl';

    /**
     * @param 'web'|'srv' $type Resolved group type — `?type=server` is
     *   normalised to `web` upstream of this DTO since the underlying
     *   table is `:prefix_groups` either way.
     * @param list<array{id:int,type:string,name:string,access:string}> $overrides
     *   Existing per-command / per-group overrides for a server group;
     *   empty list for `web`. Keys match `:prefix_srvgroups_overrides`.
     */
    public function __construct(
        public readonly int $group_id,
        public readonly string $type,
        public readonly string $group_name,
        public readonly bool $is_owner_editor,
        // Web bitmask (mirrors EditAdminPermsView shape so the
        // template can share the same checkbox grid)
        public readonly bool $web_owner,
        public readonly bool $web_list_admins,
        public readonly bool $web_add_admins,
        public readonly bool $web_edit_admins,
        public readonly bool $web_delete_admins,
        public readonly bool $web_list_servers,
        public readonly bool $web_add_server,
        public readonly bool $web_edit_servers,
        public readonly bool $web_delete_servers,
        public readonly bool $web_add_ban,
        public readonly bool $web_edit_own_bans,
        public readonly bool $web_edit_group_bans,
        public readonly bool $web_edit_all_bans,
        public readonly bool $web_ban_protests,
        public readonly bool $web_ban_submissions,
        public readonly bool $web_unban_own_bans,
        public readonly bool $web_unban_group_bans,
        public readonly bool $web_unban,
        public readonly bool $web_delete_ban,
        public readonly bool $web_ban_import,
        public readonly bool $web_list_groups,
        public readonly bool $web_add_group,
        public readonly bool $web_edit_groups,
        public readonly bool $web_delete_groups,
        public readonly bool $web_settings,
        public readonly bool $web_list_mods,
        public readonly bool $web_add_mods,
        public readonly bool $web_edit_mods,
        public readonly bool $web_delete_mods,
        public readonly bool $web_notify_sub,
        public readonly bool $web_notify_protest,
        // Server flags
        public readonly bool $sm_root,
        public readonly bool $sm_reserved_slot,
        public readonly bool $sm_generic,
        public readonly bool $sm_kick,
        public readonly bool $sm_ban,
        public readonly bool $sm_unban,
        public readonly bool $sm_slay,
        public readonly bool $sm_map,
        public readonly bool $sm_cvar,
        public readonly bool $sm_config,
        public readonly bool $sm_chat,
        public readonly bool $sm_vote,
        public readonly bool $sm_password,
        public readonly bool $sm_rcon,
        public readonly bool $sm_cheats,
        public readonly bool $sm_custom1,
        public readonly bool $sm_custom2,
        public readonly bool $sm_custom3,
        public readonly bool $sm_custom4,
        public readonly bool $sm_custom5,
        public readonly bool $sm_custom6,
        public readonly int $immunity,
        public readonly array $overrides,
    ) {
    }
}
