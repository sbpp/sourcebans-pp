<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Announce;

use Sbpp\Markup\IntroRenderer;
use Throwable;

/**
 * Anonymous, opt-in-by-default daily fetch of project announcements from
 * the docs deploy at `https://sbpp.github.io/announcements.json`.
 *
 * Lifecycle (mirrors {@see \Sbpp\Telemetry\Telemetry::tickIfDue}):
 *
 *   1. Page render reads the cache only — never blocks on the network.
 *      A cold-cache install renders no banner; the next render after
 *      the shutdown hook lands the cache shows it.
 *   2. `register_shutdown_function([self::class, 'tickIfDue'])` is
 *      registered at the tail of `init.php`. The hook fires on every
 *      panel + JSON API request; the 24h TTL gate means at most one
 *      outbound call per install per day regardless of request volume.
 *   3. On FPM, {@see flushResponseToClient} closes the user's TCP
 *      socket BEFORE the cURL call, so the upstream fetch never adds
 *      latency to a panel page.
 *   4. Stale-while-error: if the upstream call fails, the previous
 *      cache file stays served indefinitely until a successful fetch
 *      overwrites it. Mirrors `system.check_version`'s
 *      `_api_system_release_*` helpers (`web/api/handlers/system.php`).
 *
 * Trust + safety:
 *
 *   - Markdown bodies are rendered server-side through
 *     {@see IntroRenderer}, which wraps league/commonmark with
 *     `html_input: 'escape'` + `allow_unsafe_links: false`. Inline
 *     HTML in the source is rendered as escaped text; `javascript:` /
 *     `data:` / `vbscript:` URLs are stripped during rendering. The
 *     template emits `body_html` with `{nofilter}` because that's
 *     the only "this is already safe HTML" exit point the panel
 *     supports — see AGENTS.md "`nofilter` discipline" / "Admin-
 *     authored display text" for the contract. Reaching for any
 *     other Markdown library here would re-open the #1113-class
 *     stored-XSS vector.
 *   - The outbound URL is hardcoded `https://`. Operators with strict
 *     egress controls disable the fetch entirely by defining
 *     `SB_ANNOUNCEMENTS_URL` to the empty string in `config.php`
 *     (the no-op air-gap escape hatch documented in
 *     `docs/src/content/docs/configuring/announcements.mdx`).
 *   - No request parameters, no tracking pixels, no cookies. The
 *     User-Agent is `SourceBans++/<version> (announcements)` so the
 *     panel version is attributable in any reverse-proxy / WAF logs
 *     the operator routes egress through (parity with
 *     `system.check_version`'s `User-Agent: SourceBans++/<ver>` shape).
 *   - Response body is hard-capped at {@see MAX_BODY_BYTES} (256 KiB)
 *     so a misconfigured / malicious upstream can't OOM the worker.
 *     The cap is enforced post-`file_get_contents` (the stream
 *     wrapper's `length` parameter) AND validated again before JSON
 *     decode, so a future reshape that swaps the wire layer doesn't
 *     silently lose the gate.
 *
 * The class is `final` and statics-only by design: the public surface
 * (`latest()` / `tickIfDue()`) is the entire contract; the helper
 * methods exist purely to keep each step (load / parse / fetch / save)
 * independently testable via the {@see _setHttpFetcherForTests}
 * override.
 */
final class AnnouncementFetcher
{
    /** 24h cooldown between upstream calls. */
    public const CACHE_TTL_SECONDS = 86400;

    /**
     * Total upstream call timeout. Mirrors `system.check_version` (5s).
     *
     * The PHP stream wrapper exposes a single `timeout` knob (covering
     * connect + read), so there's no separate `CONNECT_TIMEOUT_SECONDS`
     * to plumb here — that would only matter under a cURL-based reshape
     * (`CURLOPT_CONNECTTIMEOUT` vs `CURLOPT_TIMEOUT`). When that day
     * comes, split the constant and add a paired test for both legs.
     */
    private const TOTAL_TIMEOUT_SECONDS = 5;

