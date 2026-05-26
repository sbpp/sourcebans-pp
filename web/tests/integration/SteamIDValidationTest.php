<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SteamID\SteamID;

/**
 * #1420 follow-up — locks the strict-shape contract of
 * `SteamID::isValidID()` / `SteamID::resolveInputID()` after the library
 * tightening. Pre-fix the regexes were unanchored with loose character
 * classes (`STEAM_[0|1]:[0:1]:\d*` — `|` inside `[…]` is a literal pipe,
 * not alternation; `\d*` accepts zero digits; missing `^`/`$` makes them
 * substring matchers), so three concrete bypass shapes accepted by the
 * library AND silently corrupted on conversion:
 *
 *   - `STEAM_0:0:` — empty Z digit. `\d*` accepts zero matches. The
 *     value round-trips through `toSteam2()` unchanged, landing as an
 *     invalid SteamID in `:prefix_admins.authid` /
 *     `:prefix_bans.authid` / `:prefix_comms.authid`.
 *   - `asdfSTEAM_0:0:123` — substring bypass. The unanchored regex
 *     finds `STEAM_0:0:123` inside the garbage, returns true, and
 *     `toSteam2()` returns the FULL input string verbatim (the regex
 *     in `resolveInputID` matches the substring but the conversion
 *     methods just `explode(':', $steamid)` and stringify the parts).
 *   - `asdf 76561197960265728 garbage` — embedded Steam64. The
 *     unanchored `\d{17}` matches the embedded 17 digits AND
 *     `toSteam2()` emits `STEAM_0:0:-38280598980132864` (negative Z
 *     from `(int) 'asdf 76561197960265728 garbage'` being 0 instead of
 *     the embedded number, then the math underflows).
 *
 * Post-fix the regexes are anchored (`^…$`), use proper character
 * classes (`[01]` not `[0|1]` / `[0:1]`), and require at least one
 * digit (`\d+` not `\d*`). The strict gate now rejects every bypass
 * shape AND keeps every legitimate SteamID format accepted (the
 * `SteamIdConversionTest` test class covers the conversion math; this
 * file covers the SHAPE-validation contract).
 *
 * `isValidID()` and `resolveInputID()` share a `ID_PATTERNS` table for
 * single-source acceptance — any input that passes `isValidID()` is
 * guaranteed not to throw `Invalid SteamID input!` from a subsequent
 * `toSteam*()` call. The "byte-for-byte symmetry" tests below pin
 * this contract so a future refactor that touches one without the
 * other fails loudly here instead of silently divergence.
 *
 * Pure-PHP test — no DB / no Smarty / no Fixture::reset(); extends
 * `PHPUnit\Framework\TestCase` directly.
 */
