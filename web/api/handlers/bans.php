<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use SteamID\SteamID;

/**
 * Resolve STEAMAPIKEY at runtime. Wrapped in a function so static analysis
 * can't narrow the constant value (the test bootstrap defines it as '',
 * but in production it's set in config.php).
 */
function _api_bans_steam_api_key(): string
{
    /** @var string $key */
    $key = defined('STEAMAPIKEY') ? (string)constant('STEAMAPIKEY') : '';
    return $key;
}

function api_bans_add(array $params): array
{
    global $userbank;
    $nickname = htmlspecialchars_decode((string)($params['nickname'] ?? ''), ENT_QUOTES);
    $type     = (int)($params['type'] ?? 0);
    $steam    = SteamID::toSteam2(trim((string)($params['steam'] ?? '')));
    $ip       = preg_replace('#[^\d\.]#', '', (string)($params['ip'] ?? ''));
    $length   = (int)($params['length'] ?? 0);
    $dfile    = (string)($params['dfile'] ?? '');
    $dname    = htmlspecialchars_decode((string)($params['dname'] ?? ''), ENT_QUOTES);
    $reason   = htmlspecialchars_decode((string)($params['reason'] ?? ''), ENT_QUOTES);
    $fromsub  = (int)($params['fromsub'] ?? 0);

    if (empty($steam) && $type === 0) {
        throw new ApiError('validation', 'You must type a Steam ID or Community ID', 'steam');
    }
    if ($type === 0 && !SteamID::isValidID($steam)) {
        throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID', 'steam');
    }
    if (empty($ip) && $type === 1) {
        throw new ApiError('validation', 'You must type an IP', 'ip');
    }
    if ($type === 1 && !filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new ApiError('validation', 'You must type a valid IP', 'ip');
    }
    if ($length < 0) {
        throw new ApiError('validation', 'Length must be positive or 0', 'length');
    }
    // Ban lengths are stored in INT(10/11) seconds. Cap at ~100 years to
    // keep `length * 60` and `UNIX_TIMESTAMP() + length*60` below 2^31-1
    // and avoid silently producing rows whose `ends` lands in 1970.
    if ($length > 60 * 24 * 365 * 100) {
        throw new ApiError('validation', 'Length is unrealistically large.', 'length');
    }

    $len = $length ? $length * 60 : 0;

    PruneBans();
    if ($type === 0) {
        $chk = $GLOBALS['PDO']->query(
            "SELECT count(bid) AS count FROM `:prefix_bans`
            WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP())
            AND RemovedBy IS NULL AND type = '0'"
        )->single([$steam]);
        if ((int)$chk['count'] > 0) {
            throw new ApiError('already_banned', "SteamID: $steam is already banned.");
        }

        foreach ($userbank->GetAllAdmins() as $admin) {
            if ($admin['authid'] === $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                throw new ApiError('immune', "SteamID: Admin {$admin['user']} ($steam) is immune.");
            }
        }
    }
    if ($type === 1) {
        $chk = $GLOBALS['PDO']->query(
            "SELECT count(bid) AS count FROM `:prefix_bans`
            WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP())
            AND RemovedBy IS NULL AND type = '1'"
        )->single([$ip]);
        if ((int)$chk['count'] > 0) {
            throw new ApiError('already_banned', "IP: $ip is already banned.");
        }
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_bans`(created,type,ip,authid,name,ends,length,reason,aid,adminIp ) VALUES
        (UNIX_TIMESTAMP(),?,?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
    )->execute([$type, $ip, $steam, $nickname, $length * 60, $len, $reason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR'] ?? '']);
    $newId = (int)$GLOBALS['PDO']->lastInsertId();

    if ($dname && $dfile && preg_match('/^[a-z0-9]*$/i', $dfile)) {
        $GLOBALS['PDO']->query("INSERT INTO `:prefix_demos`(demid,demtype,filename,origname) VALUES(?,'B',?,?)")
            ->execute([$newId, $dfile, $dname]);
    }

    if ($fromsub) {
        $GLOBALS['PDO']->query("SELECT name, email FROM `:prefix_submissions` WHERE subid = :subid");
        $GLOBALS['PDO']->bind(':subid', $fromsub);
        $sub = $GLOBALS['PDO']->single();

        if (!empty($sub['email'])) {
            Mail::send($sub['email'], EmailType::BanAdded, ['{home}' => Host::complete(true)]);
        }

        $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = '2', archivedby = ? WHERE subid = ?")
            ->execute([$userbank->GetAid(), $fromsub]);
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_submissions` SET archiv = '3', archivedby = ? WHERE SteamId = ?")
        ->execute([$userbank->GetAid(), $steam]);

    $kickit = Config::getBool('config.enablekickit');
    Log::add('m', 'Ban Added', "Ban against (" . ($type === 0 ? $steam : $ip) . ") has been added. Reason: $reason; Length: $length");

    return [
        'bid'    => $newId,
        'reload' => true,
        'kickit' => $kickit ? ['check' => $type === 0 ? $steam : $ip, 'type' => $type] : null,
        'message' => $kickit ? null : [
            'title' => 'Ban Added',
            'body'  => 'The ban has been successfully added',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=bans',
        ],
    ];
}

