<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use SteamID\SteamID;

function api_comms_add(array $params): array
{
    global $userbank;
    // See api_bans_add: the JSON API delivers raw UTF-8, so the
    // legacy xajax-era `htmlspecialchars_decode` would clobber a
    // literal `&amp;` and double-escape every subsequent render now
    // that Smarty auto-escapes (#1087). Store raw, escape on display.
    $nickname = (string)($params['nickname'] ?? '');
    $type     = (int)($params['type']   ?? 0);
    $steam    = SteamID::toSteam2(trim((string)($params['steam']  ?? '')));
    $length   = (int)($params['length'] ?? 0);
    $reason   = (string)($params['reason'] ?? '');

    if (empty($steam)) {
        throw new ApiError('validation', 'You must type a Steam ID or Community ID', 'steam');
    }
    if (!SteamID::isValidID($steam)) {
        throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID', 'steam');
    }
    if (!in_array($type, [1, 2, 3], true)) {
        throw new ApiError('validation', 'Invalid block type. Must be one of: gag (1), mute (2), or both (3).', 'type');
    }
    if ($length < 0) {
        throw new ApiError('validation', 'Length must be positive or 0', 'length');
    }
    // Block lengths are stored in INT(11) seconds. Cap at ~100 years to
    // keep `UNIX_TIMESTAMP() + length*60` below 2^31-1 and avoid silently
    // producing rows whose `ends` lands in 1970.
    if ($length > 60 * 24 * 365 * 100) {
        throw new ApiError('validation', 'Length is unrealistically large.', 'length');
    }

    $len = $length ? $length * 60 : 0;

    PruneComms();

    $typeW = match ($type) {
        1 => "type = 1",
        2 => "type = 2",
        3 => "(type = 1 OR type = 2)",
    };

    $chk = $GLOBALS['PDO']->query(
        "SELECT count(bid) AS count FROM `:prefix_comms` WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND " . $typeW
    )->single([$steam]);

    if ((int)$chk['count'] > 0) {
        throw new ApiError('already_blocked', "SteamID: $steam is already blocked.");
    }

    foreach ($userbank->GetAllAdmins() as $admin) {
        if ($admin['authid'] === $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
            throw new ApiError('immune', "SteamID: Admin {$admin['user']} ($steam) is immune.");
        }
    }

    if ($type === 1 || $type === 3) {
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_comms`(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
            (UNIX_TIMESTAMP(),1,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
        )->execute([$steam, $nickname, $length * 60, $len, $reason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR'] ?? '']);
    }
    if ($type === 2 || $type === 3) {
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_comms`(created,type,authid,name,ends,length,reason,aid,adminIp ) VALUES
            (UNIX_TIMESTAMP(),2,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
        )->execute([$steam, $nickname, $length * 60, $len, $reason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    Log::add(LogType::Message, 'Block Added', "Block against ($steam) has been added. Reason: $reason; Length: $length");

    return [
        'reload' => true,
        'block'  => ['steam' => $steam, 'type' => $type, 'length' => $len],
    ];
}

function api_comms_prepare_reblock(array $params): array
{
    $bid = (int)($params['bid'] ?? 0);

    $row = $GLOBALS['PDO']->query("SELECT name, authid, type, length, reason FROM `:prefix_comms` WHERE bid = ?;")
        ->single([$bid]);

    return [
        'bid'      => $bid,
        'nickname' => $row['name']   ?? '',
        'steam'    => $row['authid'] ?? '',
        'length'   => (int)($row['length'] ?? 0),
        'type'     => (int)($row['type']   ?? 1) - 1,
        'reason'   => $row['reason'] ?? '',
    ];
}

/**
 * Lift an active gag/mute on a comm row (#1207 ADM-5/ADM-6).
 *
 * Modern JSON twin of the legacy `?p=commslist&a=ungag|unmute&id=…&key=…`
 * GET handlers in `page.commslist.php`. The legacy GET path stays put
 * (no-JS fallback for the icon-only theme leg + third-party themes that
 * still ship the v1.x action links), but the comms list's visible-action
 * affordance now wires through this action so the row can update
 * in-place + show a toast without a full page reload. Permission gate
 * mirrors the legacy GET handler exactly:
 *
 *   ADMIN_OWNER | ADMIN_UNBAN     — unconditional, lift any row.
 *   ADMIN_UNBAN_OWN_BANS          — only the row's own admin (`aid`).
 *   ADMIN_UNBAN_GROUP_BANS        — only rows where `gid` matches the
 *                                   caller's `gid`.
 *
 * The dispatcher gate is `ADMIN_OWNER | ADMIN_UNBAN |
 * ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS` — the broadest "any
 * unban-ish flag" match — and the per-row precision check happens
 * inside the handler, since the dispatcher can't see which row the
 * caller wants to act on.
 *
 * Inputs:
 *   - `bid`     (int, required) — the comm-block id.
 *   - `ureason` (string, optional) — admin-supplied unblock reason; we
 *     trim and store as-is. Stored raw in `ureason` (per the
 *     "store raw, escape on display" anti-pattern); the column lives
 *     behind the same Smarty auto-escape pipeline as `reason`.
 *
 * @return array{ bid: int, state: string, type: int }
 */
function api_comms_unblock(array $params): array
{
    global $userbank;

    $bid = (int)($params['bid'] ?? 0);
    if ($bid <= 0) {
        throw new ApiError('bad_request', 'bid must be a positive integer', 'bid');
    }
    $ureason = trim((string)($params['ureason'] ?? ''));

    $row = $GLOBALS['PDO']->query(
        "SELECT C.bid, C.authid, C.name, C.type, C.length, C.ends, C.RemoveType, C.aid, A.gid AS gid
         FROM `:prefix_comms` AS C
         LEFT JOIN `:prefix_admins` AS A ON A.aid = C.aid
         WHERE C.bid = ?"
    )->single([$bid]);

    if (!$row) {
        throw new ApiError('not_found', 'Block not found.', null, 404);
    }

    // Mirror the legacy GET handler's per-row precision check: dispatcher
    // already accepted the caller for "some unban flag", but we only let
    // them through if the SPECIFIC row matches what their flag covers.
    $rowAid = (int)($row['aid'] ?? 0);
    $rowGid = (int)($row['gid'] ?? 0);
    $callerGid = (int)$userbank->GetProperty('gid');
    $allowed =
        $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban))
        || ($userbank->HasAccess(WebPermission::UnbanOwnBans)   && $rowAid === $userbank->GetAid())
        || ($userbank->HasAccess(WebPermission::UnbanGroupBans) && $rowGid === $callerGid);
    if (!$allowed) {
        throw new ApiError('forbidden', "You don't have access to this block.", null, 403);
    }

    if (!empty($row['RemoveType'])) {
        throw new ApiError('not_active', 'Block has already been lifted.', null, 409);
    }
    $length = (int)$row['length'];
    $ends   = (int)$row['ends'];
    if ($length > 0 && $ends <= time()) {
        throw new ApiError('not_active', 'Block has already expired.', null, 409);
    }

    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comms` SET `RemovedBy` = ?, `RemoveType` = ?, `RemovedOn` = UNIX_TIMESTAMP(), `ureason` = ? WHERE `bid` = ?"
    )->execute([$userbank->GetAid(), BanRemoval::Unbanned->value, $ureason, $bid]);

    $type = (int)$row['type'];
    $cmd  = $type === 1 ? 'sc_fw_unmute' : ($type === 2 ? 'sc_fw_ungag' : '');
    if ($cmd !== '') {
        $blocked = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE `enabled`=1")->resultset();
        foreach ($blocked as $tempban) {
            rcon($cmd . ' ' . (string)$row['authid'], (int)$tempban['sid']);
        }
    }

    $verb = $type === 1 ? 'UnMuted' : ($type === 2 ? 'UnGagged' : 'Unblocked');
    Log::add(LogType::Message, "Player $verb", "{$row['name']} ({$row['authid']}) has been " . strtolower($verb) . '.');

    return [
        'bid'   => $bid,
        'state' => 'unmuted',
        'type'  => $type,
    ];
}

/**
 * Delete a comm row (#1207 ADM-5).
 *
 * Modern JSON twin of `?p=commslist&a=delete&id=…&key=…`. Hard-deletes
 * the row and, if the row was still active, runs `sc_fw_un{mute,gag}`
 * on every enabled server so the in-game state matches.
 *
 * Permission gate (dispatcher-enforced) is `ADMIN_OWNER | ADMIN_DELETE_BAN`,
 * mirroring the legacy GET handler. Note the broader "delete any
 * comm row" reach: comm rows don't have the per-admin / per-group
 * delete flag the bans table has, so a single dispatcher gate is enough.
 *
 * Input: `bid` (int, required).
 *
 * @return array{ bid: int, deleted: bool }
 */
function api_comms_delete(array $params): array
{
    $bid = (int)($params['bid'] ?? 0);
    if ($bid <= 0) {
        throw new ApiError('bad_request', 'bid must be a positive integer', 'bid');
    }

    $row = $GLOBALS['PDO']->query(
        "SELECT name, authid, ends, length, RemoveType, type, UNIX_TIMESTAMP() AS now FROM `:prefix_comms` WHERE bid = ?"
    )->single([$bid]);

    if (!$row) {
        throw new ApiError('not_found', 'Block not found.', null, 404);
    }

    $type = (int)$row['type'];
    $cmd  = $type === 1 ? 'sc_fw_unmute' : ($type === 2 ? 'sc_fw_ungag' : '');

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_comms` WHERE `bid` = ?")->execute([$bid]);

    // Lift the in-game state ONLY if the row was still active. A row that
    // was already lifted/expired shouldn't fire an rcon command — the
    // server already ran one when the original action lifted the row.
    $end    = (int)$row['ends'];
    $length = (int)$row['length'];
    $now    = (int)$row['now'];
    if (empty($row['RemoveType']) && $cmd !== '' && ($length === 0 || $end > $now)) {
        $blocked = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE `enabled`=1")->resultset();
        foreach ($blocked as $tempban) {
            rcon($cmd . ' ' . (string)$row['authid'], (int)$tempban['sid']);
        }
    }

    Log::add(LogType::Message, 'Block Deleted', "Block {$row['name']} ({$row['authid']}) has been deleted.");

    return [
        'bid'     => $bid,
        'deleted' => true,
    ];
}

function api_comms_paste(array $params): array
{
    $sid  = (int)($params['sid']  ?? 0);
    $name = (string)($params['name'] ?? '');

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't connect to server!");
    }

    foreach (parseRconStatus($ret) as $player) {
        if ($player['name'] === $name) {
            return [
                'nickname' => $player['name'],
                'steam'    => $player['steamid'],
            ];
        }
    }

    throw new ApiError('player_not_found', "Can't get player info for " . htmlspecialchars($name) . '. Player is not on the server anymore!');
}

