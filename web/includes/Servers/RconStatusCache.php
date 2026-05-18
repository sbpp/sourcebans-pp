<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2026 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
 *************************************************************************/

declare(strict_types=1);

namespace Sbpp\Servers;

/**
 * Tiny on-disk cache around the legacy `rcon('status', $sid)` helper
 * defined in `web/includes/system-functions.php`.
 *
 * Restoring the right-click context menu on the public servers page
 * (the kick / ban / block / view profile / copy SteamID surface that
 * shipped pre-v2.0.0 and was killed at #1306) is gated on knowing the
 * SteamIDs of every player in a server card. The xPaw\SourceQuery
 * A2S `GetPlayers` UDP response — the data path
 * {@see SourceQueryCache} fronts — does NOT carry SteamIDs; the only
 * way to surface them is a TCP RCON round-trip per server with the
 * `status` command, then a regex pass through
 * {@see \parseRconStatus()}.
 *
 * That doubles the per-card latency the public list pays, and the
 * `servers.host_players` action stays public — so without aggressive
 * caching it would be a 1:1 amplifier the moment an admin viewer
 * hand-mashed the per-tile Re-query button or wrote a cURL loop
 * against `?p=servers`. The cache key is the `sid` (not `(ip, port)`)
 * because RCON authentication is per-`:prefix_servers` row — two rows
 * pointing at the same game server have their own rcon passwords and
 * could conceptually answer differently if one was stale.
 *
 * Both the success and the failure paths cache. A flapping server,
 * a row with a bad rcon password, and a row that has no rcon password
 * configured at all all share the same code path: ONE socket attempt
 * per cache window, the result (`null`) is negative-cached, and
 * subsequent callers inside the window cheaply return `null` without
 * touching the RCON socket or {@see \Log}.
 *
 * Storage mirrors {@see SourceQueryCache}: on-disk under
 * `SB_CACHE/srvstatus/<sha1(sid)>.json` (sid-keyed, NOT IP-keyed —
 * see above), atomic tempfile + `rename()` writes so two concurrent
 * FPM workers never observe a half-written entry. No advisory lock
 * during the brief "two cold workers race the same entry" window;
 * each issues its own probe and the second one's write wins
 * deterministically once both finish.
 *
 * The cache uses `rcon()` with `$silent = true` so the legacy "RCON
 * Sent" audit-log entry that `rcon()` writes on every successful
 * invocation does NOT fire for our cache-fill probes — they're a
 * panel-background side effect, not an admin-initiated action, and
 * spamming `:prefix_log` with one row per cached status probe per
 * admin-with-rcon viewer would make the audit log unreadable.
 * Legitimate audit-log generators (the `admin.rcon.php` page,
 * `api_servers_send_rcon` from the rcon console, the unban/kick
 * code paths) continue to call `rcon()` without `$silent`. See
 * `web/includes/system-functions.php` `rcon()` for the default.
 */
final class RconStatusCache
{
    /** Default cache window in seconds. Mirrors SourceQueryCache. */
    public const DEFAULT_TTL_SECONDS = 30;

    /**
     * Counter incremented every time {@see fetch} opens a real RCON
     * socket. Tests assert this stays flat across repeated `fetch()`
     * calls within a single window (cache-hit path); production code
     * never reads it.
     */
    private static int $socketAttempts = 0;

    /**
     * Override hook for unit tests so the cache can be exercised
     * without an actual RCON socket.
     *
     * @var (callable(int $sid): list<array{id: int, name: string, steamid: string, ip: string}>|null)|null
     */
    private static $probeOverride = null;

    public static function socketAttemptCount(): int
    {
        return self::$socketAttempts;
    }

    public static function resetSocketAttemptCount(): void
    {
        self::$socketAttempts = 0;
    }

    /**
     * Tests-only: install a probe override so {@see fetch} returns
     * the supplied data without touching the network. Pass `null` to
     * clear.
     *
     * The override is invoked with `(int $sid)` and must return either
     * the parsed player list (success-shape; mirrors
     * {@see \parseRconStatus()}) or `null` (treated as the
     * "no rcon password / RCON failed / unparseable status output"
     * branch).
     *
     * @param (callable(int $sid): list<array{id: int, name: string, steamid: string, ip: string}>|null)|null $probe
     */
    public static function setProbeOverrideForTesting(?callable $probe): void
    {
        self::$probeOverride = $probe;
    }

