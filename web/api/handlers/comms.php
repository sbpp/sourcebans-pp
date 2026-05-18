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

    // Surface the conflicting bid (most recent active row) so the
    // admin can investigate the OTHER active block that's blocking
    // a Re-mute / Re-gag — same shape as `api_bans_add`. Without the
    // bid the operator sees "already blocked" with no path to the
    // row that's holding the gate.
    $chk = $GLOBALS['PDO']->query(
        "SELECT bid FROM `:prefix_comms` WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND " . $typeW . " ORDER BY bid DESC LIMIT 1"
    )->single([$steam]);

    if ($chk) {
        $existingBid = (int)$chk['bid'];
        throw new ApiError('already_blocked', "SteamID: $steam is already blocked by block #$existingBid.");
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
 * #1301: `ureason` is **required**. v1.x prompted via sourcebans.js's
 * UnMute()/UnGag() helpers and required a non-empty reason; v2.0
 * silently accepted '', so the audit log lost the *why*. Both this
 * handler and the legacy GET fallback now bounce empty reasons.
 *
 * Inputs:
 *   - `bid`     (int, required)    — the comm-block id.
 *   - `ureason` (string, required) — admin-supplied unblock reason; we
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
    // #1301: v1.x prompted via sourcebans.js's UnMute()/UnGag() helpers
    // and required a non-empty reason; v2.0 silently accepted '', so the
    // audit log lost the *why*. Both the new modal in page_comms.tpl and
    // the legacy GET handler in page.commslist.php now bounce on empty
    // — this server-side check is the load-bearing gate.
    $ureason = trim((string)($params['ureason'] ?? ''));
    if ($ureason === '') {
        throw new ApiError(
            'validation',
            'You must supply a reason when lifting a block.',
            'ureason'
        );
    }

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
    // #1301: trail the unblock reason in the audit log entry so admins
    // reading the log later can see *why* the block was lifted.
    Log::add(
        LogType::Message,
        "Player $verb",
        sprintf(
            '%s (%s) has been %s. Reason: %s',
            (string) $row['name'],
            (string) $row['authid'],
            strtolower($verb),
            $ureason
        )
    );

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
 * Player-detail drawer envelope for a comm-block focal record.
 *
 * Sister handler to `api_bans_detail` — same envelope shape, same
 * field-by-field hide-* gating, but the focal record is a comm-block
 * (`:prefix_comms` row) instead of a ban. Powers the player drawer
 * when it's opened from a row in the public comms list (the
 * `data-drawer-cid` trigger added in the same change), giving comms
 * the parity-with-banlist UX users expect: click a name, see player
 * id + the focal block + comments, hop into the History / Comms /
 * Notes tabs without leaving the page.
 *
 * Mirroring `api_bans_detail` (rather than rolling a leaner shape):
 *   - `player`, `admin`, `server`, `comments`, `comments_visible`,
 *     `notes_visible` keep the SAME keys so the drawer JS can render
 *     either focal kind through the shared `renderOverviewPane`
 *     branches without per-shape forks.
 *   - The focal record itself lives under `block` (vs `ban`) with
 *     comm-flavoured fields: `type` / `type_label` for mute/gag,
 *     `started_at*` (vs `banned_at*`), `unblock_reason` (vs
 *     `unban_reason`), and the `state` vocab swaps `'unbanned'` for
 *     `'unmuted'` to match the comm column conventions
 *     (`api_comms_unblock`'s return shape). Same vocab the existing
 *     mobile card chrome uses (`pill--unmuted`).
 *   - `cid` echoes the focal id (vs `bid`). `bid` is intentionally
 *     omitted from the envelope so a future caller can't accidentally
 *     bind the comm's own id into a `bans.*` action by mistake — the
 *     drawer's lazy panes consume `player.steam_id` directly per the
 *     `authid` extension on `bans.player_history` /
 *     `comms.player_history`.
 *   - Fields `bans.detail` carries that don't apply to a comm-block
 *     (`demo_count`, `history_count`) are dropped — the comms table
 *     has no demo column and the History tab doesn't read
 *     `history_count` off the envelope (the lazy fetch's items count
 *     is the source of truth there).
 *
 * Public action: matches the public reach of `?p=commslist`. Field-
 * level hide-gating (`banlist.hideplayerips` / `banlist.hideadminname`
 * / `config.enablepubliccomments`) is enforced INSIDE the handler so
 * an anonymous caller sees the same data the public commslist page
 * would show them. Comm rows don't store an IP (`:prefix_comms` has
 * no `ip` column — see the schema dump in `web/install/includes/sql/struc.sql`
 * vs `:prefix_bans`), so `player.ip` is always `null` here regardless
 * of `banlist.hideplayerips`. Carrying the field anyway keeps the
 * envelope shape symmetric with `bans.detail` so `renderOverviewPane`
 * doesn't have to branch on missing keys.
 *
 * Comments are pulled from `:prefix_comments WHERE C.type = 'C' AND
 * C.bid = ?` — the same shape `page.commslist.php`'s comment loader
 * reads, just rebound to the comm focal row's id (the column is
 * named `bid` on `:prefix_comments` even when it points at a comm
 * row id; the disambiguator is the `type` letter `'B'`/`'C'`/`'P'`).
 *
 * Inputs: `cid` (int, the focal comm-block id — matches the
 * `data-drawer-cid` attribute the comms-list template emits).
 *
 * @return array{
 *   cid: int,
 *   player: array{
 *     name: string, steam_id: string, steam_id_3: string,
 *     community_id: string, ip: null, country: string|null
 *   },
 *   block: array{
 *     type: int, type_label: string, reason: string,
 *     started_at: int, started_at_human: string,
 *     length_seconds: int, length_human: string,
 *     expires_at: int|null, expires_at_human: string|null,
 *     state: string, unblock_reason: string,
 *     removed_at: int|null, removed_at_human: string|null,
 *     removed_by: string|null
 *   },
 *   admin: array{name: string|null},
 *   server: array{sid: int, name: string|null, mod_icon: string|null},
 *   comments_visible: bool,
 *   notes_visible: bool,
 *   comments: list<array{cid: int, added: int, added_human: string,
 *     author: string|null, text: string,
 *     edited_at: int|null, edited_by: string|null}>,
 * }
 */
