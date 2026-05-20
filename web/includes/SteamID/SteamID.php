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
     * Strict-shape patterns the library accepts as recognisable SteamID
     * inputs. Mirrored across `resolveInputID()` and `isValidID()` so
     * the two surfaces agree byte-for-byte on the accepted shape — pre-#1420
     * the two regex tables had subtly different shapes (`isValidID`'s
     * unanchored substring matcher accepted strings `resolveInputID`
     * silently corrupted on conversion, opening the embedded-Steam64
     * bypass the strict per-handler `preg_match` shipped by the JSON
     * handlers had to paper over). Single source of truth now.
     *
     * Each entry is `[regex, format-tag]`. Order matters for the
     * bracketed-vs-bracketless Steam3 disambiguation (`[U:1:N]` matches
     * both regexes after the brackets are stripped, so the bracketed
     * form must be tried FIRST — `resolveInputID()` returns the FIRST
     * match and stops; `isValidID()` is order-insensitive because it
     * only asks "does ANY pattern match").
     *
     * The shapes:
     *   - `^STEAM_[01]:[01]:\d+$` — Steam2. Universe digit 0 or 1
     *     (TF2/L4D and similar Source titles emit `STEAM_1` for the
     *     same accounts other Source titles emit `STEAM_0` for — see
     *     `toSearchPattern()` for the universe-agnostic SQL fallback
     *     that defends #1128 / #1130); Y digit is 0 or 1; Z digit is
     *     at least one digit (the empty-Z `STEAM_0:0:` shape was the
     *     #1420 bug-on-disk).
     *   - `^\[U:1:\d+\]$` — Steam3 bracketed.
     *   - `^U:1:\d+$` — Steam3 bracketless. Preserved because the
     *     conversion methods (`Steam3toSteam2`, `Steam3toSteam64`)
     *     `trim($steamid, '[]')` first, so the bracketless form
     *     converts correctly; rejecting it here would break callers
     *     that paste a Steam3 ID without brackets (the wild typed-input
     *     case, not a documented panel surface).
     *   - `^\d{17}$` — Steam64. Exactly 17 digits. The valid Steam64
     *     range for individual accounts starts at 76561197960265728
     *     (account ID 0) and grows ~linearly with account creations;
     *     this regex doesn't range-check (would require a paired
     *     migration of any caller that legitimately passes a sub-base
     *     Steam64 from a non-individual ID type), so callers needing
     *     "is this a plausible user Steam64" should layer a starts-with
     *     check on top. Tracked as a follow-up in PR #1423.
     *
     * The `D` modifier on every pattern is load-bearing: without it
     * PHP's `$` matches end-of-string OR a final `\n`, so
     * `STEAM_0:0:1\n` would slip through the gate AND `resolveInputID()`
     * would then return `'Steam2'` for it AND the `$from === $format`
     * branch in `to()` would return the trailing-newline string
     * verbatim into the DB. The `D` modifier strictly anchors `$` to
     * end-of-string only, closing the newline-bypass sibling of the
     * `^…$` substring-bypass class of #1420.
     */
    private const ID_PATTERNS = [
        ['/^STEAM_[01]:[01]:\d+$/D', 'Steam2'],
        ['/^\[U:1:\d+\]$/D',         'Steam3'],
        ['/^U:1:\d+$/D',             'Steam3'],
        ['/^\d{17}$/D',              'Steam64'],
    ];

    /**
     * Strict allowlist regex consumed by the per-handler defense-in-depth
     * gate in `web/api/handlers/{admins,bans,comms}.php` (and any future
     * caller that wants the "what the form's `pattern` attribute accepts"
     * shape without depending on the form template). Single source of
     * truth so the library and the handlers cannot drift on the accepted
     * shape — pre-#1423 follow-up #4 the handlers carried hand-rolled
     * copies that subtly differed (no `D` modifier → `STEAM_0:0:1\n`
     * newline-bypass slipped past the handler regex but failed the
     * library's `isValidID()` and threw `Exception('Invalid SteamID
     * input!')` from `toSteam2()`, which the dispatcher's `Throwable`
     * fallback wrapped as a generic `server_error` 500 envelope —
     * exactly the bug class #1420 was supposed to close).
     *
     * The shape is TIGHTER than `ID_PATTERNS` on one axis: the bracketless
     * Steam3 form (`U:1:N`) is INTENTIONALLY excluded so the gate matches
     * the form template's `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"`
     * byte-for-byte. Curl-driven callers get the same shape contract a
     * form user sees on the pattern-mismatch popover; bracketless Steam3
     * shape stays a library-side convenience for the conversion path
     * (`SteamID::toSteam2('U:1:1')` still works) but isn't an accepted
     * panel-input shape.
     *
     * The `D` modifier is load-bearing: without it `STEAM_0:0:1\n`
     * matches and the input then fails the library's `isValidID()` (which
     * carries the modifier), causing `toSteam2()` to throw on the
     * conversion the handler runs immediately after. The dispatcher
     * wraps the exception as a generic 500 — the operator gets neither
     * the inline validation message NOR the structured `validation` API
     * envelope, just a "something went wrong" page render.
     *
     * @see ID_PATTERNS for the wider library-accepted shape table.
     * @see `web/tests/integration/SteamIDValidationTest.php::testHandlerStrictRegexAgreesWithIdPatternsOnAcceptableShapes`
     *      for the cross-validation contract that pins the
     *      ID_PATTERNS / HANDLER_STRICT_REGEX relationship.
     */
    public const HANDLER_STRICT_REGEX = '/^(?:STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17})$/D';

    /**
     * @param  $steamid
     * @return string
     * @throws Exception
     */
    private static function resolveInputID($steamid)
    {
        // PHP 8.x deprecates passing non-string to preg_match's `string
        // $subject` arg. `to($format, $steamid)` accepts both int and
        // string Steam64 inputs (see SteamIdConversionTest's int-shape
        // round-trips), so the int case reaches here. Cast at the entry
        // point so the rest of the function works on a known string and
        // we don't ship a stack of `Deprecated: preg_match(): Passing
        // null to parameter…` warnings on int callers.
        $s = (string) $steamid;
        foreach (self::ID_PATTERNS as [$pattern, $format]) {
            if (preg_match($pattern, $s) === 1) {
                return $format;
            }
        }
        throw new Exception("Invalid SteamID input!");
    }

    /**
     * Strict-shape SteamID validator. Returns `true` for inputs the
     * library can convert correctly through `toSteam2()` / `toSteam3()`
     * / `toSteam64()`, `false` for everything else.
     *
     * The acceptance set is the same one `resolveInputID()` uses
     * (`ID_PATTERNS`) — so an input that passes `isValidID()` is
     * GUARANTEED not to throw on a subsequent `toSteam*()` call from
     * the same value. Pre-#1420 the two surfaces drifted: `isValidID`'s
     * unanchored loose-class regexes (`STEAM_[0|1]:[0:1]:\d*` — `|` in
     * `[…]` is a literal pipe, the missing `^`/`$` made it a substring
     * matcher, `\d*` accepted zero digits) accepted strings the
     * conversion path then either round-tripped verbatim into the DB
     * (`asdfSTEAM_0:0:123`) or converted to negative-Z garbage
     * (`asdf 76561197960265728 garbage` → `STEAM_0:0:-38280598980132864`).
     * Three concrete bypass shapes the post-fix gate correctly rejects:
     *   - `STEAM_0:0:` — empty Z. `\d+` rejects.
     *   - `asdfSTEAM_0:0:123` — substring bypass. `^…$` anchors reject.
     *   - `STEAM_2:0:0` — invalid universe digit. `[01]` rejects.
     *
     * Callers in `web/api/handlers/{bans,comms,admins}.php` carry their
     * own per-handler `preg_match` gate for defence-in-depth — both
     * layers should agree on the accepted shape. See "SteamID inputs"
     * in AGENTS.md for the contract.
     *
     * @param  mixed $steamid
     */
    public static function isValidID($steamid): bool
    {
        $s = (string) $steamid;
        foreach (self::ID_PATTERNS as [$pattern, $_format]) {
            if (preg_match($pattern, $s) === 1) {
                return true;
            }
        }
        return false;
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
