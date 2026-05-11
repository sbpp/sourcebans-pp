/**
 * Dark-theme contrast guard for #1207 CC-4 / AUTH-2.
 *
 * Locks the active-pill treatment in dark mode so a future refactor
 * can't quietly slide back to the near-white-on-zinc-900 chrome the
 * audit screenshots flagged ("hovered" rather than "selected"):
 *
 *   - CC-4: Sidebar nav, banlist filter chips, and admin/bans
 *     `Current / Archive` segmented chips paint `--brand-700`
 *     (`#c2410c`) when active in dark mode. We use brand-700 rather
 *     than `--accent` (`--brand-600` = `#ea580c`) for accessibility:
 *     `#ea580c` on white is ~3.56:1, which clears WCAG AA Large Text
 *     and Non-text but FAILS AA Normal Text (4.5:1) for the 14px /
 *     12px medium-weight nav + chip labels. `#c2410c` on white is
 *     ~5.18:1 — clears AA Normal Text. See `theme.css` rule comments
 *     for the full rationale.
 *   - AUTH-2: The "Continue with Steam" button paints Steam's brand
 *     chrome (`#1b2838`) in dark mode so it doesn't read as the
 *     `--bg-surface`-on-`--bg-page` near-disabled rectangle from the
 *     audit (~1.4:1 against the page background). White on `#1b2838`
 *     ≈ 14.93:1, clears AAA.
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
 *   - `--brand-700` = `#c2410c` = `rgb(194, 65, 12)` (active dark fill)
 *   - Steam dark chrome = `#1b2838` = `rgb(27, 40, 56)`
 *   - `--zinc-900` = `#18181b` = `rgb(24, 24, 27)` (light-theme guard)
 *   - `--bg-surface` (light) = `#ffffff` = `rgb(255, 255, 255)`
 *
 * Theme pinning mirrors `_screenshots.spec.ts` / `a11y/routes.spec.ts`:
 * write `localStorage['sbpp-theme']` on `/`, then navigate to the
 * target route, then wait on `<html class="dark">` so theme.js's
 * boot-time `applyTheme()` has resolved before we start asserting.
 *
 * Project filter: chromium-only. The mobile-chromium project shares
 * the same markup and computed-style computation; the rules under
 * test are token-driven and don't change with viewport, so running
 * on both would double the runtime without revealing different
 * findings.
 */

import { expect, test } from '../../fixtures/auth.ts';
import type { Browser, BrowserContext, TestInfo } from '@playwright/test';

const BRAND_700_RGB = 'rgb(194, 65, 12)';
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

/**
 * Anonymous context for tests that exercise logged-out chrome (the
 * Steam login button is only rendered when no JWT cookie is present).
 *
 * We pass `reducedMotion: 'reduce'` explicitly because
 * `playwright.config.ts` only sets it on the top-level `use`, not on
 * any individual project — `testInfo.project.use` does not surface
 * top-level `contextOptions`. Without this, a fresh anonymous context
 * runs with motion enabled and silently drops the contract AGENTS.md
 * "Playwright E2E specifics" calls out by name. `toHaveCSS` polls so
 * the assertion survives either way, but the contract should hold.
 */
async function newAnonContext(
    browser: Browser,
    testInfo: TestInfo,
): Promise<BrowserContext> {
    return browser.newContext({
        ...testInfo.project.use,
        storageState: { cookies: [], origins: [] },
        reducedMotion: 'reduce',
    });
}