function api_comms_prepare_block_from_ban(array $params): array
{
    $bid = (int)($params['bid'] ?? 0);
    $row = $GLOBALS['PDO']->query("SELECT name, authid FROM `:prefix_bans` WHERE bid = ?;")
        ->single([$bid]);

    return [
        'bid'      => $bid,
        'nickname' => $row['name']   ?? '',
        'steam'    => $row['authid'] ?? '',
    ];
}

/**
 * Comm-block feed for the player-detail drawer's Comms tab (#1165).
 *
 * Looks up the player's Steam ID from `:prefix_bans` for the supplied
 * `bid` and returns every gag/mute on file for the same Steam ID. Action
 * is registered public to match `bans.detail` / `bans.player_history` —
 * comms-list is a public surface (`?p=commslist`) so the drawer's Comms
 * tab follows the same reach. Admin-name exposure is gated by
 * `banlist.hideadminname` for non-admin callers, mirroring how
 * `bans.detail` handles it.
 *
 * Inputs: `bid` (int, the drawer's current ban id).
 *
 * @return array{
 *   items: list<array{
 *     bid: int, type: int, type_label: string,
 *     created: int, created_human: string,
 *     length_seconds: int, length_human: string,
 *     expires_at: int|null, expires_at_human: string|null,
 *     state: string, reason: string,
 *     admin_name: string|null,
 *     removed_by: string|null, removed_at: int|null, removed_at_human: string|null
 *   }>,
 *   total: int
 * }
 */
