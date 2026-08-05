<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

use SteamID\SteamID;

/**
 * Delete an admin row + their server group memberships (#1352).
 *
 * Modern JSON twin of the v1.x sourcebans.js `RemoveAdmin()` helper
 * (deleted at #1123 D1) — `page_admin_admins_list.tpl` wires the
 * trash-can button through `Actions.AdminsRemove` via the
 * `#admins-delete-dialog` confirm + reason modal. There is no
 * legacy GET fallback for `o=remove` (the v1.x JS helper went
 * straight to xajax then to this handler), so this is the single
 * delete path; the modal's no-JS / no-dispatcher fallback just
 * lands the operator back on the admins list.
 *
 * Inputs:
 *   - `aid`     (int, required)    — the admin id to remove. The
 *     handler refuses to delete a row that holds `ADMIN_OWNER`.
 *   - `ureason` (string, optional) — admin-supplied reason. We trim
 *     it and append `Reason: …` to the audit-log entry when
 *     non-empty. Empty / omitted is allowed (the modal carries
 *     `aria-required="false"`); admin deletion is a lifecycle
 *     action, not a moderation flip, so we don't gate the call on
 *     it the way `bans.unban` / `comms.unblock` do.
 *
 * @param array{ aid?: int|string, ureason?: string } $params
 * @return array{
 *     remove: string,
 *     counter: array{ admincount: int },
 *     rehash: string|null,
 *     message: array{ title: string, body: string, kind: string, redir: string }
 * }
 */
function api_admins_remove(array $params): array
{
    $aid = (int)($params['aid'] ?? 0);
    // Trim whitespace so a textarea that contains only spaces produces an
    // empty reason (audit-log suffix omitted) rather than `Reason:    `.
    $ureason = trim((string)($params['ureason'] ?? ''));

    $admin = $GLOBALS['PDO']->query("SELECT gid, authid, extraflags, user FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();

    if ($admin && ((int) $admin['extraflags'] & ADMIN_OWNER) !== 0) {
        throw new ApiError('cannot_delete_owner', 'Error: You cannot delete the owner.');
    }

    // Snapshot issuer names onto bans/comms before the admin row disappears
    // so historical lists keep showing who issued the action (#1509).
    if ($admin) {
        $snapName = (string) ($admin['user'] ?? '');
        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_bans` SET admin_name = :user WHERE aid = :aid AND admin_name = ''"
        );
        $GLOBALS['PDO']->bind(':user', $snapName);
        $GLOBALS['PDO']->bind(':aid', $aid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query(
            "UPDATE `:prefix_comms` SET admin_name = :user WHERE aid = :aid AND admin_name = ''"
        );
        $GLOBALS['PDO']->bind(':user', $snapName);
        $GLOBALS['PDO']->bind(':aid', $aid);
        $GLOBALS['PDO']->execute();
    }

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_admins` WHERE aid = :aid LIMIT 1");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $ok = $GLOBALS['PDO']->execute();

    $allservers = [];
    if ($ok) {
        $allservers = _api_admins_rehash_sids($aid);

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
        $GLOBALS['PDO']->bind(':aid', $aid);
        $GLOBALS['PDO']->execute();
    }

    $cnt = (int)($GLOBALS['PDO']->query("SELECT count(aid) AS cnt FROM `:prefix_admins`")->single()['cnt'] ?? 0);

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was an error removing the admin from the database, please check the logs');
    }

    // #1352: trail the optional admin-supplied reason in the audit-log
    // entry so admins reading the log later can see *why* the admin was
    // removed. Mirrors the canonical "Reason: $ureason" suffix shape
    // from `api_bans_unban` / `api_comms_unblock` — the suffix is
    // omitted when the operator left the field blank.
    $logBody = "Admin ({$admin['user']}) has been deleted.";
    if ($ureason !== '') {
        $logBody .= " Reason: {$ureason}";
    }
    Log::add(LogType::Message, 'Admin Deleted', $logBody);

    return [
        'remove'  => "aid_$aid",
        'counter' => ['admincount' => $cnt],
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Admin Deleted',
            'body'  => 'The selected admin has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=admins',
        ],
    ];
}

