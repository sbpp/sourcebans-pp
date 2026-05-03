<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

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
        Log::add('w', 'Hacking Attempt',
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
        Log::add('w', 'Hacking Attempt',
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

    if ($aid !== $userbank->GetAid() && !$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_ADMINS)) {
        Log::add('w', 'Hacking Attempt',
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

    Log::add('m', 'Password Changed', "Password changed for admin ({$admin['user']})");
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
        Log::add('w', 'Hacking Attempt',
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

    Log::add('m', 'Srv Password Changed', "Password changed for admin ($aid)");

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
        Log::add('w', 'Hacking Attempt',
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

    Log::add('m', 'E-mail Changed', "E-mail changed for admin ($aid).");

    return [
        'message' => [
            'title' => 'E-mail address changed',
            'body'  => 'Your E-mail address has been changed successfully.',
            'kind'  => 'green',
            'redir' => 'index.php?p=account',
        ],
    ];
}