function api_comms_detail(array $params): array
{
    global $userbank;
    $cid = (int)($params['cid'] ?? 0);
    if ($cid <= 0) {
        throw new ApiError('bad_request', 'cid must be a positive integer', 'cid');
    }

    // Mirror page.commslist.php: an admin sees admin-name and comments
    // unconditionally; public visitors get them suppressed when the
    // corresponding hide-* settings are on. `is_admin()` is the same
    // gate the page uses, so the JSON surface stays consistent with
    // what the HTML page would have shown the same caller.
    $isAdmin   = $userbank->is_admin();
    $hideAdmin = Config::getBool('banlist.hideadminname') && !$isAdmin;

    $row = $GLOBALS['PDO']->query(
        "SELECT C.bid AS cid, C.type, C.authid, C.name, C.created, C.ends, C.length,
                C.reason, C.aid, C.sid, C.RemovedOn, C.RemovedBy, C.RemoveType, C.ureason,
                AD.user AS admin_name,
                SE.ip AS server_ip, SE.port AS server_port,
                MO.icon AS mod_icon, MO.name AS mod_name,
                CAST(MID(C.authid, 9, 1) AS UNSIGNED)
                  + CAST('76561197960265728' AS UNSIGNED)
                  + CAST(MID(C.authid, 11, 10) * 2 AS UNSIGNED) AS community_id
           FROM `:prefix_comms` AS C
      LEFT JOIN `:prefix_servers` AS SE ON SE.sid = C.sid
      LEFT JOIN `:prefix_mods`    AS MO ON MO.mid = SE.modid
      LEFT JOIN `:prefix_admins`  AS AD ON C.aid = AD.aid
          WHERE C.bid = ?"
    )->single([$cid]);
    if (!$row) {
        throw new ApiError('not_found', 'Block not found.', null, 404);
    }

    $type      = (int)$row['type'];
    $typeLabel = match ($type) {
        1       => 'Mute',
        2       => 'Gag',
        default => 'Block',
    };
    $authid    = (string)$row['authid'];
    $created   = (int)$row['created'];
    $length    = (int)$row['length'];
    $ends      = (int)$row['ends'];
    $removedOn = $row['RemovedOn'] !== null ? (int)$row['RemovedOn'] : null;

    // State machine mirrors page.commslist.php's per-row classifier:
    // `RemoveType` marks deleted/unbanned/expired explicitly; otherwise
    // an `ends` timestamp in the past collapses to "expired". Vocab
    // matches the comm column conventions (`api_comms_unblock` returns
    // `state: 'unmuted'`); `api_bans_detail` uses `'unbanned'` because
    // bans use that vocab. The drawer JS branches on focal kind to
    // show the right state label.
    $removal = BanRemoval::tryFrom((string)($row['RemoveType'] ?? ''));
    if ($removal === BanRemoval::Unbanned || $removal === BanRemoval::Deleted) {
        $state = 'unmuted';
    } elseif ($removal === BanRemoval::Expired) {
        $state = 'expired';
    } elseif ($length === 0) {
        $state = 'permanent';
    } elseif ($ends > 0 && $ends < time()) {
        $state = 'expired';
    } else {
        $state = 'active';
    }

    $steam2 = $authid !== '' && SteamID::isValidID($authid) ? $authid : '';
    $steam3 = $steam2 !== '' ? (string)SteamID::toSteam3($steam2) : '';

    $removedByName = null;
    if ($row['RemovedBy'] !== null && (int)$row['RemovedBy'] > 0 && !$hideAdmin) {
        $removedRow = $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = ?")
            ->single([(int)$row['RemovedBy']]);
        if ($removedRow && !empty($removedRow['user'])) {
            $removedByName = (string)$removedRow['user'];
        }
    }

    $serverName = null;
    if (!empty($row['server_ip'])) {
        $serverName = $row['server_ip'] . (!empty($row['server_port']) ? ':' . $row['server_port'] : '');
    }

    $comments = [];
    $commentsVisible = Config::getBool('config.enablepubliccomments') || $isAdmin;
    if ($commentsVisible) {
        // Comm comments live on `:prefix_comments` with `type = 'C'`,
        // keyed by the comm row's `bid` column (despite our public
        // surface naming it `cid` — the column is shared between the
        // bans/comms/protests trio via the `type` letter).
        $commentRows = $GLOBALS['PDO']->query(
            "SELECT C.cid, C.commenttxt, C.added, C.edittime,
                    (SELECT user FROM `:prefix_admins` WHERE aid = C.aid)     AS author,
                    (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editor
               FROM `:prefix_comments` AS C
              WHERE C.type = 'C' AND C.bid = ?
           ORDER BY C.added DESC"
        )->resultset([$cid]);
        foreach ($commentRows as $crow) {
            $editTime = $crow['edittime'] !== null ? (int)$crow['edittime'] : null;
            $comments[] = [
                'cid'        => (int)$crow['cid'],
                'added'      => (int)$crow['added'],
                'added_human'=> Config::time((int)$crow['added']),
                'author'     => $crow['author'] !== null ? (string)$crow['author'] : null,
                'text'       => (string)$crow['commenttxt'],
                'edited_at'  => $editTime,
                'edited_by'  => $crow['editor'] !== null ? (string)$crow['editor'] : null,
            ];
        }
    }

    return [
        'cid' => $cid,
        'player' => [
            'name'         => (string)$row['name'],
            'steam_id'     => $steam2,
            'steam_id_3'   => $steam3,
            'community_id' => (string)$row['community_id'],
            // Comm rows don't store an IP — `:prefix_comms` has no `ip`
            // column. Always null; the field is only here so the
            // drawer's `renderOverviewPane` can read `player.ip`
            // without a per-kind branch.
            'ip'           => null,
            'country'      => null,
        ],
        'block' => [
            'type'             => $type,
            'type_label'       => $typeLabel,
            'reason'           => (string)$row['reason'],
            'started_at'       => $created,
            'started_at_human' => Config::time($created),
            'length_seconds'   => $length,
            'length_human'     => $length === 0 ? 'Permanent' : SecondsToString($length),
            'expires_at'       => $length === 0 ? null : $ends,
            'expires_at_human' => $length === 0 ? null : Config::time($ends),
            'state'            => $state,
            'unblock_reason'   => (string)($row['ureason'] ?? ''),
            'removed_at'       => $removedOn,
            'removed_at_human' => $removedOn !== null ? Config::time($removedOn) : null,
            'removed_by'       => $removedByName,
        ],
        'admin' => [
            'name' => $hideAdmin ? null : ($row['admin_name'] !== null ? (string)$row['admin_name'] : null),
        ],
        'server' => [
            'sid'      => (int)$row['sid'],
            'name'     => $serverName,
            'mod_icon' => !empty($row['mod_icon']) ? (string)$row['mod_icon'] : null,
        ],
        'comments_visible' => $commentsVisible,
        // Mirrors `api_bans_detail`: the drawer's Notes tab is
        // admin-only, gated on this flag. The dispatcher gate on
        // `notes.list` is the load-bearing one; this signal lets the
        // JS hide the tab chrome entirely for public callers so they
        // don't see a tab they can't reach.
        'notes_visible'    => $isAdmin,
        'comments'         => $comments,
    ];
}

/**
 * Comm-block feed for the player-detail drawer's Comms tab (#1165).
 *
 * Returns every gag/mute on file for the player anchored by the
 * supplied identifier. Action is registered public to match
 * `bans.detail` / `bans.player_history` — comms-list is a public
 * surface (`?p=commslist`) so the drawer's Comms tab follows the same
 * reach. Admin-name exposure is gated by `banlist.hideadminname` for
 * non-admin callers, mirroring how `bans.detail` handles it.
 *
 * Inputs (resolved in priority order):
 *   1. `cid` (int) — comm-focal drawer path (#COMMS-DRAWER). Resolves
 *      to authid via `:prefix_comms`, EXCLUDES that focal cid from
 *      the result (`C.bid <> ?`) so the Overview pane and the Comms
 *      tab don't render the same record twice.
 *   2. `bid` (int) — legacy bans-focal drawer path. Resolves to authid
 *      via `:prefix_bans`. No comm exclusion (cid and bid live in
 *      different tables, so the focal bid never appears in the comm
 *      feed anyway).
 *   3. `authid` (string) — caller-supplied steam id. Useful for paths
 *      that already know the player's authid; no exclusion (the
 *      caller must filter the focal record on their side if needed).
 *
 * `bad_request` (field=`cid`) when none of the three is provided —
 * preserves the legacy "must supply *something*" contract.
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

    $isAdmin   = $userbank->is_admin();
    $hideAdmin = Config::getBool('banlist.hideadminname') && !$isAdmin;

    // Three-shape input, resolved in priority order so the most-specific
    // anchor wins:
    //
    //   1. `cid` — comm-focal drawer path (this PR). Resolves the focal
    //      `:prefix_comms` row to `authid` and excludes that focal cid
    //      from the sibling feed via `C.bid <> ?` — same focal-exclusion
    //      semantics `bans.player_history` carries for its `bid` path
    //      so the Overview pane and the Comms pane don't render the same
    //      record twice.
    //   2. `bid` — legacy bans-focal drawer path. Resolves the focal
    //      `:prefix_bans` row to `authid`. No comm exclusion (cid and
    //      bid live in different tables, so the focal bid never appears
    //      in the comm feed).
    //   3. `authid` — explicit caller-supplied authid (currently no
    //      production callsite, kept for symmetry with `bans.player_history`
    //      so future paths that already know the steam id can skip the
    //      look-up).
    //
    // The drawer JS picks the shape based on `drawerKind` —
    // `loadPaneIfNeeded`'s docblock has the full per-kind matrix.
    /** @var int $excludeCid focal cid to exclude (0 = no exclusion). */
    $excludeCid  = 0;
    $cidParam    = (int)($params['cid'] ?? 0);
    $authidParam = trim((string)($params['authid'] ?? ''));
    if ($cidParam > 0) {
        $anchor = $GLOBALS['PDO']->query("SELECT authid FROM `:prefix_comms` WHERE bid = ?")
            ->single([$cidParam]);
        if (!$anchor) {
            throw new ApiError('not_found', 'Block not found.', null, 404);
        }
        $authid     = (string)($anchor['authid'] ?? '');
        $excludeCid = $cidParam;
    } elseif ($authidParam !== '') {
        $authid = $authidParam;
    } else {
        $bid = (int)($params['bid'] ?? 0);
        if ($bid <= 0) {
            // `field: 'cid'` (NOT 'bid') because cid is the priority-1
            // input under the new comm-focal contract; surfacing 'cid'
            // gives a forms-style UI a sensible field to highlight on
            // the new shape, and the message body covers all three
            // valid inputs for explicit callers.
            throw new ApiError('bad_request', 'cid, bid, or authid is required', 'cid');
        }

        // Bans-focal-drawer legacy path: the focal record lives on
        // `:prefix_bans`, so we resolve the authid from there before
        // querying `:prefix_comms` for the player's comm-block feed.
        // Reading a bans table from a comms-named handler is the
        // documented cross-table join the drawer's kind-aware
        // dispatch needs (see the docblock above for the full
        // priority order).
        $anchor = $GLOBALS['PDO']->query("SELECT authid FROM `:prefix_bans` WHERE bid = ?")
            ->single([$bid]);
        if (!$anchor) {
            throw new ApiError('not_found', 'Ban not found.', null, 404);
        }
        $authid = (string)($anchor['authid'] ?? '');
    }

    if ($authid === '') {
        // The anchor was an IP-only ban (or the supplied authid was
        // an empty string). Comm-blocks are keyed off authid (no IP
        // column on `:prefix_comms`), so there's nothing we can match
        // against — return an empty feed cleanly rather than 404'ing
        // on what is a legitimate state.
        return ['items' => [], 'total' => 0];
    }

    if ($excludeCid > 0) {
        $rows = $GLOBALS['PDO']->query(
            "SELECT C.bid, C.type, C.created, C.ends, C.length, C.reason,
                    C.RemovedOn, C.RemovedBy, C.RemoveType,
                    AD.user AS admin_name
               FROM `:prefix_comms` AS C
          LEFT JOIN `:prefix_admins` AS AD ON C.aid = AD.aid
              WHERE C.authid = ? AND C.bid <> ?
           ORDER BY C.created DESC, C.bid DESC
              LIMIT 100"
        )->resultset([$authid, $excludeCid]);
    } else {
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
    }

    $items = [];
    foreach ($rows as $r) {
        $created = (int)$r['created'];
        $length  = (int)$r['length'];
        $ends    = (int)$r['ends'];
        $type    = (int)$r['type'];
        $rowRemoval = BanRemoval::tryFrom((string) ($r['RemoveType'] ?? ''));

        $state = match (true) {
            $rowRemoval === BanRemoval::Unbanned, $rowRemoval === BanRemoval::Deleted => 'unmuted',
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