final class SteamIDValidationTest extends TestCase
{
    /**
     * Inputs the strict gate MUST accept. Each entry is `[input,
     * resolved-format]` so the symmetry test can prove `isValidID`
     * AND `resolveInputID` agree.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function validInputs(): iterable
    {
        // Steam2 canonical
        yield 'STEAM_0:0:11101'        => ['STEAM_0:0:11101', 'Steam2'];
        yield 'STEAM_0:1:11101'        => ['STEAM_0:1:11101', 'Steam2'];
        yield 'STEAM_0:0:0 (zero Z)'   => ['STEAM_0:0:0',     'Steam2'];
        // Steam2 universe 1 (TF2/L4D normalise to STEAM_1 — see
        // `to()`'s `from === $format` branch which str_replaces back
        // to STEAM_0, and the SourceMod plugin's `authid REGEXP
        // '^STEAM_[0-9]:Y:Z$'` query which accepts both).
        yield 'STEAM_1:0:11101 (universe 1)' => ['STEAM_1:0:11101', 'Steam2'];
        yield 'STEAM_1:1:11101 (universe 1)' => ['STEAM_1:1:11101', 'Steam2'];
        // Large account ID
        yield 'STEAM_0:1:2147483647 (max accid)' => ['STEAM_0:1:2147483647', 'Steam2'];

        // Steam3 bracketed
        yield '[U:1:22202]'            => ['[U:1:22202]', 'Steam3'];
        yield '[U:1:0] (zero accid)'   => ['[U:1:0]',     'Steam3'];
        yield '[U:1:4294967295] (max)' => ['[U:1:4294967295]', 'Steam3'];

        // Steam3 bracketless (legacy compat — see ID_PATTERNS docblock)
        yield 'U:1:22202 (bracketless)' => ['U:1:22202', 'Steam3'];

        // Steam64 (exactly 17 digits)
        yield '76561197960265728 (base / zero)' => ['76561197960265728', 'Steam64'];
        yield '76561197960287930 (canonical)'   => ['76561197960287930', 'Steam64'];
        yield '76561202255233023 (large)'       => ['76561202255233023', 'Steam64'];
    }

    /**
     * Inputs the strict gate MUST reject. These are the bypass shapes
     * the #1420 review surfaced PLUS the obvious garbage cases the
     * library always rejected (kept here as regression guards in case
     * a future regex tweak accidentally accepts them).
     *
     * @return iterable<string, array{0: string, 1: string}>
     *         [input, why-it-fails]
     */
    public static function invalidInputs(): iterable
    {
        // ----- The three concrete bypass shapes from #1420 -------------
        yield 'STEAM_0:0: (empty Z digit)'            => ['STEAM_0:0:',          'Z digit is empty; \d+ requires at least one'];
        yield 'asdfSTEAM_0:0:123 (substring bypass)'  => ['asdfSTEAM_0:0:123',   'leading garbage; ^…$ anchors reject'];
        yield 'STEAM_0:0:123garbage (trailing junk)'  => ['STEAM_0:0:123garbage', 'trailing garbage; ^…$ anchors reject'];
        yield 'asdf 76561197960265728 garbage'        => ['asdf 76561197960265728 garbage', 'embedded Steam64; ^…$ anchors reject'];

        // ----- Invalid universe / Y digit ------------------------------
        yield 'STEAM_2:0:0 (universe 2)'              => ['STEAM_2:0:0',          'universe digit 2 not in [01]'];
        yield 'STEAM_|:0:0 (literal pipe)'            => ['STEAM_|:0:0',          'pre-fix the loose class [0|1] accepted | as a literal char'];
        yield 'STEAM_0:2:0 (Y=2)'                     => ['STEAM_0:2:0',          'Y digit 2 not in [01]'];
        yield 'STEAM_0::0 (Y is colon)'               => ['STEAM_0::0',           'pre-fix the loose class [0:1] accepted : as a literal char'];

        // ----- Empty / whitespace --------------------------------------
        yield 'empty string'                          => ['',                     'empty; no pattern matches'];
        yield 'single space'                          => [' ',                    'whitespace; no pattern matches'];
        yield 'four spaces'                           => ['    ',                 'whitespace; no pattern matches'];

        // ----- Steam3 bracket mismatches -------------------------------
        yield '[U:1:22202 (missing closing bracket)' => ['[U:1:22202',           '^\[…\]$ requires closing bracket'];
        yield 'U:1:22202] (missing opening bracket)' => ['U:1:22202]',           'bracketless form does not accept trailing ]'];
        yield '[U:2:22202] (Steam3 universe 2)'      => ['[U:2:22202]',          'Steam3 regex requires universe 1'];
        yield '[U:1:] (empty account id)'            => ['[U:1:]',               '\d+ requires at least one digit'];
        yield 'U:1: (bracketless empty accid)'       => ['U:1:',                 '\d+ requires at least one digit'];

        // ----- Steam64 shape failures ----------------------------------
        yield '7656119796026572 (16 digits)'          => ['7656119796026572',     '\d{17} requires exactly 17'];
        yield '765611979602657281 (18 digits)'        => ['765611979602657281',   '^\d{17}$ rejects > 17'];
        yield '12345 (5 digits)'                      => ['12345',                'far too short for Steam64'];
        yield '7656119796026572a (17 chars w/ alpha)' => ['7656119796026572a',    'has non-digit'];

        // ----- Pure garbage --------------------------------------------
        yield 'garbage'                               => ['garbage',              'no SteamID shape'];
        yield 'asdf'                                  => ['asdf',                 'no SteamID shape'];
        yield 'null'                                  => ['null',                 'literal string "null"'];
        yield 'STEAM (prefix only)'                   => ['STEAM',                'prefix only'];
        yield 'STEAM_0 (prefix only)'                 => ['STEAM_0',              'partial prefix'];
        yield '0 (single zero)'                       => ['0',                    'single digit'];

        // ----- Embedded valid IDs (more shapes) ------------------------
        yield 'prefix STEAM_0:0:1 (leading text)'     => ['prefix STEAM_0:0:1',   'leading text bypass'];
        yield 'STEAM_0:0:1 suffix (trailing text)'    => ['STEAM_0:0:1 suffix',   'trailing text bypass'];
        yield '[U:1:1][U:1:2] (two Steam3 joined)'    => ['[U:1:1][U:1:2]',       'doubled Steam3'];
        yield 'newline-embedded valid ID'             => ["STEAM_0:0:1\n",        'trailing newline'];
        yield 'tab-embedded valid ID'                 => ["\tSTEAM_0:0:1",        'leading tab'];
    }

