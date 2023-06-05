<?php

use Lcobucci\JWT\Token;

/**
 * Class Auth
 */
class Auth
{
    /**
     * @var Database
     */
    private static ?Database $dbs = null;

    /**
     * @param Database $dbs
     */
    public static function init(\Database $dbs): void
    {
        self::$dbs = $dbs;
    }

    /**
     * @param int $aid
     * @param int $maxlife
     */
    public static function login(int $aid, int $maxlife): void
    {
        $jti = self::generateJTI();

        $token = JWT::create($jti, $maxlife, $aid);
        self::updateLastVisit($aid);

        self::setCookie($token->toString(), time() + $maxlife, Host::cookieDomain(), Host::isSecure());

        //Login / Logout requests will trigger GC routine
        self::gc();
    }

    /**
     * @return bool
     */
    public static function logout(): bool
    {
        $cookie = self::getJWTFromCookie();
        if (empty($cookie) || preg_match('/.*\..*\..*\./', $cookie)) {
            return false;
        }
//        $token = JWT::parse($cookie);

//        if (JWT::validate($token)) {
//            self::$dbs->query("DELETE FROM `:prefix_login_tokens` WHERE jti = :jti");
//            self::$dbs->bind(':jti', $token->claims()->get('jti'));
//            self::$dbs->execute();
//        }

        self::setCookie('', 1, Host::cookieDomain(), Host::isSecure());

        //Login / Logout requests will trigger GC routine
        self::gc();

        return true;
    }

    /**
     * @return ?Token
     */
    public static function verify(): ?Token
    {
        $cookie = self::getJWTFromCookie();
        if (empty($cookie) || preg_match('/.*\..*\..*\./', $cookie)) {
            return null;
        }

        $token = JWT::parse($cookie);

        if (JWT::validate($token)) {
            self::updateLastAccessed($token->claims()->get('jti'));
            return $token;
        }

        return null;
    }

    /**
     * @param string $data
     * @param int $lifetime
     * @param string $domain
     * @param bool $secure
     */
    private static function setCookie(string $data, int $lifetime, string $domain, bool $secure): void
    {
        if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
            setcookie('sbpp_auth', $data, [
                'expires' => $lifetime,
                'path' => '/',
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie('sbpp_auth', $data, $lifetime, '/', $domain, $secure, true);
        }
    }

    /**
     *
     */
    private static function gc(): void
    {
        self::$dbs->query(
            "DELETE FROM `:prefix_login_tokens` WHERE lastAccessed < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"
        );
        self::$dbs->execute();
    }

    /**
     * @param string $jti
     * @param string $secret
     */
    private static function insertNewToken(string $jti, string $secret)
    {
        self::$dbs->query(
            "INSERT INTO `:prefix_login_tokens` (jti, secret, lastAccessed) VALUES (:jti, :secret, UNIX_TIMESTAMP())"
        );
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        self::$dbs->bind(':secret', $secret, PDO::PARAM_STR);
        self::$dbs->execute();
    }

    /**
     * @param int $aid
     */
    private static function updateLastVisit(int $aid): void
    {
        self::$dbs->query("UPDATE `:prefix_admins` SET lastvisit = UNIX_TIMESTAMP() WHERE aid = :aid");
        self::$dbs->bind(':aid', $aid, PDO::PARAM_INT);
        self::$dbs->execute();
    }

    /**
     * @param string $jti
     */
    private static function updateLastAccessed(string $jti): void
    {
        self::$dbs->query("UPDATE `:prefix_login_tokens` SET lastAccessed = UNIX_TIMESTAMP() WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        self::$dbs->execute();
    }

    /**
     * @param string $jti
     * @return mixed
     */
    private static function getTokenSecret(string $jti)
    {
        self::$dbs->query("SELECT secret FROM `:prefix_login_tokens` WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        $result = self::$dbs->single();
        return $result['secret'];
    }

    /**
     * @return string
     */
    private static function generateJTI(): string
    {
        do {
            $jti = Crypto::genJTI();
        } while (self::checkJTI($jti));

        return $jti;
    }

    /**
     * @param string $jti
     * @return bool
     */
    private static function checkJTI(string $jti): bool
    {
        self::$dbs->query("SELECT 1 FROM `:prefix_login_tokens` WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        $result = self::$dbs->single();
        return !empty($result);
    }

    /**
     * @return string
     */
    private static function getJWTFromCookie(): string
    {
        if (isset($_COOKIE['sbpp_auth'])) {
            return filter_var($_COOKIE['sbpp_auth'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        }

        return '';
    }
}
