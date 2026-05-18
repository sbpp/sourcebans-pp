<?php
namespace SteamID;

use Exception;

/**
 * Class SteamID
 *
 * @package SteamID
 */
class SteamID
{
    private static array $validFormat = ['Steam2', 'Steam3', 'Steam64'];

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

        self::assertSixtyFourBit();

        $from = self::resolveInputID($steamid);

        if ($from === $format) {
            return str_replace("STEAM_1", "STEAM_0", $steamid);
        }

        return match ($from . '->' . $format) {
            'Steam2->Steam3'  => self::Steam2toSteam3($steamid),
            'Steam2->Steam64' => self::Steam2toSteam64($steamid),
            'Steam3->Steam2'  => self::Steam3toSteam2($steamid),
            'Steam3->Steam64' => self::Steam3toSteam64($steamid),
            'Steam64->Steam2' => self::Steam64toSteam2($steamid),
            'Steam64->Steam3' => self::Steam64toSteam3($steamid),
            // Unreachable — `$from` is constrained to one of three by
            // `resolveInputID()` (throws otherwise), `$format` to one
            // of three by the `in_array($validFormat)` guard above,
            // and the `$from === $format` branch handles the diagonal.
            // The default exists so PHPStan's match-exhaustiveness
            // check sees a covered string-valued pivot.
            default => throw new Exception("Unreachable conversion: $from -> $format"),
        };
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
     * Native-int math is correct on any 64-bit PHP (`PHP_INT_MAX` is
     * 9.22e18, ~120x the largest plausible Steam64 ~7.66e16). The
     * project's PHP floor is 8.5, which on every supported distro
     * ships 64-bit; the guard exists so the vanishingly rare
     * self-compiled-32-bit holdout (Pi Zero / armhf / a custom build)
     * fails loudly with a clear message instead of silently overflowing.
     */
    private static function assertSixtyFourBit(): void
    {
        if (PHP_INT_SIZE !== 8) {
            throw new \RuntimeException(
                "SourceBans++ requires a 64-bit PHP build for Steam ID conversion; detected PHP_INT_SIZE=" . PHP_INT_SIZE
            );
        }
    }

    private static function Steam2toSteam3(string $steamid): string
    {
        $parts = explode(':', $steamid);
        $sid = (int) $parts[2] * 2 + (int) $parts[1];
        return "[U:1:$sid]";
    }

    /**
     * Returns the Steam64 as a decimal string. The previous GMP backend
     * returned a `\GMP` object that stringified to the digits via
     * `__toString()`, so callers concatenating into URLs or binding into
     * SQL relied on the string shape. Returning a native `int` here
     * would break those call sites silently (e.g. PDO binding the value
     * as `PDO::PARAM_INT` instead of `PARAM_STR` and triggering a
     * different prepared-statement path). Keep the string return.
     */
    private static function Steam2toSteam64(string $steamid): string
    {
        $parts = explode(':', $steamid);
        $sid = (int) $parts[2] * 2 + 76561197960265728 + (int) $parts[1];
        return (string) $sid;
    }

    private static function Steam3toSteam2(string $steamid): string
    {
        $parts = explode(':', trim($steamid, '[]'));
        $idy = (int) $parts[2] % 2;
        $idz = intdiv((int) $parts[2], 2);
        return "STEAM_0:$idy:$idz";
    }

    private static function Steam3toSteam64(string $steamid): string
    {
        $parts = explode(':', trim($steamid, '[]'));
        $sid = (int) $parts[2] + 76561197960265728;
        return (string) $sid;
    }

    private static function Steam64toSteam2(int|string $steamid): string
    {
        $steamid = (int) $steamid;
        $idy = $steamid % 2;
        $idz = intdiv($steamid - 76561197960265728, 2);
        return "STEAM_0:$idy:$idz";
    }

    private static function Steam64toSteam3(int|string $steamid): string
    {
        $idz = (int) $steamid - 76561197960265728;
        return "[U:1:$idz]";
    }
}
