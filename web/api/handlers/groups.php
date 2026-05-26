<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

function api_groups_add(array $params): array
{
    global $userbank;
    $name     = (string)($params['name']     ?? '');
    $type     = (string)($params['type']     ?? '');
    $bitmask  = (int)($params['bitmask']     ?? 0);
    $srvflags = (string)($params['srvflags'] ?? '');

    $existsWeb = $GLOBALS['PDO']->query("SELECT gid FROM `:prefix_groups` WHERE name = ?")->single([$name]);
    $existsSrv = $GLOBALS['PDO']->query("SELECT id FROM `:prefix_srvgroups` WHERE name = ?")->single([$name]);

    if ($name === '') {
        throw new ApiError('validation', 'Please enter a name for this group.', 'name');
    }
    if (str_contains($name, ',')) {
        throw new ApiError('validation', "You cannot have a comma ',' in a group name.", 'name');
    }
    if ($existsWeb || $existsSrv) {
        throw new ApiError('validation', "A group is already named '$name'", 'name');
    }
    if ($type === '0') {
        throw new ApiError('validation', 'Please choose a type for the group.', 'type');
    }

    $next = (int)($GLOBALS['PDO']->query("SELECT MAX(gid) AS next_gid FROM `:prefix_groups`")->single()['next_gid'] ?? 0) + 1;

    if ($type === '1') {
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_groups` (`gid`, `type`, `name`, `flags`)
            VALUES (?, ?, ?, ?)"
        )->execute([$next, 1, $name, $bitmask]);
    } elseif ($type === '2') {
        $immunity = 0;
        if (str_contains($srvflags, '#')) {
            $immunity = (int)substr($srvflags, strpos($srvflags, '#') + 1);
            $srvflags = substr($srvflags, 0, strlen($srvflags) - strlen((string)$immunity) - 1);
        }
        $immunity = max(0, $immunity);
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_srvgroups`(immunity, flags, name, groups_immune) VALUES (?, ?, ?, ?)"
        )->execute([$immunity, $srvflags, $name, ' ']);
    } elseif ($type === '3') {
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_groups` (`gid`, `type`, `name`, `flags`) VALUES (?, '3', ?, '0')"
        )->execute([$next, $name]);
    }

    Log::add(LogType::Message, 'Group Created', "A new group was created ($name).");

    return [
        'reload'  => true,
        'message' => [
            'title' => 'Group Created',
            'body'  => 'Your group has been successfully created.',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=groups',
        ],
    ];
}

function api_groups_remove(array $params): array
{
    $gid  = (int)($params['gid']  ?? 0);
    $type = (string)($params['type'] ?? '');

    $rehashing = false;
    $allservers = [];

    if ($type === 'web') {
        $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET gid = -1 WHERE gid = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_groups` WHERE gid = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $ok = $GLOBALS['PDO']->execute();
    } elseif ($type === 'server') {
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers_groups` WHERE group_id = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_groups` WHERE gid = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $ok = $GLOBALS['PDO']->execute();
    } else {
        $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_group = NULL WHERE srv_group = (SELECT name FROM `:prefix_srvgroups` WHERE id = :gid)");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_srvgroups` WHERE id = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $ok = $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("DELETE FROM `:prefix_srvgroups_overrides` WHERE group_id = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $GLOBALS['PDO']->execute();
    }

    if (Config::getBool('config.enableadminrehashing')) {
        $rows = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE enabled = 1;")->resultset();
        foreach ($rows as $r) {
            if (!in_array($r['sid'], $allservers, true)) {
                $allservers[] = $r['sid'];
            }
        }
        $rehashing = true;
    }

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was a problem deleting the group from the database. Check the logs for more info');
    }

    Log::add(LogType::Message, 'Group Deleted', "Group ($gid) has been deleted.");

    return [
        'remove'  => "gid_$gid",
        'rehash'  => $rehashing ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Group Deleted',
            'body'  => 'The selected group has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=groups',
        ],
    ];
}

