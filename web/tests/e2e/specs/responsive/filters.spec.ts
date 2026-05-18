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
 *   - Each click navigates to `?state=<name>` server-side. The active
 *     chip carries `aria-current="true"` + `data-active="true"` after
 *     the page renders the new URL. NOTE: post-#1358 the chips are
 *     `<a>` anchors (real navigation, server-side filter), not
 *     `<button>`s; `aria-current` (NOT `aria-pressed`) is the
 *     canonical ARIA attribute for "active link" on anchor elements
 *     — axe flags `aria-pressed` on anchors as `aria-allowed-attr`.
 *     The "Hide inactive" button on the same surface still uses
 *     `aria-pressed` because it remains a `<button role="button">`;
 *     the chip strip's anchor shape is the divergence.
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
            await expect(chip).toHaveAttribute('aria-current', 'false');

            // After #1181 the row wraps onto multiple lines at
            // <=768px, so every chip renders within the viewport.
            // `.click()` works directly — no horizontal auto-scroll
            // and no `force: true` needed. Post-#1358 the click is
            // a real navigation (anchor href), so `waitForURL`
            // synchronises with the server-rendered next paint.
            await chip.click();
            await page.waitForURL(new RegExp(`[?&]state=${id}(?:&|$)`));

            // The newly-active chip locator has to be re-resolved
            // after the navigation since the previous DOM was torn
            // down — otherwise we'd be asserting against a stale
            // element handle that detached on page change.
            const active = page.locator(`[data-testid="filter-chip-${id}"]`);
            await expect(active).toHaveAttribute('aria-current', 'true');

            // Only one chip is "active" at a time — the page handler
            // sets `$active_state` from `$_GET['state']` and the
            // template renders `aria-current` per-chip from it
            // (page_bans.tpl chip strip). Lock that invariant for
            // the mobile bar too.
            for (const other of CHIPS) {
                if (other === id) continue;
                await expect(
                    page.locator(`[data-testid="filter-chip-${other}"]`),
                ).toHaveAttribute('aria-current', 'false');
            }
        }
    });
});
