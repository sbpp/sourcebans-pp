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
    $nickname = htmlspecialchars_decode((string)($params['nickname'] ?? ''), ENT_QUOTES);
    $type     = (int)($params['type']   ?? 0);
    $steam    = SteamID::toSteam2(trim((string)($params['steam']  ?? '')));
    $length   = (int)($params['length'] ?? 0);
    $reason   = htmlspecialchars_decode((string)($params['reason'] ?? ''), ENT_QUOTES);

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

    Log::add('m', 'Block Added', "Block against ($steam) has been added. Reason: $reason; Length: $length");

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

function api_comms_paste(array $params): array
{
    $sid  = (int)($params['sid']  ?? 0);
    $name = (string)($params['name'] ?? '');

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't connect to server!");
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            return [
                'nickname' => html_entity_decode($name, ENT_QUOTES),
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