function api_bans_setup_ban(array $params): array
{
    $subid = (int)($params['subid'] ?? 0);

    $GLOBALS['PDO']->query("SELECT * FROM `:prefix_submissions` WHERE subid = :subid");
    $GLOBALS['PDO']->bind(':subid', $subid);
    $ban = $GLOBALS['PDO']->single();

    $GLOBALS['PDO']->query("SELECT * FROM `:prefix_demos` WHERE demid = :subid AND demtype = 'S'");
    $GLOBALS['PDO']->bind(':subid', $subid);
    $demo = $GLOBALS['PDO']->single();

    return [
        'subid'    => $subid,
        'nickname' => $ban['name']    ?? '',
        'steam'    => $ban['SteamId'] ?? '',
        'ip'       => $ban['sip']     ?? '',
        'length'   => 0,
        'type'     => (trim($ban['SteamId'] ?? '') === '') ? 1 : 0,
        'reason'   => $ban['reason']  ?? '',
        'demo'     => $demo ? ['filename' => $demo['filename'], 'origname' => $demo['origname']] : null,
    ];
}

function api_bans_prepare_reban(array $params): array
{
    $bid = (int)($params['bid'] ?? 0);

    $ban = $GLOBALS['PDO']->query("SELECT type, ip, authid, name, length, reason FROM `:prefix_bans` WHERE bid = ?;")
        ->single([$bid]);
    $demo = $GLOBALS['PDO']->query("SELECT * FROM `:prefix_demos` WHERE demid = ? AND demtype = 'B';")->single([$bid]);

    return [
        'bid'      => $bid,
        'nickname' => $ban['name']   ?? '',
        'steam'    => $ban['authid'] ?? '',
        'ip'       => $ban['ip']     ?? '',
        'length'   => (int)($ban['length'] ?? 0),
        'type'     => (int)($ban['type']   ?? 0),
        'reason'   => $ban['reason'] ?? '',
        'demo'     => $demo ? ['filename' => $demo['filename'], 'origname' => $demo['origname']] : null,
    ];
}

function api_bans_paste(array $params): array
{
    $sid  = (int)($params['sid']  ?? 0);
    $name = (string)($params['name'] ?? '');
    $type = (int)($params['type'] ?? 0);

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't connect to server!");
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            return [
                'nickname' => html_entity_decode($name, ENT_QUOTES),
                'steam'    => SteamID::toSteam2($player['steamid']),
                'ip'       => $player['ip'] ?? '',
                'type'     => 0,
            ];
        }
    }

    throw new ApiError('player_not_found', "Can't get player info for " . htmlspecialchars($name) . '. Player is not on the server anymore!');
}

function api_bans_add_comment(array $params): array
{
    global $userbank, $username;
    $bid   = (int)($params['bid']   ?? 0);
    $ctype = (string)($params['ctype'] ?? '');
    $ctext = trim((string)($params['ctext'] ?? ''));
    $page  = (int)($params['page']  ?? -1);

    $pagelink = $page !== -1 ? '&page=' . $page : '';
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        'S' => '?p=admin&c=bans#^2',
        'P' => '?p=admin&c=bans#^1',
        default => null,
    };
    if ($redir === null) {
        throw new ApiError('bad_type', 'Bad comment type.');
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_comments`(bid,type,aid,commenttxt,added) VALUES (?,?,?,?,UNIX_TIMESTAMP())"
    )->execute([$bid, $ctype, $userbank->GetAid(), $ctext]);

    Log::add('m', 'Comment Added', "$username added a comment for ban #$bid");

    return [
        'reload'  => true,
        'message' => [
            'title' => 'Comment Added',
            'body'  => 'The comment has been successfully published',
            'kind'  => 'green',
            'redir' => 'index.php' . $redir,
        ],
    ];
}

function api_bans_edit_comment(array $params): array
{
    global $userbank, $username;
    $cid   = (int)($params['cid']   ?? 0);
    $ctype = (string)($params['ctype'] ?? '');
    $ctext = trim((string)($params['ctext'] ?? ''));
    $page  = (int)($params['page']  ?? -1);

    $pagelink = $page !== -1 ? '&page=' . $page : '';
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        'S' => '?p=admin&c=bans#^2',
        'P' => '?p=admin&c=bans#^1',
        default => null,
    };
    if ($redir === null) {
        throw new ApiError('bad_type', 'Bad comment type.');
    }

    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comments` SET commenttxt = ?, editaid = ?, edittime = UNIX_TIMESTAMP() WHERE cid = ?"
    )->execute([$ctext, $userbank->GetAid(), $cid]);

    Log::add('m', 'Comment Edited', "$username edited comment #$cid");

    return [
        'reload'  => true,
        'message' => [
            'title' => 'Comment Edited',
            'body'  => 'The comment #' . $cid . ' has been successfully edited',
            'kind'  => 'green',
            'redir' => 'index.php' . $redir,
        ],
    ];
}

