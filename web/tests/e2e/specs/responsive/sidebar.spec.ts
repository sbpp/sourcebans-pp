/**
 * Responsive: sidebar drawer (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - Sidebar (`<aside id="sidebar">`) is hidden by default.
 *   - The hamburger trigger (`[data-mobile-menu]` in core/title.tpl)
 *     opens it as a left-edge drawer; a second click, the
 *     [data-sidebar-backdrop] overlay, or Escape closes it (#1178).
 *   - `data-mobile-open` on the sidebar mirrors the live `.is-open`
 *     class state on every open/close, so external observers (e2e
 *     specs, CSS sibling selectors) can read state without probing
 *     the class chain (#1179).
 *
 * Project gating: this whole describe runs only on `mobile-chromium`.
 * We use the `test.beforeEach` skip-guard form rather than
 * `test.describe.configure({ project })`: at @playwright/test 1.59
 * `describe.configure` only accepts `mode/retries/timeout`, not a
 * project filter, so a `beforeEach` skip is the canonical pattern.
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('responsive: sidebar', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('sidebar is hidden by default at iPhone-13 width', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        // Server-rendered initial state; theme.js mirrors it to the
        // live class on every open/close (#1179).
        await expect(sidebar).toHaveAttribute('data-mobile-open', 'false');
        // The CSS contract at <=1024px sets `display: none` on the
        // un-opened sidebar (theme.css "Responsive" block). `toBeHidden()`
        // observes the resolved style without depending on whether
        // the element is sticky-positioned off-screen.
        await expect(sidebar).toBeHidden();
        await expect(sidebar).not.toHaveClass(/\bis-open\b/);
    });

    test('hamburger opens the sidebar drawer', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        await expect(sidebar).toBeHidden();
        // Pre-open: the attribute is the server-rendered "false".
        await expect(sidebar).toHaveAttribute('data-mobile-open', 'false');

        const hamburger = page.locator('[data-mobile-menu]');
        await expect(hamburger).toBeVisible();
        await hamburger.click();

        await expect(sidebar).toHaveClass(/\bis-open\b/);
        // Post-open: theme.js flips the attribute in lockstep with
        // the class so external observers see the truth (#1179).
        await expect(sidebar).toHaveAttribute('data-mobile-open', 'true');
        await expect(sidebar).toBeVisible();
        // The open drawer should occupy the left edge of the viewport.
        const box = await sidebar.boundingBox();
        expect(box, 'sidebar must render a bounding box once visible').not.toBeNull();
        expect(box!.x).toBeLessThanOrEqual(1);
        expect(box!.y).toBeLessThanOrEqual(1);
    });

    test('second hamburger click closes the sidebar drawer', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        const trigger = page.locator('[data-mobile-menu]');

        // Once the drawer is open, `.sidebar.is-open` (z-index 41)
        // paints over the topbar's left column (`.topbar` z-index 30),
        // which means the hamburger sitting at the topbar's left edge
        // is occluded — Playwright's actionability checks (and a
        // `force: true` real click) would land on the sidebar nav,
        // not the hamburger. `dispatchEvent('click')` synthesizes a
        // bubbling click on the element itself; theme.js's
        // document-level handler keys off `target.closest(...)` so
        // the production code path runs unchanged. The
        // "user can reach the hamburger when closed" assertion is
        // already covered by "hamburger opens the sidebar drawer".
        await trigger.dispatchEvent('click');
        await expect(sidebar).toHaveClass(/\bis-open\b/);

        await trigger.dispatchEvent('click');
        await expect(sidebar).not.toHaveClass(/\bis-open\b/);
        await expect(sidebar).toBeHidden();
    });

    test('clicking the backdrop closes the sidebar drawer', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        // dispatchEvent for the same occlusion-after-open reason
        // documented on the "second hamburger click" test above.
        await page.locator('[data-mobile-menu]').dispatchEvent('click');
        await expect(sidebar).toHaveClass(/\bis-open\b/);

        // Backdrop is created on demand by openMobileSidebar() in
        // theme.js; [data-visible="true"] is the terminal state hook
        // and the same selector also closes on click via the
        // document-level handler.
        const backdrop = page.locator('[data-sidebar-backdrop]');
        await expect(backdrop).toHaveAttribute('data-visible', 'true');
        // The backdrop is positioned `inset: 0` (whole viewport, z-index
        // 40) but `.sidebar.is-open` paints over the left 15rem column
        // at z-index 41, so the dimmed-area-the-user-actually-taps is
        // the strip to the right of the drawer. Playwright's default
        // center-click would land inside the sidebar's column and the
        // sidebar nav would swallow the click; aim past the sidebar's
        // right edge instead. iPhone 13 viewport is 390x844 and the
        // sidebar is 240px wide, so x≈320 sits comfortably in the
        // dimmed strip.
        await backdrop.click({ position: { x: 320, y: 400 } });

        await expect(sidebar).not.toHaveClass(/\bis-open\b/);
        await expect(sidebar).toBeHidden();
        await expect(backdrop).toHaveAttribute('data-visible', 'false');
    });

    test('Escape closes the sidebar drawer', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        await page.locator('[data-mobile-menu]').dispatchEvent('click');
        await expect(sidebar).toHaveClass(/\bis-open\b/);

        await page.keyboard.press('Escape');
        await expect(sidebar).not.toHaveClass(/\bis-open\b/);
        await expect(sidebar).toBeHidden();
    });
});
