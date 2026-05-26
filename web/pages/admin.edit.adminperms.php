<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

global $userbank, $theme;

new \Sbpp\View\AdminTabs([], $userbank, $theme);

require_once __DIR__ . '/_admin_edit_helpers.php';

$adminId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($adminId <= 0) {
    sbpp_admin_edit_die_with_toast(
        'No admin id specified. Please only follow links.',
        'index.php?p=admin&c=admins',
    );
    return;
}

if (!$userbank->HasAccess(\WebPermission::mask(\WebPermission::Owner, \WebPermission::EditAdmins))) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . ' tried to edit '
        . $userbank->GetProperty('user', $adminId) . "'s permissions, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit other admin's permissions.",
        'index.php?p=admin&c=admins',
    );
    return;
}

$adminRow = $GLOBALS['PDO']->query('SELECT * FROM `:prefix_admins` WHERE aid = :aid')
    ->single([':aid' => $adminId]);

if (!$adminRow) {
    \Sbpp\Log::add(
        \LogType::Error,
        'Getting admin data failed',
        "Can't find data for admin with id {$adminId}.",
    );
    sbpp_admin_edit_die_with_toast('Error getting current data.', 'index.php?p=admin&c=admins');
    return;
}

$srvFlags = (string) ($adminRow['srv_flags'] ?? '');
$smHas = static fn(string $flag): bool => str_contains($srvFlags, $flag);

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\EditAdminPermsView(
    admin_id:        $adminId,
    admin_name:      (string) ($adminRow['user'] ?? ''),
    is_owner_editor: $userbank->HasAccess(\WebPermission::Owner),

    web_owner:           $userbank->HasAccess(\WebPermission::Owner,        $adminId),
    web_list_admins:     $userbank->HasAccess(\WebPermission::ListAdmins,   $adminId),
    web_add_admins:      $userbank->HasAccess(\WebPermission::AddAdmins,    $adminId),
    web_edit_admins:     $userbank->HasAccess(\WebPermission::EditAdmins,   $adminId),
    web_delete_admins:   $userbank->HasAccess(\WebPermission::DeleteAdmins, $adminId),
    web_list_servers:    $userbank->HasAccess(\WebPermission::ListServers,  $adminId),
    web_add_server:      $userbank->HasAccess(\WebPermission::AddServer,    $adminId),
    web_edit_servers:    $userbank->HasAccess(\WebPermission::EditServers,  $adminId),
    web_delete_servers:  $userbank->HasAccess(\WebPermission::DeleteServers, $adminId),
    web_add_ban:         $userbank->HasAccess(\WebPermission::AddBan,        $adminId),
    web_edit_own_bans:   $userbank->HasAccess(\WebPermission::EditOwnBans,   $adminId),
    web_edit_group_bans: $userbank->HasAccess(\WebPermission::EditGroupBans, $adminId),
    web_edit_all_bans:   $userbank->HasAccess(\WebPermission::EditAllBans,   $adminId),
    web_ban_protests:    $userbank->HasAccess(\WebPermission::BanProtests,   $adminId),
    web_ban_submissions: $userbank->HasAccess(\WebPermission::BanSubmissions, $adminId),
    web_unban_own_bans:  $userbank->HasAccess(\WebPermission::UnbanOwnBans,   $adminId),
    web_unban_group_bans: $userbank->HasAccess(\WebPermission::UnbanGroupBans, $adminId),
    web_unban:           $userbank->HasAccess(\WebPermission::Unban,         $adminId),
    web_delete_ban:      $userbank->HasAccess(\WebPermission::DeleteBan,     $adminId),
    web_ban_import:      $userbank->HasAccess(\WebPermission::BanImport,     $adminId),
    web_list_groups:     $userbank->HasAccess(\WebPermission::ListGroups,    $adminId),
    web_add_group:       $userbank->HasAccess(\WebPermission::AddGroup,      $adminId),
    web_edit_groups:     $userbank->HasAccess(\WebPermission::EditGroups,    $adminId),
    web_delete_groups:   $userbank->HasAccess(\WebPermission::DeleteGroups,  $adminId),
    web_settings:        $userbank->HasAccess(\WebPermission::WebSettings,   $adminId),
    web_list_mods:       $userbank->HasAccess(\WebPermission::ListMods,      $adminId),
    web_add_mods:        $userbank->HasAccess(\WebPermission::AddMods,       $adminId),
    web_edit_mods:       $userbank->HasAccess(\WebPermission::EditMods,      $adminId),
    web_delete_mods:     $userbank->HasAccess(\WebPermission::DeleteMods,    $adminId),
    web_notify_sub:      $userbank->HasAccess(\WebPermission::NotifySub,     $adminId),
    web_notify_protest:  $userbank->HasAccess(\WebPermission::NotifyProtest, $adminId),

    sm_root:          $userbank->HasAccess(SM_ROOT,          $adminId),
    sm_reserved_slot: $userbank->HasAccess(SM_RESERVED_SLOT, $adminId),
    sm_generic:       $userbank->HasAccess(SM_GENERIC,       $adminId),
    sm_kick:          $userbank->HasAccess(SM_KICK,          $adminId),
    sm_ban:           $userbank->HasAccess(SM_BAN,           $adminId),
    sm_unban:         $userbank->HasAccess(SM_UNBAN,         $adminId),
    sm_slay:          $userbank->HasAccess(SM_SLAY,          $adminId),
    sm_map:           $userbank->HasAccess(SM_MAP,           $adminId),
    sm_cvar:          $userbank->HasAccess(SM_CVAR,          $adminId),
    sm_config:        $userbank->HasAccess(SM_CONFIG,        $adminId),
    sm_chat:          $userbank->HasAccess(SM_CHAT,          $adminId),
    sm_vote:          $userbank->HasAccess(SM_VOTE,          $adminId),
    sm_password:      $userbank->HasAccess(SM_PASSWORD,      $adminId),
    sm_rcon:          $userbank->HasAccess(SM_RCON,          $adminId),
    sm_cheats:        $userbank->HasAccess(SM_CHEATS,        $adminId),
    sm_custom1:       $userbank->HasAccess(SM_CUSTOM1,       $adminId),
    sm_custom2:       $userbank->HasAccess(SM_CUSTOM2,       $adminId),
    sm_custom3:       $userbank->HasAccess(SM_CUSTOM3,       $adminId),
    sm_custom4:       $userbank->HasAccess(SM_CUSTOM4,       $adminId),
    sm_custom5:       $userbank->HasAccess(SM_CUSTOM5,       $adminId),
    sm_custom6:       $userbank->HasAccess(SM_CUSTOM6,       $adminId),
    immunity:         (int) ($adminRow['immunity'] ?? 0),
));

