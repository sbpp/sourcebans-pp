<?php

namespace Sbpp\Auth;

/**
 * Class Host
 */
final class Host
{
    public static function domain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return preg_match('/^[A-Za-z0-9._:\[\]-]+$/', $host) ? $host : '';
    }

    public static function cookieDomain(): string {
        $domain = self::domain();
        if( ($p = strpos($domain, ':')) === false ) {
            return $domain;
        }
        return substr($domain, 0, $p);
    }

    public static function protocol(): string
    {
        return sprintf('http%s://',  self::isSecure() ? 's' : '');
    }

    /**
     * Resolve the request scheme. Returns `true` when the panel was
     * reached over HTTPS, `false` otherwise.
     *
     * Resolution order (#1381 CRIT-4):
     *
     *   1. `$_SERVER['HTTPS'] === 'on'` — the authoritative path.
     *      On the production Docker image Apache's `mod_remoteip`
     *      runs first (`RemoteIPInternalProxy` is pinned to the
     *      operator's `SBPP_TRUSTED_PROXIES` CIDR list) and a
     *      paired `SetEnvIfExpr` mirrors a trusted upstream's
     *      `X-Forwarded-Proto: https` into `HTTPS=on`. That keeps
     *      the trust decision in Apache (the right layer) — by the
     *      time PHP sees the request, `HTTPS` is the answer the
     *      panel should trust unconditionally.
     *
     *   2. `$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'`, ONLY
     *      if `$_SERVER['REMOTE_ADDR']` matches `SBPP_TRUSTED_PROXIES`.
     *      This is the fallback for non-Docker deployments where
     *      the operator never wired up `mod_remoteip` (nginx /
     *      HAProxy / Traefik with no `X-Forwarded-For` rewrite,
     *      and PHP-FPM behind it). They `define('SBPP_TRUSTED_PROXIES',
     *      '10.0.0.0/8 ...')` in `config.php` and the panel honours
     *      `XFP` only when the immediate caller is one of those
     *      ranges.
     *
     * Pre-fix the panel honoured `XFP` unconditionally, which let
     * any direct-HTTP attacker spoof `X-Forwarded-Proto: https`
     * and trick the panel into setting the `Secure` cookie flag,
     * issuing `https://…` redirects, etc. — the panel thought it
     * was behind TLS when it was actually serving plaintext to
     * the attacker.
     *
     * `SBPP_TRUSTED_PROXIES` accepts IPv4 + IPv6 literals AND CIDR
     * ranges, whitespace-separated (`'10.0.0.0/8 192.168.1.42 ::1'`).
     * Empty / undefined disables the XFP fallback entirely (the
     * dev container, which talks plain HTTP with no proxy in front,
     * hits this branch and returns `false` — its behaviour is
     * preserved).
     */
    public static function isSecure(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }
        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (!is_string($xfp) || $xfp !== 'https') {
            return false;
        }
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($remoteAddr)
            && $remoteAddr !== ''
            && self::isTrustedProxy($remoteAddr);
    }

    /**
     * Match the immediate-caller IP against the operator-defined
     * `SBPP_TRUSTED_PROXIES` list. Empty / undefined trust list
     * returns `false` — the secure default.
     *
     * Supports IPv4 + IPv6 literals AND CIDR ranges. Whitespace
     * separates entries so the same string is hand-editable in
     * `config.php` (`define('SBPP_TRUSTED_PROXIES', '10.0.0.0/8
     * 192.168.0.0/16');`) and machine-friendly as an env var
     * (`SBPP_TRUSTED_PROXIES="10.0.0.0/8 192.168.0.0/16"`).
     */
    public static function isTrustedProxy(string $ip): bool
    {
        if (!defined('SBPP_TRUSTED_PROXIES')) {
            return false;
        }
        $list = (string) constant('SBPP_TRUSTED_PROXIES');
        if (trim($list) === '') {
            return false;
        }
        $entries = preg_split('/\s+/', trim($list), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($entries as $entry) {
            if (self::ipMatchesRange($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare an IP against a single trust-list entry. The entry
     * may be a literal (`'10.0.0.5'` / `'::1'`) or CIDR notation
     * (`'10.0.0.0/8'` / `'2001:db8::/32'`). Uses `inet_pton` for
     * the binary mask compare so IPv4 + IPv6 share one code path.
     */
    private static function ipMatchesRange(string $ip, string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '') {
            return false;
        }
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }
        if (!str_contains($entry, '/')) {
            $entryBin = @inet_pton($entry);
            return $entryBin !== false && $ipBin === $entryBin;
        }
        [$range, $bits] = explode('/', $entry, 2);
        $rangeBin = @inet_pton($range);
        if ($rangeBin === false) {
            return false;
        }
        if (strlen($ipBin) !== strlen($rangeBin)) {
            return false; // address family mismatch (IPv4 vs IPv6)
        }
        $bits = (int) $bits;
        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }
        $fullBytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($rangeBin, 0, $fullBytes)) {
            return false;
        }
        if ($remainderBits === 0) {
            return true;
        }
        $mask = chr(0xff << (8 - $remainderBits) & 0xff);
        return (ord($ipBin[$fullBytes]) & ord($mask)) === (ord($rangeBin[$fullBytes]) & ord($mask));
    }

    /**
     * Build the absolute URL for the current request. With `$withoutRequest`,
     * the path component (everything after the first `/`) is omitted.
     */
    public static function complete(bool $withoutRequest = false): string
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $request = explode('/', is_string($uri) ? $uri : '');
        foreach ($request as $id => $fragment) {
            switch (true) {
                case empty($fragment):
                case str_contains($fragment, '.php'):
                case !preg_match('#^[A-Za-z0-9._~!$&\'()*+,;=:@%-]+$#', $fragment):
                    unset($request[$id]);
                    break;
                default:
            }
        }
        $request = implode('/', $request);

        return self::protocol().self::domain() . ($withoutRequest ? '' : "/$request");
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\Host` until the call-site sweep PR.
class_alias(\Sbpp\Auth\Host::class, 'Host');