    /**
     * Hard cap on the upstream response body (256 KiB).
     *
     * The expected steady-state body is well under 4 KiB (a handful of
     * JSON objects). The cap keeps a misconfigured / hostile upstream
     * from OOMing a worker: the stream-wrapper read is bounded by
     * `length` AND the post-read string is re-asserted before
     * `json_decode` so a future swap of the read layer doesn't bypass
     * the limit.
     */
    private const MAX_BODY_BYTES = 262144;

    /** Maximum length of an announcement's `id` field (chars, not bytes). */
    private const MAX_ID_LENGTH = 64;

    /** Cache file under `SB_CACHE`. Mirrors `github_release_latest.json`'s shape. */
    private const CACHE_FILENAME = 'announcements.json';

    /**
     * Optional override for {@see fetchUpstream}. Tests install a
     * closure that returns a canned JSON body string (or `null` to
     * simulate an upstream failure). Production never sets this —
     * the `?callable` shape mirrors {@see \Sbpp\Servers\SourceQueryCache::$probeOverride}.
     *
     * @var (callable(string $url): ?string)|null
     */
    private static $httpFetcher = null;

    /**
     * Optional override for the upstream URL. Constants are
     * write-once at runtime, so a test that wants to drive the
     * `resolveUpstreamUrl()` branches (empty-air-gap, non-http
     * scheme, valid URL) without redefining `SB_ANNOUNCEMENTS_URL`
     * sets this string instead. Production never sets it. The
     * `?string` shape mirrors {@see $httpFetcher} above.
     */
    private static ?string $upstreamUrlOverride = null;

    /**
     * Test-only knob to drive the fetcher with a deterministic
     * response. Pass `null` to clear. The override is invoked with
     * the upstream URL and must return either the raw body string
     * (which is then size-capped + parsed identically to a live
     * fetch) or `null` (treated as "upstream failed, fall through to
     * cache").
     *
     * @param (callable(string $url): ?string)|null $fetcher
     */
    public static function _setHttpFetcherForTests(?callable $fetcher): void
    {
        self::$httpFetcher = $fetcher;
    }

    /**
     * Test-only knob to drive `resolveUpstreamUrl()` with a
     * deterministic value. Pass `null` to clear (which falls back
     * to the `SB_ANNOUNCEMENTS_URL` constant). The empty string
     * exercises the air-gap branch; a `file://` / `php://` / etc.
     * value exercises the scheme guard. Production never sets it.
     */
    public static function _setUpstreamUrlForTests(?string $url): void
    {
        self::$upstreamUrlOverride = $url;
    }

    /**
     * Read the on-disk cache and surface the freshest non-expired
     * announcement as a typed DTO. Returns `null` when:
     *
     *   - The cache file is missing or unreadable (cold install).
     *   - The cache file decodes but contains zero entries.
     *   - Every entry has an `expires_at` in the past.
     *
     * The dashboard handler treats `null` as "render nothing"; cold
     * caches show no banner until the next request's shutdown hook
     * populates the cache. This is the documented "first render
     * after install renders no banner" behaviour from the plan.
     *
     * Side-effect-free: this never touches the network, never writes
     * to disk, never logs. Safe to call on every page paint.
     */
    public static function latest(): ?Announcement
    {
        try {
            $entries = self::loadCachedEntries();
        } catch (Throwable) {
            // Defence-in-depth: any disk / decode failure here must
            // never break a page render. tickIfDue's outer try/catch
            // covers shutdown, but `latest()` runs on the synchronous
            // page-render path so it carries its own boundary.
            return null;
        }
        if ($entries === []) {
            return null;
        }
        return self::buildAnnouncement($entries[0]);
    }

