<?php
declare(strict_types=1);

namespace Sbpp\Telemetry;

use Throwable;

/**
 * Shared-hosting fingerprint signals for the telemetry `env` block
 * (sbpp/cf-analytics#3).
 *
 * There's no single reliable "this is a shared host" indicator, so
 * the panel ships the raw inputs (SAPI, ini limits, open_basedir,
 * control-panel dirs, per-user FPM) and the shared / constrained /
 * unconstrained label is derived query-time on the analytics side.
 * Keeping the heuristic in SQL means retuning it never needs a panel
 * release.
 *
 * Privacy posture matches the rest of the payload: anything whose
 * raw value identifies the host or account (the `open_basedir`
 * value, `DOCUMENT_ROOT`, the `disable_functions` list) is reduced
 * to a boolean or a count before it leaves this class.
 *
 * Every probe runs through `probe()` so a host that disables
 * `ini_get` / `posix_*` / `fileowner`, or an `open_basedir` that
 * rejects the `/usr/local/*` checks, degrades that one field to its
 * fallback instead of throwing and losing the whole ping.
 */
final class HostFingerprint
{
    /**
     * UIDs at or above this are treated as regular accounts for
     * `sapi_per_user`. Distro service accounts (root, www-data,
     * apache, FreeBSD www, nginx, …) all sit below it.
     */
    private const MIN_ACCOUNT_UID = 1000;

    /** `nobody` / `nfsnobody` — above the threshold but never an account. */
    private const NOBODY_UID = 65534;

    private function __construct()
    {
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public static function collect(): array
    {
        $sapi = PHP_SAPI;
        return [
            'sapi'                    => $sapi,
            'memory_limit_mb'         => self::probe(
                static fn (): ?int => self::parseMemoryLimitMb(self::ini('memory_limit')),
                null
            ),
            'max_execution_time'      => self::probe(
                static fn (): int => max(0, (int) self::ini('max_execution_time')),
                0
            ),
            'disable_functions_count' => self::probe(
                static fn (): int => self::countDisabledFunctions(self::ini('disable_functions')),
                0
            ),
            'zts'                     => PHP_ZTS === 1,
            'php_64bit'               => PHP_INT_SIZE === 8,
            'open_basedir_set'        => self::probe(static fn (): bool => self::ini('open_basedir') !== '', false),
            'allow_url_fopen'         => self::probe(static fn (): bool => self::iniBool(self::ini('allow_url_fopen')), false),
            'opcache_loaded'          => extension_loaded('Zend OPcache'),
            'suhosin_loaded'          => extension_loaded('suhosin'),
            'posix_available'         => function_exists('posix_geteuid'),
            'host_panel_cpanel'       => self::dirExists('/usr/local/cpanel'),
            'host_panel_plesk'        => self::dirExists('/usr/local/psa'),
            'host_panel_directadmin'  => self::dirExists('/usr/local/directadmin'),
            'docroot_user_home'       => self::isUserHomeDocroot((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')),
            'sapi_per_user'           => self::probe(static fn (): bool => self::isPerUserSapi($sapi), false),
        ];
    }

    /**
     * `memory_limit` shorthand → whole MiB. `-1` (unlimited) and
     * unparseable values map to `null` so they land as an absent
     * double rather than a fake small limit.
     */
    public static function parseMemoryLimitMb(string $raw): ?int
    {
        if (preg_match('/^\s*(-?\d+)\s*([kmg]?)\s*$/i', $raw, $m) !== 1) {
            return null;
        }
        $n = (int) $m[1];
        if ($n < 0) {
            return null;
        }
        $bytes = match (strtolower($m[2])) {
            'g'     => $n * 1024 * 1024 * 1024,
            'm'     => $n * 1024 * 1024,
            'k'     => $n * 1024,
            default => $n,
        };
        return intdiv($bytes, 1024 * 1024);
    }

    public static function countDisabledFunctions(string $raw): int
    {
        return count(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $fn): bool => $fn !== ''
        ));
    }

    /**
     * cPanel (`/home/<user>/public_html`) and DirectAdmin
     * (`/home/<user>/domains/<domain>/public_html`) docroots.
     * `/home2`, `/home3`, … cover cPanel's multi-disk layout.
     */
    public static function isUserHomeDocroot(string $docroot): bool
    {
        return preg_match('~^/home\d*/[^/]+/(public_html|domains/.+/public_html)(/|$)~', $docroot) === 1;
    }

    /**
     * Per-user FPM / suEXEC / mod_ruid2: the process runs as the
     * account that owns the panel's files instead of a shared
     * service user. Without POSIX we can't tell, so report `false`
     * rather than guess.
     */
    private static function isPerUserSapi(string $sapi): bool
    {
        if ($sapi !== 'fpm-fcgi' && $sapi !== 'cgi-fcgi') {
            return false;
        }
        if (!function_exists('posix_geteuid')) {
            return false;
        }
        $euid  = posix_geteuid();
        $owner = @fileowner(__FILE__);
        return $owner !== false
            && $euid === $owner
            && $euid >= self::MIN_ACCOUNT_UID
            && $euid !== self::NOBODY_UID;
    }

    private static function dirExists(string $path): bool
    {
        // `@` because an `open_basedir` that excludes /usr/local
        // warns on every is_dir() call outside it.
        return self::probe(static fn (): bool => @is_dir($path), false);
    }

    /**
     * `ini_get` can itself be in `disable_functions`; treat that the
     * same as an unset directive.
     */
    private static function ini(string $key): string
    {
        if (!function_exists('ini_get')) {
            return '';
        }
        $value = ini_get($key);
        return $value === false ? '' : $value;
    }

    private static function iniBool(string $raw): bool
    {
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private static function probe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