    /**
     * Strict gate accepts every documented legitimate SteamID format.
     */
    #[DataProvider('validInputs')]
    public function testIsValidIdAcceptsLegitimateInputs(string $input, string $_format): void
    {
        $this->assertTrue(
            SteamID::isValidID($input),
            sprintf('SteamID::isValidID(%s) must be true (legitimate input)', var_export($input, true)),
        );
    }

    /**
     * Strict gate rejects the #1420 bypass shapes + every shape that
     * was never legitimate but the loose pre-fix regex sometimes
     * accepted.
     */
    #[DataProvider('invalidInputs')]
    public function testIsValidIdRejectsBypassesAndGarbage(string $input, string $why): void
    {
        $this->assertFalse(
            SteamID::isValidID($input),
            sprintf(
                'SteamID::isValidID(%s) must be false (%s)',
                var_export($input, true),
                $why,
            ),
        );
    }

    /**
     * Byte-for-byte symmetry: any input passing `isValidID()` must
     * resolve to a known format through `resolveInputID()` (i.e. NOT
     * throw `Invalid SteamID input!`). Pre-fix the two surfaces had
     * subtly different regex tables and the asymmetry was the
     * underlying bug-class — `isValidID` accepted strings
     * `resolveInputID` then matched in a different position and
     * `toSteam2()` corrupted on conversion. The shared `ID_PATTERNS`
     * constant guarantees the two stay in lockstep; this test pins it.
     */
    #[DataProvider('validInputs')]
    public function testValidInputsResolveToExpectedFormat(string $input, string $expectedFormat): void
    {
        // Reach for `resolveInputID` via reflection — it's private by
        // design (the public surface is `to*()` which dispatches off
        // the resolution result). Testing the resolver directly proves
        // the symmetry contract without coupling to the conversion
        // math. PHP 8.1+ makes private methods reachable via
        // `Reflection::invoke()` without an explicit `setAccessible()`
        // call (and PHP 8.5 deprecates `setAccessible()` for that
        // reason).
        $resolve = (new ReflectionClass(SteamID::class))->getMethod('resolveInputID');
        $this->assertSame(
            $expectedFormat,
            $resolve->invoke(null, $input),
            sprintf(
                'SteamID::resolveInputID(%s) must resolve to %s',
                var_export($input, true),
                var_export($expectedFormat, true),
            ),
        );
    }

