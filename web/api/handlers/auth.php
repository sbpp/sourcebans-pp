<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

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

/**
 * Generic response the lost-password handler returns for every reachable
 * branch — registered email, unregistered email, mail-send failure.
 *
 * Single source of truth so a future tweak (#1456 follow-up: copy
 * editing, locale support, etc.) doesn't have to be made in three
 * places and silently desync the wire shape one branch uses from the
 * other — which is exactly how the user-enumeration leak slips back in.
 *
 * The body intentionally uses "If an account is registered to that
 * email address…" rather than "We sent an email to…" so the message
 * is honest in both the matched and unmatched cases. Mirrors
 * Django's password_reset, Rails's devise/recoverable, and GitHub's
 * password-recovery defaults: indistinguishable response for present
 * vs absent accounts is the OWASP-aligned shape (OWASP ASVS v4
 * Credential Recovery §V2.5; OWASP Forgot Password Cheat Sheet §
 * "Return a consistent message"). The wording avoids "admin account
 * on this panel" — the panel URL + form heading already advertise
 * the surface, but the response copy itself should not narrow the
 * scope further (matches the major-framework convention).
 *
 * @return array{message: array{title: string, body: string, kind: string}}
 */
function _api_auth_lost_password_generic_response(): array
{
    return [
        'message' => [
            'title' => 'Check E-Mail',
            'body'  => 'If an account is registered to that email address, '
                . 'a password reset link has been sent. '
                . 'Please check your inbox (and your spam folder).',
            'kind'  => 'blue',
        ],
    ];
}

/**
 * Public password-recovery entrypoint.
 *
 * #1456 — DO NOT reveal whether the supplied email matches a row. The
 * pre-fix shape threw `ApiError('not_registered', …)` on the miss
 * branch, which let an unauthenticated visitor probe the panel for
 * registered email addresses one HTTP request at a time. The
 * post-fix contract:
 *
 *   - Unknown email          -> generic 'Check E-Mail' envelope.
 *   - Known email + send ok  -> generic 'Check E-Mail' envelope.
 *   - Known email + send err -> generic 'Check E-Mail' envelope,
 *                               server-side audit log entry only.
 *   - `config.enablenormallogin` off -> `disabled` error envelope
 *                               (an operator-side toggle, not a
 *                               per-user signal; revealing it does
 *                               not help an attacker enumerate).
 *
 * Audit-log semantics:
 *
 *   - Unknown email: NOT logged. Logging every miss would let an
 *     attacker flood `:prefix_log` with arbitrary garbage and double
 *     as a denial-of-service against the panel's log surface.
 *   - Known email + send ok: not logged at the handler level. The
 *     follow-up reset (`page.lostpassword.php`'s `?validation=…`
 *     branch) logs the actual password change.
 *   - Known email + send err: LogType::Error so an operator can
 *     diagnose the SMTP misconfiguration that's silently swallowing
 *     reset requests (otherwise the failure would be invisible).
 *
 * Caveat (documented in `AGENTS.md` "Public auth surfaces …"):
 * the response-shape uniformity above is the load-bearing privacy
 * gate, but the response-time differential remains — the matched
 * branch performs an SMTP round-trip, the missed branch does not.
 * A determined attacker can still enumerate via timing. Closing
 * that requires either a queued / asynchronous send (out of scope
 * here — the panel has no background worker), or a deliberate
 * pad-the-miss approach that's brittle in practice. Leaving the
 * timing leak open is the documented trade-off; the user-visible
 * envelope-shape leak (which the #1456 reporter saw) is closed.
 */
function api_auth_lost_password(array $params): array
{
    if (!Config::getBool('config.enablenormallogin')) {
        // `disabled` is an operator configuration, not a per-user
        // signal — same value returned for every caller — so the
        // error envelope is intentional here and does NOT enable
        // enumeration. The matching page-handler guard in
        // `page.lostpassword.php` 302s the form away on the same
        // toggle, so curl-driven callers are the only ones that
        // reach this branch.
        throw new ApiError('disabled', 'Normal login is disabled.');
    }

    $email = (string)($params['email'] ?? '');

    $GLOBALS['PDO']->query("SELECT aid, user FROM `:prefix_admins` WHERE email = :email");
    $GLOBALS['PDO']->bind(':email', $email);
    $row = $GLOBALS['PDO']->single();

    // #1456: do NOT branch the response envelope on whether the email
    // matched. Every reachable branch below returns the same
    // _api_auth_lost_password_generic_response() shape, so a hostile
    // caller can't distinguish "this address has an admin account" from
    // "this address does not". The DB writes + SMTP round-trip only
    // run when the row exists — never send an email to an address
    // that did NOT request a reset, since that would turn the form
    // into an open mail relay / spam vector.
    if (empty($row['aid'])) {
        return _api_auth_lost_password_generic_response();
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
        // Log the failure server-side so an operator can fix the SMTP
        // configuration (`Mail::send` already logs the underlying
        // `Mailer::create()` / transport exception via its own
        // `Log::add(LogType::Error, …)` calls, but those don't pin
        // the action that triggered them). Returning the generic
        // envelope means a legitimate user whose reset email failed
        // to send will be told to "check spam" and never receive
        // anything — operationally noisy, but the alternative is
        // surfacing `mail_failed` which would let an attacker
        // distinguish a registered email from an unregistered one
        // simply by toggling whether SMTP is configured (or by
        // submitting many requests; transient SMTP failures are
        // common).
        Log::add(
            LogType::Error,
            'Password reset mail failed',
            'Mail::send returned false while sending the password-reset link for an admin account.'
            . ' Check earlier "Mail not configured" / "Mail error" entries for the underlying cause.'
            . ' The user was shown the standard "check your email" confirmation; no email was sent.'
        );
        return _api_auth_lost_password_generic_response();
    }

    return _api_auth_lost_password_generic_response();
}
