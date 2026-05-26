<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

/**
 * Shared helpers for the `admin.edit.*` cluster (issue sbpp/goals#5,
 * Phase 2.5).
 *
 * Pre-fix every page handler in the cluster carried its own ad-hoc
 * `<script>ShowBox(…)</script>` emitter, its own `errorScript .= "$('id.msg')…"`
 * MooTools idiom for per-field validation errors, and its own copy
 * of the rehash-sid SELECT. With sourcebans.js gone since #1123 D1
 * the legacy emitters are silent no-ops; users see no feedback.
 *
 * Each handler now collects:
 *   - per-field errors in `array<string,string> $validationErrors`,
 *   - whether the submit succeeded in `bool $postSuccess`,
 *   - the rehash-target server ids in `list<int> $postRehashSids`,
 * then calls {@see sbpp_admin_edit_emit_tail_script()} once at the
 * bottom of the page. The tail script is vanilla JS that:
 *   1. Replays errors into the matching `<id>.msg` divs the
 *      templates already render.
 *   2. Surfaces a `window.SBPP.showToast(...)` success toast on
 *      `postSuccess`.
 *   3. Optionally fires `Actions.SystemRehashAdmins` for the
 *      affected sids.
 *   4. Redirects after a short delay.
 *
 * `setBusy` lives in theme.js — no change needed here.
 */

if (!defined('IN_SB')) {
    die('You should not be here. Only follow links!');
}

if (!function_exists('sbpp_admin_edit_die_with_toast')) {
    /**
     * Emit a self-contained `<script>` that surfaces an error toast
     * via `window.SBPP.showToast` and redirects after a short delay,
     * then ends the request. Replaces the legacy
     * `echo '<div id="msg-red">…</div>'; PageDie();` shape.
     */
    function sbpp_admin_edit_die_with_toast(string $message, string $redirect): void
    {
        $jsFlags    = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
        $msgJs      = json_encode($message,  $jsFlags);
        $redirectJs = json_encode($redirect, $jsFlags);
        echo <<<HTML
<script>
(function () {
    var SBPP = window.SBPP;
    if (SBPP && typeof SBPP.showToast === 'function') {
        SBPP.showToast({ kind: 'error', title: 'Error', body: {$msgJs} });
    }
    setTimeout(function () { window.location.href = {$redirectJs}; }, 1500);
})();
</script>
HTML;
        if (function_exists('PageDie')) {
            PageDie();
        } else {
            exit;
        }
    }
}

if (!function_exists('sbpp_admin_edit_emit_tail_script')) {
    /**
     * Emit the tail vanilla-JS block that replays validation errors,
     * shows a success toast, optionally rehashes, and redirects.
     *
     * @param array<string,string> $validationErrors Field id -> message.
     *   Field id matches the `<id>.msg` div the templates render
     *   (e.g. `adminname`, `steam`, `email`, `password`, `password2`,
     *   `a_serverpass`, `groupname`, `name`, `folder`, `address`,
     *   `port`, `rcon2`).
     * @param list<int> $rehashSids Server ids to rehash; emitted as a
     *   `Actions.SystemRehashAdmins` call when non-empty.
     */
    function sbpp_admin_edit_emit_tail_script(
        string $successTitle,
        string $successBody,
        string $successRedirect,
        bool $postSuccess,
        array $rehashSids,
        array $validationErrors,
    ): void {
        $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

        $errorsJs   = json_encode($validationErrors, $jsFlags);
        $titleJs    = json_encode($successTitle,    $jsFlags);
        $bodyJs     = json_encode($successBody,     $jsFlags);
        $redirectJs = json_encode($successRedirect, $jsFlags);
        $sidsJs     = json_encode(array_map('intval', $rehashSids), $jsFlags);
        $successFlag = $postSuccess ? 'true' : 'false';

        echo <<<HTML
<script>
(function () {
    'use strict';
    var validationErrors = {$errorsJs};
    var rehashSids       = {$sidsJs};
    var postSuccess      = {$successFlag};
    var successTitle     = {$titleJs};
    var successBody      = {$bodyJs};
    var successRedirect  = {$redirectJs};

    function setMsg(field, text) {
        var el = document.getElementById(field + '.msg');
        if (!el) return;
        if (text) {
            el.textContent = text;
            el.style.display = 'block';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function toast(kind, title, body) {
        var SBPP = window.SBPP;
        if (SBPP && typeof SBPP.showToast === 'function') {
            SBPP.showToast({ kind: kind, title: title, body: body });
        }
    }

    function applyErrors() {
        var keys = Object.keys(validationErrors);
        for (var i = 0; i < keys.length; i++) {
            setMsg(keys[i], validationErrors[keys[i]]);
        }
    }

    function fireRehash(then) {
        var api = window.sb && window.sb.api;
        var Actions = window.Actions;
        if (!api || !Actions || !Actions.SystemRehashAdmins || !rehashSids.length) {
            then();
            return;
        }
        api.call(Actions.SystemRehashAdmins, { servers: rehashSids.join(',') })
            .then(function () { then(); })
            .catch(function () { then(); });
    }

    function go() {
        if (Object.keys(validationErrors).length > 0) {
            applyErrors();
            toast('error', 'Please fix the highlighted fields.', '');
            return;
        }
        if (postSuccess) {
            toast('success', successTitle, successBody);
            fireRehash(function () {
                setTimeout(function () {
                    if (successRedirect) window.location.href = successRedirect;
                }, 1500);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', go);
    } else {
        go();
    }
})();
</script>
HTML;
    }
}

if (!function_exists('sbpp_admin_edit_collect_rehash_sids')) {
    /**
     * Mirror of the rehash-sids SELECT used across the cluster: every
     * server the admin still has access to (per-server or via group)
     * after the change. Returns the deduplicated sid list as plain
     * ints — the caller hands it to the JSON
     * `Actions.SystemRehashAdmins` action.
     *
     * @return list<int>
     */
    function sbpp_admin_edit_collect_rehash_sids(int $adminId): array
    {
        $rows = $GLOBALS['PDO']->query(
            "SELECT s.sid FROM `:prefix_servers` s
                LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = ?
                LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
                WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
                    OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
                AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id))
                AND s.enabled = 1"
        )->resultset([$adminId]);

        $sids = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['sid'] ?? 0);
            if ($sid > 0 && !in_array($sid, $sids, true)) {
                $sids[] = $sid;
            }
        }
        return $sids;
    }
}

if (!function_exists('sbpp_admin_edit_lookup_admin_field')) {
    /**
     * Look up an admin row's `user` column by an alternate field —
     * used when reporting "this Steam ID / email is already taken
     * by …". `$field` is one of `'authid'`, `'email'` (the two
     * fields any admin-edit handler asks about). Returns an empty
     * string when no row matches.
     */
    function sbpp_admin_edit_lookup_admin_field(
        \Sbpp\Auth\UserManager $userbank,
        string $field,
        string $value,
    ): string {
        foreach ($userbank->GetAllAdmins() as $admin) {
            if (($admin[$field] ?? '') === $value) {
                return (string) ($admin['user'] ?? '');
            }
        }
        return '';
    }
}
