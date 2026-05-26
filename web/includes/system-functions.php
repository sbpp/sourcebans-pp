<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

/**
 * Procedural helpers used across the panel.
 *
 * Most helpers in here are conceptually the same handful of operations
 * the legacy SourceBans 1.4.x panel exposed (build a ban-table link,
 * humanize a duration, fan a permission bitmask back to display labels,
 * walk an upload directory, rcon a server, …). The function names are
 * preserved for theme-fork compatibility; the bodies were rewritten
 * for PHP 8.5 idioms during the v2.0 cleanup. New code prefers the
 * `Sbpp\…` namespaced surfaces (`Sbpp\Db\Database`, `Sbpp\View\*`,
 * `Sbpp\Auth\*`); these globals exist for surfaces still wired
 * through the procedural entry points.
 */

use MaxMind\Db\Reader;
use xPaw\SourceQuery\SourceQuery;

if (!defined('IN_SB')) {
    die('You should not be here. Only follow links!');
}

// ---------------------------------------------------------------------
// Anchor / display helpers
// ---------------------------------------------------------------------

/**
 * Build an `<a>` tag with optional tooltip + click hook. Used by the
 * legacy default theme's table-row links and the few admin panes that
 * still build link strings server-side.
 *
 * NOTE: the `$tooltip`-bearing arm picks up the `tip` / `perm` CSS
 * class (legacy default theme); the bare arm has no class. The HTML
 * is whitespace-padded between the opening and closing tag for
 * legacy-template compatibility — the v1.x consumer relied on the
 * leading + trailing space when concatenating links inline.
 */
function CreateLinkR(string $title, string $url, string $tooltip = '', string $target = '_self', bool $wide = false, string $onclick = ''): string
{
    $hasTooltip = $tooltip !== '';
    $attrs      = [
        'href'   => $url,
        'target' => $target,
    ];
    if ($hasTooltip) {
        $attrs['class'] = $wide ? 'perm' : 'tip';
        $attrs['title'] = $tooltip;
    } else {
        $attrs['onclick'] = $onclick;
    }

    $rendered = '';
    foreach ($attrs as $name => $value) {
        $rendered .= sprintf(" %s='%s'", $name, $value);
    }

    return "<a{$rendered}> {$title} </a>";
}

/**
 * Map a web permission bitmask to a list of display labels. Returns
 * `false` if the mask is empty (kept for legacy callers that branch
 * on truthy falsy).
 *
 * `ADMIN_OWNER` short-circuits the per-flag check so an owner sees
 * every flag's display label (the legacy semantic).
 *
 * @return list<string>|false
 */
function BitToString(int $mask): array|false
{
    if ($mask === 0) {
        return false;
    }

    $perms = json_decode((string) file_get_contents(ROOT . '/configs/permissions/web.json'), true);
    if (!is_array($perms)) {
        return false;
    }

    $isOwner = ($mask & ADMIN_OWNER) !== 0;
    $out     = [];
    foreach ($perms as $perm) {
        $value = (int) $perm['value'];
        if ($value === ALL_WEB) {
            continue;
        }
        if ($isOwner || ($mask & $value) !== 0) {
            $out[] = (string) $perm['display'];
        }
    }

    return $out !== [] ? $out : false;
}

/**
 * Map a SourceMod char-flag string (`'abc'`, `'z'`, …) to the
 * corresponding human-readable labels. The `'z'` (root) flag
 * expands to every label. Returns `false` for an empty input.
 *
 * @return list<string>|false
 */
function SmFlagsToSb(string $flagstring): array|false
{
    if ($flagstring === '') {
        return false;
    }

    $flags = json_decode((string) file_get_contents(ROOT . '/configs/permissions/sourcemod.json'), true);
    if (!is_array($flags)) {
        return false;
    }

    $isRoot = str_contains($flagstring, 'z');
    $out    = [];
    foreach ($flags as $flag) {
        $char = (string) $flag['value'];
        if ($isRoot || str_contains($flagstring, $char)) {
            $out[] = (string) $flag['display'];
        }
    }

    return $out !== [] ? $out : false;
}