// Page-tail vanilla JS: parent / child composite-toggle behaviour
// (replaces UpdateCheckBox 1.4.11 helper) and form submit through
// `Actions.AdminsEditPerms` (replaces `ProcessEditAdminPermissions`).
?>
<script>
(function () {
    'use strict';
    var form = document.getElementById('edit-perms-form');
    if (!form) return;

    function setBusy(btn, busy) {
        if (window.SBPP && typeof window.SBPP.setBusy === 'function') {
            window.SBPP.setBusy(btn, busy);
        } else {
            btn.disabled = !!busy;
        }
    }

    // Parent → children: ticking the parent ticks every matching
    // [data-child=name]; unticking the parent unticks them all.
    form.querySelectorAll('input[data-parent]').forEach(function (parent) {
        var name = parent.getAttribute('data-parent');
        var children = form.querySelectorAll('input[data-child="' + name + '"]');

        function syncParentFromChildren() {
            var anyOn = false;
            for (var i = 0; i < children.length; i++) {
                if (children[i].checked) { anyOn = true; break; }
            }
            parent.checked = anyOn;
        }

        parent.addEventListener('change', function () {
            children.forEach(function (c) { c.checked = parent.checked; });
        });

        children.forEach(function (c) {
            c.addEventListener('change', syncParentFromChildren);
        });

        syncParentFromChildren();
    });

    // OWNER tick implies every other web flag — keep this passive
    // visual hint (the server still trusts the bitmask we send).
    var ownerCb = document.getElementById('p2');
    if (ownerCb) {
        ownerCb.addEventListener('change', function () {
            if (!ownerCb.checked) return;
            form.querySelectorAll('input[data-child], input[data-parent]').forEach(function (c) {
                c.checked = true;
            });
        });
    }

    // SM_ROOT (z) implies every other server flag — same passive
    // visual.
    var smRootCb = document.getElementById('s14');
    if (smRootCb) {
        smRootCb.addEventListener('change', function () {
            if (!smRootCb.checked) return;
            form.querySelectorAll('input[data-sm-flag]').forEach(function (c) {
                if (c !== smRootCb) c.checked = true;
            });
        });
    }

    // Each field's id (`pNN`) lines up with a `WebPermission::*`
    // bit; the API expects the OR'd integer mask. Values match
    // `web/configs/permissions/web.json`. JS doubles cover the
    // 32-bit unsigned bits cleanly with `+=` (avoid `|=`, which
    // would promote to 32-bit signed and lose the high bits).
    var WEB_BITS = {
        p2:  16777216,    // OWNER
        p4:  1,           // LIST_ADMINS
        p5:  2,           // ADD_ADMINS
        p6:  4,           // EDIT_ADMINS
        p7:  8,           // DELETE_ADMINS
        p9:  16,          // LIST_SERVERS
        p10: 32,          // ADD_SERVER
        p11: 64,          // EDIT_SERVERS
        p12: 128,         // DELETE_SERVERS
        p14: 256,         // ADD_BAN
        p16: 1024,        // EDIT_OWN_BANS
        p17: 2048,        // EDIT_GROUP_BANS
        p18: 4096,        // EDIT_ALL_BANS
        p19: 8192,        // BAN_PROTESTS
        p20: 16384,       // BAN_SUBMISSIONS
        p22: 32768,       // LIST_GROUPS
        p23: 65536,       // ADD_GROUP
        p24: 131072,      // EDIT_GROUPS
        p25: 262144,      // DELETE_GROUPS
        p26: 524288,      // WEB_SETTINGS
        p28: 1048576,     // LIST_MODS
        p29: 2097152,     // ADD_MODS
        p30: 4194304,     // EDIT_MODS
        p31: 8388608,     // DELETE_MODS
        p32: 67108864,    // UNBAN
        p33: 33554432,    // DELETE_BAN
        p34: 134217728,   // BAN_IMPORT
        p36: 268435456,   // NOTIFY_SUB
        p37: 536870912,   // NOTIFY_PROTEST
        p38: 1073741824,  // UNBAN_OWN_BANS
        p39: 2147483648   // UNBAN_GROUP_BANS
    };

    function collectWebFlags() {
        var mask = 0;
        Object.keys(WEB_BITS).forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.checked) mask += WEB_BITS[id];
        });
        return mask;
    }

    function collectSrvFlags() {
        var letters = '';
        form.querySelectorAll('input[data-sm-flag]').forEach(function (c) {
            if (c.checked) letters += c.getAttribute('data-sm-flag');
        });
        var im = parseInt(document.getElementById('immunity').value, 10);
        if (isNaN(im) || im < 0) im = 0;
        return letters + '#' + im;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('[data-testid="admin-edit-perms-save"]');
        if (btn) setBusy(btn, true);
        var aid = parseInt(form.getAttribute('data-aid'), 10) || 0;
        window.sb.api.call(window.Actions.AdminsEditPerms, {
            aid: aid,
            web_flags: collectWebFlags(),
            srv_flags: collectSrvFlags()
        }).then(function () {
            window.SBPP.showToast({
                kind: 'success',
                title: 'Permissions updated',
                body: "The user's permissions have been updated successfully"
            });
            setTimeout(function () {
                window.location.href = 'index.php?p=admin&c=admins';
            }, 1500);
        }).catch(function () {
            if (btn) setBusy(btn, false);
        });
    });
})();
</script>
