<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Sbpp\Util\Duration;

/**
 * Issue #1232 regression suite for `\Sbpp\Util\Duration::humanizeMinutes()`.
 *
 * The helper backs the muted echo span next to each minute-typed input
 * on the admin Settings → Authentication fieldset. Two contracts the
 * tests pin:
 *
 *   1. **Format**: unit-aligned values render *exactly* (no `≈`); only
 *      non-aligned values get the approximation glyph + one-decimal
 *      precision. The echo must read the same for the same input on
 *      both sides (PHP for the server-rendered first paint, vanilla
 *      JS for the live-on-input update). Drift between the two surfaces
 *      would mean the echo flickers on first keystroke even when the
 *      value didn't change.
 *
 *   2. **Edge cases**: `0` and negative values are normalised to the
 *      `"disabled"` sentinel so the echo matches the legend hint
 *      ("Set a value to 0 to disable a sign-in path") and so a
 *      malformed `sb_settings` row never lands as a runtime error
 *      mid-render.
 *
 * The JS mirror lives inline at the bottom of
 * `web/themes/default/page_admin_settings_settings.tpl`. If you change
 * the format here, change it there in the same PR.
 */
final class DurationTest extends TestCase
{
    /**
     * `0` is the legend's "disable a sign-in path" sentinel; the echo
     * span has to read the same so an operator setting `auth.maxlife`
     * to `0` sees the disable confirmed live, not "0 minutes".
     */
    public function testZeroRendersAsDisabledSentinel(): void
    {
        $this->assertSame('disabled', Duration::humanizeMinutes(0));
    }

    /**
     * Defensive — a hand-edited or otherwise malformed `sb_settings`
     * row can land here as a negative integer; the helper must not
     * regress to "−5 minutes" (or worse, divide-by-zero / fatal).
     * Pin the same sentinel `0` returns so the echo reads as the
     * non-functional state the value actually represents.
     */
    public function testNegativeMinutesRenderAsDisabledSentinel(): void
    {
        $this->assertSame('disabled', Duration::humanizeMinutes(-5));
    }

    /**
     * Single-minute case — pinned for the singular "minute" (no `s`).
     * Pluralisation matters because the echo is read out by screen
     * readers (`aria-live="polite"`); "1 minutes" jars.
     */
    public function testSingleMinute(): void
    {
        $this->assertSame('1 minute', Duration::humanizeMinutes(1));
    }

    /**
     * Sub-hour values render as plain "{n} minutes" — the smallest unit
     * is the minute itself so there's no `≈` and no fractional part.
     */
    public function testFortyFiveMinutes(): void
    {
        $this->assertSame('45 minutes', Duration::humanizeMinutes(45));
    }

    /**
     * Exactly one hour — the canonical "round value collapses to the
     * larger unit, no `≈`" case. `60` is hour-aligned, so the echo
     * drops the leading `≈` and the singular "1 hour" reads naturally.
     */
    public function testExactlyOneHour(): void
    {
        $this->assertSame('1 hour', Duration::humanizeMinutes(60));
    }

    /**
     * 90 minutes is hour-aligned to a half-hour, not whole hours, so
     * it crosses the `≈` threshold. The one-decimal precision is what
     * keeps "≈ 1.5 hours" readable instead of, say, "≈ 1.50 hours" or
     * "≈ 1 hour".
     */
    public function testNinetyMinutesUsesApproxHours(): void
    {
        $this->assertSame('≈ 1.5 hours', Duration::humanizeMinutes(90));
    }

    /**
     * Plural-hours regression — two hours is hour-aligned (no `≈`)
     * and uses the plural "hours". Same rule that gives "1 hour"
     * back at 60.
     */
    public function testTwoHoursExact(): void
    {
        $this->assertSame('2 hours', Duration::humanizeMinutes(120));
    }

    /**
     * The default value of `auth.maxlife` (= 24h). Day-aligned, so the
     * echo collapses to the larger "day" unit and drops the `≈`.
     */
    public function testOneDay(): void
    {
        $this->assertSame('1 day', Duration::humanizeMinutes(1440));
    }

    /**
     * The default value of `auth.maxlife.remember` and
     * `auth.maxlife.steam` (= 7 days). Day-aligned (10080 = 7 × 1440)
     * so the echo reads as the canonical "7 days" with no `≈`.
     */
    public function testSevenDays(): void
    {
        $this->assertSame('7 days', Duration::humanizeMinutes(10080));
    }

    /**
     * Above one day, non-aligned. 1500 / 1440 ≈ 1.0417, which rounds
     * to one decimal as `1.0` and then collapses to `"1"` — the echo
     * reads "≈ 1 day", not "≈ 1.0 day". The leading `≈` carries the
     * "this is a rounded approximation" signal.
     */
    public function testJustAboveOneDayRoundsToApproxOne(): void
    {
        $this->assertSame('≈ 1 day', Duration::humanizeMinutes(1500));
    }

    /**
     * Above one day, non-aligned to a half-day. 2160 / 1440 = 1.5
     * exactly, so the echo reads "≈ 1.5 days" — `≈` because the
     * underlying value isn't day-aligned (2160 % 1440 ≠ 0), even
     * though the division produces a clean half.
     */
    public function testTwoThousandOneHundredSixtyMinutesIsApproxOneAndAHalfDays(): void
    {
        $this->assertSame('≈ 1.5 days', Duration::humanizeMinutes(2160));
    }
}
