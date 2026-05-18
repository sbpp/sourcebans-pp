<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
 *************************************************************************/

declare(strict_types=1);

namespace Sbpp\Util;

/**
 * Helpers for presenting durations stored in minutes.
 *
 * Issue #1232: the admin Settings → Authentication fieldset stores token
 * lifetimes (`auth.maxlife`, `auth.maxlife.remember`, `auth.maxlife.steam`)
 * as raw minute counts because that's also the on-the-wire shape the
 * SourceMod plugin reads. The defaults shipped in `data.sql` are `1440` /
 * `10080` / `10080` — operator-readable only after a `/ 60 / 24` in their
 * head. This helper converts those minute counts into a short echo
 * (`"1 minute"` / `"≈ 12 hours"` / `"7 days"`) for the muted span next to
 * each input. The wire format is unchanged; this is presentation only.
 *
 * The format follows two rules:
 *   - Unit-aligned values render exactly: `60` → `"1 hour"`,
 *     `1440` → `"1 day"`, `10080` → `"7 days"`.
 *   - Non-aligned values render with a leading `≈` and one decimal of
 *     precision in the most useful unit: `90` → `"≈ 1.5 hours"`,
 *     `1500` → `"≈ 1 day"` (1500 / 1440 ≈ 1.04, rounded to 1 d.p.).
 *   - `0` returns the literal `"disabled"` so the echo matches the
 *     legend hint ("Set a value to 0 to disable a sign-in path").
 *   - Negative values are defensively normalised to `"disabled"` so a
 *     malformed `sb_settings` row never blows up the page handler.
 *
 * The browser-side mirror lives inline in
 * `web/themes/default/page_admin_settings_settings.tpl` (the page-tail
 * `<script>` near the bottom) and re-implements the same formula so the
 * echo updates as the operator types. If you change the format here,
 * change it there too — the unit test pins both ends.
 */
final class Duration
{
    /**
     * Number of minutes per hour. Defined as a constant so the two
     * branches below (the "exactly 60" guard and the rounding rule)
     * can't drift out of sync via a literal typo.
     */
    private const MINUTES_PER_HOUR = 60;

    /**
     * Number of minutes per day. 24 * 60.
     */
    private const MINUTES_PER_DAY = 1440;

    /**
     * Convert a minute count into a short, human-readable string for
     * display next to a minute-typed input.
     *
     * @param int $minutes Minute count from `sb_settings` (already cast
     *                     via `(int) Config::get(...)` upstream). Zero
     *                     and negative values both render as the
     *                     "disabled" sentinel.
     */
    public static function humanizeMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'disabled';
        }

        if ($minutes < self::MINUTES_PER_HOUR) {
            return $minutes === 1 ? '1 minute' : $minutes . ' minutes';
        }

        if ($minutes < self::MINUTES_PER_DAY) {
            if ($minutes % self::MINUTES_PER_HOUR === 0) {
                $hours = intdiv($minutes, self::MINUTES_PER_HOUR);
                return $hours === 1 ? '1 hour' : $hours . ' hours';
            }
            $hoursStr = self::trimZero($minutes / self::MINUTES_PER_HOUR);
            return '≈ ' . $hoursStr . ' ' . ($hoursStr === '1' ? 'hour' : 'hours');
        }

        if ($minutes % self::MINUTES_PER_DAY === 0) {
            $days = intdiv($minutes, self::MINUTES_PER_DAY);
            return $days === 1 ? '1 day' : $days . ' days';
        }
        $daysStr = self::trimZero($minutes / self::MINUTES_PER_DAY);
        return '≈ ' . $daysStr . ' ' . ($daysStr === '1' ? 'day' : 'days');
    }

    /**
     * Format a float to one decimal and strip a trailing `.0` so values
     * that round to a whole number render without a redundant decimal
     * (e.g. `1500 / 1440 ≈ 1.04` rounds to `"1"`, not `"1.0"`). Pure
     * helper — the rounding mode is `round()`'s default (half-up).
     */
    private static function trimZero(float $value): string
    {
        $formatted = number_format($value, 1, '.', '');
        return str_ends_with($formatted, '.0')
            ? substr($formatted, 0, -2)
            : $formatted;
    }
}