/**
 * Soft-retire an admin: keeps the row (and ban/comm attribution) but
 * blocks panel login and SourceMod admin load via `enabled = 0`.
 *
 * @param array{aid?: int|string, ureason?: string} $params
 * @return array{aid: int, enabled: int, rehash: string|null, message: array{title: string, body: string, kind: string}}
 */
function api_admins_deactivate(array $params): array
{
    $aid = (int)($params['aid'] ?? 0);
    $ureason = trim((string)($params['ureason'] ?? ''));

    $admin = $GLOBALS['PDO']->query(
        "SELECT user, extraflags, enabled FROM `:prefix_admins` WHERE aid = :aid"
    );
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();

    if (!$admin) {
        throw new ApiError('not_found', 'Admin not found.');
    }
    if (((int) $admin['extraflags'] & ADMIN_OWNER) !== 0) {
        throw new ApiError('cannot_deactivate_owner', 'Error: You cannot deactivate the owner.');
    }
    if ((int) ($admin['enabled'] ?? 1) === 0) {
        throw new ApiError('already_inactive', 'That admin is already inactive.');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET enabled = 0 WHERE aid = :aid LIMIT 1");
    $GLOBALS['PDO']->bind(':aid', $aid);
    if (!$GLOBALS['PDO']->execute()) {
        throw new ApiError('deactivate_failed', 'There was an error deactivating the admin.');
    }

    $allservers = _api_admins_rehash_sids($aid);
    $logBody = "Admin ({$admin['user']}) has been deactivated.";
    if ($ureason !== '') {
        $logBody .= " Reason: {$ureason}";
    }
    Log::add(LogType::Message, 'Admin Deactivated', $logBody);

    return [
        'aid'     => $aid,
        'enabled' => 0,
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Admin deactivated',
            'body'  => $admin['user'] . ' can no longer log in or use in-game admin. Ban history still shows their name.',
            'kind'  => 'green',
        ],
    ];
}

/**
 * Restore a soft-retired admin (`enabled = 1`).
 *
 * @param array{aid?: int|string, ureason?: string} $params
 * @return array{aid: int, enabled: int, rehash: string|null, message: array{title: string, body: string, kind: string}}
 */
function api_admins_reactivate(array $params): array
{
    $aid = (int)($params['aid'] ?? 0);
    $ureason = trim((string)($params['ureason'] ?? ''));

    $admin = $GLOBALS['PDO']->query(
        "SELECT user, enabled FROM `:prefix_admins` WHERE aid = :aid"
    );
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();

    if (!$admin) {
        throw new ApiError('not_found', 'Admin not found.');
    }
    if ((int) ($admin['enabled'] ?? 1) === 1) {
        throw new ApiError('already_active', 'That admin is already active.');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET enabled = 1 WHERE aid = :aid LIMIT 1");
    $GLOBALS['PDO']->bind(':aid', $aid);
    if (!$GLOBALS['PDO']->execute()) {
        throw new ApiError('reactivate_failed', 'There was an error reactivating the admin.');
    }

    $allservers = _api_admins_rehash_sids($aid);
    $logBody = "Admin ({$admin['user']}) has been reactivated.";
    if ($ureason !== '') {
        $logBody .= " Reason: {$ureason}";
    }
    Log::add(LogType::Message, 'Admin Reactivated', $logBody);

    return [
        'aid'     => $aid,
        'enabled' => 1,
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Admin reactivated',
            'body'  => $admin['user'] . ' can log in and use in-game admin again.',
            'kind'  => 'green',
        ],
    ];
}

/**
 * Apply one lifecycle / group op to many admin ids. Partial success:
 * owner / self / not-found / already-* rows land in `skipped` and the
 * rest still commit. Per-op permission is re-checked inside so a caller
 * holding only EDIT_ADMINS cannot deactivate via this entry point.
 *
 * Inputs:
 *   - `op`    (string, required) — `deactivate` | `reactivate` | `remove`
 *     | `set_web_group` | `set_srv_group`
 *   - `aids`  (list of int, required, max 100)
 *   - `ureason` (string, optional) — deactivate / remove
 *   - `gid`   (int, required for set_web_group) — 0 clears web group
 *   - `srv_group_id` (int, required for set_srv_group) — 0 clears SM group
 *
 * @param array{
 *     op?: string,
 *     aids?: list<int|string>,
 *     ureason?: string,
 *     gid?: int|string,
 *     srv_group_id?: int|string
 * } $params
 * @return array{
 *     op: string,
 *     applied: list<int>,
 *     skipped: list<array{aid: int, reason: string}>,
 *     rehash: string|null,
 *     message: array{title: string, body: string, kind: string}
 * }
 */
