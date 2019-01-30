<?php

global $theme;

if (isset($_GET['email'], $_GET['validation']) && (!empty($_GET['email']) || !empty($_GET['validation']))) {
    $email = $_GET['email'];
    $validation = $_GET['validation'];

    if (is_array($email) || is_array($validation)) {
        print "<script>ShowBox('Error', 'Invalid request.', 'red');</script>";
        Log::add("w", "Hacking attempt", "Attempted SQL-Injection.");
        PageDie();
    }

    if (strlen($validation) < 10) {
        print "<script>ShowBox('Error', 'Invalid validation string.', 'red');</script>";
        PageDie();
    }

    $GLOBALS['PDO']->query("SELECT aid, user FROM `:prefix_admins` WHERE `email` = :email AND `validate` = :validate");
    $GLOBALS['PDO']->bind(':email', $email);
    $GLOBALS['PDO']->bind(':validate', $validation);
    $result = $GLOBALS['PDO']->single();

    if (empty($result['aid']) || is_null($result['aid'])) {
        print "<script>ShowBox('Error', 'The validation string does not match the email for this reset request.', 'red');</script>";
        PageDie();
    }

    $password = Crypto::genSecret(MIN_PASS_LENGTH + 8);
    $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET `password` = :password, `validate` = NULL WHERE `aid` = :aid");
    $GLOBALS['PDO']->bind(':password', password_hash($password, PASSWORD_BCRYPT));
    $GLOBALS['PDO']->bind(':aid', $result['aid']);
    $GLOBALS['PDO']->execute();

    $message = "
        Hello $result[user],\n
        Your password reset was successful.\n
        Your password was changed to: $password\n\n
        Login to your SourceBans++ account and change your password in Your Account.
    ";

    $headers = [
        'From' => SB_EMAIL,
        'X-Mailer' => 'PHP/'.phpversion()
    ];

    mail($email, "[SourceBans++] Password Reset", $message, $headers);

    print "<script>ShowBox('Password Reset', 'Your password has been reset and sent to your email.<br />Please check your spam folder too.<br />Please login using this password, <br />then use the change password link in Your Account.', 'blue');</script>";
    PageDie();
} else {
    $theme->display('page_lostpassword.tpl');
}
