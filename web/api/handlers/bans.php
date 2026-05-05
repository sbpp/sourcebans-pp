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
    // Names/reasons arrive as raw UTF-8 in the JSON body. The legacy
    // xajax callbacks HTML-encoded payloads in transit and needed a
    // matching decode here, but the JSON dispatcher does not; decoding
    // again silently collapses a user-typed literal `&amp;` into `&`
    // and, worse, it made `htmlspecialchars_decode` a no-op for the
    // common case while masking the double-escape on re-render. Store
    // raw bytes and let the Smarty auto-escape layer (see #1087) turn
    // `<Msg>` into `&lt;Msg&gt;` at display time.
    $nickname = (string)($params['nickname'] ?? '');
    $type     = (int)($params['type'] ?? 0);
    $steam    = SteamID::toSteam2(trim((string)($params['steam'] ?? '')));
    $ip       = preg_replace('#[^\d\.]#', '', (string)($params['ip'] ?? ''));
    $length   = (int)($params['length'] ?? 0);
    $dfile    = (string)($params['dfile'] ?? '');
    $dname    = (string)($params['dname'] ?? '');
    $reason   = (string)($params['reason'] ?? '');
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
        if ($player['name'] === $name) {
            // Return the raw name the rcon status reported; the client
            // assigns it to the "nickname" input value. The previous
            // `html_entity_decode` was a no-op on raw UTF-8 and actively
            // harmful when a literal `&amp;` should be preserved.
            return [
                'nickname' => $player['name'],
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
        if ($player['name'] === $name) {
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

    // The message goes into an rcon command wrapped in double quotes.
    // Replace any user-supplied `"` so the argument stays a single token,
    // but do NOT html_entity_decode the body first — the panel now
    // receives it as raw UTF-8 via the JSON API and decoding would
    // silently collapse a literal `&amp;` the admin typed.
    $message = str_replace('"', "'", $message);

    foreach (parseRconStatus($ret) as $player) {
        if ($name === $player['name']) {
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
        if ($player['name'] === $name) {
            return ['url' => 'https://www.steamcommunity.com/profiles/' . SteamID::toSteam64($player['steamid']) . '/'];
        }
    }

    throw new ApiError('player_not_found', "Can't get playerinfo for " . htmlspecialchars($name) . '. Player not on the server anymore!');
}

/**
 * Player-detail payload for the sbpp2026 right-side drawer (#1123 C1).
 *
 * Returns the same data the public ban-list page already exposes, in a
 * stable JSON shape the drawer JS renders client-side. Action is
 * registered public so the drawer matches the public ban-list reach;
 * fields that the public ban-list selectively hides via
 * `banlist.hideplayerips` / `banlist.hideadminname` (player IP, admin
 * name, removed-by) follow the same gating here so a public caller can
 * never fan out the same data through the JSON API that the page
 * intentionally suppresses. Comments are only included when
 * `config.enablepubliccomments` is set or the caller is an admin —
 * mirroring page.banlist.php's `$view_comments` switch.
 *
 * @param array{bid?: int|string} $params
 * @return array{
 *   bid: int,
 *   player: array{name: string, type: int, steam_id: string, steam_id_3: string, community_id: string, ip: string|null, country: string|null},
 *   ban: array{reason: string, banned_at: int, banned_at_human: string, length_seconds: int, length_human: string, expires_at: int|null, expires_at_human: string|null, state: string, unban_reason: string, removed_at: int|null, removed_at_human: string|null, removed_by: string|null},
 *   admin: array{name: string|null},
 *   server: array{sid: int, name: string|null, mod_icon: string|null},
 *   demo_count: int,
 *   history_count: int,
 *   comments_visible: bool,
 *   comments: list<array{cid: int, added: int, added_human: string, author: string|null, text: string, edited_at: int|null, edited_by: string|null}>
 * }
 */
function api_bans_detail(array $params): array
{
    global $userbank;
    $bid = (int)($params['bid'] ?? 0);
    if ($bid <= 0) {
        throw new ApiError('bad_request', 'bid must be a positive integer', 'bid');
    }

    // Mirror page.banlist.php: an admin (or any logged-in user with the
    // appropriate flag) sees the IP and admin nick; public visitors
    // get them suppressed when the corresponding hide-* settings are
    // on. `is_admin()` is the same gate the page uses, so the JSON
    // surface stays consistent with what the HTML page would have
    // shown the same caller.
    $isAdmin = $userbank->is_admin();
    $hideIps   = Config::getBool('banlist.hideplayerips') && !$isAdmin;
    $hideAdmin = Config::getBool('banlist.hideadminname') && !$isAdmin;

    $row = $GLOBALS['PDO']->query(
        "SELECT BA.bid, BA.type, BA.ip, BA.authid, BA.name, BA.created, BA.ends, BA.length,
                BA.reason, BA.aid, BA.adminIp, BA.sid, BA.country, BA.RemovedOn, BA.RemovedBy,
                BA.RemoveType, BA.ureason,
                AD.user AS admin_name,
                SE.ip AS server_ip, SE.port AS server_port,
                MO.icon AS mod_icon, MO.name AS mod_name,
                CAST(MID(BA.authid, 9, 1) AS UNSIGNED)
                  + CAST('76561197960265728' AS UNSIGNED)
                  + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
                (SELECT count(*) FROM `:prefix_demos` AS DM
                  WHERE DM.demtype = 'B' AND DM.demid = BA.bid) AS demo_count,
                (SELECT count(*) FROM `:prefix_bans` AS BH
                  WHERE (BH.type = 0 AND BA.type = 0 AND BH.authid = BA.authid AND BA.authid <> '')
                     OR (BH.type = 1 AND BA.type = 1 AND BH.ip = BA.ip AND BA.ip <> '' AND BA.ip IS NOT NULL))
                  AS history_count
           FROM `:prefix_bans` AS BA
      LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
      LEFT JOIN `:prefix_mods`    AS MO ON MO.mid = SE.modid
      LEFT JOIN `:prefix_admins`  AS AD ON BA.aid = AD.aid
          WHERE BA.bid = ?"
    )->single([$bid]);
    if (!$row) {
        throw new ApiError('not_found', 'Ban not found.', null, 404);
    }

    $type      = (int)$row['type'];
    $authid    = (string)$row['authid'];
    $banIp     = (string)($row['ip'] ?? '');
    $created   = (int)$row['created'];
    $length    = (int)$row['length'];
    $ends      = (int)$row['ends'];
    $removedOn = $row['RemovedOn'] !== null ? (int)$row['RemovedOn'] : null;

    // Mirror the page-side state machine: `length=0` is permanent;
    // RemoveType marks deleted/unbanned/expired explicitly; otherwise
    // an `ends` timestamp in the past collapses to "expired" even
    // without a row update (PruneBans() catches those eventually).
    $removeType = (string)($row['RemoveType'] ?? '');
    if ($removeType === 'U' || $removeType === 'D') {
        $state = 'unbanned';
    } elseif ($removeType === 'E') {
        $state = 'expired';
    } elseif ($length === 0) {
        $state = 'permanent';
    } elseif ($ends > 0 && $ends < time()) {
        $state = 'expired';
    } else {
        $state = 'active';
    }

    $steam2 = $authid !== '' && SteamID::isValidID($authid) ? $authid : '';
    // Some legacy rows hold malformed authids (#900); fall back to a
    // canonical placeholder so toSteam3() doesn't blow up the response.
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
        $commentRows = $GLOBALS['PDO']->query(
            "SELECT C.cid, C.commenttxt, C.added, C.edittime,
                    (SELECT user FROM `:prefix_admins` WHERE aid = C.aid)     AS author,
                    (SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editor
               FROM `:prefix_comments` AS C
              WHERE C.type = 'B' AND C.bid = ?
           ORDER BY C.added DESC"
        )->resultset([$bid]);
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
        'bid' => $bid,
        'player' => [
            'name'         => (string)$row['name'],
            'type'         => $type,
            'steam_id'     => $steam2,
            'steam_id_3'   => $steam3,
            'community_id' => (string)$row['community_id'],
            'ip'           => $hideIps || $banIp === '' ? null : $banIp,
            'country'      => !empty($row['country']) && trim((string)$row['country']) !== '' ? (string)$row['country'] : null,
        ],
        'ban' => [
            'reason'           => (string)$row['reason'],
            'banned_at'        => $created,
            'banned_at_human'  => Config::time($created),
            'length_seconds'   => $length,
            'length_human'     => $length === 0 ? 'Permanent' : SecondsToString($length),
            'expires_at'       => $length === 0 ? null : $ends,
            'expires_at_human' => $length === 0 ? null : Config::time($ends),
            'state'            => $state,
            'unban_reason'     => (string)($row['ureason'] ?? ''),
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
        'demo_count'       => (int)$row['demo_count'],
        'history_count'    => (int)$row['history_count'],
        'comments_visible' => $commentsVisible,
        'comments'         => $comments,
    ];
}
