<?php

global $theme, $userbank;

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use Sbpp\Mail\Mailer;

// Issue #1207 AUTH-1: a user trying to recover their password is by
// definition logged out, so a logged-in admin (or any authenticated
// visitor) reaching this URL should be sent home rather than rendering
// the form with the admin sidebar leaking around it. Mirrors the
// equivalent guard in page.login.php (which redirects logged-in users
// to `index.php` so the form is never shown to them either).
//
// JS redirect rather than `header('Location:')` because this handler
// runs INSIDE `build()` — `pages/core/header.php`,
// `pages/core/navbar.php`, and `pages/core/title.php` have already
// flushed ~9 KB of HTML (including the admin sidebar this guard exists
// to suppress) by the time we get here. PHP's `header()` is a no-op
// after output starts, so the redirect would silently fail and the
// admin chrome leak the audit screenshot caught would still ship —
// just with the form body missing on top, because `die()` halts
// rendering mid-page. `page.login.php`'s 200-line-old equivalent
// guard (`page.login.php:27-30`) uses the same JS redirect for the
// same reason; mirror it here so the user-observable behaviour is
// identical to the login surface.
if ($userbank->is_logged_in()) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Issue #1102: when normal login is disabled the entire password-recovery
// flow is meaningless (a reset password can't be used to log in), and the
// reachable form would otherwise let an unauthenticated visitor probe for
// registered email addresses via the "not_registered" error. Bounce both
// the form and the recovery-link branch back to the login page.
if (!Config::getBool('config.enablenormallogin')) {
    header('Location: index.php?p=login');
    die();
}

if (isset($_GET['email'], $_GET['validation']) && (!empty($_GET['email']) || !empty($_GET['validation']))) {
    $email = $_GET['email'];
    $validation = $_GET['validation'];

    if (is_array($email) || is_array($validation)) {
        print "<script>ShowBox('Error', 'Invalid request.', 'red');</script>";
        Log::add("w", "Hacking attempt", "Attempted SQL-Injection.");
        PageDie();
    }

    if (strlen((string) $validation) < 10) {
        print "<script>ShowBox('Error', 'Invalid validation string.', 'red');</script>";
        PageDie();
    }

    $GLOBALS['PDO']->query("SELECT aid, user FROM `:prefix_admins` WHERE `email` = :email AND `validate` = :validate");
    $GLOBALS['PDO']->bind(':email', $email);
    $GLOBALS['PDO']->bind(':validate', $validation);
    $result = $GLOBALS['PDO']->single();

    if (empty($result['aid'])) {
        print "<script>ShowBox('Error', 'The validation string does not match the email for this reset request.', 'red');</script>";
        PageDie();
    }

    // #1269: send the new password BEFORE mutating the DB row so a
    // misconfigured mailer (e.g. an upgraded panel where the 1.x
    // installer left `config.smtp.*` empty) doesn't silently lock the
    // admin out. Pre-fix: the password was rolled to a random string,
    // mail send was attempted, the result was assigned to $isEmailSent
    // but never checked, and the user got "Your password has been
    // reset and sent to your email" regardless. With mail broken that
    // means the old password is gone, the new password was never
    // delivered, and the validation token is consumed — no recovery
    // path. Now: roll only after the mailer has accepted the message;
    // surface the error otherwise so the user can retry once SMTP is
    // configured.
    $password    = Crypto::genSecret(MIN_PASS_LENGTH + 8);
    $isEmailSent = Mail::send($email, EmailType::PasswordResetSuccess, [
        '{password}' => $password,
        '{name}'     => $result['user'],
        '{home}'     => Host::complete(true)
    ]);

    if (!$isEmailSent) {
        print "<script>ShowBox('Error', 'Could not send the new password by email. Your old password is still active. Please contact an administrator if this persists.', 'red');</script>";
        PageDie();
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET `password` = :password, `validate` = NULL WHERE `aid` = :aid");
    $GLOBALS['PDO']->bind(':password', password_hash($password, PASSWORD_BCRYPT));
    $GLOBALS['PDO']->bind(':aid', $result['aid']);
    $GLOBALS['PDO']->execute();

    print "<script>ShowBox('Password Reset', 'Your password has been reset and sent to your email.<br />Please check your spam folder too.<br />Please login using this password, <br />then use the change password link in Your Account.', 'blue');</script>";
    PageDie();
} else {
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\LostPasswordView());
}
