<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebPermission;

/**
 * Regression suite for `\WebPermission` (issue #1290 phase D.4) — the
 * backed integer enum mirroring `web/configs/permissions/web.json` (and
 * `init.php`'s `define`d `ADMIN_*` constants).
 *
 * Two contracts are pinned:
 *
 *   1. **`web.json` ↔ enum bidirectional pin**: every flag in
 *      `web/configs/permissions/web.json` (except the `ALL_WEB`
 *      meta-bucket) has a matching `WebPermission` case at the same
 *      integer value, and vice versa. A future PR that renames /
 *      renumbers a flag (or adds an enum case without a JSON entry,
 *      or adds a JSON entry without an enum case) fails this gate at
 *      PR review time so the call sites that bind `$enum->value` to
 *      `extraflags` / `flags` columns can't silently desync.
 *
 *   2. **`fromMask` decoding**: round-trips a known mask, drops bits
 *      that don't correspond to a case (so future "reserved" bits in
 *      the integer column don't break the introspection helper),
 *      handles the 2^31 high bit on 64-bit PHP without overflow, and
 *      returns `[]` on a zero mask (so the helper is safe to call on
 *      `extraflags = 0` accounts without a special case).
 *
 * The class lives in the global namespace (`web/includes/WebPermission.php`)
 * — same as the other phase D enums (`LogType`, `BanType`, …) — so the
 * `WebPermission` import above pulls from `\WebPermission`, not
 * `\Sbpp\WebPermission`.
 */
final class WebPermissionTest extends TestCase
{
    /**
     * Bidirectional pin: every `ADMIN_*` flag in `web.json` matches a
     * `WebPermission` case at the same integer value, and every case
     * is in `web.json`. The `ALL_WEB` meta-bucket is the exemption —
     * it's a rolled-up bitmask, not a flag bit, so the enum
     * deliberately doesn't carry it (mirroring the
     * `Sbpp\View\PermissionCatalog` contract).
     */
    public function testWebPermissionEnumMatchesWebJson(): void
    {
        $jsonPath = ROOT . 'configs/permissions/web.json';
        $json     = json_decode((string) file_get_contents($jsonPath), true);
        $this->assertIsArray($json, 'web.json must decode as an associative array');

        $enumByValue = [];
        foreach (WebPermission::cases() as $case) {
            $enumByValue[$case->value] = $case->name;
        }

        // Every web.json flag (except ALL_WEB) has a matching enum case
        // at the same integer value.
        foreach ($json as $name => $row) {
            if ($name === 'ALL_WEB') {
                continue;
            }
            $this->assertIsArray($row, "web.json row $name must be an object");
            $this->assertArrayHasKey('value', $row, "web.json row $name must carry a 'value' key");
            $this->assertArrayHasKey(
                $row['value'],
                $enumByValue,
                "ADMIN constant {$name} (value {$row['value']}) has no matching WebPermission case",
            );
        }

        // Every enum case is in web.json (no orphan cases that would
        // bind to nothing on disk).
        $jsonValues = [];
        foreach ($json as $name => $row) {
            if ($name === 'ALL_WEB') {
                continue;
            }
            $jsonValues[] = $row['value'];
        }
        foreach ($enumByValue as $value => $caseName) {
            $this->assertContains(
                $value,
                $jsonValues,
                "WebPermission::{$caseName} (value {$value}) doesn't exist in web.json",
            );
        }
    }

    /**
     * Round-trip: `fromMask(mask(...$cases))` returns the same set of
     * cases (order-insensitive — the helper iterates in
     * declaration order, but callers shouldn't rely on it). This is
     * the load-bearing contract for "decode an account's
     * `extraflags` column into a list of granted permissions".
     */
    public function testFromMaskRoundTripsKnownBits(): void
    {
        $cases = [
            WebPermission::Owner,
            WebPermission::AddBan,
        ];
        $mask    = WebPermission::mask(...$cases);
        $decoded = WebPermission::fromMask($mask);

        $this->assertEqualsCanonicalizing(
            array_map(fn (WebPermission $c): string => $c->name, $cases),
            array_map(fn (WebPermission $c): string => $c->name, $decoded),
        );
    }

    /**
     * Zero mask returns `[]` — the safe default for accounts /
     * groups with no permissions yet, and the contract any caller
     * can rely on without a separate `if ($mask === 0)` guard.
     */
    public function testFromMaskZeroReturnsEmpty(): void
    {
        $this->assertSame([], WebPermission::fromMask(0));
    }

    /**
     * Bits that don't correspond to a case are silently dropped.
     * `:prefix_admins.extraflags` is `int(10) UNSIGNED` and historically
     * carried bits at positions the enum doesn't enumerate (the gap
     * at value 512 between `AddBan` (256) and `EditOwnBans` (1024)
     * is the canonical example — the slot was reserved but never
     * shipped). The decoder must tolerate them so a future "reserved"
     * bit in production data doesn't blow up the introspection
     * helper. Here `513 = 1 (ListAdmins) | 512 (no case)` decodes to
     * just `[ListAdmins]`.
     */
    public function testFromMaskUnknownBitsAreSilentlyDropped(): void
    {
        $decoded = WebPermission::fromMask(513);
        $this->assertCount(1, $decoded);
        $this->assertSame(WebPermission::ListAdmins, $decoded[0]);
    }

    /**
     * `WebPermission::UnbanGroupBans = 2147483648` is `2^31`, which
     * overflows a signed 32-bit int — on a 32-bit PHP build the enum
     * value would be `float`, the bind would clamp, and `fromMask`
     * would mis-decode. PHP 8.5 is 64-bit-only on every supported
     * platform (see `web/composer.json`), so the bit lands as a
     * proper int. This test pins that round-trip so a 32-bit
     * regression (or a future "reduce the bit width to fit a
     * `mediumint`" schema change) fails loudly.
     */
    public function testFromMaskHighBitRoundTripsOn64Bit(): void
    {
        $this->assertSame(
            [WebPermission::UnbanGroupBans],
            WebPermission::fromMask(2147483648),
        );
    }
}
