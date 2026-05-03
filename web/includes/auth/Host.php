<?php

/**
 * Class Host
 */
class Host
{
    /**
     * @return string
     */
    public static function domain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return preg_match('/^[A-Za-z0-9._:\[\]-]+$/', $host) ? $host : '';
    }

    /**
     * @return string
     */
    public static function cookieDomain(): string {
        $domain = self::domain();
        if( ($p = strpos($domain, ':')) === false ) {
            return $domain;
        }
        return substr($domain, 0, $p);
    }

    /**
     * @return string
     */
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
     * @param bool $withoutRequest Don't return the rest of the link (part after the first slash)
     * @return string
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
