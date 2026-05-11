<?php
declare(strict_types=1);

namespace Sbpp\Telemetry;

use Sbpp\Config;
use Sbpp\Db\Database;
use Throwable;

/**
 * Anonymous opt-out daily telemetry ping (#1126).
 *
 * Lifecycle: `register_shutdown_function([Telemetry::class,
 * 'tickIfDue'])` is set up in `init.php` after the settings cache
 * is warm, so every panel + JSON API request runs the tick at
 * shutdown. The actual network call only fires when the 24h
 * (± 1h jitter) cooldown has lapsed and an atomic `last_ping`
 * reservation succeeded — which means the call site sees one
 * ping/day per install at the absolute most regardless of request
 * volume.
 *
 * What gets sent is locked in
 * `web/includes/Telemetry/schema-1.lock.json` (the JSON Schema
 * vendored from the cf-analytics companion repo). The extractor
 * parity test gates the contract:
 *
 *   - `TelemetrySchemaParityTest` — every leaf field in the lock
 *     file has a corresponding extractor in `collect()`, and vice
 *     versa.
 *
 * The lock file is also the single source of truth for anyone who
 * wants the field-by-field breakdown — no human-readable mirror is
 * kept anywhere in the repo.
 *
 * What's NOT sent: hostnames, IPs, install paths, admin names,
 * ban reasons, dashboard text, SteamIDs, server hostnames, server
 * IPs, MOTDs, SMTP creds, the Steam API key value. The
 * `TelemetryCollectTest::testCollectedPayloadContainsNoSeededPii`
 * regression guard asserts none of those leak.
 *
 * Design constraints from the issue:
 *
 *   - Must not delay any user request → `fastcgi_finish_request()`
 *     is invoked before the cURL POST when available, so the
 *     response is fully flushed to the user before we touch the
 *     network.
 *   - Must not error out the request → the public entry point
 *     wraps every line in `try { … } catch (\Throwable) { (swallow) }`.
 *     Telemetry can NEVER hard-fail a panel page or a JSON API call.
 *   - No `Log::add` on failure → a flapping endpoint would
 *     otherwise generate audit-log noise that scares admins.
 *   - `last_ping` is reserved at the START of the attempt, not
 *     after success, so a persistent endpoint outage costs one
 *     ping/day, not one ping/request.
 */
final class Telemetry
{
    /** Wire-format version. Mirrors the `schema` field in the lock file. */
    public const SCHEMA_VERSION = 1;

    /** 24h cooldown between pings. */
    private const PING_INTERVAL_SECONDS = 86400;

    /** Symmetric jitter applied to the cooldown so panels behind the same NAT don't synchronize. */
    private const PING_JITTER_SECONDS = 3600;

    /** cURL connect timeout. */
    private const CONNECT_TIMEOUT_SECONDS = 3;

    /** cURL total-operation timeout. */
    private const TOTAL_TIMEOUT_SECONDS = 5;

    /**
     * Process-local cache of the instance ID. Settings are loaded
     * once at request start by `Config::init()` and the cache is
     * not invalidatable from outside, so `collect()` keeps its own
     * mirror to prevent a re-mint on the second call inside the
     * same request (tests that call `collect()` twice, or a future
     * caller that does `tickIfDue + manual collect`).
     */
    private static ?string $instanceIdMemo = null;

    /**
     * Public entry point invoked by `register_shutdown_function`.
     *
     * Swallows every exception. The `tickIfDue` body itself is
     * defensive (early-return on opt-out, atomic reservation,
     * silent cURL), but the outer `try/catch` is the
     * never-fail-the-request guarantee.
     */
    public static function tickIfDue(): void
    {
        try {
            self::run();
        } catch (Throwable) {
            // Telemetry NEVER hard-fails a request. Silent on purpose.
        }
    }