function api_groups_edit(array $params): array
{
    $gid        = (int)($params['gid'] ?? 0);
    $webFlags   = (int)($params['web_flags'] ?? 0);
    $srvFlags   = (string)($params['srv_flags'] ?? '');
    $type       = (string)($params['type'] ?? '');
    $name       = (string)($params['name'] ?? '');
    $overrides  = $params['overrides']    ?? '';
    $newOverride= $params['new_override'] ?? '';

    if ($name === '') {
        throw new ApiError('validation', 'Group name is required.', 'name');
    }

    if ($type === 'web' || $type === 'server') {
        $GLOBALS['PDO']->query("UPDATE `:prefix_groups` SET flags = ?, name = ? WHERE gid = ?")
            ->execute([$webFlags, $name, $gid]);
    }

    if ($type === 'srv') {
        $gname = $GLOBALS['PDO']->query("SELECT name FROM `:prefix_srvgroups` WHERE id = :gid");
        $GLOBALS['PDO']->bind(':gid', $gid);
        $gname = $GLOBALS['PDO']->single();

        $immunity = 0;
        if (str_contains($srvFlags, '#')) {
            $immunity = (int)substr($srvFlags, strpos($srvFlags, '#') + 1);
            $srvFlags = substr($srvFlags, 0, strlen($srvFlags) - strlen((string)$immunity) - 1);
        }
        $immunity = max(0, $immunity);

        $GLOBALS['PDO']->query("UPDATE `:prefix_srvgroups` SET flags = ?, name = ?, immunity = ? WHERE id = ?")
            ->execute([$srvFlags, $name, $immunity, $gid]);

        $oldnames = $GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE srv_group = ?")
            ->resultset([$gname['name'] ?? '']);
        foreach ($oldnames as $o) {
            $GLOBALS['PDO']->query("UPDATE `:prefix_admins` SET srv_group = ? WHERE aid = ?")
                ->execute([$name, (int)$o['aid']]);
        }

        // The JS at sourcebans.js wraps these in JSON.stringify before
        // sending (legacy wire shape from the xajax era), so we still
        // need json_decode here. The xajax-era html_entity_decode is
        // gone: the JSON dispatcher hands us raw UTF-8 (#1108).
        $overridesArr   = is_array($overrides)   ? $overrides   : json_decode((string)$overrides,   true);
        $newOverrideArr = is_array($newOverride) ? $newOverride : json_decode((string)$newOverride, true);

        if (!empty($overridesArr)) {
            foreach ($overridesArr as $override) {
                if (($override['type'] ?? '') !== 'command' && ($override['type'] ?? '') !== 'group') continue;
                $id = (int)($override['id'] ?? 0);
                if (empty($override['name'])) {
                    $GLOBALS['PDO']->query("DELETE FROM `:prefix_srvgroups_overrides` WHERE id = ?;")->execute([$id]);
                    continue;
                }
                $chk = $GLOBALS['PDO']->query(
                    "SELECT * FROM `:prefix_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ? AND id != ?"
                )->resultset([$override['name'], $override['type'], $gid, $id]);
                if (!empty($chk)) {
                    throw new ApiError('duplicate_override',
                        'There already is an override with name "' . htmlspecialchars($override['name']) . '" from the selected type.');
                }
                $GLOBALS['PDO']->query(
                    "UPDATE `:prefix_srvgroups_overrides` SET name = ?, type = ?, access = ? WHERE id = ?;"
                )->execute([$override['name'], $override['type'], $override['access'] ?? '', $id]);
            }
        }

        if (!empty($newOverrideArr) && !empty($newOverrideArr['name'])
            && in_array(($newOverrideArr['type'] ?? ''), ['command', 'group'], true)) {
            $chk = $GLOBALS['PDO']->query(
                "SELECT * FROM `:prefix_srvgroups_overrides` WHERE name = ? AND type = ? AND group_id = ?"
            )->resultset([$newOverrideArr['name'], $newOverrideArr['type'], $gid]);
            if (!empty($chk)) {
                throw new ApiError('duplicate_override',
                    'There already is an override with name "' . htmlspecialchars($newOverrideArr['name']) . '" from the selected type.');
            }
            $GLOBALS['PDO']->query(
                "INSERT INTO `:prefix_srvgroups_overrides` (group_id, type, name, access) VALUES (?, ?, ?, ?);"
            )->execute([$gid, $newOverrideArr['type'], $newOverrideArr['name'], $newOverrideArr['access'] ?? '']);
        }
    }

    $allservers = [];
    if (Config::getBool('config.enableadminrehashing')) {
        $rows = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE enabled = 1;")->resultset();
        foreach ($rows as $r) $allservers[] = $r['sid'];
    }

    Log::add(LogType::Message, 'Group Updated', "Group ($name) has been updated.");

    return [
        'reload'  => true,
        'rehash'  => $allservers ? implode(',', $allservers) : null,
        'message' => [
            'title' => 'Group updated',
            'body'  => 'The group has been updated successfully',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=groups',
        ],
    ];
}