function api_admins_bulk(array $params): array
{
    global $userbank;

    $op = trim((string) ($params['op'] ?? ''));
    $allowedOps = ['deactivate', 'reactivate', 'remove', 'set_web_group', 'set_srv_group'];
    if (!in_array($op, $allowedOps, true)) {
        throw new ApiError('validation', 'Unknown bulk op.', 'op');
    }

    $rawAids = $params['aids'] ?? null;
    if (!is_array($rawAids) || $rawAids === []) {
        throw new ApiError('validation', 'Select at least one admin.', 'aids');
    }
    if (count($rawAids) > 100) {
        throw new ApiError('validation', 'Bulk selection is limited to 100 admins.', 'aids');
    }

    $aids = [];
    foreach ($rawAids as $raw) {
        $aid = (int) $raw;
        if ($aid > 0 && !in_array($aid, $aids, true)) {
            $aids[] = $aid;
        }
    }
    if ($aids === []) {
        throw new ApiError('validation', 'Select at least one admin.', 'aids');
    }

    $isLifecycle = in_array($op, ['deactivate', 'reactivate', 'remove'], true);
    if ($isLifecycle) {
        if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteAdmins))) {
            throw new ApiError('forbidden', 'You do not have permission to deactivate or delete admins.');
        }
    } else {
        if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAdmins))) {
            throw new ApiError('forbidden', 'You do not have permission to edit admin groups.');
        }
    }

    $ureason = trim((string) ($params['ureason'] ?? ''));
    $actorAid = (int) $userbank->GetAid();
    $gid = (int) ($params['gid'] ?? 0);
    $srvGroupId = (int) ($params['srv_group_id'] ?? 0);

    if ($op === 'set_web_group' && !array_key_exists('gid', $params)) {
        throw new ApiError('validation', 'Web group is required.', 'gid');
    }
    if ($op === 'set_srv_group' && !array_key_exists('srv_group_id', $params)) {
        throw new ApiError('validation', 'Server group is required.', 'srv_group_id');
    }

    $applied = [];
    $skipped = [];
    $rehashSids = [];

    foreach ($aids as $aid) {
        if ($isLifecycle && $aid === $actorAid && in_array($op, ['deactivate', 'remove'], true)) {
            $skipped[] = ['aid' => $aid, 'reason' => 'self'];
            continue;
        }

        try {
            $result = match ($op) {
                'deactivate' => api_admins_deactivate(['aid' => $aid, 'ureason' => $ureason]),
                'reactivate' => api_admins_reactivate(['aid' => $aid, 'ureason' => $ureason]),
                'remove' => api_admins_remove(['aid' => $aid, 'ureason' => $ureason]),
                'set_web_group' => _api_admins_set_web_group($aid, $gid),
                'set_srv_group' => _api_admins_set_srv_group($aid, $srvGroupId),
            };
            $applied[] = $aid;
            if (!empty($result['rehash'])) {
                foreach (explode(',', $result['rehash']) as $sid) {
                    $sid = (int) $sid;
                    if ($sid > 0 && !in_array($sid, $rehashSids, true)) {
                        $rehashSids[] = $sid;
                    }
                }
            }
        } catch (ApiError $e) {
            $skipped[] = ['aid' => $aid, 'reason' => $e->errorCode];
        }
    }

    $appliedN = count($applied);
    $skippedN = count($skipped);
    $titles = [
        'deactivate' => 'Admins deactivated',
        'reactivate' => 'Admins reactivated',
        'remove' => 'Admins deleted',
        'set_web_group' => 'Web group updated',
        'set_srv_group' => 'Server group updated',
    ];
    $verbs = [
        'deactivate' => 'deactivated',
        'reactivate' => 'reactivated',
        'remove' => 'deleted',
        'set_web_group' => 'updated',
        'set_srv_group' => 'updated',
    ];
    $body = $appliedN . ' ' . $verbs[$op];
    if ($skippedN > 0) {
        $body .= ', ' . $skippedN . ' skipped';
    }
    $body .= '.';

    return [
        'op' => $op,
        'applied' => $applied,
        'skipped' => $skipped,
        'rehash' => $rehashSids ? implode(',', $rehashSids) : null,
        'message' => [
            'title' => $titles[$op],
            'body' => $body,
            'kind' => $appliedN > 0 ? 'green' : 'red',
        ],
    ];
}