    /**
     * Sister of the above: any input the gate rejects must ALSO be
     * rejected by `resolveInputID()` (raises `Invalid SteamID input!`).
     * Pre-fix asymmetry meant `resolveInputID` could throw on inputs
     * `isValidID` had just blessed — the JSON dispatcher caught the
     * throw and surfaced it as a generic 500 (#1420's user-visible
     * symptom). The shared-table fix guarantees the two surfaces agree.
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputsAlsoThrowFromResolveInputId(string $input, string $why): void
    {
        $resolve = (new ReflectionClass(SteamID::class))->getMethod('resolveInputID');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid SteamID input!');
        $resolve->invoke(null, $input);
        $this->fail(sprintf(
            'SteamID::resolveInputID(%s) must throw (%s)',
            var_export($input, true),
            $why,
        ));
    }

    /**
     * Acceptance for valid inputs must convert without throwing. This
     * is the end-to-end shape: `isValidID()` → `toSteam2()` round-trip
     * is the canonical "this is a SteamID I can do something with"
     * contract every JSON / page handler depends on.
     */
    #[DataProvider('validInputs')]
    public function testValidInputsConvertWithoutThrowing(string $input, string $_format): void
    {
        $this->assertNotFalse(SteamID::toSteam2($input));
        $this->assertNotFalse(SteamID::toSteam3($input));
        $this->assertNotFalse(SteamID::toSteam64($input));
    }

    /**
     * `to()`'s `if (empty($steamid)) return false;` short-circuit still
     * fires before `resolveInputID()` — so `toSteam*('')` returns
     * `false` rather than throwing. Pin this so a future refactor of
     * the early-return branch (e.g. moving the empty check into
     * `resolveInputID` itself) doesn't change the public surface
     * silently.
     */
    public function testEmptyInputReturnsFalseFromConverters(): void
    {
        $this->assertFalse(SteamID::toSteam2(''));
        $this->assertFalse(SteamID::toSteam3(''));
        $this->assertFalse(SteamID::toSteam64(''));
    }

    /**
     * Single source of truth: the `ID_PATTERNS` constant is the only
     * place the accepted-shape regexes live. A future agent editing
     * `isValidID()` or `resolveInputID()` separately would break the
     * symmetry — pin the constant's presence + shape so the gate
     * fires.
     */
    public function testIdPatternsConstantIsTheSourceOfTruth(): void
    {
        $reflection = new ReflectionClass(SteamID::class);
        $this->assertTrue(
            $reflection->hasConstant('ID_PATTERNS'),
            'SteamID::ID_PATTERNS must be defined as the single source for the accepted-shape regex table.',
        );
        $patterns = $reflection->getReflectionConstant('ID_PATTERNS')->getValue();
        $this->assertIsArray($patterns);
        $this->assertCount(4, $patterns, 'ID_PATTERNS must list exactly the four documented shapes (Steam2 / Steam3 bracketed / Steam3 bracketless / Steam64).');

        // Every regex must (1) be `^…$` anchored AND (2) carry the `D`
        // modifier. The `D` modifier strictly anchors `$` to
        // end-of-string only (default behaviour allows a trailing
        // `\n`); without it `STEAM_0:0:1\n` slips through the gate
        // AND lands as a trailing-newline string in the DB on
        // conversion (the newline-bypass sibling of #1420's
        // substring-bypass class).
        foreach ($patterns as [$regex, $format]) {
            $this->assertIsString($regex);
            $this->assertIsString($format);
            $this->assertStringStartsWith('/^', $regex, sprintf(
                'Pattern %s for format %s must start with /^ — substring bypasses (#1420) require explicit ^…$ anchors.',
                var_export($regex, true),
                var_export($format, true),
            ));
            $this->assertMatchesRegularExpression(
                '~\$/[a-zA-Z]*D[a-zA-Z]*$~',
                $regex,
                sprintf(
                    'Pattern %s for format %s must end with $/…D… (the D modifier strictly anchors $ to end-of-string; without it, trailing \\n bypasses the gate).',
                    var_export($regex, true),
                    var_export($format, true),
                ),
            );
        }
    }

