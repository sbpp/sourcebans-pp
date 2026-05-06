/**
 * Dark-theme contrast guard for #1207 CC-4 / AUTH-2.
 *
 * Locks the active-pill treatment in dark mode so a future refactor
 * can't quietly slide back to the near-white-on-zinc-900 chrome the
 * audit screenshots flagged ("hovered" rather than "selected"):
 *
 *   - CC-4: Sidebar nav, banlist filter chips, and admin/bans
 *     `Current / Archive` segmented chips all paint `var(--accent)`
 *     (the same `--brand-600` the primary CTA uses) when active.
 *   - AUTH-2: The "Continue with Steam" button paints Steam's brand
 *     chrome (`#1b2838`) in dark mode so it doesn't read as the
 *     `--bg-surface`-on-`--bg-page` near-disabled rectangle from the
 *     audit (~1.4:1 against the page background).
 *
 * We assert via `expect(locator).toHaveCSS('background-color', …)`
 * rather than a one-shot `getComputedStyle()` read because theme.js
 * applies `<html class="dark">` after the first paint (the script
 * loads from the document tail, not <head>) and the resulting
 * background-color transition runs for ~150ms. `toHaveCSS` polls so
 * we land on the settled value without a hand-rolled `setTimeout`
 * (forbidden by AGENTS.md). The CSS rules under test are scoped to
 * the existing #1123 testability hooks (`[aria-current="page"]`,
 * `[data-active="true"]`, `[aria-pressed="true"]`, the Steam button's
 * `data-testid`) so this assertion survives any refactor that keeps
 * the DOM contract intact.
 *
 * Resolved colour values:
 *   - `--accent` = `--brand-600` = `#ea580c` = `rgb(234, 88, 12)`
 *   - Steam dark chrome = `#1b2838` = `rgb(27, 40, 56)`
 *   - `--zinc-900` = `#18181b` = `rgb(24, 24, 27)` (light-theme guard)
 *
 * Theme pinning mirrors `_screenshots.spec.ts` / `a11y/routes.spec.ts`:
 * write `localStorage['sbpp-theme']` on `/`, then navigate to the
 * target route, then wait on `<html class="dark">` so theme.js's
 * boot-time `applyTheme()` has resolved before we start asserting.
 *
 * Project filter: chromium-only. The mobile-chromium project shares
 * the same markup and computed-style computation; the rules under
 * test are token-driven (`var(--accent)`) and don't change with
 * viewport, so running on both would double the runtime without
 * revealing different findings.
 */

import { expect, test } from '../../fixtures/auth.ts';

const ACCENT_RGB = 'rgb(234, 88, 12)';
const STEAM_DARK_RGB = 'rgb(27, 40, 56)';
const WHITE_RGB = 'rgb(255, 255, 255)';
const ZINC_900_RGB = 'rgb(24, 24, 27)';

/**
 * Pin the resolved theme via localStorage, then re-navigate so
 * theme.js's IIFE-time `applyTheme(currentTheme())` lands the right
 * mode on first paint. The wait predicate honours the chrome's
 * actual signal (`<html class="dark">`) — see `_screenshots.spec.ts`
 * for the rationale on the class vs `[data-theme]`.
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

test.describe('#1207 dark-theme contrast', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'token-driven CSS; viewport does not change computed colour',
        );
    });

    test('CC-4 sidebar active link paints --accent', async ({ page }) => {
        await pinTheme(page, 'dark', '/');

        // The active marker is set by navbar.tpl on whichever
        // endpoint matches the current route. On `/` the home link
        // is the active one. The sidebar is rendered at every
        // viewport ≥1024px (chromium project default), so the
        // attribute is in the DOM before the assertion runs.
        const active = page.locator('.sidebar__link[aria-current="page"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', ACCENT_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('CC-4 banlist filter chip "All" paints --accent when pressed', async ({ page }) => {
        await pinTheme(page, 'dark', '/index.php?p=banlist');

        // The "All" chip is the default-selected filter in
        // page_bans.tpl: `<button class="chip" type="button"
        // data-state-filter="" aria-pressed="true">All</button>`.
        // banlist.js may toggle the pressed state based on URL
        // params, so we filter on `aria-pressed="true"` rather than
        // assuming the first chip is the active one.
        const active = page.locator('.chip[aria-pressed="true"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', ACCENT_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('CC-4 admin/bans Current sub-tab paints --accent', async ({ page }) => {
        await pinTheme(page, 'dark', '/index.php?p=admin&c=bans');

        // The admin/bans page emits two `chip-row` segmented groups
        // (`Ban protests` Current/Archive, `Ban submissions`
        // Current/Archive) — see `web/pages/admin.bans.php`. The
        // protests row's "Current" chip has the data-testid
        // `filter-chip-protests-current` and lands `data-active="true"`
        // on first paint. The submissions row mirrors it; we assert
        // on protests-current as the canonical example since both
        // share the same CSS rule.
        const active = page.locator('[data-testid="filter-chip-protests-current"]');
        await expect(active).toBeVisible();
        await expect(active).toHaveAttribute('data-active', 'true');
        await expect(active).toHaveCSS('background-color', ACCENT_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('AUTH-2 Steam login button paints Steam brand chrome in dark mode', async ({ browser }, testInfo) => {
        // Steam button is only rendered for logged-out visitors
        // (page.login.php -> page_login.tpl). Spin up an anonymous
        // context so the button's surroundings match what a real
        // unauth visitor would see.
        const ctx = await browser.newContext({
            ...testInfo.project.use,
            storageState: { cookies: [], origins: [] },
        });
        try {
            const anon = await ctx.newPage();
            await pinTheme(anon, 'dark', '/index.php?p=login');

            const steam = anon.locator('[data-testid="login-steam"]');
            await expect(steam).toBeVisible();
            await expect(steam).toHaveCSS('background-color', STEAM_DARK_RGB);
            // The lucide gamepad icon inherits via `currentColor`,
            // so the button's `color` resolving to white is what
            // turns the SVG strokes white. Assert on the button's
            // computed colour directly — that's the upstream cause.
            await expect(steam).toHaveCSS('color', WHITE_RGB);
        } finally {
            await ctx.close();
        }
    });

    test('CC-4 light theme sidebar active link is unchanged (regression guard)', async ({ page }) => {
        // Light-theme treatment: zinc-900 pill / white text. The
        // dark-theme override above is scoped under `html.dark`, so
        // the light branch must continue to resolve to `--zinc-900`
        // even after the slice ships. This protects against an
        // accidental light-theme regression if a future change
        // unscopes the rule.
        await pinTheme(page, 'light', '/');

        const active = page.locator('.sidebar__link[aria-current="page"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', ZINC_900_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });
});
