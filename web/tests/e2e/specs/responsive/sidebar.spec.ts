/**
 * Responsive: sidebar drawer (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - Sidebar (`<aside id="sidebar">`) is hidden by default.
 *   - The hamburger trigger (`[data-mobile-menu]` in core/title.tpl)
 *     opens it as a left-edge drawer.
 *
 * Project gating: this whole describe runs only on `mobile-chromium`.
 * We use the `test.beforeEach` skip-guard form rather than
 * `test.describe.configure({ project })`: at @playwright/test 1.59
 * `describe.configure` only accepts `mode/retries/timeout`, not a
 * project filter, so a `beforeEach` skip is the canonical pattern.
 *
 * == Divergences from the #1123 testability-hooks contract ==
 *
 *   1. (Tracked as #1177.) The hamburger button in core/title.tpl
 *      carries an inline `style="display:none"` and theme.css ships
 *      no media-query override to surface it on mobile. The click
 *      handler in theme.js (lines 49–55) IS wired correctly, but
 *      Playwright's actionability checks (and `click({ force: true })`'s
 *      "scroll into view" pre-step) refuse to fire a click against
 *      a `display:none` element regardless. We `dispatchEvent('click')`
 *      directly so the document-level listener still receives the
 *      bubbling click event and toggles `is-open` — the production
 *      JS path. A flesh-and-blood mobile user can't see the button
 *      until the theme regression is fixed in a follow-up; this
 *      spec asserts the JS contract rather than gate the slice on
 *      a CSS bug.
 *
 *   2. (Tracked as #1178.) theme.js only ADDS `is-open` (line 53).
 *      It does not toggle, and there is no close trigger inside the
 *      open sidebar (no X button, no backdrop). The brief calls for
 *      a "click again to close" assertion; with no implementation to
 *      test against, we omit that subtest and document the gap here
 *      so future maintainers see why it isn't covered. Once the
 *      sidebar grows a real close affordance, replace the omission
 *      with a real assertion.
 *
 *   3. (Tracked as #1179.) The `data-mobile-open` attribute on
 *      `<aside id="sidebar">` is rendered as `"false"` from the
 *      server (navbar.tpl line 20) but theme.js's open path doesn't
 *      update it — it flips the `.is-open` class instead. We assert
 *      against the class (the real signal) and treat the static
 *      attribute as a marker.
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
        // Server-rendered marker; static at this point in the lifecycle.
        await expect(sidebar).toHaveAttribute('data-mobile-open', 'false');
        // The CSS contract at <=1024px sets `display: none` on the
        // un-opened sidebar (theme.css line 261-264). `toBeHidden()`
        // observes the resolved style without depending on whether
        // the element is sticky-positioned off-screen.
        await expect(sidebar).toBeHidden();
        await expect(sidebar).not.toHaveClass(/\bis-open\b/);
    });

    test('hamburger opens the sidebar drawer', async ({ page }) => {
        await page.goto('/');

        const sidebar = page.locator('#sidebar');
        await expect(sidebar).toBeHidden();

        // The hamburger is `display:none` (divergence #1). Both
        // `click()` and `click({ force: true })` refuse a hidden
        // element. theme.js's handler attaches at `document` and
        // uses `closest('[data-mobile-menu]')`, so a synthetic
        // click event dispatched at the button still bubbles to
        // the listener and runs the production code path.
        await page.locator('[data-mobile-menu]').dispatchEvent('click');

        await expect(sidebar).toHaveClass(/\bis-open\b/);
        await expect(sidebar).toBeVisible();
        // The open drawer should occupy the left edge of the viewport.
        const box = await sidebar.boundingBox();
        expect(box, 'sidebar must render a bounding box once visible').not.toBeNull();
        expect(box!.x).toBeLessThanOrEqual(1);
        expect(box!.y).toBeLessThanOrEqual(1);
    });

    // NOTE: a "second click closes" assertion belongs here per the
    // brief, but theme.js currently lacks any close affordance for
    // the mobile sidebar (divergence #2 / #1178). Document the gap;
    // do not fake-pass by manually mutating classes from the spec.
});
