<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use Sbpp\Servers\RconStatusCache;
use Sbpp\Servers\SourceQueryCache;

/**
 * Mirrors the access loop in web/pages/admin.rcon.php so the API enforces
 * the same per-server scoping the UI does. The dispatcher's SM_RCON|SM_ROOT
 * check is a global "is this admin allowed to RCON anything?" flag; this
 * adds the per-`sid` check that says "is this admin actually mapped to
 * this server (directly or via a server group)?".
 */
function _api_servers_admin_can_rcon(int $aid, int $sid): bool
{
    if ($aid <= 0 || $sid <= 0) {
        return false;
    }
    $GLOBALS['PDO']->query("SELECT server_id, srv_group_id FROM `:prefix_admins_servers_groups` WHERE admin_id = :aid");
    $GLOBALS['PDO']->bind(':aid', $aid);
    $rows = $GLOBALS['PDO']->resultset();
    foreach ($rows as $row) {
        if ((int)$row['server_id'] === $sid) {
            return true;
        }
        if ((int)$row['srv_group_id'] > 0) {
            $GLOBALS['PDO']->query("SELECT server_id FROM `:prefix_servers_groups` WHERE group_id = :gid");
            $GLOBALS['PDO']->bind(':gid', (int)$row['srv_group_id']);
            foreach ($GLOBALS['PDO']->resultset() as $g) {
                if ((int)$g['server_id'] === $sid) {
                    return true;
                }
            }
        }
    }
    return false;
}

function api_servers_add(array $params): array
{
    $ip      = (string)($params['ip'] ?? '');
    $port    = (string)($params['port'] ?? '');
    $rcon    = (string)($params['rcon'] ?? '');
    $rcon2   = (string)($params['rcon2'] ?? '');
    $mod     = (int)($params['mod'] ?? -2);
    $enabledRaw = $params['enabled'] ?? null;
    $enabled    = $enabledRaw === true || $enabledRaw === 'true';
    $group   = (string)($params['group'] ?? '');

    if ($ip === '') {
        throw new ApiError('validation', 'You must type the server address.', 'address');
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new ApiError('validation', 'You must type a valid IP.', 'address');
    }
    if ($port === '') {
        throw new ApiError('validation', 'You must type the server port.', 'port');
    }
    if (!is_numeric($port)) {
        throw new ApiError('validation', 'You must type a valid port number.', 'port');
    }
    if (!empty($rcon) && $rcon !== $rcon2) {
        throw new ApiError('validation', "The passwords don't match.", 'rcon2');
    }
    if ($mod === -2) {
        throw new ApiError('validation', 'You must select the mod your server runs.', 'mod');
    }
    if ($group === '-2') {
        throw new ApiError('validation', 'You must select an option.', 'group');
    }

    $chk = $GLOBALS['PDO']->query("SELECT sid FROM `:prefix_servers` WHERE ip = ? AND port = ?;")
        ->single([$ip, (int)$port]);
    if ($chk) {
        throw new ApiError('duplicate', 'There already is a server with that IP:Port combination.');
    }

    $sid = nextSid();
    $GLOBALS['PDO']->query(
        "INSERT INTO `:prefix_servers` (`sid`, `ip`, `port`, `rcon`, `modid`, `enabled`)
        VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$sid, $ip, (int)$port, $rcon, $mod, $enabled ? 1 : 0]);

    foreach (explode(',', $group) as $g) {
        if ($g !== '') {
            $GLOBALS['PDO']->query(
                "INSERT INTO `:prefix_servers_groups` (`server_id`, `group_id`) VALUES (?, ?)"
            )->execute([$sid, $g]);
        }
    }

    Log::add(LogType::Message, 'Server Added', "Server ($ip:$port) has been added.");

    return [
        'sid'     => $sid,
        'reload'  => true,
        'message' => [
            'title' => 'Server Added',
            'body'  => 'Your server has been successfully created.',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=servers',
        ],
    ];
}