test.describe('#1207 dark-theme contrast', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'token-driven CSS; viewport does not change computed colour',
        );
    });

    test('CC-4 sidebar active link paints --brand-700 in dark mode', async ({ page }) => {
        await pinTheme(page, 'dark', '/');

        // The active marker is set by navbar.tpl on whichever
        // endpoint matches the current route. On `/` the home link
        // is the active one. The sidebar is rendered at every
        // viewport ≥1024px (chromium project default), so the
        // attribute is in the DOM before the assertion runs.
        const active = page.locator('.sidebar__link[aria-current="page"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', BRAND_700_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('CC-4 banlist filter chip "All" paints --brand-700 when active', async ({ page }) => {
        await pinTheme(page, 'dark', '/index.php?p=banlist');

        // The "All" chip is the default-selected filter in
        // page_bans.tpl: `<a class="chip" data-state-filter=""
        // data-active="true" aria-current="true">All</a>`. #1352
        // converted the chip strip from `<button>` to `<a>` so a
        // no-JS browser can navigate; the active marker switched
        // from `aria-pressed="true"` (only valid on role=button —
        // axe rejected it as `aria-allowed-attr` on `<a>`) to the
        // canonical `aria-current="true"`. Filter on
        // `data-active="true"` (the #1123 testability hook the
        // CSS rule also keys on) rather than the ARIA attribute
        // so a future role refactor doesn't break this test.
        const active = page.locator('.chip[data-active="true"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', BRAND_700_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('CC-4 admin/bans Current sub-tab paints --brand-700 in dark mode', async ({ page }) => {
        // #1275 — admin-bans is Pattern A; the protests queue lives at
        // `?section=protests` (the chip row is part of THAT section's
        // render). The bare `?p=admin&c=bans` URL defaults to `add-ban`,
        // which has no chip row.
        await pinTheme(page, 'dark', '/index.php?p=admin&c=bans&section=protests');

        // The admin/bans page renders ONE chip-row per section after
        // #1275 — the protests section's `Current/Archive` chip-row
        // is server-rendered (the active sub-view is picked via
        // `?view=<slug>` in `web/pages/admin.bans.php`). The "Current"
        // chip carries `data-testid="filter-chip-protests-current"`
        // and lands `data-active="true"` on first paint.
        const active = page.locator('[data-testid="filter-chip-protests-current"]');
        await expect(active).toBeVisible();
        await expect(active).toHaveAttribute('data-active', 'true');
        await expect(active).toHaveCSS('background-color', BRAND_700_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('AUTH-2 Steam login button paints Steam brand chrome in dark mode', async ({ browser }, testInfo) => {
        const ctx = await newAnonContext(browser, testInfo);
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

    /*
     * Light-theme regression guard.
     *
     * The three dark overrides above (`html.dark .sidebar__link[…]`,
     * `html.dark .chip[…]`, `html.dark .btn[data-testid="login-steam"]`)
     * are all scoped under `html.dark`. If a future change accidentally
     * un-scopes any of them, the orange/Steam paint would silently
     * leak into the light theme. Each light-theme assertion below
     * locks the pre-PR computed colour for the corresponding surface
     * so any single un-scoping fires here.
     *
     * One test per surface (rather than three light-theme `pinTheme`
     * calls inside one mega-test) keeps the failure attribution clean —
     * the test name tells you which override regressed.
     */
    test('CC-4 light theme sidebar active link stays zinc-900 (regression guard)', async ({ page }) => {
        await pinTheme(page, 'light', '/');

        const active = page.locator('.sidebar__link[aria-current="page"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', ZINC_900_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('CC-4 light theme active chip stays zinc-900 (regression guard)', async ({ page }) => {
        await pinTheme(page, 'light', '/index.php?p=banlist');

        // Filter on `data-active="true"` rather than the legacy
        // `aria-pressed="true"` selector — #1352 converted the
        // banlist chip strip from `<button>` to `<a>` so a no-JS
        // browser can navigate, and switched the active-marker ARIA
        // attribute to the canonical `aria-current="true"`. The
        // CSS rule keys on `data-active="true"` so the test stays
        // resilient against either ARIA shape.
        const active = page.locator('.chip[data-active="true"]').first();
        await expect(active).toBeVisible();
        await expect(active).toHaveCSS('background-color', ZINC_900_RGB);
        await expect(active).toHaveCSS('color', WHITE_RGB);
    });

    test('AUTH-2 light theme Steam button keeps secondary-surface chrome (regression guard)', async ({ browser }, testInfo) => {
        // In light mode `.btn--secondary` resolves
        // `--btn-bg = var(--bg-surface) = #ffffff`. The dark override
        // is the only place `#1b2838` should ever appear; locking the
        // light surface as `rgb(255, 255, 255)` catches an un-scoping
        // of the `html.dark .btn[data-testid="login-steam"]` rule.
        const ctx = await newAnonContext(browser, testInfo);
        try {
            const anon = await ctx.newPage();
            await pinTheme(anon, 'light', '/index.php?p=login');

            const steam = anon.locator('[data-testid="login-steam"]');
            await expect(steam).toBeVisible();
            await expect(steam).toHaveCSS('background-color', WHITE_RGB);
        } finally {
            await ctx.close();
        }
    });
});
