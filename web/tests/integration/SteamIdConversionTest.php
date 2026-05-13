<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SteamID\SteamID;

/**
 * Locks the post-#TASK_GMP_DROP shape of `\SteamID\SteamID`'s converters:
 * native 64-bit `int` math instead of the GMP / BCMath / SQL calc-method
 * chooser tier. The on-wire contract every converter pair has to honour
 * stays byte-identical (Steam2 → `STEAM_0:Y:Z`, Steam3 → `[U:1:N]`,
 * Steam64 → decimal string), so this test pins each pair against a
 * shared verified-vector table and proves:
 *
 *   - The six conversions agree on the same five-vector set (covers
 *     even/odd account IDs, the zero edge, and the largest plausible
 *     Steam64 from a 32-bit unsigned account id at 2147483647).
 *   - `Steam64`-side inputs accept BOTH `int` and `string` (callers in
 *     `pages/page.banlist.php` / `pages/page.commslist.php` /
 *     `api/handlers/bans.php` pass either depending on whether the
 *     value came from a `_GET` lookup or from a typed row column).
 *   - `Steam2toSteam64` and `Steam3toSteam64` return `string` (NOT a
 *     bare `int`). The previous GMP backend returned a `\GMP` object
 *     that stringified to the digits via `__toString()`, so callers
 *     concatenating into URLs or binding into PDO via `PARAM_STR`
 *     relied on the string shape — keep it.
 *   - The `STEAM_1` → `STEAM_0` universe normalisation in `to()` still
 *     fires when the input is already in the requested format (that
 *     path doesn't go through the new conversion methods).
 *   - `isValidID` accepts every documented format, rejects garbage.
 *   - `compare` returns true across formats for equal accounts and
 *     false for non-equal accounts.
 *   - `toSearchPattern` builds a `^STEAM_[0-9]:Y:Z$` regex from any of
 *     the three input formats, returns null for non-Steam input.
 *   - The `assertSixtyFourBit()` private guard is wired up. We can't
 *     mutate `PHP_INT_SIZE` from PHP, so the assertion is structural:
 *     the method exists and is called from `to()`. Bench testing on a
 *     hypothetical 32-bit build is out of scope; the runtime guard
 *     makes the failure mode loud, this test confirms the wiring.
 *
 * Pure-PHP test — no DB / no Smarty / no Fixture::reset(), so we extend
 * `PHPUnit\Framework\TestCase` directly (the converter class is
 * stateless after the GMP drop).
 */
final class SteamIdConversionTest extends TestCase
{
    /**
     * Verified Steam ID vectors covering even/odd account IDs, the zero
     * edge, and the largest plausible 32-bit unsigned account id (the
     * theoretical Steam64 ceiling at the time of writing). Math:
     *
     *   Steam64 = 76561197960265728 + (Z * 2 + Y)
     *   Steam3.id = Z * 2 + Y
     *
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     *         [steam2, steam3, steam64]
     */
    public static function vectors(): iterable
    {
        yield 'STEAM_0:0:11101 (canonical Y=0)'  => ['STEAM_0:0:11101',     '[U:1:22202]',     '76561197960287930'];
        yield 'STEAM_0:1:11101 (canonical Y=1)'  => ['STEAM_0:1:11101',     '[U:1:22203]',     '76561197960287931'];
        yield 'STEAM_0:0:0 (zero edge Y=0)'      => ['STEAM_0:0:0',         '[U:1:0]',         '76561197960265728'];
        yield 'STEAM_0:1:0 (zero edge Y=1)'      => ['STEAM_0:1:0',         '[U:1:1]',         '76561197960265729'];
        yield 'STEAM_0:1:2147483647 (max accid)' => ['STEAM_0:1:2147483647', '[U:1:4294967295]', '76561202255233023'];
    }

    #[DataProvider('vectors')]
    public function testSteam2ToSteam3(string $steam2, string $steam3, string $_steam64): void
    {
        $this->assertSame($steam3, SteamID::toSteam3($steam2));
    }

    #[DataProvider('vectors')]
    public function testSteam2ToSteam64(string $steam2, string $_steam3, string $steam64): void
    {
        $result = SteamID::toSteam64($steam2);
        $this->assertSame($steam64, $result);
        $this->assertIsString($result, 'Steam64 must be returned as a decimal string for URL / PDO bind compatibility.');
    }

    #[DataProvider('vectors')]
    public function testSteam3ToSteam2(string $steam2, string $steam3, string $_steam64): void
    {
        $this->assertSame($steam2, SteamID::toSteam2($steam3));
    }

    #[DataProvider('vectors')]
    public function testSteam3ToSteam64(string $_steam2, string $steam3, string $steam64): void
    {
        $result = SteamID::toSteam64($steam3);
        $this->assertSame($steam64, $result);
        $this->assertIsString($result, 'Steam64 must be returned as a decimal string for URL / PDO bind compatibility.');
    }