    /**
     * Build the wire payload from the live DB + runtime
     * environment.
     *
     * Side-effect-free except for the one-shot `instance_id`
     * generation (lazy: missing → `random_bytes(16)` →
     * `:prefix_settings.telemetry.instance_id` → settings cache
     * mutated for the rest of the request).
     *
     * @return array<string, mixed>
     */
    public static function collect(): array
    {
        return [
            'schema'      => self::SCHEMA_VERSION,
            'instance_id' => self::resolveInstanceId(),
            'panel'       => self::collectPanel(),
            'env'         => self::collectEnv(),
            'scale'       => self::collectScale(),
            'features'    => self::collectFeatures(),
        ];
    }

    /**
     * POST the payload via cURL with tight timeouts and silent
     * failure. Any non-2xx, any timeout, any DNS error: dropped.
     */
    public static function send(array $payload, string $endpoint, string $userAgent): void
    {
        if ($endpoint === '') {
            return;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }
        if (!function_exists('curl_init')) {
            return;
        }
        $ch = curl_init();
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ' . $userAgent,
            ],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL       => true,
        ]);
        @curl_exec($ch);
        // PHP 8.0+ removed the need to call curl_close(); the handle is
        // garbage-collected when the local goes out of scope. Calling it
        // explicitly emits a deprecation warning under
        // phpstan-deprecation-rules. Letting the local fall out of scope
        // is the supported lifecycle.
        unset($ch);
    }

    /**
     * The actual tick body: opt-out gate → cooldown check →
     * atomic reservation → flush response → POST.
     *
     * Kept private so the public entry point owns the throw-swallow
     * boundary.
     */
    private static function run(): void
    {
        if (!Config::getBool('telemetry.enabled')) {
            return;
        }

        $now       = time();
        $jitter    = self::randomJitter();
        $threshold = $now - self::PING_INTERVAL_SECONDS - $jitter;

        $lastPing = (int) (Config::get('telemetry.last_ping') ?? 0);
        if ($lastPing > $threshold) {
            return;
        }

        if (!self::reserveSlot($threshold, $now)) {
            return;
        }

        self::flushResponseToClient();

        $endpoint = (string) (Config::get('telemetry.endpoint') ?? '');
        if ($endpoint === '') {
            return;
        }

        $payload = self::collect();
        $version = defined('SB_VERSION') ? (string) constant('SB_VERSION') : 'unknown';
        $userAgent = 'SourceBans++/' . $version . ' (telemetry)';
        self::send($payload, $endpoint, $userAgent);
    }

    /**
     * Atomically claim the next ping slot.
     *
     * `UPDATE … SET value = :now WHERE setting = 'telemetry.last_ping'
     * AND CAST(value AS UNSIGNED) <= :threshold` either matches one
     * row (we win the race) or zero (someone else already claimed
     * this 24h window). `rowCount() === 1` is the gate.
     */
    private static function reserveSlot(int $threshold, int $now): bool
    {
        $db = self::db();
        if ($db === null) {
            return false;
        }
        $db->query(
            "UPDATE `:prefix_settings` SET `value` = :now "
            . "WHERE `setting` = 'telemetry.last_ping' "
            . "AND CAST(`value` AS UNSIGNED) <= :threshold"
        );
        $db->bind(':now', (string) $now);
        $db->bind(':threshold', (string) $threshold);
        $db->execute();
        return $db->rowCount() === 1;
    }

    /**
     * Flush the response to the user before we touch the network.
     *
     * On FPM, `fastcgi_finish_request()` closes the user's TCP
     * socket and runs the rest of the shutdown chain in the
     * background. Apache mod_php falls back to ob_end_flush + flush,
     * which is the best we can do without FPM. CLI / phpdbg short-
     * circuit because there's no client socket to flush — closing
     * PHPUnit's output buffer would break the test reporter.
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
        // Best-effort flush for non-FPM SAPIs (Apache mod_php + IIS).
        // Won't actually close the TCP socket, but at least pushes
        // the buffered bytes before the cURL call.
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    private static function randomJitter(): int
    {
        try {
            return random_int(-self::PING_JITTER_SECONDS, self::PING_JITTER_SECONDS);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Resolve `panel.theme`. Reports the active theme name only when
     * it lives at `web/themes/<name>/` AND that name is in the small
     * shipped allowlist (currently `default` plus anything we add
     * here). Custom forks report `'custom'` so the actual fork name
     * (e.g. `gridiron-clan-2025`) never leaves the panel.
     */
    private static function resolveTheme(): string
    {
        $active = defined('SB_THEME') ? (string) constant('SB_THEME') : '';
        if ($active === '') {
            return 'custom';
        }
        if (!defined('SB_THEMES')) {
            return 'custom';
        }
        $shipped = self::shippedThemes();
        return in_array($active, $shipped, true) ? $active : 'custom';
    }

    /**
     * Enumerate the themes shipped in this checkout — every
     * directory under `web/themes/` at request time. The ENUM in
     * the lock file is `["default", "custom"]` today; if a future
     * release ships `default-classic` next to `default`, this
     * function reports it (and the schema's enum widens in the
     * cf-analytics repo at the same time).
     *
     * @return list<string>
     */
    private static function shippedThemes(): array
    {
        $dir = (string) constant('SB_THEMES');
        if (!is_dir($dir)) {
            return ['default'];
        }
        $themes = [];
        $handle = @opendir($dir);
        if ($handle === false) {
            return ['default'];
        }
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($dir . $entry) && is_file($dir . $entry . '/theme.conf.php')) {
                $themes[] = $entry;
            }
        }
        closedir($handle);
        return $themes !== [] ? $themes : ['default'];
    }

    /**
     * Lazily generate + persist the `instance_id`.
     *
     * Reads the cached settings value first; if missing /
     * empty / wrong-shape, mints a fresh 32-char lowercase hex
     * and writes it back to `:prefix_settings`. The freshly-minted
     * value is also memo'd for the rest of the request so a
     * second `collect()` call (tests, a future caller, etc.)
     * doesn't re-mint a different ID — Config's process-local
     * cache is loaded once at bootstrap and isn't invalidatable
     * from outside, so we own the memo here.
     */
    private static function resolveInstanceId(): string
    {
        if (self::$instanceIdMemo !== null) {
            return self::$instanceIdMemo;
        }
        $existing = (string) (Config::get('telemetry.instance_id') ?? '');
        if (preg_match('/^[0-9a-f]{32}$/', $existing) === 1) {
            self::$instanceIdMemo = $existing;
            return $existing;
        }
        try {
            $fresh = bin2hex(random_bytes(16));
        } catch (Throwable) {
            // random_bytes() can only fail when the OS RNG is
            // misconfigured; fall back to mt_rand-derived bytes so
            // the request still completes. The fallback is a
            // shape-correct hex string, which is all the Worker
            // cares about.
            $fresh = '';
            for ($i = 0; $i < 16; $i++) {
                $fresh .= sprintf('%02x', mt_rand(0, 255));
            }
        }
        self::persistInstanceId($fresh);
        self::$instanceIdMemo = $fresh;
        return $fresh;
    }

    private static function persistInstanceId(string $id): void
    {
        $db = self::db();
        if ($db === null) {
            return;
        }
        $db->query(
            "UPDATE `:prefix_settings` SET `value` = :id WHERE `setting` = 'telemetry.instance_id'"
        );
        $db->bind(':id', $id);
        $db->execute();
    }

    /**
     * Test-only hook to drop the process-local instance-id memo so
     * a second test method starts from a clean slate. Production
     * never calls this — Config's settings cache covers a single
     * request, and `register_shutdown_function` only fires once
     * per request.
     */
    public static function resetInstanceIdMemoForTests(): void
    {
        self::$instanceIdMemo = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectPanel(): array
    {
        /** @var string $version */
        $version = defined('SB_VERSION') ? (string) constant('SB_VERSION') : '';
        // `SB_GITREV` carries `int 0` (Version::resolve's no-git fallback)
        // or a string SHA. The schema wants a string, so coerce int 0 to
        // empty string rather than the literal "0" — same is-empty
        // semantics either way, but the wire format stays clean.
        /** @var mixed $rawGit */
        $rawGit  = defined('SB_GITREV') ? constant('SB_GITREV') : '';
        $git     = is_string($rawGit) ? $rawGit : '';
        return [
            'version' => $version,
            'git'     => $git,
            'dev'     => $version === \Sbpp\Version::DEV_SENTINEL,
            'theme'   => self::resolveTheme(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectEnv(): array
    {
        return [
            'php'        => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'db_engine'  => self::resolveDbEngine(),
            'db_version' => self::resolveDbVersion(),
            'web_server' => self::resolveWebServer((string) ($_SERVER['SERVER_SOFTWARE'] ?? '')),
            'os_family'  => self::resolveOsFamily(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function collectScale(): array
    {
        $db = self::db();
        if ($db === null) {
            return [
                'admins'          => 0,
                'servers_enabled' => 0,
                'bans_active'     => 0,
                'bans_total'      => 0,
                'comms_active'    => 0,
                'comms_total'     => 0,
                'submissions_30d' => 0,
                'protests_30d'    => 0,
            ];
        }

        $thirtyDaysAgo = time() - 30 * 86400;

        return [
            'admins'          => self::countSimple($db, "SELECT COUNT(*) AS c FROM `:prefix_admins`"),
            'servers_enabled' => self::countSimple($db, "SELECT COUNT(*) AS c FROM `:prefix_servers` WHERE `enabled` = 1"),
            'bans_active'     => self::countSimple(
                $db,
                "SELECT COUNT(*) AS c FROM `:prefix_bans` "
                . "WHERE (`ends` > UNIX_TIMESTAMP() OR `length` = 0) AND `RemoveType` IS NULL"
            ),
            'bans_total'      => self::countSimple($db, "SELECT COUNT(*) AS c FROM `:prefix_bans`"),
            'comms_active'    => self::countSimple(
                $db,
                "SELECT COUNT(*) AS c FROM `:prefix_comms` "
                . "WHERE (`ends` > UNIX_TIMESTAMP() OR `length` = 0) AND `RemoveType` IS NULL"
            ),
            'comms_total'     => self::countSimple($db, "SELECT COUNT(*) AS c FROM `:prefix_comms`"),
            'submissions_30d' => self::countWithCutoff(
                $db,
                "SELECT COUNT(*) AS c FROM `:prefix_submissions` WHERE `submitted` >= :cutoff",
                $thirtyDaysAgo
            ),
            'protests_30d'    => self::countWithCutoff(
                $db,
                "SELECT COUNT(*) AS c FROM `:prefix_protests` WHERE `datesubmitted` >= :cutoff",
                $thirtyDaysAgo
            ),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private static function collectFeatures(): array
    {
        return [
            'submit'             => Config::getBool('config.enablesubmit'),
            'protest'            => Config::getBool('config.enableprotest'),
            'comms'              => Config::getBool('config.enablecomms'),
            'kickit'             => Config::getBool('config.enablekickit'),
            'exportpublic'       => Config::getBool('config.exportpublic'),
            'publiccomments'     => Config::getBool('config.enablepubliccomments'),
            'steamlogin'         => Config::getBool('config.enablesteamlogin'),
            'normallogin'        => Config::getBool('config.enablenormallogin'),
            'groupbanning'       => Config::getBool('config.enablegroupbanning'),
            'friendsbanning'     => Config::getBool('config.enablefriendsbanning'),
            'adminrehashing'     => Config::getBool('config.enableadminrehashing'),
            'smtp_configured'    => self::resolveSmtpConfigured(),
            'steam_api_key_set'  => self::resolveSteamApiKeySet(),
            'geoip_present'      => self::resolveGeoipPresent(),
        ];
    }

    private static function countSimple(Database $db, string $query): int
    {
        try {
            $db->query($query);
            $row = $db->single();
            return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countWithCutoff(Database $db, string $query, int $cutoff): int
    {
        try {
            $db->query($query);
            $db->bind(':cutoff', $cutoff);
            $row = $db->single();
            return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private static function resolveDbEngine(): string
    {
        $version = self::rawDbVersion();
        return str_contains(strtolower($version), 'mariadb') ? 'mariadb' : 'mysql';
    }

    private static function resolveDbVersion(): string
    {
        $raw = self::rawDbVersion();
        if ($raw === '') {
            return '0.0';
        }
        if (preg_match('/(\d+)\.(\d+)/', $raw, $m) === 1) {
            return $m[1] . '.' . $m[2];
        }
        return '0.0';
    }

    private static function rawDbVersion(): string
    {
        $db = self::db();
        if ($db === null) {
            return '';
        }
        try {
            $db->query("SELECT VERSION() AS v");
            $row = $db->single();
            return is_array($row) ? (string) ($row['v'] ?? '') : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Substring-match `$_SERVER['SERVER_SOFTWARE']` against the
     * shipped-list of supported web servers. Order matters because
     * a few headers ambiguously contain multiple names (e.g.
     * `LiteSpeed/Apache`); the first match wins.
     */
    private static function resolveWebServer(string $raw): string
    {
        $needle = strtolower($raw);
        $candidates = [
            'apache'    => 'apache',
            'nginx'     => 'nginx',
            'litespeed' => 'litespeed',
            'iis'       => 'iis',
            'caddy'     => 'caddy',
        ];
        foreach ($candidates as $token => $label) {
            if (str_contains($needle, $token)) {
                return $label;
            }
        }
        return 'other';
    }

    private static function resolveOsFamily(): string
    {
        $raw = strtolower(PHP_OS_FAMILY);
        return match ($raw) {
            'linux'   => 'linux',
            'windows' => 'windows',
            'darwin'  => 'mac',
            'bsd'     => 'bsd',
            default   => 'other',
        };
    }

    /**
     * The panel's SMTP-active gate. Mirrors what the Settings → Main
     * form drives: a configured `smtp.host` is the load-bearing
     * indicator that the panel will route mail through Symfony
     * Mailer's SMTP transport rather than fall back to the
     * `mailtype = phpmail` shape. Stored value is ALWAYS reported
     * as a boolean — the host string itself never leaves the panel.
     */
    private static function resolveSmtpConfigured(): bool
    {
        $host = (string) (Config::get('smtp.host') ?? '');
        return trim($host) !== '';
    }

    /**
     * True iff `STEAMAPIKEY` is `define()`d to a non-empty value in
     * `config.php`. The key VALUE is never reported. Wrapped in a
     * function so PHPStan can't narrow the constant value against
     * its phpstan-bootstrap.php sentinel ('') — the exact same
     * workaround `adminSettingsHasSteamApiKey()` uses in
     * `web/pages/admin.settings.php`.
     */
    private static function resolveSteamApiKeySet(): bool
    {
        /** @var string $key */
        $key = defined('STEAMAPIKEY') ? (string) constant('STEAMAPIKEY') : '';
        return $key !== '';
    }

    /**
     * True iff the Maxmind GeoIP database lives at
     * `web/data/GeoLite2-Country.mmdb` and is readable. Mirrors
     * what `system_check.php` checks before the country-flag
     * lookup runs.
     */
    private static function resolveGeoipPresent(): bool
    {
        if (!defined('MMDB_PATH')) {
            return false;
        }
        /** @var string $path */
        $path = (string) constant('MMDB_PATH');
        return $path !== '' && @is_file($path) && @is_readable($path);
    }

    /**
     * Resolve the panel's `Database` instance. Returns `null` when
     * the global isn't wired (no init.php yet, or test harness that
     * hasn't booted Fixture::install). Callers branch on null and
     * skip the work — `tickIfDue` swallows the failure either way.
     */
    private static function db(): ?Database
    {
        $db = $GLOBALS['PDO'] ?? null;
        return $db instanceof Database ? $db : null;
    }
}
