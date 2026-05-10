/**
 * Color-scheme contract guard for #1309.
 *
 * Locks the `color-scheme` CSS property on `<html>` so the browser
 * paints native UA surfaces (the open `<select>` dropdown panel,
 * native scrollbars, `<input type="date|time|color">` pickers, and
 * form autofill highlighting) in the matching scheme.
 *
 * Pre-#1309 the chrome bound DOM-rendered tokens (`--bg-surface`,
 * `--text`, etc.) under `:root` and `html.dark` but never declared
 * `color-scheme`, so the browser kept rendering its top-layer
 * system UI in the user-agent default (light). On mobile (iOS Safari
 * especially) the native `<select>` picker full-screens, so opening
 * one over a dark page slid a stark-white sheet over everything else.
 *
 * The fix is two single-line declarations on the existing token
 * blocks in `web/themes/default/css/theme.css`:
 *   :root      → color-scheme: light;
 *   html.dark  → color-scheme: dark;
 *
 * We assert via `expect(locator).toHaveCSS('color-scheme', …)` rather
 * than a one-shot `getComputedStyle()` read because theme.js applies
 * `<html class="dark">` after the first paint (the script loads from
 * the document tail, not <head>) and the resolved color-scheme value
 * follows. `toHaveCSS` polls so we land on the settled value without
 * a hand-rolled `setTimeout` (forbidden by AGENTS.md).
 *
 * Theme pinning mirrors `a11y/dark-theme-contrast.spec.ts` /
 * `_screenshots.spec.ts`: write `localStorage['sbpp-theme']` on `/`,
 * then navigate to the target route, then wait on `<html class="dark">`
 * so theme.js's boot-time `applyTheme()` has resolved before the
 * assertion runs.
 *
 * Project filter: chromium-only. The fix is a token-block change in
 * `theme.css`; the computed value of `color-scheme` does not depend on
 * viewport, so running the same assertion on mobile-chromium would
 * double the runtime without revealing different findings (the mobile
 * symptom of the bug — full-screen picker — can't be observed via
 * `toHaveCSS` regardless of viewport, since the picker is painted
 * outside the DOM).
 */

import { expect, test } from '../../fixtures/auth.ts';

/**
 * Pin the resolved theme via localStorage, then re-navigate so
 * theme.js's IIFE-time `applyTheme(currentTheme())` lands the right
 * mode on first paint. The wait predicate honours the chrome's
 * actual signal (`<html class="dark">`).
 */
async function pinTheme(
    page: import('@playwright/test').Page,
    mode: 'light' | 'dark',
    target: string,
): Promise<void> {
    await page.goto('/');
    await page.evaluate((m: 'light' | 'dark') => {
        try {
            localStorage.setItem('sbpp-theme', m);
        } catch {
            /* localStorage unavailable; skip */
        }
    }, mode);
    await page.goto(target);
    await page.waitForFunction((expected: 'light' | 'dark') => {
        const isDark = document.documentElement.classList.contains('dark');
        return expected === 'dark' ? isDark : !isDark;
    }, mode);
}

test.describe('#1309 color-scheme contract', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'token-driven CSS; viewport does not change computed color-scheme',
        );
    });

    test('html resolves color-scheme: dark in dark mode', async ({ page }) => {
        await pinTheme(page, 'dark', '/');
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
    });

    test('html resolves color-scheme: light in light mode (regression guard)', async ({ page }) => {
        await pinTheme(page, 'light', '/');
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');
    });
});