    #[DataProvider('vectors')]
    public function testSteam64ToSteam2(string $steam2, string $_steam3, string $steam64): void
    {
        // Both shapes appear in callers — string from $_GET / row reads,
        // int from typed-column reads or numeric-context arithmetic.
        $this->assertSame($steam2, SteamID::toSteam2($steam64),         'Steam64 string input must round-trip to the canonical Steam2.');
        $this->assertSame($steam2, SteamID::toSteam2((int) $steam64),   'Steam64 int input must round-trip to the canonical Steam2.');
    }

    #[DataProvider('vectors')]
    public function testSteam64ToSteam3(string $_steam2, string $steam3, string $steam64): void
    {
        $this->assertSame($steam3, SteamID::toSteam3($steam64));
        $this->assertSame($steam3, SteamID::toSteam3((int) $steam64));
    }

    /**
     * `to()`'s `from === format` branch normalises the legacy `STEAM_1`
     * universe digit to `STEAM_0` without going through any conversion
     * method. That path predates the GMP drop and must keep working.
     */
    public function testSteam1NormalizesToSteam0(): void
    {
        $this->assertSame('STEAM_0:0:11101', SteamID::toSteam2('STEAM_1:0:11101'));
        $this->assertSame('STEAM_0:1:11101', SteamID::toSteam2('STEAM_1:1:11101'));
    }

    public function testIsValidIdAcceptsAllThreeFormats(): void
    {
        $this->assertTrue(SteamID::isValidID('STEAM_0:0:11101'));
        $this->assertTrue(SteamID::isValidID('[U:1:22202]'));
        $this->assertTrue(SteamID::isValidID('U:1:22202'));
        $this->assertTrue(SteamID::isValidID('76561197960287930'));

        $this->assertFalse(SteamID::isValidID(''));
        $this->assertFalse(SteamID::isValidID('garbage'));
        $this->assertFalse(SteamID::isValidID('STEAM_2:0:0'));
    }

    public function testCompareEqualAcrossFormats(): void
    {
        $this->assertTrue(SteamID::compare('STEAM_0:0:11101', '[U:1:22202]'),
            'Same account, different formats (Steam2 vs Steam3) must compare equal.');
        $this->assertTrue(SteamID::compare('[U:1:22202]', '76561197960287930'),
            'Same account, different formats (Steam3 vs Steam64) must compare equal.');
        $this->assertTrue(SteamID::compare('STEAM_0:0:11101', '76561197960287930'),
            'Same account, different formats (Steam2 vs Steam64) must compare equal.');
        $this->assertTrue(SteamID::compare('STEAM_1:0:11101', '76561197960287930'),
            'Universe-1 Steam2 and Steam64 of the same account must compare equal.');

        $this->assertFalse(SteamID::compare('STEAM_0:0:11101', 'STEAM_0:0:11102'),
            'Different account IDs must compare unequal.');
        $this->assertFalse(SteamID::compare('[U:1:22202]', '[U:1:22204]'),
            'Different account IDs in Steam3 form must compare unequal.');
    }

    /**
     * #1128 / #1130: `toSearchPattern` is the universe-agnostic
     * `^STEAM_[0-9]:Y:Z$` REGEXP the banlist / commslist search uses
     * so a row stored under `STEAM_1:Y:Z` matches a search for the
     * same account in `STEAM_0:Y:Z` form (the SourceMod plugin can
     * write either depending on the game).
     */
    public function testToSearchPatternProducesUniverseAgnosticRegex(): void
    {
        $expected = '^STEAM_[0-9]:0:11101$';
        $this->assertSame($expected, SteamID::toSearchPattern('STEAM_0:0:11101'));
        $this->assertSame($expected, SteamID::toSearchPattern('STEAM_1:0:11101'));
        $this->assertSame($expected, SteamID::toSearchPattern('[U:1:22202]'));
        $this->assertSame($expected, SteamID::toSearchPattern('76561197960287930'));

        $this->assertNull(SteamID::toSearchPattern('garbage'),
            'Non-Steam input must return null so callers fall back to plain LIKE.');
        $this->assertNull(SteamID::toSearchPattern(''),
            'Empty string must return null.');
    }

    /**
     * Structural check: the 32-bit guard (`assertSixtyFourBit`) must be
     * defined and called from `to()`. We can't mutate `PHP_INT_SIZE`
     * from PHP, so we can't trip the throw at runtime; we can prove the
     * wiring exists. If a future sweep re-introduces the GMP/BCMath
     * chooser, the guard's job is the loud-fail message — without it
     * we'd silently overflow on 32-bit PHP.
     */
    public function testSixtyFourBitGuardIsWiredIntoDispatchPath(): void
    {
        $reflection = new ReflectionClass(SteamID::class);

        $this->assertTrue($reflection->hasMethod('assertSixtyFourBit'),
            'SteamID must declare the assertSixtyFourBit() guard.');
        $guard = $reflection->getMethod('assertSixtyFourBit');
        $this->assertTrue($guard->isPrivate(), 'assertSixtyFourBit must be private.');
        $this->assertTrue($guard->isStatic(), 'assertSixtyFourBit must be static.');

        $toSource = file_get_contents($reflection->getFileName());
        $this->assertNotFalse($toSource);
        $this->assertStringContainsString('self::assertSixtyFourBit()', $toSource,
            'to() must call self::assertSixtyFourBit() before dispatching.');
    }
}
