<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

global $userbank, $theme;

new \Sbpp\View\AdminTabs([], $userbank, $theme);

require_once __DIR__ . '/_admin_edit_helpers.php';

$groupId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($groupId <= 0) {
    sbpp_admin_edit_die_with_toast(
        'No group id specified. Please only follow links.',
        'index.php?p=admin&c=groups',
    );
    return;
}

$rawType = (string) ($_GET['type'] ?? '');
$type = match ($rawType) {
    'web', 'server' => 'web',
    'srv'           => 'srv',
    default         => '',
};
if ($type === '') {
    sbpp_admin_edit_die_with_toast(
        'No valid group type specified. Please only follow links.',
        'index.php?p=admin&c=groups',
    );
    return;
}

$pdo = $GLOBALS['PDO'];

$groupName = '';
$webFlags  = 0;
$srvFlags  = '';
$immunity  = 0;
/** @var list<array{id:int,type:string,name:string,access:string}> $overrides */
$overrides = [];

if ($type === 'web') {
    $row = $pdo->query('SELECT flags, name FROM `:prefix_groups` WHERE gid = :id')
        ->single([':id' => $groupId]);
    if (!$row) {
        sbpp_admin_edit_die_with_toast('Group not found.', 'index.php?p=admin&c=groups');
        return;
    }
    $groupName = (string) ($row['name']  ?? '');
    $webFlags  = (int)    ($row['flags'] ?? 0);
} else {
    $row = $pdo->query('SELECT flags, name, immunity FROM `:prefix_srvgroups` WHERE id = :id')
        ->single([':id' => $groupId]);
    if (!$row) {
        sbpp_admin_edit_die_with_toast('Group not found.', 'index.php?p=admin&c=groups');
        return;
    }
    $groupName = (string) ($row['name']     ?? '');
    $srvFlags  = (string) ($row['flags']    ?? '');
    $immunity  = (int)    ($row['immunity'] ?? 0);

    $overrideRows = $pdo->query(
        'SELECT id, type, name, access FROM `:prefix_srvgroups_overrides` WHERE group_id = :gid'
    )->resultset([':gid' => $groupId]);
    foreach ($overrideRows as $or) {
        $overrides[] = [
            'id'     => (int)    ($or['id']     ?? 0),
            'type'   => (string) ($or['type']   ?? 'command'),
            'name'   => (string) ($or['name']   ?? ''),
            'access' => (string) ($or['access'] ?? 'allow'),
        ];
    }
}

$has = static fn(int $bit): bool => ($webFlags & $bit) !== 0;
$smHas = static fn(string $flag): bool => str_contains($srvFlags, $flag);

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\EditGroupView(
    group_id:        $groupId,
    type:            $type,
    group_name:      $groupName,
    is_owner_editor: $userbank->HasAccess(\WebPermission::Owner),

    web_owner:           $has(ADMIN_OWNER),
    web_list_admins:     $has(ADMIN_LIST_ADMINS),
    web_add_admins:      $has(ADMIN_ADD_ADMINS),
    web_edit_admins:     $has(ADMIN_EDIT_ADMINS),
    web_delete_admins:   $has(ADMIN_DELETE_ADMINS),
    web_list_servers:    $has(ADMIN_LIST_SERVERS),
    web_add_server:      $has(ADMIN_ADD_SERVER),
    web_edit_servers:    $has(ADMIN_EDIT_SERVERS),
    web_delete_servers:  $has(ADMIN_DELETE_SERVERS),
    web_add_ban:         $has(ADMIN_ADD_BAN),
    web_edit_own_bans:   $has(ADMIN_EDIT_OWN_BANS),
    web_edit_group_bans: $has(ADMIN_EDIT_GROUP_BANS),
    web_edit_all_bans:   $has(ADMIN_EDIT_ALL_BANS),
    web_ban_protests:    $has(ADMIN_BAN_PROTESTS),
    web_ban_submissions: $has(ADMIN_BAN_SUBMISSIONS),
    web_unban_own_bans:  $has(ADMIN_UNBAN_OWN_BANS),
    web_unban_group_bans: $has(ADMIN_UNBAN_GROUP_BANS),
    web_unban:           $has(ADMIN_UNBAN),
    web_delete_ban:      $has(ADMIN_DELETE_BAN),
    web_ban_import:      $has(ADMIN_BAN_IMPORT),
    web_list_groups:     $has(ADMIN_LIST_GROUPS),
    web_add_group:       $has(ADMIN_ADD_GROUP),
    web_edit_groups:     $has(ADMIN_EDIT_GROUPS),
    web_delete_groups:   $has(ADMIN_DELETE_GROUPS),
    web_settings:        $has(ADMIN_WEB_SETTINGS),
    web_list_mods:       $has(ADMIN_LIST_MODS),
    web_add_mods:        $has(ADMIN_ADD_MODS),
    web_edit_mods:       $has(ADMIN_EDIT_MODS),
    web_delete_mods:     $has(ADMIN_DELETE_MODS),
    web_notify_sub:      $has(ADMIN_NOTIFY_SUB),
    web_notify_protest:  $has(ADMIN_NOTIFY_PROTEST),

    sm_root:          $smHas(SM_ROOT),
    sm_reserved_slot: $smHas(SM_RESERVED_SLOT),
    sm_generic:       $smHas(SM_GENERIC),
    sm_kick:          $smHas(SM_KICK),
    sm_ban:           $smHas(SM_BAN),
    sm_unban:         $smHas(SM_UNBAN),
    sm_slay:          $smHas(SM_SLAY),
    sm_map:           $smHas(SM_MAP),
    sm_cvar:          $smHas(SM_CVAR),
    sm_config:        $smHas(SM_CONFIG),
    sm_chat:          $smHas(SM_CHAT),
    sm_vote:          $smHas(SM_VOTE),
    sm_password:      $smHas(SM_PASSWORD),
    sm_rcon:          $smHas(SM_RCON),
    sm_cheats:        $smHas(SM_CHEATS),
    sm_custom1:       $smHas(SM_CUSTOM1),
    sm_custom2:       $smHas(SM_CUSTOM2),
    sm_custom3:       $smHas(SM_CUSTOM3),
    sm_custom4:       $smHas(SM_CUSTOM4),
    sm_custom5:       $smHas(SM_CUSTOM5),
    sm_custom6:       $smHas(SM_CUSTOM6),
    immunity:         $immunity,
    overrides:        $overrides,
));

