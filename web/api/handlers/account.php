<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

function api_account_check_password(array $params): array
{
    global $userbank;
    $aid  = (int)($params['aid']  ?? 0);
    $pass = (string)($params['password'] ?? '');

    // The dispatcher already enforces is_logged_in() for non-public actions,
    // but check the aid match here so a logged-in user can only probe their
    // own password. Without this, any admin could brute-force any other
    // admin's password through the API.
    if ($aid !== $userbank->GetAid()) {
        $affected = $userbank->GetProperty('user', $aid);
        Log::add(LogType::Warning, 'Hacking Attempt',
            $userbank->GetProperty('user') . " tried to check {$affected}'s password, but doesn't have access.");
        return Api::redirect('index.php?p=login&m=no_access');
    }

    $GLOBALS['PDO']->query("SELECT password FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $row = $GLOBALS['PDO']->single();

    return ['matches' => (bool)($row && password_verify($pass, $row['password']))];
}

function api_account_check_srv_password(array $params): array
{
    global $userbank;
    $aid  = (int)($params['aid']  ?? 0);
    $pass = (string)($params['password'] ?? '');

    if (!$userbank->is_logged_in() || $aid !== $userbank->GetAid()) {
        $affected = $userbank->GetProperty('user', $aid);
        Log::add(LogType::Warning, 'Hacking Attempt',
            $userbank->GetProperty('user') . " tried to check {$affected}'s server password, but doesn't have access.");
        return Api::redirect('index.php?p=login&m=no_access');
    }

    $GLOBALS['PDO']->query("SELECT srv_password FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $row = $GLOBALS['PDO']->single();

    $matches = !($row['srv_password'] !== null && $row['srv_password'] !== $pass);
    return ['matches' => $matches];
}

function api_account_change_password(array $params): array
{
    global $userbank, $username;
    $aid     = (int)($params['aid']     ?? 0);
    $newPass = (string)($params['new_password'] ?? '');
    $oldPass = (string)($params['old_password'] ?? '');

    if ($aid !== $userbank->GetAid() && !$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAdmins))) {
        Log::add(LogType::Warning, 'Hacking Attempt',
            ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " tried to change a password without permission.");
        return Api::redirect('index.php?p=login&m=no_access');
    }

    if (!$userbank->isCurrentPasswordValid($aid, $oldPass)) {
        throw new ApiError('bad_password', 'Current password doesn\'t match.', 'current');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET password = :password WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':password', password_hash($newPass, PASSWORD_BCRYPT));
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $admin = $GLOBALS['PDO']->single();

    Log::add(LogType::Message, 'Password Changed', "Password changed for admin ({$admin['user']})");
    Auth::logout();

    return Api::redirect('index.php?p=login');
}

function api_account_change_srv_password(array $params): array
{
    global $userbank, $username;
    $aid  = (int)($params['aid'] ?? 0);
    $pass = (string)($params['srv_password'] ?? '');

    if (!$userbank->is_logged_in() || $aid !== $userbank->GetAid()) {
        $affected = $userbank->GetProperty('user', $aid);
        Log::add(LogType::Warning, 'Hacking Attempt',
            "$username tried to change {$affected}'s server password, but doesn't have access.");
        return Api::redirect('index.php?p=login&m=no_access');
    }

    if ($pass === 'NULL' || $pass === '') {
        $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_password = NULL WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':aid', $aid);
        $GLOBALS['PDO']->execute();
    } else {
        $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_password = :pass WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':pass', $pass);
        $GLOBALS['PDO']->bind(':aid',  $aid);
        $GLOBALS['PDO']->execute();
    }

    Log::add(LogType::Message, 'Srv Password Changed', "Password changed for admin ($aid)");

    return [
        'message' => [
            'title'  => 'Server Password changed',
            'body'   => 'Your server password has been changed successfully.',
            'kind'   => 'green',
            'redir'  => 'index.php?p=account',
        ],
    ];
}

function api_account_change_email(array $params): array
{
    global $userbank, $username;
    $aid      = (int)($params['aid'] ?? 0);
    $email    = (string)($params['email']    ?? '');
    $password = (string)($params['password'] ?? '');

    if (!$userbank->is_logged_in() || $aid !== $userbank->GetAid()) {
        Log::add(LogType::Warning, 'Hacking Attempt',
            "$username tried to change " . $userbank->GetProperty('user', $aid) . "'s email, but doesn't have access.");
        return Api::redirect('index.php?p=login&m=no_access');
    }

    if (!password_verify($password, $userbank->getProperty('password'))) {
        throw new ApiError('bad_password', 'The password you supplied is wrong.', 'emailpw');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiError('bad_email', 'You must type a valid email address.', 'email1');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET email = :email WHERE aid = :aid");
    $GLOBALS['PDO']->bind(':email', $email);
    $GLOBALS['PDO']->bind(':aid', $aid);
    $GLOBALS['PDO']->execute();

    Log::add(LogType::Message, 'E-mail Changed', "E-mail changed for admin ($aid).");

    return [
        'message' => [
            'title' => 'E-mail address changed',
            'body'  => 'Your E-mail address has been changed successfully.',
            'kind'  => 'green',
            'redir' => 'index.php?p=account',
        ],
    ];
}

/**
 * @return array{tokens: list<array{id: int, name: string, token_prefix: string, created: int, last_used: int|null, expires_at: int|null}>}
 */
function api_account_tokens_list(array $params): array
{
    global $userbank;
    return ['tokens' => \Sbpp\Rest\PatAuthenticator::listForAid($userbank->GetAid())];
}

/**
 * @param array{name?: string, expires_days?: int|string|null} $params
 * @return array{id: int, name: string, token: string, token_prefix: string, created: int, expires_at: int|null}
 */
function api_account_tokens_create(array $params): array
{
    global $userbank;
    $name = trim((string) ($params['name'] ?? ''));
    if ($name === '' || strlen($name) > 64) {
        throw new ApiError('validation', 'Give this token a name (1 to 64 characters).', 'name');
    }

    $daysRaw = $params['expires_days'] ?? 0;
    $days = is_numeric($daysRaw) ? (int) $daysRaw : -1;
    if (!in_array($days, [0, 30, 90, 365], true)) {
        throw new ApiError('validation', 'Expiry must be never, 30, 90, or 365 days.', 'expires_days');
    }
    $expiresAt = $days === 0 ? null : time() + ($days * 86400);

    $minted = \Sbpp\Rest\PatAuthenticator::mint($userbank->GetAid(), $name, $expiresAt);
    Log::add(LogType::Message, 'API token created', 'API token "' . $name . '" created.');

    return [
        'id' => $minted['id'],
        'name' => $minted['name'],
        'token' => $minted['secret'],
        'token_prefix' => $minted['prefix'],
        'created' => $minted['created'],
        'expires_at' => $minted['expires_at'],
    ];
}

/**
 * @param array{id?: int|string} $params
 * @return array{revoked: int}
 */
function api_account_tokens_revoke(array $params): array
{
    global $userbank;
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) {
        throw new ApiError('validation', 'Token id is required.', 'id');
    }
    if (!\Sbpp\Rest\PatAuthenticator::revoke($userbank->GetAid(), $id)) {
        throw new ApiError('not_found', 'Token not found.');
    }
    Log::add(LogType::Message, 'API token revoked', 'API token #' . $id . ' revoked.');
    return ['revoked' => $id];
}