    /**
     * Returns the cached `status` probe result for the given `sid`,
     * refreshing it (and updating the cache) on miss / staleness.
     * `null` is returned when the live probe could not produce a
     * usable result (no rcon password / RCON connect failure /
     * unparseable status output). `null` is itself cached for the
     * same window so a panel pointed at a flapping rcon endpoint
     * doesn't keep hammering the socket.
     *
     * The TTL is configurable so tests can drive the stale-entry
     * path without `sleep()`; production code uses the default.
     *
     * @return array{players: list<array{id: int, name: string, steamid: string, ip: string}>}|null
     */
    public static function fetch(int $sid, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): ?array
    {
        if ($sid <= 0) {
            return null;
        }

        $cacheFile = self::cacheFile($sid);
        $cached    = self::readCache($cacheFile);

        if ($cached !== null && (time() - $cached['cached_at']) < $ttlSeconds) {
            if (($cached['error'] ?? false) === true) {
                return null;
            }
            return ['players' => $cached['players'] ?? []];
        }

        $players = self::probe($sid);

        self::writeCache($cacheFile, $players);

        if ($players === null) {
            return null;
        }
        return ['players' => $players];
    }

    /**
     * Drop the cached entry for `$sid`. Safe to call when no entry
     * exists. Useful for tests that need a clean slate; production
     * code has no force-refresh surface (the contract is
     * "rate-limit, don't bypass", same as SourceQueryCache).
     */
    public static function invalidate(int $sid): void
    {
        $cacheFile = self::cacheFile($sid);
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * @return list<array{id: int, name: string, steamid: string, ip: string}>|null
     */
    private static function probe(int $sid): ?array
    {
        self::$socketAttempts++;

        if (self::$probeOverride !== null) {
            return (self::$probeOverride)($sid);
        }

        // `rcon($cmd, $sid, $silent = true)` — the `$silent` flag is
        // load-bearing here (see the class-level docblock for the
        // why). The cache-fill probes must NOT write a "RCON Sent"
        // audit-log row per call; only admin-initiated paths
        // (`admin.rcon.php`, `api_servers_send_rcon`, the unban /
        // kick code paths) keep `$silent = false` (the default) and
        // generate the audit entry.
        $output = \rcon('status', $sid, true);
        if ($output === false || $output === '') {
            return null;
        }

        $players = \parseRconStatus($output);
        if (count($players) === 0) {
            // An empty status output can mean "no players online" OR
            // "the regex didn't match anything in the response". Both
            // shapes return an empty list and we can't distinguish
            // them from outside the parser; treat the empty list as
            // a successful result (the typical "empty server" case)
            // so subsequent callers cache-hit instead of re-probing.
            return [];
        }

        /** @var list<array{id: int, name: string, steamid: string, ip: string}> $shaped */
        $shaped = [];
        foreach ($players as $p) {
            $shaped[] = [
                'id'      => (int) $p['id'],
                'name'    => (string) $p['name'],
                'steamid' => (string) $p['steamid'],
                'ip'      => (string) $p['ip'],
            ];
        }
        return $shaped;
    }

    private static function cacheFile(int $sid): string
    {
        $dir = SB_CACHE . 'srvstatus/';
        if (!is_dir($dir)) {
            // Best-effort: the writability check on SB_CACHE in
            // init.php already gated panel boot, so the parent dir
            // is guaranteed writable. The mkdir failure path only
            // fires on a race with another worker creating the same
            // dir — handled by the `is_dir()` re-check.
            @mkdir($dir, 0o775, true);
        }
        return $dir . sha1('sid:' . $sid) . '.json';
    }

    /**
     * @return array{cached_at: int, players?: list<array{id: int, name: string, steamid: string, ip: string}>, error?: bool}|null
     */
    private static function readCache(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['cached_at']) || !is_int($decoded['cached_at'])) {
            return null;
        }
        return $decoded;
    }

    /**
     * @param list<array{id: int, name: string, steamid: string, ip: string}>|null $payload
     */
    private static function writeCache(string $file, ?array $payload): void
    {
        $entry = $payload === null
            ? ['cached_at' => time(), 'error' => true]
            : ['cached_at' => time(), 'players' => $payload];

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json) === strlen($json)) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
