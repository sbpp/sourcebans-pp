<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Edit admin permissions" page (issue sbpp/goals#5, Phase 2.5c).
 *
 * Pre-rewrite the page handler `echo`'d a 1.4.11-shaped HTML form
 * built around `groups.web.perm.php` / `groups.server.perm.php`
 * partials pulled in via `file_get_contents`, with inline
 * `onclick="UpdateCheckBox(...)"` / `ProcessEditAdminPermissions()` /
 * `ButtonOver(...)` calls that all bound to JS that hasn't existed
 * since #1123 D1. The new shape is one Smarty template + one View
 * DTO + a vanilla JS page-tail script wired through
 * `Actions.AdminsEditPerms` (the existing JSON action).
 *
 * `$web_*` flags are bools indicating whether the target admin has
 * the matching `WebPermission::*` flag right now (server-rendered
 * `checked` state — no `$('p2').checked = true` re-paint script).
 *
 * `$server_*` flags do the same for SourceMod char flags, plus
 * `$immunity` for the immunity number and `$is_owner_editor` to gate
 * the OWNER and SM_ROOT toggles. The latter two stay hidden when the
 * editing admin doesn't themselves hold OWNER (matching the legacy
 * `if(!$userbank->HasAccess(WebPermission::Owner))` / `wrootcheckbox`
 * `setStyle('display', 'none')` shape).
 */
final class EditAdminPermsView extends View
{
    public const TEMPLATE = 'page_admin_edit_admins_perms.tpl';

    public function __construct(
        public readonly int $admin_id,
        public readonly string $admin_name,
        public readonly bool $is_owner_editor,
        // Web permission bits — names mirror WebPermission::*
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
        // Server permission flags (SourceMod char flag presence)
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
    ) {
    }
}
