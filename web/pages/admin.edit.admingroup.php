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
if ($adminId <= 0) {
    sbpp_admin_edit_die_with_toast('No admin id specified. Please only follow links.', 'index.php?p=admin&c=admins');
    return;
}

if (!$userbank->HasAccess(\WebPermission::mask(\WebPermission::Owner, \WebPermission::EditAdmins))) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . ' tried to edit '
        . $userbank->GetProperty('user', $adminId) . "'s groups, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit other admin's groups.",
        'index.php?p=admin&c=admins',
    );
    return;
}

if (!$userbank->GetProperty('user', $adminId)) {
    \Sbpp\Log::add(
        \LogType::Error,
        'Getting admin data failed',
        "Can't find data for admin with id {$adminId}.",
    );
    sbpp_admin_edit_die_with_toast('Error getting current data.', 'index.php?p=admin&c=admins');
    return;
}

/** @var array<string,string> $validationErrors */
$validationErrors = [];
$postSuccess      = false;
$postRehashSids   = [];

// The legacy form submitted POST['wg'] / POST['sg']. The (unused but
// surviving) "Set web group from URL" / "Set server group from URL"
// shortcut paths arrived as GET[wg] / GET[sg]; preserve so existing
// inbound links don't break.
$incomingWg = $_POST['wg'] ?? $_GET['wg'] ?? null;
$incomingSg = $_POST['sg'] ?? $_GET['sg'] ?? null;

if ($incomingWg !== null || $incomingSg !== null) {
    \CSRF::rejectIfInvalid();

    $newWg = (int) $incomingWg;
    $newSg = (int) $incomingSg;

    $existingPassword = (string) $userbank->GetProperty('password', $adminId);
    $existingEmail    = (string) $userbank->GetProperty('email', $adminId);

    if ($newWg > 0 && ($existingPassword === '' || $existingEmail === '')) {
        $validationErrors['wgroup'] = 'Admins need a password and email before you can give them web permissions. '
            . 'Set the details first and try again.';
    } else {
        $pdo = $GLOBALS['PDO'];

        if ($newWg !== -2) {
            $persistWg = $newWg === -1 ? 0 : $newWg;
            $pdo->query('UPDATE `:prefix_admins` SET `gid` = :gid WHERE `aid` = :aid');
            $pdo->bindMultiple([
                ':gid' => $persistWg,
                ':aid' => $adminId,
            ]);
            $pdo->execute();
        }

        if ($newSg !== -2) {
            $resolvedGroupName = '';
            if ($newSg !== -1) {
                $pdo->query('SELECT name FROM `:prefix_srvgroups` WHERE id = :id');
                $pdo->bind(':id', $newSg);
                $row = $pdo->single();
                if ($row) {
                    $resolvedGroupName = (string) ($row['name'] ?? '');
                }
            }

            $pdo->query('UPDATE `:prefix_admins` SET `srv_group` = :name WHERE `aid` = :aid');
            $pdo->bindMultiple([
                ':name' => $resolvedGroupName,
                ':aid'  => $adminId,
            ]);
            $pdo->execute();

            $pdo->query('UPDATE `:prefix_admins_servers_groups` SET `group_id` = :gid WHERE `admin_id` = :aid');
            $pdo->bindMultiple([
                ':gid' => $newSg,
                ':aid' => $adminId,
            ]);
            $pdo->execute();
        }

        if (\Config::getBool('config.enableadminrehashing')) {
            $postRehashSids = sbpp_admin_edit_collect_rehash_sids($adminId);
        }

        $admName = $pdo->query('SELECT user FROM `:prefix_admins` WHERE aid = :aid')
            ->single([':aid' => $adminId])['user'] ?? '';
        \Sbpp\Log::add(
            \LogType::Message,
            "Admin's Groups Updated",
            "Admin ({$admName}) groups have been updated.",
        );
        $postSuccess = true;
    }
}

$webGroupList    = $GLOBALS['PDO']->query('SELECT gid, name FROM `:prefix_groups` WHERE type != 3')->resultset();
$serverGroupList = $GLOBALS['PDO']->query('SELECT id, name FROM `:prefix_srvgroups`')->resultset();

$assignedServerGroupName = (string) $userbank->GetProperty('srv_groups', $adminId);
$assignedServerGroupId   = 0;
foreach ($serverGroupList as $sg) {
    if (($sg['name'] ?? '') === $assignedServerGroupName) {
        $assignedServerGroupId = (int) $sg['id'];
        break;
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\EditAdminGroupView(
    group_admin_name:      (string) $userbank->GetProperty('user', $adminId),
    group_admin_id:        (int) $userbank->GetProperty('gid', $adminId),
    group_lst:             $serverGroupList,
    web_lst:               $webGroupList,
    server_admin_group_id: $assignedServerGroupId,
));

sbpp_admin_edit_emit_tail_script(
    successTitle:    'Admin updated',
    successBody:     'The admin has been updated successfully.',
    successRedirect: 'index.php?p=admin&c=admins',
    postSuccess:     $postSuccess,
    rehashSids:      $postRehashSids,
    validationErrors:$validationErrors,
);