function api_bans_remove_comment(array $params): array
{
    global $username;
    $cid   = (int)($params['cid']   ?? 0);
    $ctype = (string)($params['ctype'] ?? '');
    $page  = (int)($params['page']  ?? -1);

    $pagelink = $page !== -1 ? '&page=' . $page : '';
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        default => '?p=admin&c=bans',
    };

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_comments` WHERE cid = ?")->execute([$cid]);
    Log::add('m', 'Comment Deleted', "$username deleted comment #$cid");

    return [
        'message' => [
            'title' => 'Comment Deleted',
            'body'  => 'The selected comment has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php' . $redir,
        ],
    ];
}

function api_bans_group_ban(array $params): array
{
    if (!Config::getBool('config.enablegroupbanning')) {
        return [];
    }
    $groupuri = (string)($params['groupuri'] ?? '');
    $isgrpurl = (string)($params['isgrpurl'] ?? 'no');
    $queue    = (string)($params['queue']    ?? 'no');
    $reason   = (string)($params['reason']   ?? '');
    $last     = (string)($params['last']     ?? '');

    if ($isgrpurl === 'yes') {
        $grpname = $groupuri;
    } else {
        $url = parse_url($groupuri, PHP_URL_PATH);
        $url = explode('/', (string)$url);
        $grpname = $url[2] ?? '';
    }
    if ($grpname === '') {
        throw new ApiError('validation', 'Error parsing the group url.', 'groupurl');
    }

    return [
        'grpname' => $grpname,
        'queue'   => $queue,
        'reason'  => $reason,
        'last'    => $last,
        'message' => [
            'title' => 'Please Wait...',
            'body'  => 'Banning all members of ' . htmlspecialchars($grpname) . '...',
            'kind'  => 'blue',
        ],
    ];
}

function api_bans_ban_member_of_group(array $params): array
{
    set_time_limit(0);
    if (!Config::getBool('config.enablegroupbanning')) {
        return [];
    }
    $apiKey = _api_bans_steam_api_key();
    if ($apiKey === '') {
        return [];
    }
    global $userbank;

    $grpurl = (string)($params['grpurl'] ?? '');
    $queue  = (string)($params['queue']  ?? '');
    $reason = (string)($params['reason'] ?? '');
    $last   = (string)($params['last']   ?? '');

    $GLOBALS['PDO']->query("SELECT CAST(MID(authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(authid, 11, 10) * 2 AS UNSIGNED) AS community_id FROM `:prefix_bans` WHERE RemoveType IS NULL;");
    $bans = $GLOBALS['PDO']->resultset();
    $already = array_column($bans, 'community_id');

    $steamids = [];
    $fetch = function ($url, &$out) use (&$fetch) {
        $xml = @simplexml_load_file($url);
        if (!$xml) return;
        $out = array_merge($out, (array)$xml->members->steamID64);
        if ($xml->nextPageLink) $fetch($xml->nextPageLink, $out);
    };
    $fetch('https://steamcommunity.com/groups/' . $grpurl . '/memberslistxml?xml=1', $steamids);

    $data = [];
    foreach (array_chunk($steamids, 100) as $package) {
        $package = rawurlencode(json_encode($package));
        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2?key=' . $apiKey . '&steamids=' . $package;
        $raw = @json_decode((string)@file_get_contents($url), true);
        $data = array_merge($data, $raw['response']['players'] ?? []);
    }

    $amount = ['total' => count($data), 'banned' => 0, 'before' => 0, 'failed' => 0];

    foreach ($data as $player) {
        if (in_array($player['steamid'], $already, true)) {
            $amount['before']++;
            continue;
        }
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
            VALUES (UNIX_TIMESTAMP(), :type, :ip, :authid, :name, UNIX_TIMESTAMP(), :length, :reason, :aid, :adminIp)"
        );
        $GLOBALS['PDO']->bindMultiple([
            ':type'    => 0,
            ':ip'      => '',
            ':authid'  => SteamID::toSteam2($player['steamid']),
            ':name'    => $player['personaname'],
            ':length'  => 0,
            ':reason'  => 'Steam Community Group Ban (' . $grpurl . '): ' . $reason,
            ':aid'     => $userbank->GetAid(),
            ':adminIp' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        if ($GLOBALS['PDO']->execute()) {
            $amount['banned']++;
        } else {
            $amount['failed']++;
        }
    }

    Log::add('m', 'Group Banned',
        "Banned " . ($amount['total'] - $amount['before'] - $amount['failed']) . "/{$amount['total']} players of group ($grpurl). {$amount['before']} were banned already. {$amount['failed']} failed.");

    return [
        'grpurl' => $grpurl,
        'queue'  => $queue,
        'last'   => $last,
        'amount' => $amount,
    ];
}

function api_bans_get_groups(array $params): array
{
    set_time_limit(0);
    if (!Config::getBool('config.enablegroupbanning')) {
        return ['groups' => []];
    }
    $friendid = (string)($params['friendid'] ?? '');
    if (!is_numeric($friendid)) {
        return ['groups' => []];
    }

    $headers = @get_headers('http://steamcommunity.com/profiles/' . $friendid . '/', 1);
    $base = !empty($headers['Location']) ? $headers['Location'] : 'http://steamcommunity.com/profiles/' . $friendid . '/';
    $raw = (string)@file_get_contents($base . '?xml=1');
    preg_match('/<privacyState>([^\]]*)<\/privacyState>/', $raw, $status);

    $groups = [];
    if (($status && $status[1] !== 'public') || str_contains($raw, '<groups>')) {
        $raw = str_replace('&', '', $raw);
        $xml = @simplexml_load_string($raw);
        if ($xml) {
            $result = $xml->xpath('/profile/groups/group');
            foreach ($result ?? [] as $node) {
                if (empty($node->groupName)) {
                    $memberlistxml = (string)@file_get_contents('http://steamcommunity.com/gid/' . $node->groupID64 . '/memberslistxml/?xml=1');
                    $memberlistxml = str_replace('&', '', $memberlistxml);
                    $groupxml = @simplexml_load_string($memberlistxml);
                    if ($groupxml) {
                        $detail = $groupxml->xpath('/memberList/groupDetails');
                        if (!empty($detail)) $node = $detail[0];
                    }
                }
                $groups[] = [
                    'name'         => (string)$node->groupName,
                    'url'          => (string)$node->groupURL,
                    'member_count' => (int)$node->memberCount,
                ];
            }
        }
    }

    return ['groups' => $groups];
}

function api_bans_ban_friends(array $params): array
{
    set_time_limit(0);
    if (!Config::getBool('config.enablefriendsbanning')) {
        return [];
    }
    global $userbank;

    $friendid = (string)($params['friendid'] ?? '');
    $name     = (string)($params['name'] ?? '');
    if (!is_numeric($friendid)) {
        throw new ApiError('bad_request', 'friendid must be numeric');
    }

    $steam = SteamID::toSteam64($friendid);
    $apiKey = _api_bans_steam_api_key();
    $raw = @file_get_contents('http://api.steampowered.com/ISteamUser/GetFriendList/v0001/?key=' . $apiKey . '&steamid=' . $steam . '&relationship=friend');
    $data = $raw ? json_decode($raw, true) : null;
    $friends = $data['friendslist']['friends'] ?? null;

    if (is_null($friends)) {
        throw new ApiError('private_profile', 'There was an error retrieving the friend list.');
    }

    $total = count($friends);
    $before = 0;
    $error = 0;

    foreach ($friends as $friend) {
        $authid = SteamID::toSteam2($friend['steamid']);
        $fname  = GetCommunityName($friend['steamid']);

        $GLOBALS['PDO']->query("SELECT 1 FROM `:prefix_bans` WHERE authid = :authid");
        $GLOBALS['PDO']->bind(':authid', $authid);
        $banned = $GLOBALS['PDO']->single();
        if ($banned) {
            $before++;
            continue;
        }
        $GLOBALS['PDO']->query(
            "INSERT INTO `:prefix_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
            VALUES(UNIX_TIMESTAMP(), 0, '', :authid, :name, (UNIX_TIMESTAMP() + 0), 0, :reason, :aid, :admip)"
        );
        $GLOBALS['PDO']->bindMultiple([
            ':authid' => $authid,
            ':name'   => $fname,
            ':reason' => 'Steam Community Friend Ban (' . $name . ')',
            ':aid'    => $userbank->GetAid(),
            ':admip'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        if (!$GLOBALS['PDO']->execute()) {
            $error++;
        }
    }

    if ($total === 0) {
        throw new ApiError('no_friends', "There was an error retrieving the friend list. Check if the profile isn't private or if he hasn't got any friends!");
    }

    Log::add('m', 'Friends Banned',
        'Banned ' . ($total - $before - $error) . "/$total friends of $name. $before were banned already. $error failed.");

    return [
        'amount' => ['total' => $total, 'before' => $before, 'failed' => $error],
        'message' => [
            'title' => 'Friends banned successfully',
            'body'  => 'Banned ' . ($total - $before - $error) . "/$total friends of '" . htmlspecialchars($name) . "'.<br>$before were banned already.<br>$error failed.",
            'kind'  => 'green',
            'redir' => 'index.php?p=banlist',
        ],
    ];
}