// ---------------------------------------------------------------------
// `:prefix_*` ID allocation
// ---------------------------------------------------------------------

/**
 * Returns the next available `:prefix_servers.sid` slot. The actual
 * INSERT is done by the caller — this is a defensive pre-check used
 * by some admin pages to render the id alongside the form.
 */
function NextSid(): int
{
    $row = $GLOBALS['PDO']->query('SELECT MAX(sid) AS next_sid FROM `:prefix_servers`')->single();
    return ((int) ($row['next_sid'] ?? 0)) + 1;
}

/**
 * Returns the next available `:prefix_admins.aid` slot. Same shape as
 * {@see NextSid()}.
 */
function NextAid(): int
{
    $row = $GLOBALS['PDO']->query('SELECT MAX(aid) AS next_aid FROM `:prefix_admins`')->single();
    return ((int) ($row['next_aid'] ?? 0)) + 1;
}

// ---------------------------------------------------------------------
// Misc string helpers
// ---------------------------------------------------------------------

/**
 * Truncate `$text` to `$len` characters with a trailing `...`. Reads
 * the byte length, not the multibyte codepoint length — kept that way
 * for byte-budget callers that pass an actual byte limit (e.g. SQL
 * column max size).
 */
function trunc(string $text, int $len): string
{
    return strlen($text) > $len ? substr($text, 0, $len) . '...' : $text;
}

/**
 * Bounce the caller back to the login screen with the `no_access`
 * marker if `$mask` isn't satisfied. Wraps `$userbank->HasAccess()`
 * for the procedural admin pages that gate at the top of the file.
 */
function CheckAdminAccess(int $mask): void
{
    global $userbank;
    if ($userbank->HasAccess($mask)) {
        return;
    }
    header('Location: index.php?p=login&m=no_access');
    exit;
}

/**
 * Render a duration in seconds as a human-readable string.
 *
 * `$textual = true` emits the multi-unit shape "1 mo, 2 wk, 4 d" used
 * across the banlist Length column. `$textual = false` emits the
 * compact "h:m:s" shape. Negative seconds are the legacy "session"
 * sentinel.
 */
function SecondsToString(int $sec, bool $textual = true): string
{
    if ($sec < 0) {
        return 'Session';
    }

    if (!$textual) {
        $hours = intdiv($sec, 3600);
        $rest  = $sec - ($hours * 3600);
        $mins  = intdiv($rest, 60);
        $secs  = $rest % 60;
        return "{$hours}:{$mins}:{$secs}";
    }

    static $units = [
        ['mo',  2592000],
        ['wk',  604800],
        ['d',   86400],
        ['hr',  3600],
        ['min', 60],
        ['sec', 1],
    ];

    $parts = [];
    foreach ($units as [$label, $size]) {
        if ($sec < $size) {
            continue;
        }
        $count = intdiv($sec, $size);
        $sec   = $sec - ($count * $size);
        $parts[] = "{$count} {$label}";
    }

    return $parts !== [] ? implode(', ', $parts) : '0 sec';
}

/**
 * Look up the ISO country code for `$ip` against the bundled MaxMind
 * country DB. Returns the placeholder `"zz"` on any failure (missing
 * DB, malformed IP, MaxMind exception).
 */
function FetchIp(string $ip): string
{
    try {
        $reader = new Reader(MMDB_PATH);
        $row    = $reader->get($ip);
        return is_array($row) ? (string) ($row['country']['iso_code'] ?? 'zz') : 'zz';
    } catch (Exception) {
        return 'zz';
    }
}

/**
 * Render the chrome footer + stop. Used by the legacy admin pages
 * that gate at the top with `if (...) { echo '...'; PageDie(); }`.
 */
function PageDie(): never
{
    include_once TEMPLATES_PATH . '/core/footer.php';
    exit;
}

/**
 * Resolve the per-server map thumbnail URL. Falls back to the bundled
 * `nomap.jpg` placeholder when the per-map asset is missing.
 */
