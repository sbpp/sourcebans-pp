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

$adminId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($adminId <= 0 || !$userbank->GetProperty('user', $adminId)) {
    if ($adminId > 0) {
        \Sbpp\Log::add(
            \LogType::Error,
            'Getting admin data failed',
            "Can't find data for admin with id {$adminId}.",
        );
    }
    sbpp_admin_edit_die_with_toast('No admin found for that id.', 'index.php?p=admin&c=admins');
    return;
}

$isOwnerEditor = $userbank->HasAccess(\WebPermission::Owner);
$isSelfEdit    = $adminId === $userbank->GetAid();
$canEditTarget = $isOwnerEditor
    || ($userbank->HasAccess(\WebPermission::EditAdmins)
        && (!$userbank->HasAccess(\WebPermission::Owner, $adminId) || $isSelfEdit));

if (!$canEditTarget) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . ' tried to edit '
        . $userbank->GetProperty('user', $adminId) . "'s details, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit this admin's details.",
        'index.php?p=admin&c=admins',
    );
    return;
}

$canEditPasswords = $isOwnerEditor || $isSelfEdit;
$webBitmask        = (int) $userbank->GetProperty('extraflags', $adminId);
$webGroupId        = (int) $userbank->GetProperty('gid', $adminId);
$hasWebPermissions = $webBitmask !== 0 || $webGroupId > 0;

/** @var array<string, string> $validationErrors */
$validationErrors = [];
$postSuccess      = false;
$postRehashSids   = [];