function api_comms_player_history(array $params): array
{
    global $userbank;

    $bid = (int)($params['bid'] ?? 0);
    if ($bid <= 0) {
        throw new ApiError('bad_request', 'bid must be a positive integer', 'bid');
    }

    $isAdmin   = $userbank->is_admin();
    $hideAdmin = Config::getBool('banlist.hideadminname') && !$isAdmin;

    $anchor = $GLOBALS['PDO']->query("SELECT authid FROM `:prefix_bans` WHERE bid = ?")
        ->single([$bid]);
    if (!$anchor) {
        throw new ApiError('not_found', 'Ban not found.', null, 404);
    }

    $authid = (string)($anchor['authid'] ?? '');
    if ($authid === '') {
        // The anchor was an IP-only ban. Comm-blocks are keyed off
        // authid (no IP column on `:prefix_comms`), so there's nothing
        // we can match against — return an empty feed cleanly rather
        // than 404'ing on what is a legitimate state.
        return ['items' => [], 'total' => 0];
    }

    $rows = $GLOBALS['PDO']->query(
        "SELECT C.bid, C.type, C.created, C.ends, C.length, C.reason,
                C.RemovedOn, C.RemovedBy, C.RemoveType,
                AD.user AS admin_name
           FROM `:prefix_comms` AS C
      LEFT JOIN `:prefix_admins` AS AD ON C.aid = AD.aid
          WHERE C.authid = ?
       ORDER BY C.created DESC, C.bid DESC
          LIMIT 100"
    )->resultset([$authid]);

    $items = [];
    foreach ($rows as $r) {
        $created = (int)$r['created'];
        $length  = (int)$r['length'];
        $ends    = (int)$r['ends'];
        $type    = (int)$r['type'];
        $rowRemoval = BanRemoval::tryFrom((string) ($r['RemoveType'] ?? ''));

        $state = match (true) {
            $rowRemoval === BanRemoval::Unbanned, $rowRemoval === BanRemoval::Deleted => 'unblocked',
            $rowRemoval === BanRemoval::Expired => 'expired',
            $length === 0                       => 'permanent',
            $ends > 0 && $ends < time()         => 'expired',
            default                             => 'active',
        };

        $typeLabel = match ($type) {
            1 => 'Mute',
            2 => 'Gag',
            default => 'Block',
        };

        $removedOn = $r['RemovedOn'] !== null ? (int)$r['RemovedOn'] : null;
        $removedByName = null;
        if ($r['RemovedBy'] !== null && (int)$r['RemovedBy'] > 0 && !$hideAdmin) {
            $removedRow = $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = ?")
                ->single([(int)$r['RemovedBy']]);
            if ($removedRow && !empty($removedRow['user'])) {
                $removedByName = (string)$removedRow['user'];
            }
        }

        $items[] = [
            'bid'              => (int)$r['bid'],
            'type'             => $type,
            'type_label'       => $typeLabel,
            'created'          => $created,
            'created_human'    => Config::time($created),
            'length_seconds'   => $length,
            'length_human'     => $length === 0 ? 'Permanent' : SecondsToString($length),
            'expires_at'       => $length === 0 ? null : $ends,
            'expires_at_human' => $length === 0 ? null : Config::time($ends),
            'state'            => $state,
            'reason'           => (string)$r['reason'],
            'admin_name'       => $hideAdmin ? null : ($r['admin_name'] !== null ? (string)$r['admin_name'] : null),
            'removed_by'       => $removedByName,
            'removed_at'       => $removedOn,
            'removed_at_human' => $removedOn !== null ? Config::time($removedOn) : null,
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}
