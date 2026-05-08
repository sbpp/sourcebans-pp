<?php
namespace SteamID;

use Database;
use Exception;

// Require the calculation classes
require_once __DIR__ . '/calc/GMP.php';
require_once __DIR__ . '/calc/BCMATH.php';

/**
 * Class SteamID
 *
 * @package SteamID
 */
class SteamID
{
    private static ?string $calcMethod = null;

    private static array $validFormat = ['Steam2', 'Steam3', 'Steam64'];

    /**
     * @param  Database|null $dbs
     * @throws Exception
     */
    public static function init(?Database $dbs = null)
    {
        self::$calcMethod = self::getCalcMethod();

        if (self::$calcMethod === 'SQL') {
            if (is_null($dbs)) {
                throw new Exception('No suitable calculation Method found!');
            }
            calc\SQL::setDB($dbs);
        }
    }

    /**
     * @param  $steamid
     * @return bool|mixed|string|string[]
     * @throws Exception
     */
    public static function toSteam2($steamid)
    {
        return self::to('Steam2', $steamid);
    }

    /**
     * @param  $steamid
     * @return bool|mixed|string|string[]
     * @throws Exception
     */
    public static function toSteam3($steamid)
    {
        return self::to('Steam3', $steamid);
    }

    /**
     * @param  $steamid
     * @return bool|mixed|string|string[]
     * @throws Exception
     */
    public static function toSteam64($steamid)
    {
        return self::to('Steam64', $steamid);
    }

    /**
     * @param  $format
     * @param  $steamid
     * @return bool|mixed|string|string[]
     * @throws Exception
     */
    private static function to($format, $steamid)
    {
        if (empty($steamid)) {
            return false;
        }

        if (!in_array($format, self::$validFormat)) {
            throw new Exception("Invalid input format!");
        }
        $from = self::resolveInputID($steamid);

        if ($from === $format) {
            return str_replace("STEAM_1", "STEAM_0", $steamid);
        }

        return call_user_func("SteamID\calc\\".self::$calcMethod.'::'.$from.'to'.$format, $steamid);
    }

    /**
     * @param  $steamid
     * @return string
     * @throws Exception
     */
    private static function resolveInputID($steamid)
    {
        switch (true) {
            case preg_match("/STEAM_[0|1]:[0:1]:\d*/", $steamid):
                return 'Steam2';
            case preg_match("/\[U:1:\d*\]/", $steamid):
                return 'Steam3';
            case preg_match("/U:1:\d*/", $steamid):
                return 'Steam3';
            case preg_match("/\d{17}/", $steamid):
                return 'Steam64';
            default:
                throw new Exception("Invalid SteamID input!");
        }
    }

    /**
     * @param  $steamid
     * @return bool
     */
    public static function isValidID($steamid)
    {
        switch (true) {
            case preg_match("/STEAM_[0|1]:[0:1]:\d*/", $steamid):
            case preg_match("/\[U:1:\d*\]/", $steamid):
            case preg_match("/U:1:\d*/", $steamid):
            case preg_match("/\d{17}/", $steamid):
                return true;
            default:
                return false;
        }
    }

    /**
     * @param  $steam1
     * @param  $steam2
     * @return bool
     * @throws Exception
     */
    public static function compare($steam1, $steam2)
    {
        return strcasecmp(self::toSteam64($steam1), self::toSteam64($steam2)) === 0;
    }

    /**
     * Build a MySQL REGEXP that matches an `authid` column against both
     * `STEAM_0:Y:Z` and `STEAM_1:Y:Z` forms of the same account, mirroring
     * the pattern the SourceMod plugin uses (see `sbpp_main.sp` /
     * `sbpp_checker.sp`, which always query `authid REGEXP '^STEAM_[0-9]:Y:Z$'`
     * because both universe digits legitimately end up in the column —
     * `GetClientAuthId(client, AuthId_Steam2, …)` returns `STEAM_1:…` on
     * TF2/L4D and similar Source titles).
     *
     * Returns `null` when `$value` isn't a recognisable Steam ID, so callers
     * can fall back to plain equality / LIKE for non-Steam inputs.
     *
     * Defends #1128 / #1130: `toSteam2()` always rewrites `STEAM_1` →
     * `STEAM_0`, so a strict-equality search after that normalisation
     * silently misses any row stored under the other universe digit.
     *
     * @param  mixed $value
     * @return string|null
     */
    public static function toSearchPattern($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            self::init();
            if (!self::isValidID($value)) {
                return null;
            }
            $steam2 = self::toSteam2($value);
            if (!is_string($steam2) || !preg_match('/^STEAM_[01]:([01]):(\d+)$/', $steam2, $m)) {
                return null;
            }
            return '^STEAM_[0-9]:' . $m[1] . ':' . $m[2] . '$';
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return string
     */
    private static function getCalcMethod()
    {
        switch (true) {
            case extension_loaded('gmp'):
                return 'GMP';
            case extension_loaded('bcmath'):
                return 'BCMATH';
            default:
                return 'SQL';
        }
    }
}