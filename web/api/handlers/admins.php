<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use SteamID\SteamID;

function api_admins_remove(array $params): array
{
    $aid = (int)($params['aid'] ?? 0);

    $admin = $GLOBALS['PDO']->query("SELECT gid, authid, extraflags, user FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();

    if ($admin && ((int) $admin['extraflags'] & ADMIN_OWNER) !== 0) {
        throw new ApiError('cannot_delete_owner', 'Error: You cannot delete the owner.');
    }

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_admins` WHERE aid = :aid LIMIT 1");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $ok = $GLOBALS['PDO']->execute();

    $allservers = [];
    if ($ok) {
        if (Config::getBool('config.enableadminrehashing')) {
            $rows = $GLOBALS['PDO']->query("SELECT s.sid FROM `:prefix_servers` s
                LEFT JOIN `:prefix_admins_servers_groups` asg ON asg.admin_id = ?
                LEFT JOIN `:prefix_servers_groups` sg ON sg.group_id = asg.srv_group_id
                WHERE ((asg.server_id != '-1' AND asg.srv_group_id = '-1')
                OR (asg.srv_group_id != '-1' AND asg.server_id = '-1'))
                AND (s.sid IN(asg.server_id) OR s.sid IN(sg.server_id)) AND s.enabled = 1")->resultset([$aid]);
            foreach ($rows as $r) {
                if (!in_array($r['sid'], $allservers, true)) {
                    $allservers[] = $r['sid'];
                }
            }
        }

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
        $GLOBALS['PDO']->bind(':aid', $aid);
        $GLOBALS['PDO']->execute();
    }

    $cnt = (int)($GLOBALS['PDO']->query("SELECT count(aid) AS cnt FROM `:prefix_admins`")->single()['cnt'] ?? 0);

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was an error removing the admin from the database, please check the logs');
    }

    Log::add('m', 'Admin Deleted', "Admin ({$admin['user']}) has been deleted.");

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

function api_admins_add(array $params): array
{
    global $userbank;

    $mask        = (int)($params['mask'] ?? 0);
    $srvMask     = (string)($params['srv_mask'] ?? '');
    $name        = (string)($params['name'] ?? '');
    $steam       = SteamID::toSteam2((string)($params['steam'] ?? ''));
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
    if (empty($steam)) {
        throw new ApiError('validation', 'You must type a Steam ID or Community ID for the admin.', 'steam');
    }
    if (!SteamID::isValidID($steam)) {
        throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID.', 'steam');
    }
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

    Log::add('m', 'Admin added', "Admin ($name) has been added.");

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
    if (!$userbank->HasAccess(ADMIN_OWNER) && ($webFlags & ADMIN_OWNER)) {
        Log::add('w', 'Hacking Attempt',
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
    Log::add('m', 'Permissions Changed', "Permissions have been changed for ({$admin['user']})");

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

function api_admins_update_perms(array $params): array
{
    global $userbank;
    $type = (int)($params['type'] ?? 0);
    $value = (string)($params['value'] ?? '');

    $permissions = '';
    $id = '';
    if ($type === 1) {
        $id = 'web';
        if ($value === 'c') {
            $permissions = (string)@file_get_contents(TEMPLATES_PATH . '/groups.web.perm.php');
            $permissions = str_replace('{title}', 'Web Admin Permissions', $permissions);
        } elseif ($value === 'n') {
            $permissions = (string)@file_get_contents(TEMPLATES_PATH . '/group.name.php')
                . (string)@file_get_contents(TEMPLATES_PATH . '/groups.web.perm.php');
            $permissions = str_replace(['{name}', '{title}'], ['webname', 'New Group Permissions'], $permissions);
        }
    }
    if ($type === 2) {
        $id = 'server';
        if ($value === 'c') {
            $permissions = (string)file_get_contents(TEMPLATES_PATH . '/groups.server.perm.php');
            $permissions = str_replace('{title}', 'Server Admin Permissions', $permissions);
        } elseif ($value === 'n') {
            $permissions = (string)@file_get_contents(TEMPLATES_PATH . '/group.name.php')
                . (string)@file_get_contents(TEMPLATES_PATH . '/groups.server.perm.php');
            $permissions = str_replace(['{name}', '{title}'], ['servername', 'New Group Permissions'], $permissions);
        }
    }

    return [
        'id'          => $id,
        'permissions' => $permissions,
        'is_owner'    => $userbank->HasAccess(ADMIN_OWNER),
    ];
}

function api_admins_generate_password(array $params): array
{
    return ['password' => Crypto::genPassword()];
}