if (isset($_POST['adminname'])) {
    \CSRF::rejectIfInvalid();

    $rawName       = trim((string) $_POST['adminname']);
    $rawSteamInput = trim((string) ($_POST['steam'] ?? ''));
    // #1420 follow-up #2 — validate the raw Steam ID shape BEFORE the
    // `SteamID::toSteam2()` conversion. Pre-fix `toSteam2()` ran on
    // the raw POST value unconditionally; on a garbage input the
    // converter threw `Invalid SteamID input!` from `resolveInputID()`,
    // the exception escaped the page handler unhandled, and the user
    // got a 500 page render instead of the inline per-field "Please
    // enter a valid Steam ID or Community ID" message on the form.
    //
    // The library tightening (follow-up #1) made the throw stricter,
    // making the 500 page render strictly MORE frequent. The
    // validate-before-convert order surfaces the failure on the form
    // as the per-field message; `$steamIsValidShape` carries the
    // distinction (empty vs invalid-but-non-empty) into the validation
    // ladder below so the operator sees the right error wording.
    $steamIsValidShape = $rawSteamInput !== ''
        && \SteamID\SteamID::isValidID($rawSteamInput);
    $resolvedSteam = $steamIsValidShape
        ? (string) \SteamID\SteamID::toSteam2($rawSteamInput)
        : $rawSteamInput;
    $rawEmail      = trim((string) ($_POST['email'] ?? ''));
    $useServerPass = isset($_POST['a_useserverpass']) && $_POST['a_useserverpass'] === 'on';
    $newPassword   = (string) ($_POST['password']  ?? '');
    $newPassword2  = (string) ($_POST['password2'] ?? '');
    $newServerPass = (string) ($_POST['a_serverpass'] ?? '');

    // Identity ----------------------------------------------------------
    if ($rawName === '') {
        $validationErrors['adminname'] = 'You must type a name for the admin.';
    } elseif (str_contains($rawName, "'")) {
        $validationErrors['adminname'] = "An admin name can not contain a \" ' \".";
    } elseif ($rawName !== $userbank->GetProperty('user', $adminId)
        && $userbank->isNameTaken($rawName)) {
        $validationErrors['adminname'] = 'An admin with this name already exists.';
    }

    if ($rawSteamInput === '') {
        $validationErrors['steam'] = 'You must type a Steam ID or Community ID for the admin.';
    } elseif (!$steamIsValidShape) {
        // Non-empty but failed `SteamID::isValidID()` — typo, bypass
        // shape (`STEAM_0:0:` / `asdfSTEAM_0:0:123` / embedded
        // Steam64), or some other invalid format. Surface the
        // distinct error so the operator knows the field needs
        // fixing, not just filling.
        $validationErrors['steam'] = 'Please enter a valid Steam ID or Community ID.';
    } elseif ($resolvedSteam !== $userbank->GetProperty('authid', $adminId)
        && $userbank->isSteamIDTaken($resolvedSteam)) {
        $taker = sbpp_admin_edit_lookup_admin_field($userbank, 'authid', $resolvedSteam);
        $validationErrors['steam'] = $taker !== ''
            ? 'Admin ' . htmlspecialchars($taker) . ' already uses this Steam ID.'
            : 'This Steam ID is already taken.';
    }

    if ($rawEmail === '') {
        if ($hasWebPermissions) {
            $validationErrors['email'] = 'You must type an e-mail address.';
        }
    } elseif ($rawEmail !== $userbank->GetProperty('email', $adminId)
        && $userbank->isEmailTaken($rawEmail)) {
        $taker = sbpp_admin_edit_lookup_admin_field($userbank, 'email', $rawEmail);
        $validationErrors['email'] = $taker !== ''
            ? 'This email address is already being used by ' . htmlspecialchars($taker) . '.'
            : 'This email address is already in use.';
    }

    // Passwords ---------------------------------------------------------
    $passwordChanged   = false;
    $serverPassChanged = false;

    if ($canEditPasswords) {
        if ($newPassword !== '') {
            $passwordChanged = true;
            if (strlen($newPassword) < MIN_PASS_LENGTH) {
                $validationErrors['password'] = 'Your password must be at least '
                    . MIN_PASS_LENGTH . ' characters long.';
            } elseif ($newPassword2 === '') {
                $validationErrors['password2'] = 'You must confirm the password.';
            } elseif ($newPassword !== $newPassword2) {
                $validationErrors['password2'] = "Your passwords don't match.";
            }
        }

        if ($useServerPass) {
            if ($newServerPass !== '') {
                $serverPassChanged = true;
            }
            $existingServerPass = (string) $userbank->GetProperty('srv_password', $adminId);
            if ($newServerPass === '' && $existingServerPass === '') {
                $validationErrors['a_serverpass'] = 'You must type a server password or uncheck the box.';
            } elseif ($newServerPass !== '' && strlen($newServerPass) < MIN_PASS_LENGTH) {
                $validationErrors['a_serverpass'] = 'Your password must be at least '
                    . MIN_PASS_LENGTH . ' characters long.';
            }
        }
    }

    if ($validationErrors === []) {
        $pdo = $GLOBALS['PDO'];

        $pdo->query(
            'UPDATE `:prefix_admins`
                SET `user` = :user, `authid` = :authid, `email` = :email
                WHERE `aid` = :aid'
        );
        $pdo->bindMultiple([
            ':user'   => $rawName,
            ':authid' => $resolvedSteam,
            ':email'  => $rawEmail,
            ':aid'    => $adminId,
        ]);
        $pdo->execute();

        if ($passwordChanged) {
            $pdo->query('UPDATE `:prefix_admins` SET `password` = :pw WHERE `aid` = :aid');
            $pdo->bindMultiple([
                ':pw'  => password_hash($newPassword, PASSWORD_BCRYPT),
                ':aid' => $adminId,
            ]);
            $pdo->execute();
        }

        if ($serverPassChanged) {
            $pdo->query('UPDATE `:prefix_admins` SET `srv_password` = :sp WHERE `aid` = :aid');
            $pdo->bindMultiple([
                ':sp'  => $newServerPass,
                ':aid' => $adminId,
            ]);
            $pdo->execute();
        } elseif (!$useServerPass) {
            $pdo->query('UPDATE `:prefix_admins` SET `srv_password` = NULL WHERE `aid` = :aid');
            $pdo->bind(':aid', $adminId);
            $pdo->execute();
        }

        if (\Config::getBool('config.enableadminrehashing')) {
            $postRehashSids = sbpp_admin_edit_collect_rehash_sids($adminId);
        }

        \Sbpp\Log::add(
            \LogType::Message,
            'Admin Details Updated',
            'Admin (' . $rawName . ') details has been changed.',
        );
        $postSuccess = true;
    }

    // Reflect submitted-but-invalid values back so the form re-paints
    // what the user typed instead of the stored row.
    $userDisplay  = $rawName;
    $authidValue  = $resolvedSteam !== '' ? $resolvedSteam : $rawSteamInput;
    $emailDisplay = $rawEmail;
    $haveServerPw = $useServerPass;
} else {
    $userDisplay  = (string) $userbank->GetProperty('user', $adminId);
    $authidValue  = trim((string) $userbank->GetProperty('authid', $adminId));
    $emailDisplay = (string) $userbank->GetProperty('email', $adminId);
    $rawServerPw  = (string) $userbank->GetProperty('srv_password', $adminId);
    $haveServerPw = $rawServerPw !== '';
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\EditAdminDetailsView(
    user:        $userDisplay,
    authid:      $authidValue,
    email:       $emailDisplay,
    a_spass:     $haveServerPw,
    change_pass: $canEditPasswords,
));

sbpp_admin_edit_emit_tail_script(
    successTitle:   'Admin details updated',
    successBody:    'The admin details have been updated successfully.',
    successRedirect:'index.php?p=admin&c=admins',
    postSuccess:    $postSuccess,
    rehashSids:     $postRehashSids,
    validationErrors:$validationErrors,
);
