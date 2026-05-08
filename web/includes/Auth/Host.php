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

    public static function isSecure(): bool
    {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        if (!$isHttps)
            $isHttps = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';

        return $isHttps;
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