function GetMapImage(string $map): string
{
    $candidate = SB_MAP_LOCATION . "/{$map}.jpg";
    return is_file($candidate) ? $candidate : SB_MAP_LOCATION . '/nomap.jpg';
}

/**
 * Case-insensitive extension allowlist check. `$validExts` is the
 * lowercased extension list (no leading dot).
 */
function checkExtension(string $file, array $validExts): bool
{
    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, $validExts, true);
}

// ---------------------------------------------------------------------
// Cron-style maintenance writers
// ---------------------------------------------------------------------

/**
 * Sweep expired bans into the `RemoveType = 'E'` (Expired) terminal
 * state and archive the matching protests + submissions. Idempotent.
 */
function PruneBans(): void
{
    global $userbank;
    $pdo     = $GLOBALS['PDO'];
    $adminId = max(0, $userbank?->GetAid() ?? 0);

    $pdo->query(
        'UPDATE `:prefix_bans`
            SET `RemovedBy` = 0,
                `RemoveType` = \'E\',
                `RemovedOn` = UNIX_TIMESTAMP()
          WHERE `length` != 0
            AND `ends` < UNIX_TIMESTAMP()
            AND `RemoveType` IS NULL'
    );
    $pdo->execute();

    $pdo->query(
        'UPDATE `:prefix_protests`
            SET `archiv` = 3,
                `archivedby` = :id
          WHERE `archiv` = 0
            AND bid IN (
                SELECT bid FROM `:prefix_bans` WHERE `RemoveType` = \'E\'
            )'
    );
    $pdo->bind(':id', $adminId);
    $pdo->execute();

    // Two single-column SELECTs are intentionally separate from the
    // composite UPDATE below: `UPDATE … WHERE` locks every row it
    // examines for the predicate, not just the rows it changes. We
    // surface the candidate `subid`s with a SELECT first so the
    // UPDATE only locks rows it'll mutate.
    $steamIds = $pdo
        ->query('SELECT DISTINCT authid FROM `:prefix_bans` WHERE `type` = 0 AND `RemoveType` IS NULL')
        ->resultset(null, PDO::FETCH_COLUMN);
    $banIps = $pdo
        ->query('SELECT ip FROM `:prefix_bans` WHERE type = 1 AND RemoveType IS NULL')
        ->resultset(null, PDO::FETCH_COLUMN);

    if ($steamIds === [] && $banIps === []) {
        return;
    }

    $clauses = [];
    $args    = [];
    if ($steamIds !== []) {
        $clauses[] = 'SteamId IN (' . implode(',', array_fill(0, count($steamIds), '?')) . ')';
        array_push($args, ...$steamIds);
    }
    if ($banIps !== []) {
        $clauses[] = 'sip IN (' . implode(',', array_fill(0, count($banIps), '?')) . ')';
        array_push($args, ...$banIps);
    }

    $subIds = $pdo
        ->query('SELECT `subid` FROM `:prefix_submissions` WHERE `archiv` = 0 AND (' . implode(' OR ', $clauses) . ')')
        ->resultset($args, PDO::FETCH_COLUMN);

    if ($subIds === []) {
        return;
    }

    $pdo
        ->query('UPDATE `:prefix_submissions`
                    SET `archiv` = 3,
                        `archivedby` = ?
                  WHERE `subid` IN (' . implode(',', array_fill(0, count($subIds), '?')) . ')')
        ->execute([$adminId, ...$subIds]);
}

/**
 * Sweep expired comm blocks into the `RemoveType = 'E'` terminal
 * state. Idempotent.
 */
function PruneComms(): void
{
    $GLOBALS['PDO']->query(
        'UPDATE `:prefix_comms`
            SET `RemovedBy` = 0,
                `RemoveType` = \'E\',
                `RemovedOn` = UNIX_TIMESTAMP()
          WHERE `length` != 0
            AND `ends` < UNIX_TIMESTAMP()
            AND `RemoveType` IS NULL'
    );
    $GLOBALS['PDO']->execute();
}

// ---------------------------------------------------------------------
// Filesystem stats
// ---------------------------------------------------------------------

/**
 * Human-readable total size of every file under `$dir`, recursively.
 *
 * The byte-count traversal lives in {@see getDirSizeBytes()}; this
 * thin wrapper formats the total via {@see sizeFormat()}. Keeping the
 * recursion in a typed-int helper avoids the legacy bug where the
 * inner recursive call returned a `sizeFormat()` string and `+=`'d
 * it back into the running total — PHP 8 warned "non-numeric value
 * encountered" and undercounted any tree with nested subdirectories
 * (the canonical `web/demos/<server>/<demo>.dem` layout would lose
 * its per-server subtotals).
 */
function getDirSize(string $dir): string
{
    return sizeFormat(getDirSizeBytes($dir));
}

/**
 * Recursive byte-count of every file under `$dir`. Returns a strict
 * int so callers can `+=` the result without tripping PHP 8's
 * "non-numeric value encountered" warning.
 */
function getDirSizeBytes(string $dir): int
{
    $bytes   = 0;
    $entries = glob(rtrim($dir, '/') . '/*', GLOB_NOSORT);
    if ($entries === false) {
        return 0;
    }
    foreach ($entries as $entry) {
        if (is_file($entry)) {
            $bytes += (int) filesize($entry);
            continue;
        }
        if (is_dir($entry)) {
            $bytes += getDirSizeBytes($entry);
        }
    }
    return $bytes;
}

/**
 * Format a byte-count as a human-readable string with binary
 * (`1024`-base) units. Uses 0 decimal places below MB and 2-3
 * decimals beyond, matching the precision the legacy `Statistics`
 * card on the admin home renders.
 */
function sizeFormat(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $exp        = (int) floor(log($bytes, 1024));
    $exp        = max(0, min(4, $exp));
    $precision  = [0, 0, 2, 2, 3][$exp];
    $unitLabel  = [' B', ' kB', ' MB', ' GB', ' TB'][$exp];
    return round($bytes / (1024 ** $exp), $precision) . $unitLabel;
}

// ---------------------------------------------------------------------
// RCON / SourceQuery helpers
// ---------------------------------------------------------------------

/**
 * For each Steam ID in `$steamids`, return the player record for the
 * matching slot on server `$sid` if they're connected. Used by the
 * "find this player on a live server" shortcut on a few admin
 * surfaces.
 *
 * @param list<string> $steamids Each entry is a Steam2 / Steam3 / Steam64 string.
 * @return array<string, array{name: string, steam: string, ip: string}> Keyed by Steam2 id.
 */
function checkMultiplePlayers(int $sid, array $steamids): array
{
    $status = rcon('status', $sid);
    if ($status === false || $status === '') {
        return [];
    }

    $players = [];
    foreach (parseRconStatus($status) as $player) {
        foreach ($steamids as $needle) {
            if (\SteamID\SteamID::compare($player['steamid'], $needle)) {
                $steam2 = \SteamID\SteamID::toSteam2($player['steamid']);
                $players[$steam2] = [
                    'name'  => $player['name'],
                    'steam' => $steam2,
                    'ip'    => $player['ip'],
                ];
            }
        }
    }
    return $players;
}

/**
 * Resolve the persona name for `$steamid` via Steam's WebAPI. Returns
 * an empty string on any failure (missing API key, network error,
 * unexpected payload shape).
 */
function GetCommunityName(string $steamid): string
{
    $endpoint = 'http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?'
        . http_build_query([
            'key'      => STEAMAPIKEY,
            'steamids' => \SteamID\SteamID::toSteam64($steamid),
        ]);

    $body = @file_get_contents($endpoint);
    if ($body === false) {
        return '';
    }
    $data = json_decode($body, true);
    return is_array($data) ? (string) ($data['response']['players'][0]['personaname'] ?? '') : '';
}

/**
 * Run a single RCON command against the server identified by `$sid`.
 *
 * `$silent` controls whether the helper writes a `:prefix_log` audit
 * row on every successful invocation. Defaults to `false` — every
 * admin-initiated path (the `admin.rcon.php` console, the
 * `api_servers_send_rcon` JSON dispatcher, the unban / kick code
 * paths in `bans.php` / `comms.php` / `kickit.php` / `blockit.php`,
 * the `page.banlist.php` row-removal flow) keeps the default so the
 * audit log carries one row per admin-driven RCON.
 *
 * Background side effects pass `$silent = true` so they don't spam
 * the audit log. Currently the only such caller is
 * {@see \Sbpp\Servers\RconStatusCache::probe()} — its cache-fill
 * `status` probes fire on every public-servers-list view for an
 * admin with rcon access (used to surface SteamIDs on player rows
 * for the right-click context menu, restored after #1306). Without
 * `$silent`, each viewer would generate `N_servers` audit-log rows
 * per ~30s cache window per page load, drowning out real RCON
 * activity. The auth-failure / generic-Exception log entries below
 * are NOT silenced — those are real operator-visible problems
 * (stale rcon password, network error) that should always surface
 * regardless of which caller produced them.
 */
function rcon(string $cmd, int $sid, bool $silent = false): false|string
{
    $pdo = $GLOBALS['PDO'];
    $pdo->query('SELECT ip, port, rcon FROM `:prefix_servers` WHERE sid = :sid');
    $pdo->bind(':sid', $sid);
    $server = $pdo->single();

    if (!$server || ($server['rcon'] ?? '') === '') {
        return false;
    }

    $sourceQuery = new SourceQuery();
    try {
        $sourceQuery->Connect($server['ip'], (int) $server['port'], 1, SourceQuery::SOURCE);
        $sourceQuery->setRconPassword($server['rcon']);
        $output = $sourceQuery->Rcon($cmd);

        if (!$silent) {
            Log::add(
                LogType::Message,
                'RCON Sent',
                sprintf('RCON Command (%s) was sent to server (%s:%d)', $cmd, $server['ip'], $server['port']),
            );
        }
        return $output;
    } catch (\xPaw\SourceQuery\Exception\AuthenticationException $e) {
        $pdo->query("UPDATE `:prefix_servers` SET rcon = '' WHERE sid = :sid");
        $pdo->bind(':sid', $sid);
        $pdo->execute();

        Log::add(LogType::Error, "Rcon Password Error [ServerID: {$sid}]", $e->getMessage());
        return false;
    } catch (Exception $e) {
        Log::add(LogType::Error, "Rcon Error [ServerID: {$sid}]", $e->getMessage());
        return false;
    } finally {
        $sourceQuery->Disconnect();
    }
}

/**
 * Parse the `rcon status` payload into a list of player records.
 *
 * @return list<array{id: string, name: string, steamid: string, ip: string}>
 */
function parseRconStatus(string $status): array
{
    $regex = '/#\s*(\d+)(?>\s|\d)*"(.*)"\s*(STEAM_[01]:[01]:\d+|\[U:1:\d+\])(?>\s|:|\d)*[a-zA-Z]*\s*\d*\s([0-9.]+)/';
    if (preg_match_all($regex, $status, $matches, PREG_SET_ORDER) === false) {
        return [];
    }

    $players = [];
    foreach ($matches as $match) {
        $players[] = [
            'id'      => $match[1],
            'name'    => $match[2],
            'steamid' => $match[3],
            'ip'      => $match[4],
        ];
    }
    return $players;
}

/**
 * Encode `$text` for HTML output while preserving any `<br>` /
 * `<br/>` tags as literal line breaks (the comment-store format
 * pre-#1113 wraps newlines in `<br/>`s; we want to escape every other
 * fragment but emit the line breaks back as `<br/>` after `nl2br()`).
 */
function encodePreservingBr(string $text): string
{
    $segments = preg_split('/(<br\s*\/?>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($segments === false) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    $rendered = '';
    foreach ($segments as $segment) {
        $rendered .= preg_match('/^<br\s*\/?>$/i', $segment)
            ? "\n"
            : htmlspecialchars($segment, ENT_QUOTES, 'UTF-8');
    }

    return nl2br($rendered);
}
