<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;

function _api_auth_get_user(string $username): ?array
{
    $GLOBALS['PDO']->query("SELECT aid, password, attempts, lockout_until FROM `:prefix_admins` WHERE user = :user");
    $GLOBALS['PDO']->bind(':user', $username);
    $row = $GLOBALS['PDO']->single();
    return $row ?: null;
}

function api_auth_login(array $params): array
{
    $username = (string)($params['username'] ?? '');
    $password = (string)($params['password'] ?? '');
    $rememberRaw = $params['remember'] ?? null;
    $remember    = $rememberRaw === true || $rememberRaw === 'true';
    $redirect = (string)($params['redirect'] ?? '');

    if (!Config::getBool('config.enablenormallogin')) {
        return Api::redirect('?p=login&m=failed');
    }

    $maxAttempts = 5;
    $lockoutTime = 10 * 60;

    $user = _api_auth_get_user($username);
    if (!$user) {
        return Api::redirect('?p=login&m=failed');
    }

    if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
        $remaining = (strtotime($user['lockout_until']) - time()) / 60;
        return Api::redirect('?p=login&m=locked&time=' . round($remaining));
    }

    if ($password === '') {
        return Api::redirect('?p=login&m=empty_pwd');
    }

    $auth = new NormalAuthHandler($GLOBALS['PDO'], $username, $password, $remember);

    if (!$auth->getResult()) {
        $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET attempts = attempts + 1 WHERE user = :user");
        $GLOBALS['PDO']->bind(':user', $username);
        $GLOBALS['PDO']->execute();

        $user = _api_auth_get_user($username);
        if (($user['attempts'] ?? 0) >= $maxAttempts) {
            $until = date('Y-m-d H:i:s', time() + $lockoutTime);
            $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET lockout_until = :until WHERE user = :user");
            $GLOBALS['PDO']->bind(':until', $until);
            $GLOBALS['PDO']->bind(':user', $username);
            $GLOBALS['PDO']->execute();
            return Api::redirect('?p=login&m=locked&time=' . round($lockoutTime / 60));
        }

        return Api::redirect('?p=login&m=failed');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET attempts = 0, lockout_until = NULL WHERE user = :user");
    $GLOBALS['PDO']->bind(':user', $username);
    $GLOBALS['PDO']->execute();

    return Api::redirect('?' . $redirect);
}

function api_auth_lost_password(array $params): array
{
    if (!Config::getBool('config.enablenormallogin')) {
        throw new ApiError('disabled', 'Normal login is disabled.');
    }

    $email = (string)($params['email'] ?? '');

    $GLOBALS['PDO']->query("SELECT aid, user FROM `:prefix_admins` WHERE email = :email");
    $GLOBALS['PDO']->bind(':email', $email);
    $row = $GLOBALS['PDO']->single();

    if (empty($row['aid'])) {
        throw new ApiError('not_registered', 'The email address you supplied is not registered on the system');
    }

    $validation = Crypto::recoveryHash();
    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET validate = :validate WHERE email = :email");
    $GLOBALS['PDO']->bind(':validate', $validation);
    $GLOBALS['PDO']->bind(':email', $email);
    $GLOBALS['PDO']->execute();

    $url = Host::complete(true) . '/index.php?p=lostpassword&email=' . urlencode($email)
        . '&validation=' . urlencode($validation);

    $sent = Mail::send($email, EmailType::PasswordReset, [
        '{link}' => $url,
        '{name}' => $row['user'],
        '{home}' => Host::complete(true),
    ]);

    if (!$sent) {
        throw new ApiError('mail_failed', 'Error sending email.');
    }

    return [
        'message' => [
            'title' => 'Check E-Mail',
            'body'  => 'Please check your email inbox (and spam) for a link which will help you reset your password.',
            'kind'  => 'blue',
        ],
    ];
}