function api_bans_kick_player(array $params): array
{
    global $userbank, $username;
    $sid  = (int)($params['sid'] ?? 0);
    $name = (string)($params['name'] ?? '');

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't kick " . htmlspecialchars($name) . ". Can't connect to server!");
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            $GLOBALS['PDO']->query("SELECT a.immunity AS aimmune, g.immunity AS gimmune FROM `:prefix_admins` AS a
                LEFT JOIN `:prefix_srvgroups` AS g ON g.name = a.srv_group WHERE authid = :authid");
            $GLOBALS['PDO']->bind(':authid', SteamID::toSteam2($player['steamid']));
            $admin = $GLOBALS['PDO']->single();

            $immune = 0;
            if ($admin && $admin['gimmune'] > $admin['aimmune']) {
                $immune = $admin['gimmune'];
            } else if ($admin) {
                $immune = $admin['aimmune'];
            }

            if ($immune <= $userbank->GetProperty('srv_immunity')) {
                rcon("sm_kick #{$player['id']} You have been kicked from this server", $sid);
                Log::add('m', 'Player kicked', "$username kicked player {$player['name']} ({$player['steamid']})");
                return [
                    'message' => [
                        'title' => 'Success',
                        'body'  => 'Player ' . htmlspecialchars($name) . ' has been kicked from the server!',
                        'kind'  => 'green',
                    ],
                ];
            }

            throw new ApiError('immune', "Can't kick " . htmlspecialchars($name) . '. Player is immune!');
        }
    }

    throw new ApiError('player_not_found', "Can't kick " . htmlspecialchars($name) . '. Player not on the server anymore!');
}