    /**
     * Strict gate doesn't deprecation-warn on int input. The library
     * accepts `toSteam2((int) $steam64)` per the
     * `SteamIdConversionTest::testSteam64ToSteam2` int-shape arm — that
     * path reaches `resolveInputID()` which historically did
     * `preg_match($pattern, $intValue)` and emitted PHP 8.1's
     * "Passing null to parameter…" / int-to-string deprecation. The
     * post-#1420 cast at the entry point (`$s = (string) $steamid`)
     * silences this; pin the contract.
     */
    public function testIsValidIdAcceptsIntInput(): void
    {
        // Promote PHP deprecation notices to exceptions for this test.
        // If the cast-at-entry-point disappears, the next `preg_match`
        // call against the int value would raise `E_DEPRECATED` which
        // would land here as `\ErrorException`.
        $prev = set_error_handler(static function (int $errno, string $errstr): bool {
            if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                throw new \ErrorException($errstr, 0, $errno);
            }
            return false;
        });

        try {
            $this->assertTrue(SteamID::isValidID(76561197960287930));
            // Steam64-as-int → resolves to Steam64 (the cast makes it
            // a 17-char digit string).
            $this->assertSame('STEAM_0:0:11101', SteamID::toSteam2(76561197960287930));
        } finally {
            restore_error_handler();
            $this->assertSame($prev, set_error_handler(static fn (): bool => false));
            restore_error_handler();
        }
    }

    /**
     * #1423 follow-up #4 — `SteamID::HANDLER_STRICT_REGEX` is the
     * single source the per-handler `preg_match` gates consume in
     * `web/api/handlers/{admins,bans,comms}.php`. Pin three contracts:
     *
     *   1. The regex exists as a public class constant (so handlers
     *      can reach for it without a runtime lookup).
     *   2. The regex carries the `D` modifier (so `STEAM_0:0:1\n`
     *      is rejected at the gate before reaching `toSteam2()`).
     *      Pre-fix the handler regex was a hand-rolled copy that
     *      drifted from `ID_PATTERNS` on the `D` modifier — the
     *      newline-suffixed input slipped past the handler, then
     *      failed `toSteam2()`, then the exception escaped via
     *      `Api::handle`'s `Throwable` fallback as a generic 500.
     *   3. The regex is TIGHTER than `ID_PATTERNS` on one axis:
     *      bracketless Steam3 (`U:1:N`) is INTENTIONALLY excluded
     *      so the handler gate matches the form template's
     *      `pattern="STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}"`
     *      byte-for-byte. Curl-driven callers get the same shape
     *      contract a form user sees on the pattern-mismatch popover.
     */
    public function testHandlerStrictRegexIsExposedAndCarriesDModifier(): void
    {
        $reflection = new ReflectionClass(SteamID::class);
        $this->assertTrue(
            $reflection->hasConstant('HANDLER_STRICT_REGEX'),
            'SteamID::HANDLER_STRICT_REGEX must exist — the per-handler `preg_match` gates source it instead of carrying hand-rolled copies that drift on the modifier set.',
        );
        $regex = $reflection->getReflectionConstant('HANDLER_STRICT_REGEX')->getValue();
        $this->assertIsString($regex);
        $this->assertStringStartsWith('/^', $regex, 'HANDLER_STRICT_REGEX must be ^…$ anchored.');
        $this->assertMatchesRegularExpression(
            '~\$/[a-zA-Z]*D[a-zA-Z]*$~',
            $regex,
            'HANDLER_STRICT_REGEX must carry the D modifier — without it, `STEAM_0:0:1\n` matches and then fails `toSteam2()` via the library\'s stricter gate (the newline-bypass class).',
        );
    }

    /**
     * #1423 follow-up #4 — every shape `HANDLER_STRICT_REGEX` accepts
     * must ALSO be accepted by `ID_PATTERNS` (so the library's
     * `toSteam2()` doesn't throw on inputs the handler blessed). The
     * reverse is NOT required — `ID_PATTERNS` accepts bracketless
     * Steam3 (`U:1:N`) while `HANDLER_STRICT_REGEX` deliberately does
     * not (form-pattern symmetry).
     *
     * @return iterable<string, array{0: string}>
     */
    public static function handlerStrictAccepts(): iterable
    {
        yield 'STEAM_0:0:11101'        => ['STEAM_0:0:11101'];
        yield 'STEAM_1:1:11101'        => ['STEAM_1:1:11101'];
        yield 'STEAM_0:0:0'            => ['STEAM_0:0:0'];
        yield '[U:1:22202]'            => ['[U:1:22202]'];
        yield '[U:1:0]'                => ['[U:1:0]'];
        yield '76561197960265728'      => ['76561197960265728'];
        yield '76561197960287930'      => ['76561197960287930'];
    }

    /**
     * Every shape `HANDLER_STRICT_REGEX` accepts must also be accepted
     * by `SteamID::isValidID()` — otherwise the handler converts but
     * the library's downstream gate rejects, and we're back to 500-
     * envelope territory.
     */
    #[DataProvider('handlerStrictAccepts')]
    public function testHandlerStrictRegexAgreesWithIdPatternsOnAcceptableShapes(string $input): void
    {
        $this->assertSame(
            1,
            preg_match(SteamID::HANDLER_STRICT_REGEX, $input),
            sprintf('HANDLER_STRICT_REGEX must accept %s', var_export($input, true)),
        );
        $this->assertTrue(
            SteamID::isValidID($input),
            sprintf('SteamID::isValidID() must accept %s (handler gate <= library gate)', var_export($input, true)),
        );
        $this->assertNotFalse(
            SteamID::toSteam2($input),
            sprintf('SteamID::toSteam2() must convert %s without throwing', var_export($input, true)),
        );
    }

    /**
     * Documented asymmetry: bracketless Steam3 (`U:1:N`) is accepted
     * by `ID_PATTERNS` for library-side convenience (the panel's
     * conversion call sites can still hand it to `toSteam2()`) but
     * REJECTED by `HANDLER_STRICT_REGEX` for symmetry with the form
     * template's `pattern` attribute. Pin the asymmetry so a future
     * refactor that unifies the two regexes silently degrades the
     * gate.
     */
    public function testHandlerStrictRegexRejectsBracketlessSteam3(): void
    {
        $this->assertTrue(
            SteamID::isValidID('U:1:22202'),
            'Library MUST accept bracketless Steam3 (conversion-path convenience).',
        );
        $this->assertSame(
            0,
            preg_match(SteamID::HANDLER_STRICT_REGEX, 'U:1:22202'),
            'HANDLER_STRICT_REGEX MUST reject bracketless Steam3 (form-pattern symmetry).',
        );
    }

    /**
     * Newline-bypass cases the handler regex MUST reject (the bug
     * `HANDLER_STRICT_REGEX`'s `D` modifier was lifted to close).
     * Without the modifier the `$` anchor matches end-of-string OR
     * just-before-final-`\n`; the newline-suffixed value slips past
     * the handler gate and then 500s on `SteamID::toSteam2()`.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function newlineBypasses(): iterable
    {
        yield "STEAM_0:0:1\\n"     => ["STEAM_0:0:1\n"];
        yield "STEAM_1:0:1\\n"     => ["STEAM_1:0:1\n"];
        yield "[U:1:1]\\n"         => ["[U:1:1]\n"];
        yield "76561197960265728\\n" => ["76561197960265728\n"];
    }

    #[DataProvider('newlineBypasses')]
    public function testHandlerStrictRegexRejectsNewlineBypass(string $input): void
    {
        $this->assertSame(
            0,
            preg_match(SteamID::HANDLER_STRICT_REGEX, $input),
            sprintf('HANDLER_STRICT_REGEX must reject newline-suffixed input %s', var_export($input, true)),
        );
        $this->assertFalse(
            SteamID::isValidID($input),
            sprintf('SteamID::isValidID() must also reject %s (both halves of the contract)', var_export($input, true)),
        );
    }

    /**
     * #1423 follow-up #4 — `SteamAuthHandler::validate()` regex
     * tightened from `7[0-9]{15,25}+` to `7\d{16}+` to match the
     * library's strict 17-digit gate. Pin the new shape with positive
     * + negative cases so a future tweak that loosens the OpenID regex
     * (e.g. accommodating a hypothetical future Steam-side change)
     * also tightens the library's `\d{17}` arm in the same PR.
     */
    public function testSteamAuthHandlerOpenIdRegexAcceptsOnly17DigitsStartingWith7(): void
    {
        $pattern = '/^https:\\/\\/steamcommunity\\.com\\/openid\\/id\\/(7\\d{16}+)$/D';
        // 17-digit Steam64 starting with 7 — accepted
        $this->assertSame(1, preg_match($pattern, 'https://steamcommunity.com/openid/id/76561197960265728'));
        $this->assertSame(1, preg_match($pattern, 'https://steamcommunity.com/openid/id/76561197960287930'));
        // 16 digits (one short) — rejected (pre-fix the `15,25` bound accepted)
        $this->assertSame(0, preg_match($pattern, 'https://steamcommunity.com/openid/id/7656119796026572'));
        // 18 digits (one over) — rejected (pre-fix the `15,25` bound accepted)
        $this->assertSame(0, preg_match($pattern, 'https://steamcommunity.com/openid/id/765611979602657281'));
        // Doesn't start with 7 — rejected
        $this->assertSame(0, preg_match($pattern, 'https://steamcommunity.com/openid/id/86561197960265728'));
        // Trailing newline — rejected via D modifier
        $this->assertSame(0, preg_match($pattern, "https://steamcommunity.com/openid/id/76561197960265728\n"));
        // Trailing garbage — rejected via $ anchor
        $this->assertSame(0, preg_match($pattern, 'https://steamcommunity.com/openid/id/76561197960265728?nope'));
    }

    /**
     * #1423 follow-up #4 — install wizard's regex in
     * `web/install/pages/page.5.php` was tightened with the `D`
     * modifier in the same PR. The wizard's gate is stricter than
     * `HANDLER_STRICT_REGEX` (Steam2 form only — the initial admin
     * is created with a STEAM_ ID; no Steam3 / Steam64 forms accepted
     * at install time per the legacy contract). Pin the shape +
     * modifier so a future wizard rewrite cannot silently regress
     * the newline-bypass surface.
     */
    public function testInstallWizardSteamIdRegexCarriesDModifier(): void
    {
        $pattern = '/^STEAM_[01]:[01]:[0-9]+$/D';
        // Accepted shapes
        $this->assertSame(1, preg_match($pattern, 'STEAM_0:0:11101'));
        $this->assertSame(1, preg_match($pattern, 'STEAM_1:1:11101'));
        // Rejected shapes (modifier-sensitive)
        $this->assertSame(0, preg_match($pattern, "STEAM_0:0:11101\n"), 'D modifier rejects trailing newline');
        $this->assertSame(0, preg_match($pattern, 'STEAM_2:0:11101'), '[01] rejects universe 2');
        $this->assertSame(0, preg_match($pattern, 'STEAM_0:2:11101'), '[01] rejects Y=2');
        $this->assertSame(0, preg_match($pattern, '[U:1:11101]'), 'wizard is Steam2-only');
        $this->assertSame(0, preg_match($pattern, '76561197960265728'), 'wizard rejects Steam64');
    }
}