/**
 * @return array{rehash: string|null}
 */
function _api_admins_set_web_group(int $aid, int $gid): array
{
    $admin = $GLOBALS['PDO']->query(
        "SELECT aid, user, password, email, extraflags FROM `:prefix_admins` WHERE aid = :aid"
    );
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();
    if (!$admin) {
        throw new ApiError('not_found', 'Admin not found.');
    }

    if ($gid > 0) {
        $group = $GLOBALS['PDO']->query(
            "SELECT gid FROM `:prefix_groups` WHERE gid = :gid AND type != 3"
        );
        $GLOBALS['PDO']->bind(':gid', $gid);
        if (!$GLOBALS['PDO']->single()) {
            throw new ApiError('validation', 'Unknown web group.', 'gid');
        }
        $password = (string) ($admin['password'] ?? '');
        $email = (string) ($admin['email'] ?? '');
        if ($password === '' || $email === '') {
            throw new ApiError(
                'missing_credentials',
                'Admins need a password and email before you can give them web permissions.',
            );
        }
    }

    $persistGid = max(0, $gid);
    $GLOBALS['PDO']->query('UPDATE `:prefix_admins` SET `gid` = :gid WHERE `aid` = :aid');
    $GLOBALS['PDO']->bind(':gid', $persistGid);
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    $allservers = _api_admins_rehash_sids($aid);
    Log::add(
        LogType::Message,
        "Admin's Groups Updated",
        "Admin ({$admin['user']}) web group has been updated.",
    );

    return ['rehash' => $allservers ? implode(',', $allservers) : null];
}

/**
 * @return array{rehash: string|null}
 */
function _api_admins_set_srv_group(int $aid, int $srvGroupId): array
{
    $admin = $GLOBALS['PDO']->query(
        "SELECT aid, user, extraflags FROM `:prefix_admins` WHERE aid = :aid"
    );
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();
    if (!$admin) {
        throw new ApiError('not_found', 'Admin not found.');
    }

    $resolvedGroupName = '';
    $persistId = 0;
    if ($srvGroupId > 0) {
        $GLOBALS['PDO']->query('SELECT id, name FROM `:prefix_srvgroups` WHERE id = :id');
        $GLOBALS['PDO']->bind(':id', $srvGroupId);
        $row = $GLOBALS['PDO']->single();
        if (!$row) {
            throw new ApiError('validation', 'Unknown server group.', 'srv_group_id');
        }
        $resolvedGroupName = (string) ($row['name'] ?? '');
        $persistId = (int) $row['id'];
    }

    $GLOBALS['PDO']->query('UPDATE `:prefix_admins` SET `srv_group` = :name WHERE `aid` = :aid');
    $GLOBALS['PDO']->bind(':name', $resolvedGroupName);
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query(
        'UPDATE `:prefix_admins_servers_groups` SET `group_id` = :gid WHERE `admin_id` = :aid'
    );
    $GLOBALS['PDO']->bind(':gid', $persistId > 0 ? $persistId : -1);
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    $allservers = _api_admins_rehash_sids($aid);
    Log::add(
        LogType::Message,
        "Admin's Groups Updated",
        "Admin ({$admin['user']}) server group has been updated.",
    );

    return ['rehash' => $allservers ? implode(',', $allservers) : null];
}

/**
 * Server SIDs that need `sm_rehash` after an admin access change.
 *
 * @return list<int>
 */
