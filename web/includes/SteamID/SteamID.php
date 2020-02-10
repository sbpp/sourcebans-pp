<?php
namespace SteamID;

use Database;
use Exception;

/**
 * Class SteamID
 *
 * @package SteamID
 */
class SteamID
{
    /**
     * @var string
     */
    private static $calcMethod = null;
    /**
     * @var array
     */
    private static $validFormat = ['Steam2', 'Steam3', 'Steam64'];

    /**
     * @param  Database|null $dbs
     * @throws Exception
     */
    public static function init(Database $dbs = null)
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