function api_servers_remove(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $info = $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $info = $GLOBALS['PDO']->single();

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $ok = $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("DELETE FROM `:prefix_servers_groups` WHERE server_id = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $GLOBALS['PDO']->execute();

    $GLOBALS['PDO']->query("UPDATE `:prefix_admins_servers_groups` SET server_id = -1 WHERE server_id = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $GLOBALS['PDO']->execute();

    $cnt = (int)($GLOBALS['PDO']->query("SELECT count(sid) AS cnt FROM `:prefix_servers`")->single()['cnt'] ?? 0);

    if (!$ok) {
        throw new ApiError('delete_failed', 'There was a problem deleting the server from the database. Check the logs for more info');
    }

    Log::add(LogType::Message, 'Server Deleted', "Server ({$info['ip']}:{$info['port']}) has been deleted.");

    return [
        'remove'  => "sid_$sid",
        'counter' => ['srvcount' => $cnt],
        'message' => [
            'title' => 'Server Deleted',
            'body'  => 'The selected server has been deleted from the database',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=servers',
        ],
    ];
}

function api_servers_setup_edit(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $server = $GLOBALS['PDO']->query("SELECT sid, ip, port, modid, gid FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $server = $GLOBALS['PDO']->single();
    if (!$server) {
        throw new ApiError('not_found', 'Server not found');
    }

    // The rcon password is intentionally not returned. admin.edit.server.php
    // pre-fills the form with the placeholder '+-#*_'; the client treats an
    // unchanged value as "keep the existing password" on submit.
    return [
        'sid'   => (int)$server['sid'],
        'ip'    => $server['ip'],
        'port'  => $server['port'],
        'mod'   => $server['modid'],
        'group' => $server['gid'] ?? 0,
    ];
}

function api_servers_refresh(array $params): array
{
    return ['sid' => (int)($params['sid'] ?? 0)];
}

/**
 * Returns server info + player list for the requested server. Pure data —
 * the client decides how to render it.
 *
 * The A2S `GetInfo + GetPlayers` round-trip is delegated to
 * `Sbpp\Servers\SourceQueryCache` so back-to-back calls (a hand-mashed
 * Re-query button, a `for` loop hitting `?p=servers`) coalesce into ONE
 * UDP probe per `(ip, port)` per ~30s window. The handler still maps
 * the cached payload through the per-caller permission check
 * (`is_owner` / `can_ban`) and the per-call hostname truncation, so the
 * cache stays user-agnostic. See #1311 for the threat model.
 *
 * Per-player SteamID surfacing (restoring the pre-v2.0.0 right-click
 * context menu on the public servers list — see
 * `web/scripts/server-context-menu.js`): the A2S `GetPlayers` UDP
 * response does NOT carry SteamIDs. To surface them we issue a paired
 * RCON `status` round-trip per server via
 * `Sbpp\Servers\RconStatusCache::fetch` (sid-keyed, ~30s cache,
 * negative caches failures) and match by exact player name.
 *
 * The SteamID surfacing is the side-channel that's permission-gated,
 * NOT the action registration: `servers.host_players` stays public so
 * anonymous viewers continue to see hostname / map / online-count.
 * SteamIDs are attached ONLY when the caller holds
 * `WebPermission::Owner | WebPermission::AddBan` AND has per-server
 * RCON access via `_api_servers_admin_can_rcon`. The handler itself
 * is the load-bearing gate; the client feature-detects the `steamid`
 * field on each row and skips rows that don't carry it (bots, players
 * without a name match between the A2S and RCON responses). The new
 * `can_ban_player` boolean signals to the client whether to render
 * the kick/ban/block menu items at all — separate from the existing
 * `can_ban` flag which the legacy ban-row affordance already uses.
 */
function api_servers_host_players(array $params): array
{
    global $userbank;
    $sid           = (int)($params['sid'] ?? 0);
    $trunchostname = (int)($params['trunchostname'] ?? 48);

    $server = _api_servers_lookup_address($sid);

    $cached = SourceQueryCache::fetch((string) $server['ip'], (int) $server['port']);
    if ($cached === null) {
        return [
            'sid'      => $sid,
            'ip'       => $server['ip'],
            'port'     => $server['port'],
            'error'    => 'connect',
            'is_owner' => $userbank->HasAccess(WebPermission::Owner),
        ];
    }

    $info    = $cached['info'];
    $players = $cached['players'];
    $info['HostName'] = (string) preg_replace('/[\x00-\x1f]/', '', htmlspecialchars((string) ($info['HostName'] ?? '')));

    $os = match ($info['Os'] ?? '') {
        'w'     => 'fab fa-windows',
        'l'     => 'fab fa-linux',
        default => 'fas fa-server',
    };

    $canBan       = $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan));
    $canBanPlayer = $canBan && _api_servers_admin_can_rcon($userbank->GetAid(), $sid);

    // Build the per-player SteamID lookup if the caller is allowed to
    // see SteamIDs. The RCON probe is fronted by RconStatusCache so a
    // panel hit costs ONE TCP round-trip per (sid, ~30s window), and
    // failure (no rcon password / RCON connect failure / unparseable
    // status output) is negative-cached the same way.
    //
    // Anonymous and partial-permission callers never reach this branch
    // — they fall through to the no-SteamID `player_list[]` shape
    // below. The handler is the load-bearing gate; the client's
    // feature-detection (presence of `steamid` on a row) is the UX
    // signal, not the security boundary.
    //
    // Name-collision handling: SteamIDs are surfaced ONLY for names
    // that appear EXACTLY ONCE in BOTH the RCON `status` output AND
    // the A2S `GetPlayers` response. Two players with identical names
    // on one server can't be disambiguated from those two data sources
    // alone — picking one of the two SteamIDs (e.g. "first wins")
    // would mis-attribute it to the wrong row and the right-click
    // menu would mis-target a kick/ban. We drop both rows from the
    // SteamID side-channel instead; the JS gates the menu on the
    // presence of the field, so the affected rows fall back to the
    // native browser context menu and the admin reaches for the
    // existing `?p=admin&c=kickit/blockit/bans` flows.
    /** @var array<string, string> $steamidByName */
    $steamidByName = [];
    /** @var array<string, int> $rconNameCount */
    $rconNameCount = [];
    if ($canBanPlayer) {
        $statusCache = RconStatusCache::fetch($sid);
        if ($statusCache !== null) {
            foreach ($statusCache['players'] as $sp) {
                $name = $sp['name'];
                if ($name === '') {
                    continue;
                }
                $rconNameCount[$name] = ($rconNameCount[$name] ?? 0) + 1;
                if (!isset($steamidByName[$name])) {
                    $steamidByName[$name] = $sp['steamid'];
                }
            }
        }
    }

    /** @var array<string, int> $sqNameCount */
    $sqNameCount = [];
    foreach ($players as $p) {
        $nm = (string) ($p['Name'] ?? '');
        if ($nm !== '') {
            $sqNameCount[$nm] = ($sqNameCount[$nm] ?? 0) + 1;
        }
    }

    $playerList = [];
    foreach ($players as $p) {
        $name = (string) ($p['Name'] ?? '');
        $row  = [
            'id'     => $p['Id']    ?? null,
            'name'   => $name,
            'frags'  => (int) ($p['Frags'] ?? 0),
            'time'   => $p['Time']  ?? 0,
            'time_f' => $p['TimeF'] ?? '',
        ];
        if (
            $canBanPlayer
            && $name !== ''
            && isset($steamidByName[$name])
            && ($rconNameCount[$name] ?? 0) === 1
            && ($sqNameCount[$name] ?? 0) === 1
        ) {
            $row['steamid'] = $steamidByName[$name];
        }
        $playerList[] = $row;
    }

    return [
        'sid'        => $sid,
        'ip'         => $server['ip'],
        'port'       => $server['port'],
        'hostname'   => trunc($info['HostName'], $trunchostname),
        'players'    => (int) ($info['Players']    ?? 0),
        'maxplayers' => (int) ($info['MaxPlayers'] ?? 0),
        'map'        => basename((string) ($info['Map'] ?? '')),
        'mapfull'    => (string) ($info['Map'] ?? ''),
        'mapimg'     => GetMapImage((string) ($info['Map'] ?? '')),
        'os_class'   => $os,
        'secure'     => (bool) ($info['Secure'] ?? false),
        'player_list'    => $playerList,
        'can_ban'        => $canBan,
        'can_ban_player' => $canBanPlayer,
    ];
}

function api_servers_host_property(array $params): array
{
    $sid           = (int)($params['sid'] ?? 0);
    $trunchostname = (int)($params['trunchostname'] ?? 48);

    $server = _api_servers_lookup_address($sid);

    $cached = SourceQueryCache::fetch((string) $server['ip'], (int) $server['port']);
    if ($cached === null) {
        return ['ip' => $server['ip'], 'port' => $server['port'], 'error' => 'connect'];
    }

    $hostname = (string) preg_replace('/[\x00-\x1f]/', '', htmlspecialchars((string) ($cached['info']['HostName'] ?? '')));
    return [
        'hostname' => trunc($hostname, $trunchostname),
        'ip'       => $server['ip'],
        'port'     => $server['port'],
    ];
}

function api_servers_host_players_list(array $params): array
{
    $idsStr = (string)($params['sids'] ?? '');
    $ids = array_filter(explode(';', $idsStr));
    if (count($ids) < 1) {
        return ['lines' => []];
    }

    $lines = [];
    foreach ($ids as $sid) {
        $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
        $GLOBALS['PDO']->bind(':sid', (int)$sid, PDO::PARAM_INT);
        $server = $GLOBALS['PDO']->single();
        if (empty($server['ip']) || empty($server['port'])) {
            continue;
        }
        $cached = SourceQueryCache::fetch((string) $server['ip'], (int) $server['port']);
        if ($cached === null) {
            $lines[] = "ERROR " . $server['ip'] . ':' . $server['port'];
            continue;
        }
        $hostname = (string) preg_replace('/[\x00-\x1f]/', '', htmlspecialchars((string) ($cached['info']['HostName'] ?? '')));
        $lines[]  = trunc($hostname, 48);
    }
    return ['lines' => $lines];
}

function api_servers_players(array $params): array
{
    $sid = (int)($params['sid'] ?? 0);

    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();
    if (empty($server['ip']) || empty($server['port'])) {
        return ['sid' => $sid, 'players' => []];
    }

    $cached = SourceQueryCache::fetch((string) $server['ip'], (int) $server['port']);
    if ($cached === null) {
        return ['sid' => $sid, 'players' => []];
    }

    return [
        'sid' => $sid,
        'players' => array_map(fn($p) => [
            'name'  => $p['Name']  ?? '',
            'frags' => (int) ($p['Frags'] ?? 0),
            'time'  => $p['Time']  ?? 0,
        ], $cached['players']),
    ];
}

/**
 * Lookup the `(ip, port)` for a `sid`, throwing the same `not_found`
 * envelope every public server-query handler used to throw inline.
 * Extracted so the cache-fronted handlers don't repeat the SELECT and
 * the empty-row guard.
 *
 * @return array{ip: string, port: int}
 */
function _api_servers_lookup_address(int $sid): array
{
    $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid, PDO::PARAM_INT);
    $server = $GLOBALS['PDO']->single();

    if (empty($server['ip']) || empty($server['port'])) {
        throw new ApiError('not_found', 'Server not found');
    }

    return ['ip' => (string) $server['ip'], 'port' => (int) $server['port']];
}