// Page-tail vanilla JS: composite-toggle behaviour (replaces
// `UpdateCheckBox`), submission via `Actions.GroupsEdit` (replaces
// `ProcessEditGroup`), and the dynamic overrides editor.
?>
<script>
(function () {
    'use strict';
    var form = document.getElementById('edit-group-form');
    if (!form) return;
    var type = form.getAttribute('data-type');
    var gid  = parseInt(form.getAttribute('data-gid'), 10) || 0;

    function setBusy(btn, busy) {
        if (window.SBPP && typeof window.SBPP.setBusy === 'function') {
            window.SBPP.setBusy(btn, busy);
        } else {
            btn.disabled = !!busy;
        }
    }

    function setMsg(field, text) {
        var el = document.getElementById(field + '.msg');
        if (!el) return;
        el.textContent = text || '';
        el.style.display = text ? 'block' : 'none';
    }

    // Composite parent / child toggles (web mode only)
    form.querySelectorAll('input[data-parent]').forEach(function (parent) {
        var name = parent.getAttribute('data-parent');
        var children = form.querySelectorAll('input[data-child="' + name + '"]');
        function syncParent() {
            var anyOn = false;
            for (var i = 0; i < children.length; i++) {
                if (children[i].checked) { anyOn = true; break; }
            }
            parent.checked = anyOn;
        }
        parent.addEventListener('change', function () {
            children.forEach(function (c) { c.checked = parent.checked; });
        });
        children.forEach(function (c) { c.addEventListener('change', syncParent); });
        syncParent();
    });

    var ownerCb = document.getElementById('p2');
    if (ownerCb) {
        ownerCb.addEventListener('change', function () {
            if (!ownerCb.checked) return;
            form.querySelectorAll('input[data-child], input[data-parent]').forEach(function (c) {
                c.checked = true;
            });
        });
    }

    var smRootCb = document.getElementById('s14');
    if (smRootCb) {
        smRootCb.addEventListener('change', function () {
            if (!smRootCb.checked) return;
            form.querySelectorAll('input[data-sm-flag]').forEach(function (c) {
                if (c !== smRootCb) c.checked = true;
            });
        });
    }

    var WEB_BITS = {
        p2:  16777216,    p4:  1,           p5:  2,           p6:  4,
        p7:  8,           p9:  16,          p10: 32,          p11: 64,
        p12: 128,         p14: 256,         p16: 1024,        p17: 2048,
        p18: 4096,        p19: 8192,        p20: 16384,       p22: 32768,
        p23: 65536,       p24: 131072,      p25: 262144,      p26: 524288,
        p28: 1048576,     p29: 2097152,     p30: 4194304,     p31: 8388608,
        p32: 67108864,    p33: 33554432,    p34: 134217728,   p36: 268435456,
        p37: 536870912,   p38: 1073741824,  p39: 2147483648
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

    function collectOverrides() {
        var list = [];
        form.querySelectorAll('[data-override-row]').forEach(function (row) {
            list.push({
                id:     parseInt(row.getAttribute('data-override-id'), 10) || 0,
                type:   row.querySelector('[data-override-field="type"]').value,
                name:   row.querySelector('[data-override-field="name"]').value,
                access: row.querySelector('[data-override-field="access"]').value
            });
        });
        return list;
    }

    function collectNewOverride() {
        var n = document.getElementById('new_override_name');
        if (!n || !n.value.trim()) return null;
        return {
            type:   document.getElementById('new_override_type').value,
            name:   n.value,
            access: document.getElementById('new_override_access').value
        };
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        setMsg('groupname', '');

        var name = document.getElementById('groupname').value.trim();
        if (!name) {
            setMsg('groupname', 'Group name is required.');
            return;
        }

        var btn = form.querySelector('[data-testid="admin-edit-group-save"]');
        if (btn) setBusy(btn, true);

        var params = {
            gid: gid, type: type, name: name,
            web_flags: type === 'web' ? collectWebFlags() : 0,
            srv_flags: type === 'srv' ? collectSrvFlags() : ''
        };
        if (type === 'srv') {
            params.overrides    = JSON.stringify(collectOverrides());
            var fresh = collectNewOverride();
            params.new_override = fresh ? JSON.stringify(fresh) : '';
        }

        window.sb.api.call(window.Actions.GroupsEdit, params).then(function (r) {
            window.SBPP.showToast({
                kind: 'success',
                title: 'Group updated',
                body: 'The group has been updated successfully.'
            });
            setTimeout(function () {
                window.location.href = 'index.php?p=admin&c=groups';
            }, 1500);
        }).catch(function (err) {
            if (btn) setBusy(btn, false);
            if (err && err.field) setMsg(err.field, err.msg || 'Validation error');
        });
    });
})();
</script>
