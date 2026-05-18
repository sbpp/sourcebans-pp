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
    $rawType  = (int)($params['type'] ?? 0);
    $banType  = BanType::tryFrom($rawType) ?? BanType::Steam;
    $type     = $banType->value;
    $steam    = SteamID::toSteam2(trim((string)($params['steam'] ?? '')));
    $ip       = preg_replace('#[^\d\.]#', '', (string)($params['ip'] ?? ''));
    $length   = (int)($params['length'] ?? 0);
    $dfile    = (string)($params['dfile'] ?? '');
    $dname    = (string)($params['dname'] ?? '');
    $reason   = (string)($params['reason'] ?? '');
    $fromsub  = (int)($params['fromsub'] ?? 0);

    if (empty($steam) && $banType === BanType::Steam) {
        throw new ApiError('validation', 'You must type a Steam ID or Community ID', 'steam');
    }
    if ($banType === BanType::Steam && !SteamID::isValidID($steam)) {
        throw new ApiError('validation', 'Please enter a valid Steam ID or Community ID', 'steam');
    }
    if (empty($ip) && $banType === BanType::Ip) {
        throw new ApiError('validation', 'You must type an IP', 'ip');
    }
    if ($banType === BanType::Ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
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
    // Surface the conflicting bid in the error envelope so a Re-apply
    // attempt against an unbanned/expired row gives the admin enough
    // context to investigate the OTHER active row that's blocking the
    // re-add (the row visibly says unbanned/expired but a sibling
    // active ban for the same identity makes the duplicate-check fire
    // — without the bid the admin sees "already banned" with no way
    // to tell which row, and the Re-apply UX reads as broken). The
    // production-realistic case (one ban, lifted, re-applied) was
    // never affected because the unbanned row's `RemovedBy IS NOT NULL`
    // already excludes it from the check; the bid call-out is for the
    // multi-ban shape (legacy data, race, or admin adding a second
    // ban while the first is still active). `LIMIT 1 ORDER BY bid DESC`
    // picks the most recent active row, which is the one the admin
    // most likely wants to look at.
    if ($banType === BanType::Steam) {
        $chk = $GLOBALS['PDO']->query(
            "SELECT bid FROM `:prefix_bans`
            WHERE authid = ? AND (length = 0 OR ends > UNIX_TIMESTAMP())
            AND RemovedBy IS NULL AND type = '0'
            ORDER BY bid DESC LIMIT 1"
        )->single([$steam]);
        if ($chk) {
            $existingBid = (int)$chk['bid'];
            throw new ApiError('already_banned', "SteamID: $steam is already banned by ban #$existingBid.");
        }

        foreach ($userbank->GetAllAdmins() as $admin) {
            if ($admin['authid'] === $steam && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity']) {
                throw new ApiError('immune', "SteamID: Admin {$admin['user']} ($steam) is immune.");
            }
        }
    }
    if ($banType === BanType::Ip) {
        $chk = $GLOBALS['PDO']->query(
            "SELECT bid FROM `:prefix_bans`
            WHERE ip = ? AND (length = 0 OR ends > UNIX_TIMESTAMP())
            AND RemovedBy IS NULL AND type = '1'
            ORDER BY bid DESC LIMIT 1"
        )->single([$ip]);
        if ($chk) {
            $existingBid = (int)$chk['bid'];
            throw new ApiError('already_banned', "IP: $ip is already banned by ban #$existingBid.");
        }
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_bans`(created,type,ip,authid,name,ends,length,reason,aid,adminIp ) VALUES
        (UNIX_TIMESTAMP(),?,?,?,?,(UNIX_TIMESTAMP() + ?),?,?,?,?)"
    )->execute([$banType->value, $ip, $steam, $nickname, $length * 60, $len, $reason, $userbank->GetAid(), $_SERVER['REMOTE_ADDR'] ?? '']);
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
    Log::add(LogType::Message, 'Ban Added', "Ban against (" . ($banType === BanType::Steam ? $steam : $ip) . ") has been added. Reason: $reason; Length: $length");

    return [
        'bid'    => $newId,
        'reload' => true,
        'kickit' => $kickit ? ['check' => $banType === BanType::Steam ? $steam : $ip, 'type' => $banType->value] : null,
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

    $inferredType = (trim($ban['SteamId'] ?? '') === '') ? BanType::Ip : BanType::Steam;

    return [
        'subid'    => $subid,
        'nickname' => $ban['name']    ?? '',
        'steam'    => $ban['SteamId'] ?? '',
        'ip'       => $ban['sip']     ?? '',
        'length'   => 0,
        'type'     => $inferredType->value,
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

    $banType = BanType::tryFrom((int)($ban['type'] ?? 0)) ?? BanType::Steam;

    return [
        'bid'      => $bid,
        'nickname' => $ban['name']   ?? '',
        'steam'    => $ban['authid'] ?? '',
        'ip'       => $ban['ip']     ?? '',
        'length'   => (int)($ban['length'] ?? 0),
        'type'     => $banType->value,
        'reason'   => $ban['reason'] ?? '',
        'demo'     => $demo ? ['filename' => $demo['filename'], 'origname' => $demo['origname']] : null,
    ];
}

function api_bans_paste(array $params): array
{
    $sid  = (int)($params['sid']  ?? 0);
    $name = (string)($params['name'] ?? '');

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
                'type'     => BanType::Steam->value,
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
    // #1275 — admin-bans is Pattern A (`?section=…`); the legacy
    // `#^2` / `#^1` fragment anchors that targeted the old page-toc
    // chrome are no longer wired (the page handler renders ONE
    // section per request now). Redirect callers back to the section
    // they came from so adding a comment doesn't ricochet to the
    // default add-ban surface.
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        'S' => '?p=admin&c=bans&section=submissions',
        'P' => '?p=admin&c=bans&section=protests',
        default => null,
    };
    if ($redir === null) {
        throw new ApiError('bad_type', 'Bad comment type.');
    }

    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_comments`(bid,type,aid,commenttxt,added) VALUES (?,?,?,?,UNIX_TIMESTAMP())"
    )->execute([$bid, $ctype, $userbank->GetAid(), $ctext]);

    Log::add(LogType::Message, 'Comment Added', "$username added a comment for ban #$bid");

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
    // #1275 — see api_bans_add_comment for the rationale; submissions
    // / protests redirect back into their own Pattern A sections.
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        'S' => '?p=admin&c=bans&section=submissions',
        'P' => '?p=admin&c=bans&section=protests',
        default => null,
    };
    if ($redir === null) {
        throw new ApiError('bad_type', 'Bad comment type.');
    }

    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_comments` SET commenttxt = ?, editaid = ?, edittime = UNIX_TIMESTAMP() WHERE cid = ?"
    )->execute([$ctext, $userbank->GetAid(), $cid]);

    Log::add(LogType::Message, 'Comment Edited', "$username edited comment #$cid");

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
    // #1275 — match the section-aware redirect shape from
    // api_bans_add_comment / api_bans_edit_comment so a deleted
    // comment lands the admin back on the queue they were on.
    $redir = match ($ctype) {
        'B' => '?p=banlist' . $pagelink,
        'C' => '?p=commslist' . $pagelink,
        'S' => '?p=admin&c=bans&section=submissions',
        'P' => '?p=admin&c=bans&section=protests',
        default => '?p=admin&c=bans',
    };

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_comments` WHERE cid = ?")->execute([$cid]);
    Log::add(LogType::Message, 'Comment Deleted', "$username deleted comment #$cid");

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
            ':type'    => BanType::Steam->value,
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

    Log::add(LogType::Message, 'Group Banned',
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

    if ($friends === null) {
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

    Log::add(LogType::Message, 'Friends Banned',
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
                Log::add(LogType::Message, 'Player kicked', "$username kicked player {$player['name']} ({$player['steamid']})");
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

    Log::add(LogType::Message, 'Message sent to player', "The following message was sent to $name on server (#$sid): $message");

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
 * Player-detail payload for the right-side drawer (#1123 C1).
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
 *   notes_visible: bool,
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

    $banType   = BanType::tryFrom((int)$row['type']) ?? BanType::Steam;
    $type      = $banType->value;
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
    //
    // #1352: pre-2.0 admin-lifted rows whose `RemoveType IS NULL` (the
    // v1.x admin-unban write path didn't always populate the column —
    // see `web/updater/data/810.php`'s backfill migration) get tagged
    // `unbanned` via the `RemovedOn IS NOT NULL && RemovedBy > 0`
    // disjunction, mirroring the same arm in `page.banlist.php`'s
    // post-loop state computation. Without this branch the API would
    // return `state: 'active'` for rows the page-side `?state=unbanned`
    // SQL filter just pulled in — visibly broken on the drawer's
    // detail surface.
    $removal      = BanRemoval::tryFrom((string)($row['RemoveType'] ?? ''));
    $removedByInt = $row['RemovedBy'] !== null ? (int)$row['RemovedBy'] : 0;
    $isPre2AdminLift = $removal === null && $removedOn !== null && $removedByInt > 0;
    if ($removal === BanRemoval::Unbanned || $removal === BanRemoval::Deleted) {
        $state = 'unbanned';
    } elseif ($removal === BanRemoval::Expired) {
        $state = 'expired';
    } elseif ($isPre2AdminLift) {
        $state = 'unbanned';
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
        // notes_visible is the drawer's signal for whether to render the
        // Notes tab at all (#1165). It mirrors the dispatcher gate on
        // `notes.list` (requireAdmin=true) so a public visitor sees three
        // tabs (Overview / History / Comms) and an admin sees four.
        'notes_visible'    => $isAdmin,
        'comments'         => $comments,
    ];
}

/**
 * Autocomplete backend for the command palette (#1123 C2).
 *
 * Returns up to `limit` matching ban rows for a free-text query that the
 * palette types into as the admin presses keys. The handler is admin-only
 * (the palette only mounts when logged in) so the wire format can include
 * IPs unconditionally; the public ban list separately gates IP exposure
 * behind `banlist.hideplayerips` but the palette never renders to anonymous
 * visitors. Matching covers `name` (LIKE %q%), `authid`, and `ip`. Steam
 * IDs are normalised through `SteamID::toSearchPattern()` so `STEAM_0`
 * and `STEAM_1` variants of the same account both match (#1130 fix
 * extended to autocomplete). Numeric queries shorter than 2 chars short-
 * circuit to an empty result so a single keypress doesn't sweep the bans
 * table.
 *
 * Inputs: `q` (string, free text) and `limit` (int, default 10, clamped to 20).
 *
 * @return array{bans: array<int, array{bid:int, name:string, steam:string, ip:string, type:int}>}
 */
function api_bans_search(array $params): array
{
    $q = trim((string)($params['q'] ?? ''));
    if ($q === '' || mb_strlen($q) < 2) {
        return ['bans' => []];
    }

    $limit = (int)($params['limit'] ?? 10);
    if ($limit < 1) $limit = 1;
    if ($limit > 20) $limit = 20;

    // Mirror page.banlist.php's authid handling so the palette finds the
    // same rows the full search would. `toSearchPattern()` returns a
    // REGEXP that matches both STEAM_0 and STEAM_1 variants; if the input
    // isn't a recognisable Steam ID it returns null and we fall back to
    // a plain LIKE on the raw query.
    $authidPattern = SteamID::toSearchPattern($q);
    if ($authidPattern !== null) {
        $authidClause = 'BA.authid REGEXP ?';
        $authidParam  = $authidPattern;
    } else {
        $authidClause = 'BA.authid LIKE ?';
        $authidParam  = '%' . $q . '%';
    }

    $like = '%' . $q . '%';

    // PDO emulation prepare quotes bound parameters; MariaDB rejects a
    // quoted string in `LIMIT`. The value is server-clamped to 1..20
    // a few lines above, so inlining the int literal is safe and avoids
    // the per-call PDOStatement::bindValue dance the wrapper doesn't
    // expose for LIMIT.
    $rows = $GLOBALS['PDO']->query(
        "SELECT BA.bid, BA.name, BA.authid, BA.ip, BA.type
           FROM `:prefix_bans` AS BA
          WHERE BA.name LIKE ?
             OR " . $authidClause . "
             OR BA.ip   LIKE ?
       ORDER BY BA.created DESC
          LIMIT " . $limit
    )->resultset([$like, $authidParam, $like]);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'bid'   => (int)$r['bid'],
            'name'  => (string)$r['name'],
            'steam' => (string)$r['authid'],
            'ip'    => (string)($r['ip'] ?? ''),
            'type'  => (int)$r['type'],
        ];
    }
    return ['bans' => $out];
}

/**
 * Sibling-bans feed for the player-detail drawer's History tab (#1165).
 *
 * Returns the player's other bans (same authid for type=0, same IP for
 * type=1) excluding the bid the drawer is currently displaying. The
 * Overview tab already shows the current ban; the History tab is "what
 * else is on file for this player". Action is registered public to
 * match `bans.detail`'s reach so the drawer's tab chrome behaves
 * identically for anonymous and admin callers — IP exposure follows the
 * same `banlist.hideplayerips` / admin gate `bans.detail` enforces, and
 * admin names follow `banlist.hideadminname`.
 *
 * Inputs: `bid` (int, the drawer's current ban id) — OR `authid`
 * (string, SteamID), supplied directly. The `authid` path lets the
 * drawer call this handler when the focal record is a comm
 * (`api_comms_detail`) and there's no anchor `bid` in `:prefix_bans`
 * to do the type/authid/ip lookup against. When `authid` is provided,
 * the handler matches Steam bans only (`BA.type = 0 AND BA.authid = ?`)
 * because IP matching needs an IP from the focal record and the comm
 * focal has none. When neither is provided, returns `bad_request`.
 *
 * @return array{
 *   items: list<array{
 *     bid: int, type: int, banned_at: int, banned_at_human: string,
 *     length_seconds: int, length_human: string,
 *     expires_at: int|null, expires_at_human: string|null,
 *     state: string, reason: string,
 *     admin_name: string|null, removed_by: string|null,
 *     removed_at: int|null, removed_at_human: string|null,
 *     server_name: string|null
 *   }>,
 *   total: int
 * }
 */
function api_bans_player_history(array $params): array
{
    global $userbank;

    $isAdmin = $userbank->is_admin();
    $hideAdmin = Config::getBool('banlist.hideadminname') && !$isAdmin;

    // Two-shape input: either a `bid` (existing bans-focal drawer path,
    // looks up the anchor ban so we can match siblings by the same
    // authid for type=0 or the same IP for type=1) OR an `authid`
    // (new comm-focal drawer path — there's no anchor bid because the
    // drawer was opened from a comm row, and the player may have no
    // bans on file at all). When `authid` is supplied, we match Steam
    // bans only — IP matching needs an IP from the anchor and the comm
    // focal record has none.
    $authidParam = trim((string)($params['authid'] ?? ''));
    if ($authidParam !== '') {
        $anchorType = BanType::Steam;
        $authid     = $authidParam;
        $ip         = '';
        $bid        = 0;
    } else {
        $bid = (int)($params['bid'] ?? 0);
        if ($bid <= 0) {
            throw new ApiError('bad_request', 'bid or authid is required', 'bid');
        }

        // Look up the anchor ban so we can match siblings by the same
        // authid (type=0) or the same IP (type=1). Both columns are
        // forwarded as plain string params; `:prefix_bans` is the same
        // table the dispatcher's existing handlers read so phpstan-dba
        // type-checks the SQL.
        $anchor = $GLOBALS['PDO']->query(
            "SELECT type, authid, ip FROM `:prefix_bans` WHERE bid = ?"
        )->single([$bid]);
        if (!$anchor) {
            throw new ApiError('not_found', 'Ban not found.', null, 404);
        }

        $anchorType = BanType::tryFrom((int)$anchor['type']) ?? BanType::Steam;
        $authid     = (string)$anchor['authid'];
        $ip         = (string)($anchor['ip'] ?? '');
    }

    // No anchor identifier -> no siblings to match against. This also
    // shields the SQL from a `WHERE authid = ''` sweep on legacy rows
    // that have a blank authid + blank ip.
    if (($anchorType === BanType::Steam && $authid === '') || ($anchorType === BanType::Ip && $ip === '')) {
        return ['items' => [], 'total' => 0];
    }

    $matchClause = $anchorType === BanType::Ip
        ? "BA.type = 1 AND BA.ip = ? AND BA.ip <> ''"
        : "BA.type = 0 AND BA.authid = ? AND BA.authid <> ''";
    $matchParam  = $anchorType === BanType::Ip ? $ip : $authid;

    // The bid-keyed path needs `BA.bid <> ?` to exclude the focal ban
    // from the list of "other bans"; the authid-keyed path has no
    // focal ban to exclude (the drawer's focal is a comm), so we drop
    // the `<> ?` clause when bid is 0. Adding a `BA.bid <> 0` clause
    // would be a no-op (auto_increment guarantees no row has bid=0)
    // but keeping the SQL identical between paths makes the explain
    // plan clearer.
    if ($bid > 0) {
        $rows = $GLOBALS['PDO']->query(
            "SELECT BA.bid, BA.type, BA.created, BA.ends, BA.length, BA.reason,
                    BA.RemovedOn, BA.RemovedBy, BA.RemoveType,
                    AD.user AS admin_name,
                    SE.ip AS server_ip, SE.port AS server_port
               FROM `:prefix_bans` AS BA
          LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
          LEFT JOIN `:prefix_admins`  AS AD ON BA.aid = AD.aid
              WHERE " . $matchClause . " AND BA.bid <> ?
           ORDER BY BA.created DESC, BA.bid DESC
              LIMIT 100"
        )->resultset([$matchParam, $bid]);
    } else {
        $rows = $GLOBALS['PDO']->query(
            "SELECT BA.bid, BA.type, BA.created, BA.ends, BA.length, BA.reason,
                    BA.RemovedOn, BA.RemovedBy, BA.RemoveType,
                    AD.user AS admin_name,
                    SE.ip AS server_ip, SE.port AS server_port
               FROM `:prefix_bans` AS BA
          LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
          LEFT JOIN `:prefix_admins`  AS AD ON BA.aid = AD.aid
              WHERE " . $matchClause . "
           ORDER BY BA.created DESC, BA.bid DESC
              LIMIT 100"
        )->resultset([$matchParam]);
    }

    $items = [];
    foreach ($rows as $r) {
        $created = (int)$r['created'];
        $length  = (int)$r['length'];
        $ends    = (int)$r['ends'];
        $rowRemoval = BanRemoval::tryFrom((string)($r['RemoveType'] ?? ''));

        // #1352: same pre-2.0 admin-lift fallback as `api_bans_detail`
        // above — rows where v1.x left `RemoveType IS NULL` despite
        // `RemovedOn` + `RemovedBy` being set. The drawer's history
        // pane is a primary surface for this case (it's where players
        // see their full ban arc), so the parity matters.
        $removedOn       = $r['RemovedOn'] !== null ? (int)$r['RemovedOn'] : null;
        $rowRemovedBy    = $r['RemovedBy'] !== null ? (int)$r['RemovedBy'] : 0;
        $rowIsPre2Lift   = $rowRemoval === null && $removedOn !== null && $rowRemovedBy > 0;

        if ($rowRemoval === BanRemoval::Unbanned || $rowRemoval === BanRemoval::Deleted) {
            $state = 'unbanned';
        } elseif ($rowRemoval === BanRemoval::Expired) {
            $state = 'expired';
        } elseif ($rowIsPre2Lift) {
            $state = 'unbanned';
        } elseif ($length === 0) {
            $state = 'permanent';
        } elseif ($ends > 0 && $ends < time()) {
            $state = 'expired';
        } else {
            $state = 'active';
        }
        $removedByName = null;
        if ($r['RemovedBy'] !== null && (int)$r['RemovedBy'] > 0 && !$hideAdmin) {
            $removedRow = $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = ?")
                ->single([(int)$r['RemovedBy']]);
            if ($removedRow && !empty($removedRow['user'])) {
                $removedByName = (string)$removedRow['user'];
            }
        }

        $serverName = null;
        if (!empty($r['server_ip'])) {
            $serverName = $r['server_ip'] . (!empty($r['server_port']) ? ':' . $r['server_port'] : '');
        }

        $items[] = [
            'bid'              => (int)$r['bid'],
            'type'             => (int)$r['type'],
            'banned_at'        => $created,
            'banned_at_human'  => Config::time($created),
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
            'server_name'      => $serverName,
        ];
    }

    return ['items' => $items, 'total' => count($items)];
}

/**
 * Lift an active ban (#1301).
 *
 * Modern JSON twin of the legacy `?p=banlist&a=unban&id=…&key=…` GET
 * handler in `page.banlist.php`. The legacy GET path stays put (no-JS
 * fallback for the icon-only theme leg + third-party themes that still
 * ship the v1.x action links), but the banlist's visible-action
 * affordance now wires through this action so the row can update
 * in-place + show a toast without a full page reload.
 *
 * Permission gate mirrors the legacy GET handler exactly:
 *
 *   ADMIN_OWNER | ADMIN_UNBAN     — unconditional, lift any row.
 *   ADMIN_UNBAN_OWN_BANS          — only the row's own admin (`aid`).
 *   ADMIN_UNBAN_GROUP_BANS        — only rows where the issuing
 *                                   admin's `gid` matches the
 *                                   caller's `gid`.
 *
 * The dispatcher gate is `ADMIN_OWNER | ADMIN_UNBAN |
 * ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS` — the broadest "any
 * unban-ish flag" match — and the per-row precision check happens
 * inside the handler, since the dispatcher can't see which row the
 * caller wants to act on.
 *
 * #1301: `ureason` is **required**. v1.x prompted via sourcebans.js's
 * UnbanBan() helper and required a non-empty reason; v2.0 silently
 * accepted '', so the audit log lost the *why*. Both this handler and
 * the legacy GET fallback now bounce empty reasons.
 *
 * Inputs:
 *   - `bid`     (int, required)    — the ban id.
 *   - `ureason` (string, required) — admin-supplied unban reason; we
 *     trim and store as-is. Stored raw in `ureason` (per the
 *     "store raw, escape on display" anti-pattern); the column lives
 *     behind the same Smarty auto-escape pipeline as `reason`.
 *
 * @return array{ bid: int, state: string }
 */
function api_bans_unban(array $params): array
{
    global $userbank;

    $bid = (int)($params['bid'] ?? 0);
    if ($bid <= 0) {
        throw new ApiError('bad_request', 'bid must be a positive integer', 'bid');
    }
    $ureason = trim((string)($params['ureason'] ?? ''));
    if ($ureason === '') {
        throw new ApiError(
            'validation',
            'You must supply a reason when unbanning a player.',
            'ureason'
        );
    }

    $row = $GLOBALS['PDO']->query(
        "SELECT B.bid, B.ip, B.authid, B.name, B.created, B.sid, B.type,
                B.length, B.ends, B.RemoveType, B.aid,
                A.gid AS gid,
                M.steam_universe,
                UNIX_TIMESTAMP() AS now
           FROM `:prefix_bans` AS B
      LEFT JOIN `:prefix_servers` AS S ON S.sid = B.sid
      LEFT JOIN `:prefix_mods`    AS M ON M.mid = S.modid
      LEFT JOIN `:prefix_admins`  AS A ON A.aid = B.aid
          WHERE B.bid = ?"
    )->single([$bid]);

    if (!$row) {
        throw new ApiError('not_found', 'Ban not found.', null, 404);
    }

    $rowAid = (int)($row['aid'] ?? 0);
    $rowGid = (int)($row['gid'] ?? 0);
    $callerGid = (int)$userbank->GetProperty('gid');
    $allowed =
        $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban))
        || ($userbank->HasAccess(WebPermission::UnbanOwnBans)   && $rowAid === $userbank->GetAid())
        || ($userbank->HasAccess(WebPermission::UnbanGroupBans) && $rowGid === $callerGid);
    if (!$allowed) {
        throw new ApiError('forbidden', "You don't have access to this ban.", null, 403);
    }

    if (!empty($row['RemoveType'])) {
        throw new ApiError('not_active', 'Ban has already been lifted.', null, 409);
    }
    $length = (int)$row['length'];
    $ends   = (int)$row['ends'];
    if ($length > 0 && $ends <= time()) {
        throw new ApiError('not_active', 'Ban has already expired.', null, 409);
    }

    $GLOBALS['PDO']->query(
        "UPDATE `:prefix_bans`
            SET `RemovedBy`  = ?,
                `RemoveType` = ?,
                `RemovedOn`  = UNIX_TIMESTAMP(),
                `ureason`    = ?
          WHERE `bid` = ?"
    )->execute([$userbank->GetAid(), BanRemoval::Unbanned->value, $ureason, $bid]);

    // Mirror the legacy GET handler: archive any open protests for this
    // ban so they don't keep showing up in the moderation queue.
    $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '4' WHERE bid = ?")
        ->execute([$bid]);

    // Mirror the legacy GET handler's RCON-based in-game cleanup: any
    // server the player was banned on within the last 5 minutes (per the
    // banlog) gets a `removeid` / `removeip` so the in-game state
    // matches the panel state immediately. Same lookback (300s) as
    // page.banlist.php.
    $blocked = $GLOBALS['PDO']->query(
        "SELECT s.sid, m.steam_universe
           FROM `:prefix_banlog` AS bl
     INNER JOIN `:prefix_servers` AS s ON s.sid = bl.sid
     INNER JOIN `:prefix_mods`    AS m ON m.mid = s.modid
          WHERE bl.bid = ?
            AND (UNIX_TIMESTAMP() - bl.time <= 300)"
    )->resultset([$bid]);

    $rowBanType = BanType::tryFrom((int)$row['type']) ?? BanType::Steam;
    $type       = $rowBanType === BanType::Steam ? (string)$row['authid'] : (string)$row['ip'];

    foreach ($blocked as $tempban) {
        if ($rowBanType === BanType::Steam) {
            rcon('removeid STEAM_' . (string)$tempban['steam_universe'] . substr((string)$row['authid'], 7), (int)$tempban['sid']);
        } else {
            rcon('removeip ' . (string)$row['ip'], (int)$tempban['sid']);
        }
    }
    $blockedSids = array_map(static fn(array $b): int => (int)$b['sid'], $blocked);
    $createdAt   = (int)$row['created'];
    $now         = (int)$row['now'];
    $banSid      = (int)$row['sid'];
    if (($now - $createdAt) <= 300 && $banSid !== 0 && !in_array($banSid, $blockedSids, true)) {
        if ($rowBanType === BanType::Steam) {
            rcon('removeid STEAM_' . (string)$row['steam_universe'] . substr((string)$row['authid'], 7), $banSid);
        } else {
            rcon('removeip ' . (string)$row['ip'], $banSid);
        }
    }

    Log::add(
        LogType::Message,
        'Player Unbanned',
        sprintf('%s (%s) has been unbanned. Reason: %s', (string)$row['name'], $type, $ureason)
    );

    return [
        'bid'   => $bid,
        'state' => 'unbanned',
    ];
}
