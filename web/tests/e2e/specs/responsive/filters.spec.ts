/**
 * Responsive: filter bar usability (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - Every filter chip on the public ban list (`page_bans.tpl`) is
 *     clickable and updates the URL state. At mobile widths the chip
 *     row wraps via `flex-wrap` (#1181, theme.css `<=768px`), so
 *     every chip renders inside the viewport without horizontal
 *     scroll; banlist.spec.ts covers the wrap geometry, this file
 *     focuses on interaction.
 *   - Each click pins `aria-pressed="true"` on the chosen chip and
 *     stamps `?state=<name>` onto the URL (banlist.js
 *     `applyStateFilter`).
 *
 * Project gating: mobile-chromium only.
 *
 * == Out-of-scope ==
 *
 *   This spec deliberately does NOT assert that the filter
 *   actually filters the row data — that's a behavioural test that
 *   belongs to a smoke / flow slice. Here we only assert the chip
 *   surface remains interactive on mobile.
 */

import { expect, test } from '../../fixtures/auth.ts';

const CHIPS = ['permanent', 'active', 'expired', 'unbanned'] as const;

test.describe('responsive: filter bar', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('every filter chip is clickable and updates the URL state', async ({ page }) => {
        await page.goto('/index.php?p=banlist');

        for (const id of CHIPS) {
            const chip = page.locator(`[data-testid="filter-chip-${id}"]`);
            await expect(chip).toHaveAttribute('aria-pressed', 'false');

            // After #1181 the row wraps onto multiple lines at
            // <=768px, so every chip renders within the viewport.
            // `.click()` works directly — no horizontal auto-scroll
            // and no `force: true` needed.
            await chip.click();

            await expect(chip).toHaveAttribute('aria-pressed', 'true');
            await expect(page).toHaveURL(new RegExp(`[?&]state=${id}(?:&|$)`));

            // Only one chip is "pressed" at a time — applyStateFilter
            // (banlist.js L45) loops every chip on each click. This
            // spec also locks that invariant for the mobile bar.
            for (const other of CHIPS) {
                if (other === id) continue;
                await expect(
                    page.locator(`[data-testid="filter-chip-${other}"]`),
                ).toHaveAttribute('aria-pressed', 'false');
            }
        }
    });
});
