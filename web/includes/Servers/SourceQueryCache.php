<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
 *************************************************************************/

declare(strict_types=1);

namespace Sbpp\Servers;

use Throwable;
use xPaw\SourceQuery\SourceQuery;

/**
 * Tiny on-disk cache around the xPaw\SourceQuery UDP probe.
 *
 * Issue #1311 — every panel request that hit the public servers list
 * fanned one A2S `GetInfo + GetPlayers` UDP query out to each configured
 * `:prefix_servers` row. The Re-query button per tile (and bare page
 * reloads, both anonymous-callable through `servers.host_players` /
 * `servers.host_property` / `servers.host_players_list` /
 * `servers.players`) made the panel a 1:1 amplifier — a hand-mash of the
 * refresh button or `for i in $(seq 1 100); do curl ...; done` translated
 * directly to A2S spam at the configured game servers. The dispatcher's
 * only friction is CSRF, and anonymous browsers have one for free off
 * the public chrome.
 *
 * The cache key is the (ip, port) pair, NOT the request shape — multiple
 * `:prefix_servers` rows could legitimately point at the same game
 * server (or different sids in the same family), and they should share a
 * cache entry. The handlers stamp their per-caller fields (`is_owner`,
 * `can_ban`, the per-call `trunchostname`) on top of the cached data
 * after the fetch, so the cache stays user-agnostic and a logged-out
 * caller's response never leaks a logged-in caller's permission bits.
 *
 * Both the success and the failure paths cache. A flapping / unreachable
 * server costs ONE socket attempt per cache window, not one per request:
 * without negative caching, an attacker hammering the refresh button on
 * an offline server would still get the full A2S amplification (each
 * miss attempts the connect with a 1s timeout, then returns the
 * "connect" error — same UDP traffic shape as a successful query).
 *
 * Storage is on-disk under `SB_CACHE/srvquery/<sha1(ip:port)>.json`,
 * mirroring the `system.check_version` cache pattern (see
 * `web/api/handlers/system.php`'s `_api_system_release_*` helpers).
 * APCu would have been the obvious "shared between FPM workers" choice
 * but isn't in the platform requirements (`web/composer.json` lists
 * only `ext-pdo` + `ext-openssl`), and the on-disk variant is what the
 * panel already uses for cross-request caches. Atomic writes via
 * tempfile + `rename()` so two concurrent FPM workers never read a
 * half-written entry.
 *
 * The cache is intentionally NOT advisory-locked across processes:
 * during the brief "two cold workers race for the same entry" window
 * each will issue its own UDP probe; once one of them lands the entry,
 * subsequent callers (within TTL) hit the cached payload. That's
 * orders of magnitude better than the un-cached state and avoids the
 * lock-acquisition latency on every read.
 */
final class SourceQueryCache
{
    /** Default cache window in seconds. Mirrors the issue's "~30s" suggestion. */
    public const DEFAULT_TTL_SECONDS = 30;

    /**
     * Counter incremented every time `fetch()` opens a real UDP socket.
     * Tests assert this stays flat across repeated `fetch()` calls within
     * a single window (cache-hit path); production code never reads it.
     */
    private static int $socketAttempts = 0;

    /**
     * Override hook for unit tests so the cache can be exercised against
     * an in-memory probe instead of the live UDP path.
     *
     * @var (callable(string $ip, int $port): array{info: array<string, mixed>, players: list<array<string, mixed>>}|null)|null
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
     * Tests-only: install a probe override so `fetch()` returns the
     * supplied data without touching the network. Pass `null` to clear.
     *
     * The override is invoked with `(string $ip, int $port)` and must
     * return either the success-shape array or `null` (treated as the
     * "connect failed" branch).
     *
     * @param (callable(string $ip, int $port): array{info: array<string, mixed>, players: list<array<string, mixed>>}|null)|null $probe
     */
    public static function setProbeOverrideForTesting(?callable $probe): void
    {
        self::$probeOverride = $probe;
    }

    /**
     * Returns the cached A2S `{info, players}` pair for the given
     * `(ip, port)`, refreshing it (and updating the cache) on miss /
     * staleness. `null` is returned when the live probe could not reach
     * the server — the caller turns that into the structured "connect"
     * envelope. `null` is itself cached for the same window so a panel
     * pointed at an offline server doesn't keep hammering its IP.
     *
     * The TTL is configurable so tests can drive the stale-entry path
     * without `sleep()`; production code uses the default.
     *
     * @return array{info: array<string, mixed>, players: list<array<string, mixed>>}|null
     */
    public static function fetch(string $ip, int $port, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): ?array
    {
        $cacheFile = self::cacheFile($ip, $port);
        $cached    = self::readCache($cacheFile);

        if ($cached !== null && (time() - $cached['cached_at']) < $ttlSeconds) {
            // Negative-cache entries serialize as `error: true` so the
            // shape distinguishes "no row" from "row with no data".
            // We surface the cached miss the same way a fresh miss would.
            if (($cached['error'] ?? false) === true) {
                return null;
            }
            return ['info' => $cached['info'] ?? [], 'players' => $cached['players'] ?? []];
        }

        $fresh = self::probe($ip, $port);

        // Atomic write: dump JSON into a sibling tempfile, then rename
        // into place. Without this two cold workers racing the same
        // (ip, port) could leave a half-written file that subsequent
        // reads would silently reject as malformed; mirrors the
        // `_api_system_release_save_cache` shape in `system.php`.
        self::writeCache($cacheFile, $fresh);

        return $fresh;
    }

    /**
     * Drop the cached entry for `(ip, port)`. Safe to call when no entry
     * exists. Useful for an admin-triggered "force re-query" surface
     * (none today; the contract is "rate-limit, don't bypass") and for
     * tests that need a clean slate.
     */
    public static function invalidate(string $ip, int $port): void
    {
        $cacheFile = self::cacheFile($ip, $port);
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * @return array{info: array<string, mixed>, players: list<array<string, mixed>>}|null
     */
    private static function probe(string $ip, int $port): ?array
    {
        self::$socketAttempts++;

        if (self::$probeOverride !== null) {
            return (self::$probeOverride)($ip, $port);
        }

        $query = new SourceQuery();
        try {
            $query->Connect($ip, $port, 1, SourceQuery::SOURCE);
            $info    = $query->GetInfo();
            $players = $query->GetPlayers();
        } catch (Throwable) {
            return null;
        } finally {
            $query->Disconnect();
        }

        return [
            'info'    => $info,
            'players' => array_values($players),
        ];
    }

    private static function cacheFile(string $ip, int $port): string
    {
        $dir = SB_CACHE . 'srvquery/';
        if (!is_dir($dir)) {
            // Best-effort: the writability check on SB_CACHE in init.php
            // already gated panel boot, so the parent dir is guaranteed
            // writable. The mkdir failure path here would only fire on a
            // race with another worker creating the same dir — handled
            // by the `is_dir()` re-check.
            @mkdir($dir, 0o775, true);
        }
        return $dir . sha1($ip . ':' . $port) . '.json';
    }

    /**
     * @return array{cached_at: int, info?: array<string, mixed>, players?: list<array<string, mixed>>, error?: bool}|null
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
     * @param array{info: array<string, mixed>, players: list<array<string, mixed>>}|null $payload
     */
    private static function writeCache(string $file, ?array $payload): void
    {
        $entry = $payload === null
            ? ['cached_at' => time(), 'error' => true]
            : ['cached_at' => time(), 'info' => $payload['info'], 'players' => $payload['players']];

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
