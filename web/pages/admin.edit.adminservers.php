<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

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
    sbpp_admin_edit_die_with_toast(
        'No admin id specified. Please only follow links.',
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

if (!$userbank->HasAccess(\WebPermission::mask(\WebPermission::Owner, \WebPermission::EditAdmins))) {
    \Sbpp\Log::add(
        \LogType::Warning,
        'Hacking Attempt',
        $userbank->GetProperty('user') . ' tried to edit '
        . $userbank->GetProperty('user', $adminId) . "'s server access, but doesn't have access.",
    );
    sbpp_admin_edit_die_with_toast(
        "You aren't allowed to edit other admin's server access.",
        'index.php?p=admin&c=admins',
    );
    return;
}

$pdo = $GLOBALS['PDO'];

/**
 * @return list<array{server_id: string, srv_group_id: string}>
 */
$loadAssignments = static function (int $aid) use ($pdo): array {
    $rows = $pdo->query(
        'SELECT `server_id`, `srv_group_id` FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid'
    )->resultset([':aid' => $aid]);

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'server_id'    => (string) ($row['server_id']    ?? '-1'),
            'srv_group_id' => (string) ($row['srv_group_id'] ?? '-1'),
        ];
    }
    return $out;
};

$existingAssignments = $loadAssignments($adminId);

// Resolve the admin's server-group id (via the joined name lookup the
// legacy handler did). Used as the `group_id` column on every new
// assignment row — keeps the legacy semantics intact.
$serverGroupId = (int) ($pdo->query(
    'SELECT id FROM `:prefix_srvgroups` sg, `:prefix_admins` a
        WHERE sg.name = a.srv_group AND a.aid = :aid LIMIT 1'
)->single([':aid' => $adminId])['id'] ?? 0);

$postSuccess    = false;
$postRehashSids = [];

if (isset($_POST['editadminserver'])) {
    \CSRF::rejectIfInvalid();

    /** @var list<int> $serverIds */
    $serverIds = [];
    if (isset($_POST['servers']) && is_array($_POST['servers'])) {
        foreach ($_POST['servers'] as $s) {
            $sid = (int) substr((string) $s, 1);
            if ($sid > 0 && !in_array($sid, $serverIds, true)) {
                $serverIds[] = $sid;
            }
        }
    }

    /** @var list<int> $groupIds */
    $groupIds = [];
    if (isset($_POST['group']) && is_array($_POST['group'])) {
        foreach ($_POST['group'] as $g) {
            $gid = (int) substr((string) $g, 1);
            if ($gid > 0 && !in_array($gid, $groupIds, true)) {
                $groupIds[] = $gid;
            }
        }
    }

    // The `:prefix_admins_servers_groups` table has no UNIQUE
    // covering (admin_id, srv_group_id, server_id) so we can't lean
    // on `INSERT ... ON DUPLICATE KEY UPDATE`. The transaction wrap
    // keeps the DELETE-then-INSERT atomic — a half-applied state was
    // possible pre-rewrite. (Adding the UNIQUE would need a paired
    // schema migration and is out of #5's scope.)
    $pdo->beginTransaction();
    try {
        $pdo->query('DELETE FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid');
        $pdo->bind(':aid', $adminId);
        $pdo->execute();

        $insert = static function (int $sgId, int $sid) use ($pdo, $adminId, $serverGroupId): void {
            $pdo->query(
                'INSERT INTO `:prefix_admins_servers_groups`
                    (admin_id, group_id, srv_group_id, server_id)
                    VALUES (:aid, :gid, :srv_group_id, :server_id)'
            );
            $pdo->bindMultiple([
                ':aid'          => $adminId,
                ':gid'          => $serverGroupId,
                ':srv_group_id' => $sgId,
                ':server_id'    => $sid,
            ]);
            $pdo->execute();
        };

        foreach ($serverIds as $sid) {
            $insert(-1, $sid);
        }
        foreach ($groupIds as $gid) {
            $insert($gid, -1);
        }

        $pdo->endTransaction();
    } catch (\Throwable $e) {
        $pdo->cancelTransaction();
        throw $e;
    }

    $admName = (string) ($pdo->query('SELECT user FROM `:prefix_admins` WHERE aid = :aid')
        ->single([':aid' => $adminId])['user'] ?? '');
    \Sbpp\Log::add(
        \LogType::Message,
        'Admin Servers Updated',
        "Admin ({$admName}) server access has been changed.",
    );

    if (\Config::getBool('config.enableadminrehashing')) {
        // Mirror of the legacy "every server the admin still has
        // access to AFTER the swap" SELECT, plus the "every server
        // the admin used to have access to BEFORE the swap" union
        // (so a removed server still gets a rehash).
        $postRehashSids = sbpp_admin_edit_collect_rehash_sids($adminId);
        foreach ($existingAssignments as $row) {
            $oldSid   = (int) $row['server_id'];
            $oldGroup = (int) $row['srv_group_id'];
            if ($oldSid > 0 && !in_array($oldSid, $postRehashSids, true)) {
                $postRehashSids[] = $oldSid;
            }
            if ($oldGroup > 0) {
                $sids = $pdo->query('SELECT server_id FROM `:prefix_servers_groups` WHERE group_id = :gid')
                    ->resultset([':gid' => $oldGroup]);
                foreach ($sids as $row2) {
                    $sid = (int) ($row2['server_id'] ?? 0);
                    if ($sid > 0 && !in_array($sid, $postRehashSids, true)) {
                        $postRehashSids[] = $sid;
                    }
                }
            }
        }
    }

    $existingAssignments = $loadAssignments($adminId);
    $postSuccess         = true;
}

$serverList = $pdo->query('SELECT * FROM `:prefix_servers`')->resultset();
$groupList  = $pdo->query("SELECT * FROM `:prefix_groups` WHERE type = '3'")->resultset();

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\EditAdminServersView(
    row_count:        count($serverList) + count($groupList),
    group_list:       $groupList,
    server_list:      $serverList,
    assigned_servers: $existingAssignments,
));

sbpp_admin_edit_emit_tail_script(
    successTitle:    'Admin server access updated',
    successBody:     'The admin server access has been updated successfully.',
    successRedirect: 'index.php?p=admin&c=admins',
    postSuccess:     $postSuccess,
    rehashSids:      $postRehashSids,
    validationErrors:[],
);
