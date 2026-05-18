/**
 * Responsive: topbar / palette trigger (#1207 CC-1).
 *
 * iPhone-13 viewport contract:
 *   - The topbar palette trigger (`.topbar__search` /
 *     `[data-testid="palette-trigger"]`) collapses to an icon-only
 *     button at <=768px. The label "Search players, SteamIDs…" and
 *     the keyboard hint ("Ctrl K" / "⌘K") are visually hidden but
 *     stay in the DOM so:
 *       - SR users still hear the existing aria-label,
 *       - theme.js's applyPlatformHints() can rewrite the kbd text
 *         on Mac/iOS without re-rendering,
 *       - the testability hooks (`data-palette-open`,
 *         `data-testid="palette-trigger"`) keep working unchanged.
 *   - Tapping the icon-only button opens the same `<dialog
 *     id="palette-root">` the desktop trigger does, with the same
 *     `data-palette-open="true"` mirror the gate specs lock.
 *   - Page-level: nothing in the chrome leaks past the viewport
 *     horizontally — the topbar fits the breadcrumb + icon trigger
 *     + theme toggle without any 28-px-style overflow.
 *
 * Project gating: mobile-chromium only (see sidebar.spec.ts header
 * for the rationale on `beforeEach` skip vs. `describe.configure`).
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('responsive: topbar', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('palette trigger renders icon-only at iPhone-13 width', async ({ page }) => {
        await page.goto('/');

        const trigger = page.locator('[data-testid="palette-trigger"]');
        await expect(trigger).toBeVisible();

        // The label and the keyboard hint are present in the DOM
        // (so SR users hear them via the parent aria-label) but
        // visually hidden via CSS at <=768px. `display:none` is the
        // CSS contract; `toBeHidden()` observes the resolved style
        // without depending on what selector hid them.
        const label = trigger.locator('.topbar__search-label');
        await expect(label).toBeAttached();
        await expect(label).toBeHidden();

        const kbd = trigger.locator('.topbar__search-kbd');
        await expect(kbd).toBeAttached();
        await expect(kbd).toBeHidden();

        // The icon stays the visible affordance — the parent button
        // collapses to a 2.75rem square (44px at the default font
        // size). 44px is the slice 1 review's explicit touch-target
        // floor (WCAG 2.1 AAA / Apple HIG / Material) — anything
        // smaller and the icon-only collapse becomes the smallest
        // tap target in the chrome (CC-1 review finding 1). Lock
        // both axes so a future shrink fails this spec instead of
        // silently regressing the tap target.
        const box = await trigger.boundingBox();
        expect(box, 'palette trigger must render a bounding box').not.toBeNull();
        expect(box!.width).toBeGreaterThanOrEqual(44);
        expect(box!.height).toBeGreaterThanOrEqual(44);
        // Upper bound stops the rule from accidentally re-expanding
        // back to a labelled control via cascade drift.
        expect(box!.width).toBeLessThanOrEqual(56);
    });

    test('tapping the icon-only trigger opens the command palette', async ({ page }) => {
        await page.goto('/');

        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');

        // The trigger keeps its `data-palette-open` attribute (same
        // contract as desktop), so theme.js's document-level click
        // handler funnels through openPalette() the same way.
        const trigger = page.locator('[data-testid="palette-trigger"]');
        await trigger.click();

        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        await expect(page.locator('#palette-input')).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');
    });

    test('topbar fits the iPhone-13 viewport without horizontal overflow', async ({ page }) => {
        await page.goto('/');

        // Page-level: nothing in the chrome (collapsed search +
        // breadcrumb + theme toggle) leaks past the viewport
        // horizontally. CC-1's pre-fix shape produced ~28px of scroll
        // because the search input min-width pinned the topbar
        // wider than the iPhone-13 viewport; the icon-only collapse
        // brings that back to zero.
        await expect.poll(() => page.evaluate(() =>
            document.documentElement.scrollWidth - document.documentElement.clientWidth
        )).toBeLessThanOrEqual(1);

        // The topbar itself is bounded by the viewport. Selector via
        // `data-testid="topbar"` rather than the `.topbar` class chain
        // per AGENTS.md "Anti-patterns" (#1123 testability hooks rule).
        const topbar = page.locator('[data-testid="topbar"]');
        const vw = page.viewportSize()?.width ?? 0;
        const box = await topbar.boundingBox();
        expect(box, 'topbar must render a bounding box').not.toBeNull();
        expect(box!.x).toBeGreaterThanOrEqual(-1);
        expect(box!.x + box!.width).toBeLessThanOrEqual(vw + 1);
    });
});