function api_servers_send_rcon(array $params): array
{
    global $userbank;
    $sid     = (int)($params['sid'] ?? 0);
    $command = (string)($params['command'] ?? '');
    $output  = (bool)($params['output'] ?? true);

    // The dispatcher already checked the caller's global SM_RCON|SM_ROOT
    // flag. Verify they are also mapped to this specific server, mirroring
    // the access check admin.rcon.php uses to render the page.
    if (!_api_servers_admin_can_rcon($userbank->GetAid(), $sid)) {
        Log::add(LogType::Warning, 'Hacking Attempt',
            $userbank->GetProperty('user') . " tried to RCON server $sid without per-server access.");
        throw new ApiError('forbidden', 'No access to that server', null, 403);
    }

    if ($command === '') {
        return ['kind' => 'noop'];
    }
    if ($command === 'clr') {
        return ['kind' => 'clear'];
    }

    // Defense-in-depth substring filter: AGENTS.md disallows
    // `html_entity_decode` on JSON-API params (#1108) because the
    // dispatcher already gives us raw UTF-8 and re-decoding silently
    // collapses literal `&amp;`. This call site is a deliberate
    // exemption — the decoded value is *only* inspected for the
    // substring `rcon_password` and never stored or rendered, so a
    // user typing `rcon&#95;password` to dodge the filter still gets
    // caught. The literal-typed-entity attack remains plausible
    // because the rcon console is a free-text input forwarded to a
    // Source-engine RCON socket; the substring guard is the last
    // line of defense before the command leaves the panel.
    $command = html_entity_decode($command, ENT_QUOTES);

    if (stripos($command, 'rcon_password') !== false) {
        return [
            'kind'  => 'error',
            'error' => "You have to use this console. Don't try to cheat the rcon password!",
        ];
    }

    $ret = rcon($command, $sid);

    if (!$ret) {
        return ['kind' => 'error', 'error' => "Can't connect to server!"];
    }

    if (!$output) {
        return ['kind' => 'noop'];
    }

    // Return command + raw output as separate fields. The client renders
    // them with textContent so a malicious gameserver response cannot
    // inject HTML into an admin's panel.
    return [
        'kind'    => 'append',
        'command' => $command,
        'output'  => (string)$ret,
    ];
}