    /**
     * Public entry point for the shutdown hook. Mirrors
     * {@see \Sbpp\Telemetry\Telemetry::tickIfDue}'s contract:
     *
     *   - Wraps the body in `try { run(); } catch (\Throwable) {}` so
     *     a misbehaving fetch / disk write / parser NEVER hard-fails
     *     a panel page or a JSON API call.
     *   - Early-returns when the air-gap escape hatch is set
     *     (`SB_ANNOUNCEMENTS_URL = ''`) or the TTL hasn't lapsed.
     *   - Calls `fastcgi_finish_request()` (or its non-FPM fallback)
     *     before the upstream HTTP call so the user's TCP socket
     *     closes first.
     *
     * `tickIfDue` runs every request; the cooldown gate is what makes
     * "every install does at most one fetch per day" a real property,
     * not a wish.
     */
    public static function tickIfDue(): void
    {
        try {
            self::run();
        } catch (Throwable) {
            // The body is already defensive (each step wraps its own
            // I/O); this is the never-fail-the-request guarantee.
        }
    }

    /**
     * Body of the shutdown hook. Kept private so the public entry
     * point owns the throw-swallow boundary.
     *
     * Order matters:
     *
     *   1. Resolve the upstream URL. Empty string = air-gap escape
     *      hatch; bail without flushing or fetching.
     *   2. Compare the cache mtime against the TTL. Fresh = no work.
     *   3. Flush the response BEFORE we fetch (so the network call
     *      runs after the user's socket closes on FPM).
     *   4. Fetch, validate, persist. Failures preserve the existing
     *      cache (stale-while-error).
     */
    private static function run(): void
    {
        $url = self::resolveUpstreamUrl();
        if ($url === '') {
            return;
        }
        if (!self::cacheIsStale()) {
            return;
        }

        self::flushResponseToClient();

        $body = self::fetchUpstream($url);
        if ($body === null) {
            // Stale-while-error: the existing cache stays served
            // unchanged; the NEXT shutdown hook (24h later, or
            // whenever the operator hits the panel after the upstream
            // recovers) will refresh it. Don't touch the cache mtime
            // either — leaving the file untouched lets the TTL gate
            // re-fire on the next request, which is the behaviour we
            // want when the upstream is flapping.
            return;
        }
        self::saveCache($body);
    }