function api_bans_send_message(array $params): array
{
    global $userbank;
    $sid     = (int)($params['sid'] ?? 0);
    $name    = (string)($params['name']    ?? '');
    $message = (string)($params['message'] ?? '');

    if (!$userbank->is_admin()) {
        return Api::redirect('index.php?p=login&m=no_access');
    }

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't connect to server!");
    }

    $message = html_entity_decode($message, ENT_QUOTES);
    $message = str_replace('"', "'", $message);

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($name, $player['name'])) {
            rcon("sm_psay #{$player['id']} \"$message\"", $sid);
        }
    }

    Log::add('m', 'Message sent to player', "The following message was sent to $name on server (#$sid): $message");

    return [
        'message' => [
            'title' => 'Message Sent',
            'body'  => "The message has been sent to player '" . htmlspecialchars($name) . "' successfully!",
            'kind'  => 'green',
        ],
    ];
}

function api_bans_view_community(array $params): array
{
    global $userbank;
    $sid  = (int)($params['sid']  ?? 0);
    $name = (string)($params['name'] ?? '');

    if (!$userbank->is_admin()) {
        return Api::redirect('index.php?p=login&m=no_access');
    }

    $ret = rcon('status', $sid);
    if (!$ret) {
        throw new ApiError('rcon_failed', "Can't connect to server!");
    }

    foreach (parseRconStatus($ret) as $player) {
        if (compareSanitizedString($player['name'], $name)) {
            return ['url' => 'https://www.steamcommunity.com/profiles/' . SteamID::toSteam64($player['steamid']) . '/'];
        }
    }

    throw new ApiError('player_not_found', "Can't get playerinfo for " . htmlspecialchars($name) . '. Player not on the server anymore!');
}