function _api_admins_rehash_sids(int $aid): array
{
    if (!Config::getBool('config.enableadminrehashing')) {
        return [];
    }

    $rows = $GLOBALS['PDO']->query("SELECT s.sid FROM `:prefix_servers` s
        LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = ?
        LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
        WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
        OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
        AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1")->resultset([$aid]);

    $allservers = [];
    foreach ($rows as $r) {
        if (!in_array($r['sid'], $allservers, true)) {
            $allservers[] = $r['sid'];
        }
    }
    return $allservers;
}

function api_admins_add(array $params): array
{
    global $userbank;

    $mask        = (int)($params['mask'] ?? 0);
    $srvMask     = (string)($params['srv_mask'] ?? '');
    $name        = (string)($params['name'] ?? '');
    // #1420 — defer `SteamID::toSteam2()` until AFTER the strict-shape
    // gate below. Pre-fix this line called `toSteam2()` directly on
    // the raw input; `resolveInputID()` throws a generic `\Exception`
    // for unrecognised shapes (`'asdf'`, `'12345'`, … the same
    // garbage the comms / bans handlers ate) which escaped the
    // handler and surfaced as a 500. See "JSON API" / "SteamID inputs"
    // in AGENTS.md for the contract; this file is the third reference
    // shape after `api_comms_add` and `api_bans_add`.
    $rawSteam    = trim((string)($params['steam'] ?? ''));
    $email       = (string)($params['email'] ?? '');
    $password    = (string)($params['password']  ?? '');
    $password2   = (string)($params['password2'] ?? '');
    $sg          = (string)($params['server_group'] ?? '-2');
    $wg          = (string)($params['web_group']    ?? '-2');
    $serverPass  = (string)($params['server_password'] ?? '-1');
    $webName     = (string)($params['web_name']    ?? '');
    $serverName  = (string)($params['server_name'] ?? '');
    $servers     = (string)($params['servers']     ?? '');
    $singleSrv   = (string)($params['single_servers'] ?? '');

    if ($serverName === '0' || $serverName === '') $serverName = null;

    // #1402 (adversarial review HIGH 1) — OWNER-flag privilege-escalation
    // guard. Mirrors api_admins_edit_perms's check (see below in this
    // file). Two ways an attacker could land `ADMIN_OWNER` on disk via
    // this handler:
    //   1. `web_group === 'c'` (Custom permissions) + `mask & ADMIN_OWNER`
    //      → goes straight onto `:prefix_admins.extraflags`.
    //   2. `web_group === 'n'` (New admin group) + `mask & ADMIN_OWNER`
    //      → baked into the new `:prefix_groups.flags`, after which any
    //      future admin assigned to that group inherits it.
    // Both shapes carry the OWNER bit in the inbound `mask` param, so a
    // single check on `$mask & ADMIN_OWNER` covers both. The bare check
    // does NOT close the existing-group escalation surface (assigning a
    // pre-existing OWNER-bearing group to a new admin via `web_group` =
    // an integer > 0); that path was pre-existing pre-#1402 and is
    // tracked separately. The UI side mirrors this by gating the OWNER
    // checkbox itself on `can_grant_owner` so non-owners don't see the
    // affordance — defense in depth.
    if (!$userbank->HasAccess(WebPermission::Owner) && ($mask & ADMIN_OWNER)) {
        Log::add(LogType::Warning, 'Hacking Attempt',
            $userbank->GetProperty('user') . ' tried to grant OWNER while adding an admin, but doesnt have access.');
        return Api::redirect('index.php?p=login&m=no_access');
    }

    // Validation -------------------------------------------------------
    if (empty($name)) {
        throw new ApiError('validation', 'You must type a name for the admin.', 'name');
    }
    if (str_contains($name, "'")) {
        throw new ApiError('validation', "An admin name can not contain a \"'\".", 'name');
    }
    if ($userbank->isNameTaken($name)) {
        throw new ApiError('validation', 'An admin with this name already exists', 'name');
    }
    if ($rawSteam === '') {
        throw new ApiError('validation', 'You must type a Steam ID or Community ID for the admin.', 'steam');
    }
    // #1420 — strict anchored regex mirrors the form template's
    // client-side `pattern` (HTML's `pattern` is implicitly `^…$`),
    // so a curl-driven caller can't smuggle embedded-substring
    // garbage past the gate (`'asdf 76561197960265728 garbage'`
    // matches `SteamID::isValidID`'s unanchored substring regex and
    // `toSteam2()` then emits a negative-Z-component canonical form
    // into `:prefix_admins.authid`).
    if (!preg_match(SteamID::HANDLER_STRICT_REGEX, $rawSteam)) {
        throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID.', 'steam');
    }
    $steam = SteamID::toSteam2($rawSteam);
    if ($userbank->isSteamIDTaken($steam)) {
        $taken = '';
        foreach ($userbank->GetAllAdmins() as $a) {
            if ($a['authid'] === $steam) { $taken = $a['user']; break; }
        }
        throw new ApiError('validation', "Admin " . htmlspecialchars($taken) . " already uses this Steam ID.", 'steam');
    }
    if (empty($email)) {
        if ($mask !== 0) {
            throw new ApiError('validation', 'You must type an e-mail address.', 'email');
        }
    } else if ($userbank->isEmailTaken($email)) {
        $taken = '';
        foreach ($userbank->GetAllAdmins() as $a) {
            if ($a['email'] === $email) { $taken = $a['user']; break; }
        }
        throw new ApiError('validation', 'This email address is already being used by ' . htmlspecialchars($taken) . '.', 'email');
    }
    if (empty($password)) {
        throw new ApiError('validation', 'You must type a password.', 'password');
    }
    if (strlen($password) < MIN_PASS_LENGTH) {
        throw new ApiError('validation', 'Your password must be at-least ' . MIN_PASS_LENGTH . ' characters long.', 'password');
    }
    if (empty($password2)) {
        throw new ApiError('validation', 'You must confirm the password', 'password2');
    }
    if ($password !== $password2) {
        throw new ApiError('validation', "Your passwords don't match", 'password2');
    }
    if ($serverPass !== '-1') {
        if ($serverPass === '') {
            throw new ApiError('validation', 'You must type a server password or uncheck the box.', 'a_serverpass');
        }
        if (strlen($serverPass) < MIN_PASS_LENGTH) {
            throw new ApiError('validation', 'Your password must be at-least ' . MIN_PASS_LENGTH . ' characters long.', 'a_serverpass');
        }
    } else {
        $serverPass = '';
    }

    if ($sg === '-2') {
        throw new ApiError('validation', 'You must choose a group.', 'server');
    }
    if ($sg === 'n') {
        if ($serverName === null) {
            throw new ApiError('validation', 'You need to type a name for the new group.', 'servername_err');
        }
        if (str_contains((string)$serverName, ',')) {
            throw new ApiError('validation', "Group name cannot contain a ','", 'servername_err');
        }
    }
    if ($wg === '-2') {
        throw new ApiError('validation', 'You must choose a group.', 'web');
    }
    if ($wg === 'n') {
        if (empty($webName)) {
            throw new ApiError('validation', 'You need to type a name for the new group.', 'webname_err');
        }
        if (str_contains($webName, ',')) {
            throw new ApiError('validation', "Group name cannot contain a ','", 'webname_err');
        }
    }

    // ---- INSERT ------------------------------------------------------
    $immunity = 0;
    if (str_contains($srvMask, '#')) {
        $immunity = (int)substr($srvMask, strpos($srvMask, '#') + 1);
        $srvMask = substr($srvMask, 0, strlen($srvMask) - strlen((string)$immunity) - 1);
    }
    $immunity = max(0, $immunity);

    if ($wg === 'n') {
        $GLOBALS['PDO']->query("INSERT INTO `:prefix_groups`(type, name, flags) VALUES (?, ?, ?)")
            ->execute([1, $webName, $mask]);
        $webGroup = (int)$GLOBALS['PDO']->lastInsertId();
        $mask = 0;
    } elseif ($wg !== 'c' && (int)$wg > 0) {
        $webGroup = (int)$wg;
    } else {
        $webGroup = -1;
    }

    if ($sg === 'n') {
        $GLOBALS['PDO']->query("INSERT INTO `:prefix_srvgroups`(immunity, flags, name, groups_immune) VALUES (?, ?, ?, ?)")
            ->execute([$immunity, $srvMask, $serverName, ' ']);
        $srvGroupName = $serverName;
        $srvGroupId   = (int)$GLOBALS['PDO']->lastInsertId();
        $srvMask = '';
    } elseif ($sg !== 'c' && (int)$sg > 0) {
        $GLOBALS['PDO']->query("SELECT name FROM `:prefix_srvgroups` WHERE id = :id");
        $GLOBALS['PDO']->bind(':id', (int)$sg);
        $row = $GLOBALS['PDO']->single();
        $srvGroupName = $row['name'] ?? null;
        $srvGroupId   = (int)$sg;
    } else {
        $srvGroupName = '';
        $srvGroupId   = -1;
    }

    $aid = $userbank->AddAdmin($name, $steam, $password, $email, $webGroup, $mask, $srvGroupName, $srvMask, $immunity, $serverPass);

    if ($aid <= -1) {
        throw new ApiError('create_failed', 'The admin failed to be added to the database. Check the logs for any SQL errors.');
    }

    foreach (explode(',', $servers) as $g) {
        if ($g !== '') {
            $GLOBALS['PDO']->query(
                "INSERT INTO `:prefix_admins_servers_groups`(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)"
            )->execute([$aid, $srvGroupId, substr($g, 1), '-1']);
        }
    }
    foreach (explode(',', $singleSrv) as $s) {
        if ($s !== '') {
            $GLOBALS['PDO']->query(
                "INSERT INTO `:prefix_admins_servers_groups`(admin_id,group_id,srv_group_id,server_id) VALUES (?,?,?,?)"
            )->execute([$aid, $srvGroupId, '-1', substr($s, 1)]);
        }
    }

    $allservers = [];
    if (Config::getBool('config.enableadminrehashing')) {
        $rows = $GLOBALS['PDO']->query("SELECT s.sid FROM `:prefix_servers` s
            LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = ?
            LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
            WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
            OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
            AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1")->resultset([$aid]);
        foreach ($rows as $r) {
            if (!in_array($r['sid'], $allservers, true)) $allservers[] = $r['sid'];
        }
    }

    Log::add(LogType::Message, 'Admin added', "Admin ($name) has been added.");

    return [
        'aid'     => $aid,
        'reload'  => true,
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Admin Added',
            'body'  => 'The admin has been added successfully',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=admins',
        ],
    ];
}

function api_admins_edit_perms(array $params): array
{
    global $userbank;
    $aid       = (int)($params['aid'] ?? 0);
    $webFlags  = (int)($params['web_flags'] ?? 0);
    $srvFlags  = (string)($params['srv_flags'] ?? '');

    if ($aid === 0) {
        throw new ApiError('bad_request', 'aid is required');
    }
    if (!$userbank->HasAccess(WebPermission::Owner) && ($webFlags & ADMIN_OWNER)) {
        Log::add(LogType::Warning, 'Hacking Attempt',
            $userbank->GetProperty('user') . ' tried to gain OWNER admin permissions, but doesnt have access.');
        return Api::redirect('index.php?p=login&m=no_access');
    }

    $password = $userbank->GetProperty('password', $aid);
    $email    = $userbank->GetProperty('email',    $aid);
    if ($webFlags > 0 && (empty($password) || empty($email))) {
        throw new ApiError('missing_credentials',
            'Admins have to have a password and email set in order to get web permissions. ' .
            '<a href="index.php?p=admin&c=admins&o=editdetails&id=' . $aid . '">Set the details</a> first and try again.');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET extraflags = :flags WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':flags', $webFlags);
    $GLOBALS['PDO']->bind(':aid',   $aid);
    $GLOBALS['PDO']->execute();

    $immunity = 0;
    if (str_contains($srvFlags, '#')) {
        $immunity = (int)substr($srvFlags, strpos($srvFlags, '#') + 1);
        $srvFlags = substr($srvFlags, 0, strlen($srvFlags) - strlen((string)$immunity) - 1);
    }
    $immunity = max(0, $immunity);

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_flags = ?, immunity = ? WHERE aid = ?")
        ->execute([$srvFlags, $immunity, $aid]);

    $allservers = [];
    if (Config::getBool('config.enableadminrehashing')) {
        $rows = $GLOBALS['PDO']->query("SELECT s.sid FROM `:prefix_servers` s
            LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = ?
            LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
            WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
            OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
            AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1")->resultset([$aid]);
        foreach ($rows as $r) {
            if (!in_array($r['sid'], $allservers, true)) $allservers[] = $r['sid'];
        }
    }

    $admin = $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = ?")->single([$aid]);
    Log::add(LogType::Message, 'Permissions Changed', "Permissions have been changed for ({$admin['user']})");

    return [
        'reload'  => true,
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Permissions updated',
            'body'  => "The user's permissions have been updated successfully",
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=admins',
        ],
    ];
}

function api_admins_generate_password(array $params): array
{
    return ['password' => Crypto::genPassword()];
}
