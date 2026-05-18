<?php

namespace Sbpp\Auth;

use Lcobucci\JWT\Token;
use PDO;
use Sbpp\Db\Database;
use Sbpp\Security\Crypto;

/**
 * Class Auth
 */
final class Auth
{
    private static ?Database $dbs = null;

    public static function init(Database $dbs): void
    {
        self::$dbs = $dbs;
    }

    public static function login(int $aid, int $maxlife): void
    {
        $jti = self::generateJTI();

        $token = JWT::create($jti, $maxlife, $aid);
        self::updateLastVisit($aid);

        self::setCookie($token->toString(), time() + $maxlife, Host::cookieDomain(), Host::isSecure());

        //Login / Logout requests will trigger GC routine
        self::gc();
    }

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

    private static function setCookie(string $data, int $lifetime, string $domain, bool $secure): void
    {
        setcookie('sbpp_auth', $data, [
            'expires' => $lifetime,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    private static function gc(): void
    {
        self::$dbs->query(
            "DELETE FROM `:prefix_login_tokens` WHERE lastAccessed < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"
        );
        self::$dbs->execute();
    }

    private static function insertNewToken(string $jti, string $secret): void
    {
        self::$dbs->query(
            "INSERT INTO `:prefix_login_tokens` (jti, secret, lastAccessed) VALUES (:jti, :secret, UNIX_TIMESTAMP())"
        );
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        self::$dbs->bind(':secret', $secret, PDO::PARAM_STR);
        self::$dbs->execute();
    }

    private static function updateLastVisit(int $aid): void
    {
        self::$dbs->query("UPDATE `:prefix_admins` SET lastvisit = UNIX_TIMESTAMP() WHERE aid = :aid");
        self::$dbs->bind(':aid', $aid, PDO::PARAM_INT);
        self::$dbs->execute();
    }

    private static function updateLastAccessed(string $jti): void
    {
        self::$dbs->query("UPDATE `:prefix_login_tokens` SET lastAccessed = UNIX_TIMESTAMP() WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        self::$dbs->execute();
    }

    private static function getTokenSecret(string $jti): mixed
    {
        self::$dbs->query("SELECT secret FROM `:prefix_login_tokens` WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        $result = self::$dbs->single();
        return $result['secret'];
    }

    private static function generateJTI(): string
    {
        do {
            $jti = Crypto::genJTI();
        } while (self::checkJTI($jti));

        return $jti;
    }

    private static function checkJTI(string $jti): bool
    {
        self::$dbs->query("SELECT 1 FROM `:prefix_login_tokens` WHERE jti = :jti");
        self::$dbs->bind(':jti', $jti, PDO::PARAM_STR);
        $result = self::$dbs->single();
        return !empty($result);
    }

    private static function getJWTFromCookie(): string
    {
        return is_string($_COOKIE['sbpp_auth'] ?? null) ? $_COOKIE['sbpp_auth'] : '';
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\Auth` until the call-site sweep PR.
class_alias(\Sbpp\Auth\Auth::class, 'Auth');