    /**
     * Resolve the configured upstream URL. The
     * `SB_ANNOUNCEMENTS_URL` constant is set in `init.php` (with a
     * `defined()` guard so `config.php` can override) and the empty
     * string is the documented air-gap escape hatch.
     *
     * Defence-in-depth scheme guard: even though the operator owns
     * `config.php` and could in principle put anything in there,
     * `file_get_contents` happily honours every PHP stream wrapper
     * (`file://`, `php://`, `phar://`, `data://`, `ftp://`, …). A
     * misconfiguration (or a typo, or compromised config.php) that
     * pointed at `file:///etc/passwd` would land that file's contents
     * inside `SB_CACHE/announcements.json` — useless to the dashboard
     * (the parser would reject the non-JSON body), but still a
     * surprising side effect to land on disk. Restricting to http(s)
     * shrinks the operator-misconfiguration blast radius to "the
     * panel hits some HTTP endpoint" instead of "the panel reads
     * arbitrary files via stream wrappers". Same shape
     * `_api_system_release_fetch_upstream` relies on (the upstream
     * URL is hardcoded https there; here we lean on the same
     * assumption but with the gate explicit because the constant
     * IS operator-overridable).
     */
    private static function resolveUpstreamUrl(): string
    {
        // Test-only override path — see `_setUpstreamUrlForTests`.
        // Production callers never reach this branch (the override
        // is null by default). When set, the override is the only
        // input considered (so a test can exercise the scheme-guard
        // branch with `file://` etc. without redefining the
        // write-once `SB_ANNOUNCEMENTS_URL` constant).
        if (self::$upstreamUrlOverride !== null) {
            $raw = self::$upstreamUrlOverride;
            if ($raw === '' || preg_match('~^https?://~i', $raw) !== 1) {
                return '';
            }
            return $raw;
        }
        if (!defined('SB_ANNOUNCEMENTS_URL')) {
            return '';
        }
        // Read through `mixed` so PHPStan doesn't narrow to the
        // init.php compile-time literal — operators MAY redefine the
        // constant in `config.php` to either the empty string (the
        // air-gap escape hatch) or another http(s) URL (a self-hosted
        // mirror). Both branches need to be reachable from the
        // analyser's perspective.
        /** @var mixed $raw */
        $raw = constant('SB_ANNOUNCEMENTS_URL');
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $raw) !== 1) {
            return '';
        }
        return $raw;
    }

    /**
     * The cache TTL gate. Returns `true` when the cache file is
     * missing OR older than {@see CACHE_TTL_SECONDS}. Touches no
     * other state.
     */
    private static function cacheIsStale(): bool
    {
        $file = self::cachePath();
        if (!is_file($file)) {
            return true;
        }
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return true;
        }
        return (time() - $mtime) >= self::CACHE_TTL_SECONDS;
    }

    /**
     * Pull the upstream JSON body. Returns the response text on
     * success, `null` on any failure (timeout, non-2xx, malformed
     * JSON shape, oversized body — every branch maps to "fall back
     * to the cache").
     *
     * The upstream is anonymous-readable static JSON, so cURL would
     * be overkill. We use the same `file_get_contents` + stream
     * context shape `system.check_version`'s
     * `_api_system_release_fetch_upstream` does — fewer moving
     * parts, identical timeout semantics.
     *
     * The size cap is enforced by the stream wrapper (`maxlen`
     * argument) AND re-asserted on the returned string. Without
     * both, a future refactor that swaps the read layer (e.g. cURL)
     * could silently lose the cap.
     */
    private static function fetchUpstream(string $url): ?string
    {
        if (self::$httpFetcher !== null) {
            $body = (self::$httpFetcher)($url);
            return self::validateBody($body);
        }

        $version = defined('SB_VERSION') ? (string) constant('SB_VERSION') : 'unknown';
        $headers = 'User-Agent: SourceBans++/' . $version . " (announcements)\r\n"
                 . "Accept: application/json\r\n";
        $context = stream_context_create([
            'https' => [
                'method'        => 'GET',
                'header'        => $headers,
                'timeout'       => self::TOTAL_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
            'http' => [
                'method'        => 'GET',
                'header'        => $headers,
                'timeout'       => self::TOTAL_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);

        // `maxlen` caps the read at MAX_BODY_BYTES so a hostile
        // upstream serving a 50MB body doesn't keep the worker
        // reading. The post-read length check below catches the
        // exact-MAX_BODY_BYTES case (the stream may legitimately
        // hand us up to that many bytes; that's the cap, anything
        // longer would be `false` from this call when the read
        // exceeds maxlen).
        $body = @file_get_contents(
            $url,
            false,
            $context,
            0,
            self::MAX_BODY_BYTES + 1,
        );
        if ($body === false || $body === '') {
            return null;
        }

        // PHP 8.5: `http_get_last_response_headers()` replaces the
        // magic local `$http_response_header` array. Mirrors
        // `_api_system_release_fetch_upstream`. With
        // `ignore_errors=true` the body comes back even on a non-2xx
        // status, so check the status line and reject anything
        // outside 2xx.
        $responseHeaders = http_get_last_response_headers();
        if (is_array($responseHeaders) && isset($responseHeaders[0])
            && preg_match('~HTTP/\S+\s+(\d+)~', $responseHeaders[0], $m) === 1
        ) {
            $status = (int) $m[1];
            if ($status < 200 || $status >= 300) {
                return null;
            }
        }

        return self::validateBody($body);
    }

    /**
     * Reject a body that exceeds {@see MAX_BODY_BYTES} or is
     * non-string. Returns the body on success, `null` on rejection.
     * Centralised so the test-override path goes through the same
     * cap as the live fetch.
     */
    private static function validateBody(mixed $body): ?string
    {
        if (!is_string($body) || $body === '') {
            return null;
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return null;
        }
        return $body;
    }

    /**
     * Atomic write: dump the body into a sibling tempfile, then
     * `rename()` into place. Without that, two concurrent panel hits
     * on a stale cache could both write to the live file and
     * interleave their content, leaving a malformed JSON that
     * subsequent reads would reject — every reader would then try
     * to re-fetch and re-cache, potentially racing again. `rename()`
     * is atomic on POSIX (the panel runs only on Linux per
     * `docker-compose.yml`).
     *
     * We persist the raw upstream body verbatim so the cache file is
     * a byte-for-byte copy of what the upstream served — easier to
     * inspect when triaging a "wrong content rendered" report than a
     * re-encoded subset would be. Parsing happens at read time in
     * {@see loadCachedEntries}.
     */
    private static function saveCache(string $body): void
    {
        $file = self::cachePath();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            // SB_CACHE is asserted writable by init.php at boot, so
            // mkdir failure here would only fire on a race with
            // another worker creating the same dir; the is_dir()
            // re-check resolves it.
            @mkdir($dir, 0o775, true);
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $body) === strlen($body)) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    /**
     * Read + parse the cache file into a `list<array{...}>` of valid,
     * non-expired entries sorted newest-first by `published_at`
     * (entries without `published_at` sort last).
     *
     * Validation drops anything that fails the shape check — missing
     * / empty / overlong `id`, missing / empty `title`, body that
     * isn't a string, URL that isn't an http/https string, etc.
     * Drops entries whose `expires_at` is in the past. The page
     * handler treats an empty list as "no banner".
     *
     * @return list<array{
     *     id: string,
     *     title: string,
     *     body_md: string,
     *     url: string,
     *     published_at: ?int,
     * }>
     */
    private static function loadCachedEntries(): array
    {
        $file = self::cachePath();
        if (!is_file($file)) {
            return [];
        }
        // Cap the read at MAX_BODY_BYTES + 1 so a hand-edited / hostile
        // cache file (e.g. an attacker who landed a 1 GiB JSON blob via
        // a misconfigured SB_ANNOUNCEMENTS_URL pointing at file://, or a
        // genuinely confused operator) can NEVER OOM the worker before
        // the post-read size check below fires. The post-read check
        // catches the exact-cap-and-one-over case (anything strictly
        // larger than MAX_BODY_BYTES means the upstream / source served
        // > 256 KiB; reject) — symmetric with `fetchUpstream`'s read
        // cap so the parse boundary is identical regardless of which
        // way the bytes got onto disk.
        $raw = @file_get_contents($file, false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false || $raw === '') {
            return [];
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            // Defence-in-depth: a hand-edited cache or a future
            // reshape that bypassed saveCache's tempfile path could
            // grow past the cap. Reject silently — the next tick
            // overwrites with a fresh upstream body.
            return [];
        }
        return self::parseEntries($raw);
    }

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     body_md: string,
     *     url: string,
     *     published_at: ?int,
     * }>
     */
    private static function parseEntries(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $now      = time();
        $valid    = [];
        $seenIds  = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = $entry['id'] ?? null;
            if (!is_string($id) || $id === '' || strlen($id) > self::MAX_ID_LENGTH) {
                continue;
            }
            // De-duplicate on `id` so a sloppy upstream edit doesn't
            // double-publish the same entry; the first occurrence
            // wins (newest-first sort happens after this loop).
            if (isset($seenIds[$id])) {
                continue;
            }

            $title = $entry['title'] ?? null;
            if (!is_string($title) || $title === '') {
                continue;
            }

            $bodyMd = $entry['body_md'] ?? '';
            if (!is_string($bodyMd)) {
                continue;
            }

            $url = $entry['url'] ?? '';
            if (!is_string($url)) {
                continue;
            }
            if ($url !== '' && !preg_match('~^https?://~i', $url)) {
                // Defence-in-depth: the IntroRenderer on the body
                // already strips javascript:/data:/vbscript:, but the
                // top-level `url` field is rendered as a literal
                // `<a href="…">` so we gate it here. Empty string is
                // the "no link" sentinel; everything else must be
                // http(s).
                continue;
            }

            $publishedAt = self::parseTimestamp($entry['published_at'] ?? null);
            $expiresAt   = self::parseTimestamp($entry['expires_at'] ?? null);
            if ($expiresAt !== null && $expiresAt < $now) {
                continue;
            }

            $seenIds[$id] = true;
            $valid[] = [
                'id'           => $id,
                'title'        => $title,
                'body_md'      => $bodyMd,
                'url'          => $url,
                'published_at' => $publishedAt,
            ];
        }

        // Sort newest-first by `published_at`. Entries without a
        // timestamp sort last (treat missing as "older than every
        // dated entry") so a maintainer who forgets the field on a
        // legacy entry doesn't accidentally pin it to the top.
        usort($valid, static function (array $a, array $b): int {
            $aAt = $a['published_at'];
            $bAt = $b['published_at'];
            if ($aAt === null && $bAt === null) {
                return 0;
            }
            if ($aAt === null) {
                return 1;
            }
            if ($bAt === null) {
                return -1;
            }
            return $bAt <=> $aAt;
        });

        return $valid;
    }

    /**
     * Coerce a JSON timestamp into a unix int, or `null` when the
     * value is missing / unparseable. Accepts ISO-8601 strings
     * (`2026-05-15T00:00:00Z`) AND raw integers (already-unix
     * timestamps) so future feeds that prefer either shape stay
     * compatible with the panel without a re-spec.
     */
    private static function parseTimestamp(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts === false ? null : $ts;
    }

    /**
     * Build the public-shape DTO from a validated cache entry. The
     * Markdown body is rendered through {@see IntroRenderer} HERE
     * (not at parse time) so the cached file stays a verbatim copy
     * of the upstream body — easier to inspect, and the rendered
     * HTML never lands on disk.
     *
     * @param array{
     *     id: string,
     *     title: string,
     *     body_md: string,
     *     url: string,
     *     published_at: ?int,
     * } $entry
     */
    private static function buildAnnouncement(array $entry): Announcement
    {
        $bodyMd   = $entry['body_md'];
        $bodyHtml = $bodyMd === '' ? '' : IntroRenderer::renderIntroText($bodyMd);

        return new Announcement(
            id:              $entry['id'],
            title:           $entry['title'],
            body_html:       $bodyHtml,
            url:             $entry['url'],
            published_at:    $entry['published_at'],
            published_human: self::formatPublishedHuman($entry['published_at']),
        );
    }

    /**
     * Render `published_at` as `YYYY-MM-DD` (UTC). Compact, locale-
     * neutral, and readable enough for a one-line "published on …"
     * date stamp on the dashboard strip. Returns `null` when the
     * input is `null` so the template can branch on the missing-date
     * case.
     */
    private static function formatPublishedHuman(?int $ts): ?string
    {
        if ($ts === null) {
            return null;
        }
        return gmdate('Y-m-d', $ts);
    }

    /**
     * Flush the response to the user before we touch the network.
     *
     * On FPM, `fastcgi_finish_request()` closes the user's TCP
     * socket and runs the rest of the shutdown chain in the
     * background — so the upstream call NEVER adds latency to a
     * panel page. Apache mod_php falls back to `ob_end_flush + flush`,
     * which is the best we can do without FPM. CLI / phpdbg short-
     * circuit because there's no client socket to flush — closing
     * PHPUnit's output buffer would break the test reporter.
     *
     * Mirrors {@see \Sbpp\Telemetry\Telemetry::flushResponseToClient}
     * byte-for-byte.
     */
    private static function flushResponseToClient(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            return;
        }
        $sapi = PHP_SAPI;
        if ($sapi === 'cli' || $sapi === 'phpdbg') {
            return;
        }
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    private static function cachePath(): string
    {
        return SB_CACHE . self::CACHE_FILENAME;
    }
}
