/**
 * Per-PR screenshot gallery (#1124).
 *
 * Tagged `@screenshot` and skipped by default. Two ways to opt in:
 *
 *   1. `SCREENSHOTS=1 npx playwright test` — recommended; matches the
 *      shape `web/tests/e2e/scripts/upload-screenshots.sh` invokes.
 *   2. `npx playwright test --grep @screenshot` — Playwright's tag
 *      filter; works without the env var. The `test.skip(...)` guard
 *      below means option (1) is the canonical path; option (2)
 *      stays for ad-hoc local debugging.
 *
 * For every (route × theme × project), this spec emits a full-page
 * PNG into `web/tests/e2e/screenshots/<theme>/<viewport>/<route>.png`.
 * `<viewport>` is derived from the project name (`chromium` ->
 * `desktop`, `mobile-chromium` -> `mobile`). The upload script then
 * pushes the tree to the `screenshots-archive` orphan branch under a
 * unique per-PR/per-slice subdirectory and prints the markdown table
 * pointing at raw.githubusercontent.com.
 *
 * == APPENDABLE ==
 * Slices 1–8 extend this gallery by appending entries to the `ROUTES`
 * array below — one row per new route covered. Don't fork this spec
 * into per-route files; the merge surface is intentionally one array
 * literal so subsequent PRs almost never conflict.
 *
 * The two divergences below from the issue's literal text are noted
 * in the PR body; both reflect the actual #1123 chrome contract:
 *
 *   - The localStorage key is `'sbpp-theme'` (set in
 *     `web/themes/default/js/theme.js`), not the bare `'theme'`.
 *   - Resolved theme state lands on `<html>` as the `dark` CSS class
 *     (`document.documentElement.classList.toggle('dark', dark)`),
 *     not as a `data-theme="…"` attribute. Specs wait on
 *     `documentElement.classList.contains('dark')` accordingly.
 *
 * If a future slice prefers `[data-theme]`, mirror it inside theme.js
 * in the same PR — don't fork the chrome contract here.
 */

import type { Browser, Page, TestInfo } from '@playwright/test';

import { test, expect } from '../fixtures/auth.ts';
import {
    adminApprove,
    anonymousSubmit,
    newAnonymousContext,
    type SubmissionFixture,
} from '../pages/SubmitBanFlow.ts';
import { seedBanViaApi } from '../fixtures/seeds.ts';
import { truncateE2eDb } from '../fixtures/db.ts';
import { mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

interface RouteSpec {
    name: string;
    path: string;
    auth: boolean;
}

const ROUTES: RouteSpec[] = [
    { name: 'login', path: '/index.php?p=login', auth: false },
];

const THEMES = ['light', 'dark'] as const;
type Theme = (typeof THEMES)[number];

/**
 * Project name -> screenshot directory bucket. Keeps the markdown
 * table in upload-screenshots.sh stable regardless of how many
 * mobile/desktop variants we add later.
 */
function viewportFor(projectName: string): string {
    if (projectName === 'mobile-chromium') return 'mobile';
    return 'desktop';
}

/**
 * Capture a single (route × theme × project) screenshot.
 *
 * The route gallery's per-test body lives here so future slices
 * can append a new block (e.g. `ROUTES_SMOKE_PUBLIC` below) and
 * call this helper, instead of forking the loop and drifting on
 * the chrome contract. Theme pinning, anonymous-context handling,
 * and the `<html class="dark">` wait predicate stay in one place.
 *
 * Exported as a module-scope function rather than re-exported via
 * a fixture so the surface stays small (no `test.extend<…>(…)`
 * gymnastics for a helper that's purely DOM-side).
 *
 * @param route   Route descriptor — name slug, target path, auth flag.
 * @param theme   `'light'` or `'dark'`; pinned via localStorage.
 * @param page    Default project Page (logged-in admin).
 * @param browser Browser used to mint a logged-out context if `route.auth` is false.
 * @param testInfo Playwright TestInfo — used for project name + project use options.
 */
async function captureRoute(
    route: RouteSpec,
    theme: Theme,
    page: Page,
    browser: Browser,
    testInfo: TestInfo,
): Promise<void> {
    const viewport = viewportFor(testInfo.project.name);
    const outPath = resolve(
        __dirname,
        '..',
        'screenshots',
        theme,
        viewport,
        `${route.name}.png`,
    );
    await mkdir(dirname(outPath), { recursive: true });

    let activePage = page;
    let ownContext: Awaited<ReturnType<typeof browser.newContext>> | null = null;
    try {
        if (!route.auth) {
            // Spin up a logged-out context so the chrome matches
            // what an anonymous visitor would see.
            ownContext = await browser.newContext({
                ...testInfo.project.use,
                storageState: { cookies: [], origins: [] },
            });
            activePage = await ownContext.newPage();
        }

        // Pin theme via localStorage BEFORE navigation: theme.js
        // reads `localStorage['sbpp-theme']` on boot via
        // applyTheme(currentTheme()), so a hit before that runs
        // lands the right mode on first paint. We `goto('/')`
        // first because origin localStorage requires a
        // same-origin document.
        await activePage.goto('/');
        await activePage.evaluate((mode: Theme) => {
            try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable; skip */ }
        }, theme);

        await activePage.goto(route.path);

        // Wait until theme.js's applyTheme has run and the
        // resolved mode is reflected on <html>. We don't wait on
        // a `[data-theme]` attribute (the chrome uses the `dark`
        // class, not a data-attribute); see the file-level
        // comment for the rationale.
        await activePage.waitForFunction((expected: Theme) => {
            const isDark = document.documentElement.classList.contains('dark');
            return expected === 'dark' ? isDark : !isDark;
        }, theme);

        await activePage.screenshot({ fullPage: true, path: outPath });
        expect(outPath).toMatch(/\.png$/);
    } finally {
        if (ownContext) await ownContext.close();
    }
}

test.describe('@screenshot gallery', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    for (const route of ROUTES) {
        for (const theme of THEMES) {
            test(`${route.name} ${theme}`, async ({ page, browser }, testInfo) => {
                await captureRoute(route, theme, page, browser, testInfo);
            });
        }
    }
});

// ---- Slice 1: smoke-public ----
//
// Public route gallery for #1124 Slice 1. Each entry maps a slug
// (used as the screenshot filename and the markdown row label) to
// the canonical `?p=` query string. `auth: true` because every
// public route renders fine for the seeded admin (and storage
// state is the default), so we don't pay the per-test
// browser-context spin-up that the logged-out login capture pays.
const ROUTES_SMOKE_PUBLIC: RouteSpec[] = [
    { name: 'home',      path: '/',                       auth: true },
    { name: 'banlist',   path: '/index.php?p=banlist',    auth: true },
    { name: 'commslist', path: '/index.php?p=commslist',  auth: true },
    { name: 'servers',   path: '/index.php?p=servers',    auth: true },
    { name: 'submit',    path: '/index.php?p=submit',     auth: true },
    { name: 'protest',   path: '/index.php?p=protest',    auth: true },
];

test.describe('@screenshot smoke-public', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    for (const route of ROUTES_SMOKE_PUBLIC) {
        for (const theme of THEMES) {
            test(`${route.name} ${theme}`, async ({ page, browser }, testInfo) => {
                await captureRoute(route, theme, page, browser, testInfo);
            });
        }
    }
});

// ---- Slice 2: smoke-admin (#1124) ----
//
// One row per admin route covered by `specs/smoke/admin/*.spec.ts`.
// The `auth: true` flag means we reuse the project-default storage
// state (admin/admin minted in fixtures/global-setup.ts) instead of
// spinning up a logged-out context. The describe block below mirrors
// the `@screenshot gallery` shape exactly, just looped over this
// slice's route list — kept inline rather than lifted into a shared
// helper because Slice 0 left the gallery as a flat literal and the
// extraction is a separate concern best done once a third slice has
// to repeat the same body.
const ROUTES_SMOKE_ADMIN: RouteSpec[] = [
    { name: 'admin-home',     path: '/index.php?p=admin',             auth: true },
    { name: 'admin-bans',     path: '/index.php?p=admin&c=bans',      auth: true },
    { name: 'admin-admins',   path: '/index.php?p=admin&c=admins',    auth: true },
    { name: 'admin-groups',   path: '/index.php?p=admin&c=groups',    auth: true },
    { name: 'admin-settings', path: '/index.php?p=admin&c=settings',  auth: true },
    { name: 'admin-audit',    path: '/index.php?p=admin&c=audit',     auth: true },
    { name: 'myaccount',      path: '/index.php?p=account',           auth: true },
];

test.describe('@screenshot smoke-admin', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    for (const route of ROUTES_SMOKE_ADMIN) {
        for (const theme of THEMES) {
            test(`${route.name} ${theme}`, async ({ page, browser }, testInfo) => {
                const viewport = viewportFor(testInfo.project.name);
                const outPath = resolve(
                    __dirname,
                    '..',
                    'screenshots',
                    theme,
                    viewport,
                    `${route.name}.png`,
                );
                await mkdir(dirname(outPath), { recursive: true });

                let activePage = page;
                let ownContext: Awaited<ReturnType<typeof browser.newContext>> | null = null;
                try {
                    if (!route.auth) {
                        ownContext = await browser.newContext({
                            ...testInfo.project.use,
                            storageState: { cookies: [], origins: [] },
                        });
                        activePage = await ownContext.newPage();
                    }

                    await activePage.goto('/');
                    await activePage.evaluate((mode: Theme) => {
                        try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable; skip */ }
                    }, theme);

                    await activePage.goto(route.path);

                    await activePage.waitForFunction((expected: Theme) => {
                        const isDark = document.documentElement.classList.contains('dark');
                        return expected === 'dark' ? isDark : !isDark;
                    }, theme);

                    await activePage.screenshot({ fullPage: true, path: outPath });
                    expect(outPath).toMatch(/\.png$/);
                } finally {
                    if (ownContext) await ownContext.close();
                }
            });
        }
    }
});

/**
 * Stateful captures specific to Slice 3 (flow-public-submission).
 *
 * The route-driven gallery above can't capture the moderation queue
 * with a row visible (it'd render the empty state) or the public
 * banlist with the freshly-approved ban (it'd render "no bans match
 * those filters"). This block walks the actual flow up to each
 * capture point, reusing the helpers in `pages/SubmitBanFlow.ts` so
 * the screenshot path and the gate spec drive exactly the same UI.
 *
 * Captures (one PNG per theme × viewport for each):
 *
 *   - `flow-public-submit-form`         — public `/submit` form, logged
 *                                          out. No DB state required.
 *   - `flow-public-submit-admin-queue`  — admin moderation queue with
 *                                          the just-submitted row.
 *   - `flow-public-submit-banlist`      — public ban list with the
 *                                          freshly-approved ban.
 *
 * Why the inline-flow shape (vs the issue's "small PHP shim that
 * inserts the same submission" alternative): we already have
 * page-object helpers from Slice 3's gate spec, and reusing them
 * keeps the shotline data identical to what a real admin would see
 * after going through the form. A shim would need its own truncate,
 * its own DB-side fixture insert, and its own approve path — three
 * surfaces that drift over time. The flow approach has zero
 * additional surfaces; the runtime cost (a few extra page hits per
 * variant) is acceptable when SCREENSHOTS=1 is the only invocation
 * that exercises this block.
 *
 * Cross-project parallelism: chromium + mobile-chromium share a single
 * `sourcebans_e2e` DB, so naively running the same fixture in both
 * would race on `(SteamId)` uniqueness inside `:prefix_submissions`
 * and trip BansAdd's `already_banned` guard on the second admin
 * approve. We give every (state × theme × viewport) variant a unique
 * SteamID seed, so concurrent runs never collide. We also do NOT
 * `truncateE2eDb()` between captures — that would race the gate spec
 * if both ran in the same invocation (which is the supported case
 * when CI runs `npx playwright test` once with SCREENSHOTS=1 set in
 * a different job, or when a developer runs the full suite locally).
 */
test.describe('@screenshot flow-public-submission', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    /**
     * Pin the theme key in localStorage on the panel origin so the
     * NEXT navigation boots theme.js with the right value (theme.js
     * reads `localStorage['sbpp-theme']` once at IIFE-time via
     * `applyTheme(currentTheme())`; setting localStorage on the page
     * we're already on is too late, the class is locked at the
     * pre-set value until a fresh document loads). Pair with
     * `expectTheme()` after the screenshot navigation if you need a
     * deterministic post-navigation wait — the form/queue/banlist
     * screenshot anchors below already gate on a visible primary
     * element, which is enough to guarantee theme.js has resolved.
     */
    async function pinTheme(page: import('@playwright/test').Page, theme: Theme): Promise<void> {
        await page.goto('/');
        await page.evaluate((mode: Theme) => {
            try {
                localStorage.setItem('sbpp-theme', mode);
            } catch {
                /* localStorage unavailable; skip */
            }
        }, theme);
    }

    /**
     * Wait until <html> reflects the resolved theme. Call AFTER the
     * page navigates to the screenshot target, never on the
     * `pinTheme` page (where the class is already locked at the
     * pre-pin value).
     */
    async function expectTheme(page: import('@playwright/test').Page, theme: Theme): Promise<void> {
        await page.waitForFunction((expected: Theme) => {
            const isDark = document.documentElement.classList.contains('dark');
            return expected === 'dark' ? isDark : !isDark;
        }, theme);
    }

    function snapshotPath(theme: Theme, viewport: string, name: string): string {
        return resolve(__dirname, '..', 'screenshots', theme, viewport, `${name}.png`);
    }

    /**
     * Build a fixture whose SteamID, name, and email are unique per
     * (state × theme × viewport). The third octet of the steamid
     * encodes the state (queue vs banlist), the fourth is the
     * theme/viewport tuple. Without this, two parallel projects'
     * `BansAdd` calls would race on `(authid)` and the second one
     * would 4xx as `already_banned`.
     */
    function fixture(
        state: 'queue' | 'banlist',
        theme: Theme,
        viewport: string,
    ): SubmissionFixture {
        // Two distinct seeds keep `queue` and `banlist` from sharing a
        // SteamID — the banlist capture inserts a ban with the
        // submission's SteamID, and the next queue capture would
        // otherwise trip `already_banned` on its own approve step.
        const stateSeed = state === 'queue' ? '88' : '99';
        // Theme + viewport seed: `light/desktop = 0`, `light/mobile = 1`,
        // `dark/desktop = 2`, `dark/mobile = 3`. Numeric to keep the
        // SteamID's third group a plain integer (SteamID::isValidID
        // only accepts /STEAM_[01]:[01]:\d+/).
        const tvSeed =
            (theme === 'dark' ? 2 : 0) + (viewport === 'mobile' ? 1 : 0);
        const tag = `${state}-${theme}-${viewport}`;
        return {
            steam: `STEAM_0:1:${stateSeed}1124${tvSeed}`,
            playerName: `e2e-flow-${tag}`,
            reason: `e2e screenshot: ${tag}`,
            reporterName: `e2e-screenshot-${tag}`,
            email: `e2e-${tag}@example.test`,
        };
    }

    for (const theme of THEMES) {
        test(`flow-public-submit-form ${theme}`, async ({ browser }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const path = snapshotPath(theme, viewport, 'flow-public-submit-form');
            await mkdir(dirname(path), { recursive: true });

            // Public form is rendered for logged-out visitors; spin up
            // an anonymous context so the chrome matches what a real
            // submitter would see (no admin chrome, no nav-account).
            const ctx = await newAnonymousContext(browser, testInfo.project.use);
            try {
                const anon = await ctx.newPage();
                await pinTheme(anon, theme);
                await anon.goto('/index.php?p=submit');
                // The form has no async loads (the server list is
                // pre-rendered into the <select>), so a successful
                // navigation is itself the terminal state. We assert
                // on the submit button to make sure the form mounted
                // before snapshotting, then on the resolved theme so
                // the screenshot shows the right palette.
                await expect(anon.locator('[data-testid="submitban-submit"]')).toBeVisible();
                await expectTheme(anon, theme);
                await anon.screenshot({ fullPage: true, path });
                expect(path).toMatch(/\.png$/);
            } finally {
                await ctx.close();
            }
        });

        test(`flow-public-submit-admin-queue ${theme}`, async ({ page, browser }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const path = snapshotPath(theme, viewport, 'flow-public-submit-admin-queue');
            await mkdir(dirname(path), { recursive: true });

            const fx = fixture('queue', theme, viewport);

            // Submit anonymously first (separate context) so the
            // queue has a row to render.
            const anonCtx = await newAnonymousContext(browser, testInfo.project.use);
            try {
                const anon = await anonCtx.newPage();
                await anonymousSubmit(anon, fx);
            } finally {
                await anonCtx.close();
            }

            // Pin theme on the admin context, then navigate to the
            // queue. The page is server-rendered (no skeletons on
            // this surface) so the row is in the DOM by the time
            // theme application is observable on <html>.
            await pinTheme(page, theme);
            await page.goto('/index.php?p=admin&c=bans');
            await expect(
                page
                    .locator('[data-testid="submission-row"]')
                    .filter({ has: page.locator('[data-testid="submission-row-steam"]', { hasText: fx.steam }) }),
            ).toBeVisible();
            await expectTheme(page, theme);

            await page.screenshot({ fullPage: true, path });
            expect(path).toMatch(/\.png$/);
        });

        test(`flow-public-submit-banlist ${theme}`, async ({ page, browser }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const path = snapshotPath(theme, viewport, 'flow-public-submit-banlist');
            await mkdir(dirname(path), { recursive: true });

            const fx = fixture('banlist', theme, viewport);

            // Walk submit → approve via the same helpers the gate
            // spec uses. The DB stays mutated after this test
            // finishes; that's intentional — every fixture is unique
            // per (theme × viewport), so the next gallery run won't
            // collide. `globalSetup` runs `Fixture::install()` (drop +
            // recreate sourcebans_e2e) at the start of every
            // `playwright test` invocation, so the leftover rows
            // never accumulate across runs.
            const anonCtx = await newAnonymousContext(browser, testInfo.project.use);
            try {
                const anon = await anonCtx.newPage();
                await anonymousSubmit(anon, fx);
            } finally {
                await anonCtx.close();
            }

            // Pin theme on the admin context BEFORE the approve flow
            // navigates the page — adminApprove does its own
            // page.goto under the hood and we want the resolved theme
            // to land on first paint there too. (pinTheme does the
            // localStorage write on `/`, which is a same-origin
            // document, so the value carries over.)
            await pinTheme(page, theme);
            await adminApprove(page, fx);

            // Public ban list should now show the row. We pick the
            // desktop testid; on mobile-chromium the `<tr>` is in the
            // DOM but `display:none`, and the visible card view's
            // `<a class="ban-row">` is selected by `.ban-cards
            // .ban-row[data-state]` rather than testid (the testid
            // only lives on the desktop `<tr>` in #1123 B2). For the
            // screenshot we just need the page to settle in a
            // post-load state; either projection is acceptable.
            await page.goto('/index.php?p=banlist');
            const row = page.locator('[data-testid="ban-row"]').filter({ hasText: fx.steam });
            const card = page.locator(`.ban-cards .ban-row[data-state]`).filter({ hasText: fx.steam });
            // `attached` rather than `visible` so the assertion holds
            // for both desktop (testid'd `<tr>` is visible) and mobile
            // (testid'd `<tr>` exists in the DOM but is `display:none`,
            // and the card sibling carries the visible markup).
            const present = row.or(card);
            await expect(present.first()).toBeAttached();
            await expectTheme(page, theme);

            await page.screenshot({ fullPage: true, path });
            expect(path).toMatch(/\.png$/);
        });
    }
});

/**
 * Flow-UI gallery (Slice 6 of #1124).
 *
 * The route-driven gallery above can't capture transient interactive
 * states (palette open, drawer loaded), so this block emits its own
 * PNGs for the three flow-ui surfaces:
 *   - `flow-ui-theme`   : home dashboard rendered in the active theme
 *                          (light/dark variants come from the THEMES loop).
 *   - `flow-ui-palette` : ⌘K-opened palette with a seeded "e2e-pal" hit.
 *   - `flow-ui-drawer`  : `/bans` row drawer, post-load (no skeletons).
 *
 * Each variant pins theme via `localStorage['sbpp-theme']` BEFORE the
 * target navigation so theme.js's boot path lands the right mode on
 * first paint, then drives the interactive state via the same testability
 * hooks the gate specs use (`[data-palette-open]`, `[data-drawer-open]`,
 * `[data-loading]`). Seeded data uses a per-(state × theme × viewport)
 * unique nickname so the two projects (chromium + mobile-chromium) don't
 * race on the same bans.search query.
 */
test.describe('@screenshot flow-ui', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    /**
     * Pin the theme key in localStorage on the same origin as the
     * subsequent navigation. theme.js reads it on boot via
     * `applyTheme(currentTheme())` so the resolved class lands on
     * first paint.
     */
    async function pinTheme(page: import('@playwright/test').Page, theme: Theme): Promise<void> {
        await page.goto('/');
        await page.evaluate((mode: Theme) => {
            try {
                localStorage.setItem('sbpp-theme', mode);
            } catch {
                /* localStorage unavailable; skip */
            }
        }, theme);
    }

    async function waitForThemeApplied(
        page: import('@playwright/test').Page,
        theme: Theme,
    ): Promise<void> {
        await page.waitForFunction((expected: Theme) => {
            const isDark = document.documentElement.classList.contains('dark');
            return expected === 'dark' ? isDark : !isDark;
        }, theme);
    }

    function outPath(theme: Theme, viewport: string, route: string): string {
        return resolve(__dirname, '..', 'screenshots', theme, viewport, `${route}.png`);
    }

    for (const theme of THEMES) {
        test(`flow-ui-theme ${theme}`, async ({ page }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const path = outPath(theme, viewport, 'flow-ui-theme');
            await mkdir(dirname(path), { recursive: true });

            await pinTheme(page, theme);
            // The home dashboard (`/`) is the canonical surface for
            // theme-mode screenshots — it carries the topbar
            // (theme toggle button + palette trigger) so the same
            // PNG doubles as the chrome reference for the slice.
            await page.goto('/');
            await waitForThemeApplied(page, theme);

            await page.screenshot({ fullPage: true, path });
            expect(path).toMatch(/\.png$/);
        });

        test(`flow-ui-palette ${theme}`, async ({ page }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const path = outPath(theme, viewport, 'flow-ui-palette');
            await mkdir(dirname(path), { recursive: true });

            // Per-(state × theme × viewport) unique nickname so the
            // two projects' searches never collide on a shared "ban"
            // result row, and so re-running the gallery without a
            // truncate doesn't snowball duplicates.
            const nick = `e2e-palette-${theme}-${viewport}`;
            // Spread STEAM_0:1:N values across enough room that the
            // four (theme × viewport) variants don't collide.
            const steamSeed = `STEAM_0:1:6660${theme === 'dark' ? '1' : '0'}${viewport === 'mobile' ? '01' : '00'}`;
            // Tolerate `already_banned` on retry — a previous failed
            // attempt leaves the row in place and the search filter
            // below still finds it.
            try {
                await seedBanViaApi(page, { nickname: nick, steam: steamSeed });
            } catch (err) {
                if (!String(err).includes('already_banned')) throw err;
            }

            await pinTheme(page, theme);
            await page.goto('/');
            await waitForThemeApplied(page, theme);

            await page.keyboard.press('Meta+k');
            const dialog = page.locator('#palette-root');
            await expect(dialog).toHaveAttribute('data-palette-open', 'true');
            await expect(page.locator('#palette-input')).toBeFocused();

            // Type a long-enough prefix to clear PALETTE_MIN_QUERY (2)
            // and then wait on the result locator instead of a fixed
            // 200ms debounce timer.
            await page.locator('#palette-input').fill(nick.slice(0, 14));
            await expect(
                page
                    .locator('[data-testid="palette-result"][data-result-kind="ban"]')
                    .filter({ hasText: nick })
                    .first(),
            ).toBeVisible();

            await page.screenshot({ fullPage: true, path });
            expect(path).toMatch(/\.png$/);
        });

        test(`flow-ui-drawer ${theme}`, async ({ page }, testInfo) => {
            // Drawer screenshots are desktop-only — the marquee
            // banlist's mobile chrome is `.ban-cards`, not a `<table>`,
            // and `responsive/drawer.spec.ts` already covers a mobile
            // capture of an open drawer. Re-shooting it here would
            // duplicate that PNG without adding flow coverage.
            test.skip(testInfo.project.name === 'mobile-chromium', 'drawer mobile chrome covered by responsive/drawer.spec.ts');

            const viewport = viewportFor(testInfo.project.name);
            const path = outPath(theme, viewport, 'flow-ui-drawer');
            await mkdir(dirname(path), { recursive: true });

            const nick = `e2e-drawer-${theme}-${viewport}`;
            const steamSeed = `STEAM_0:1:7770${theme === 'dark' ? '1' : '0'}${viewport === 'mobile' ? '01' : '00'}`;
            let seededBid: number | null = null;
            try {
                const seeded = await seedBanViaApi(page, {
                    nickname: nick,
                    steam: steamSeed,
                    reason: 'flow-ui drawer reference screenshot',
                });
                seededBid = seeded.bid;
            } catch (err) {
                if (!String(err).includes('already_banned')) throw err;
                // Retry path: look up the existing row via bans.search.
                seededBid = await page.evaluate(async (steam) => {
                    const w = window as unknown as {
                        sb: { api: { call: (a: string, p: object) => Promise<{ ok?: boolean; data?: { bans?: Array<{ bid: number; steam: string }> } }> } };
                        Actions: Record<string, string>;
                    };
                    const env = await w.sb.api.call(w.Actions.BansSearch, { q: steam, limit: 5 });
                    const found = env?.data?.bans?.find((b) => b.steam === steam);
                    return found?.bid ?? null;
                }, steamSeed);
                if (seededBid === null) throw err;
            }

            await pinTheme(page, theme);
            await page.goto('/index.php?p=banlist');
            await waitForThemeApplied(page, theme);

            await page
                .locator(
                    `[data-testid="drawer-trigger"][data-drawer-href*="id=${seededBid}"]`,
                )
                .first()
                .click();

            const drawer = page.locator('#drawer-root');
            await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
            await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

            await page.screenshot({ fullPage: true, path });
            expect(path).toMatch(/\.png$/);
        });
    }
});

/* ============================================================
 * Responsive (#1124 Slice 7) stateful captures.
 * ============================================================
 *
 * The static-route gallery above already runs each ROUTE through
 * mobile-chromium, so we don't need MORE captures of bare pages —
 * we need pictures of the mobile-only INTERACTIONS that the
 * specs/responsive/*.spec.ts files exercise:
 *
 *   - Sidebar drawer open (hamburger toggled)
 *   - Banlist card view, populated with a seed row
 *   - Drawer overlay open at full viewport width
 *
 * Each scenario emits `screenshots/<theme>/mobile/responsive-<state>.png`.
 * upload-screenshots.sh's `find -mindepth 3 -maxdepth 3 -name '*.png'`
 * picks every distinct filename up; the desktop columns of the
 * markdown table render `—` for these mobile-only rows, which is
 * the intended shape.
 *
 * Each seed uses a unique SteamID (separate from the responsive
 * specs' seeds) so a screenshot run after a regular spec run inside
 * the same `playwright test` invocation doesn't trip
 * `bans.add` -> `already_banned`. The `bans.add` envelope is
 * `expect()`'d to be ok; if a future change makes the seed
 * collision benign, tighten this back to a hard assert.
 */
const RESPONSIVE_SCREENSHOTS = ['sidebar-open', 'banlist-cards', 'drawer-open'] as const;
type ResponsiveScenario = (typeof RESPONSIVE_SCREENSHOTS)[number];

async function pinThemeResponsive(activePage: import('@playwright/test').Page, theme: Theme): Promise<void> {
    // Mirror the gallery loop's three-step pattern: bootstrap an
    // origin so localStorage is reachable, write the preference,
    // then RE-navigate so theme.js's IIFE runs with the new value
    // on first paint. Setting localStorage and waiting on the
    // class without a second goto silently times out on the dark
    // path because theme.js's `applyTheme(currentTheme())` already
    // ran during the first nav.
    await activePage.goto('/');
    await activePage.evaluate((mode: Theme) => {
        try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable */ }
    }, theme);
    await activePage.goto('/');
    await activePage.waitForFunction((expected: Theme) => {
        const isDark = document.documentElement.classList.contains('dark');
        return expected === 'dark' ? isDark : !isDark;
    }, theme);
}

async function seedBan(
    activePage: import('@playwright/test').Page,
    steam: string,
    nickname: string,
): Promise<void> {
    const env = await activePage.evaluate(
        async ({ steam: s, nickname: n }) => {
            const w = /** @type {any} */ (window);
            return await w.sb.api.call(w.Actions.BansAdd, {
                nickname: n,
                type: 0,
                steam: s,
                length: 0,
                reason: 'e2e/responsive screenshot seed',
            });
        },
        { steam, nickname },
    );
    // `already_banned` is a benign collision when the same seed
    // ran in an earlier spec of the same playwright invocation —
    // the row we want is already there, carry on.
    if (env && env.ok === false && env.error?.code !== 'already_banned') {
        throw new Error(`bans.add seed failed: ${JSON.stringify(env)}`);
    }
}

test.describe('@screenshot responsive', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Responsive captures are mobile-chromium only.',
        );
    });

    for (const scenario of RESPONSIVE_SCREENSHOTS) {
        for (const theme of THEMES) {
            test(`responsive ${scenario} ${theme}`, async ({ page }) => {
                const outPath = resolve(
                    __dirname,
                    '..',
                    'screenshots',
                    theme,
                    'mobile',
                    `responsive-${scenario}.png`,
                );
                await mkdir(dirname(outPath), { recursive: true });

                await pinThemeResponsive(page, theme);
                await captureResponsive(page, scenario);
                await page.screenshot({ fullPage: true, path: outPath });
                expect(outPath).toMatch(/\.png$/);
            });
        }
    }
});

/**
 * Drive the browser into the per-scenario terminal state. Kept out
 * of the `test()` body so the screenshot loop reads as a flat
 * (scenario, theme) cross product.
 */
async function captureResponsive(
    page: import('@playwright/test').Page,
    scenario: ResponsiveScenario,
): Promise<void> {
    if (scenario === 'sidebar-open') {
        // dispatchEvent('click') rather than click({ force: true })
        // because the hamburger is `display:none` in the current
        // theme — see specs/responsive/sidebar.spec.ts divergence #1.
        await page.locator('[data-mobile-menu]').dispatchEvent('click');
        await expect(page.locator('#sidebar')).toHaveClass(/\bis-open\b/);
        return;
    }

    if (scenario === 'banlist-cards') {
        await seedBan(page, 'STEAM_0:0:71090007', 'e2e-screenshot-banlist');
        await page.goto('/index.php?p=banlist');
        await expect(page.locator('#banlist-root .ban-cards')).toBeVisible();
        return;
    }

    if (scenario === 'drawer-open') {
        await seedBan(page, 'STEAM_0:0:72090007', 'e2e-screenshot-drawer');
        await page.goto('/index.php?p=banlist');
        const trigger = page
            .locator('#banlist-root .ban-cards [data-testid="drawer-trigger"]')
            .filter({ hasText: 'e2e-screenshot-drawer' });
        await trigger.click();
        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawerRoot).not.toHaveAttribute('data-loading', 'true');
        await expect(drawerRoot.locator('.drawer')).toBeVisible();
        return;
    }
}

/**
 * Flow gallery — Slice 5 (`flow-comms-gag-mute`).
 *
 * Drives the `comms-gag-mute.spec.ts` flow once per (theme × viewport)
 * and captures three screenshots along the way:
 *
 *   1. `flow-comms-gag-mute-add-form`       — empty add-block form.
 *   2. `flow-comms-gag-mute-list-active`    — /commslist with the new
 *                                              gag visible (state=active).
 *   3. `flow-comms-gag-mute-list-unblocked` — /commslist after the
 *                                              unmute URL fired
 *                                              (state=unmuted).
 *
 * The brief asks for a fourth shot of the "unblock prompt/modal";
 * there is no such modal in the new theme — the row's `unmute_url`
 * is a direct anchor (the legacy `UnGag()` JS confirm prompt was
 * removed at #1123 D1) and `sb.message.show()` is a silent no-op
 * because sbpp2026 dropped the `#dialog-placement` shell. The PR
 * body flags both as follow-ups.
 *
 * The flow mirrors the assertions in `specs/flows/comms-gag-mute.spec.ts`
 * but does NOT re-import them — keeping the gallery as its own spec
 * means a flow regression doesn't silently kill the gallery, and a
 * gallery breakage doesn't spam the flow's failure with screenshot
 * noise. The DB is reset per-test so the row's `data-id` is stable
 * across runs.
 */
test.describe('@screenshot flow-comms-gag-mute', () => {
    // Same race surfaces *within* a project too: light + dark would
    // run in parallel under `fullyParallel: true`, hit truncate at
    // overlapping times, and collide on the seed insert. Serial mode
    // sequences the two theme runs within this describe.
    test.describe.configure({ mode: 'serial' });

    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    test.beforeEach(async ({}, testInfo) => {
        // Flow gallery is desktop-only for now: state-mutating specs
        // share the `sourcebans_e2e` schema and `truncateE2eDb()`
        // doesn't hold a cross-process lock, so two projects
        // (chromium + mobile-chromium) racing on `beforeEach` for the
        // same test fail with `1062 Duplicate entry '0' for key
        // 'PRIMARY'`. Mobile viewport coverage for the comms-add
        // chrome will move into the gallery once Slice 0 grows a
        // per-DB advisory lock around `Fixture::truncateOnly()`.
        test.skip(
            testInfo.project.name !== 'chromium',
            'state-mutating gallery; truncateE2eDb is not parallel-project-safe (Slice 0 follow-up)',
        );
        await truncateE2eDb();
    });

    for (const theme of THEMES) {
        test(`flow ${theme}`, async ({ page }, testInfo) => {
            const viewport = viewportFor(testInfo.project.name);
            const outDir = resolve(__dirname, '..', 'screenshots', theme, viewport);
            await mkdir(outDir, { recursive: true });

            // Pin theme via localStorage BEFORE the form-page nav:
            // theme.js reads `sbpp-theme` on every boot via
            // applyTheme(currentTheme()), so a hit on `/` to seed the
            // origin then a navigation to the actual page lands the
            // right mode on first paint. Mirrors the static-route
            // gallery contract (see file-level docblock for the
            // `sbpp-theme` localStorage / `<html>.dark` rationale).
            await page.goto('/');
            await page.evaluate((mode: Theme) => {
                try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable; skip */ }
            }, theme);

            // ---- 1. Add form (empty) -----------------------------
            await page.goto('/index.php?p=admin&c=comms');
            await page.waitForFunction((expected: Theme) => {
                const isDark = document.documentElement.classList.contains('dark');
                return expected === 'dark' ? isDark : !isDark;
            }, theme);
            await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();
            await page.screenshot({
                fullPage: true,
                path: resolve(outDir, 'flow-comms-gag-mute-add-form.png'),
            });

            // ---- 2. Submit; wait for the JSON API to settle ------
            // (`sb.message.show` is a silent no-op in the new theme —
            // see the file-level docblock — so we can't screenshot a
            // success dialog here.)
            await page.locator('[data-testid="addcomm-steam"]').fill('STEAM_0:1:7654321');
            await page.locator('[data-testid="addcomm-nickname"]').fill('e2e-gag-target');
            await page.locator('[data-testid="addcomm-type"]').selectOption('2');
            await page.locator('[data-testid="addcomm-length"]').selectOption('5');
            await page.locator('[data-testid="addcomm-reason"]').selectOption('other');
            await page.locator('[data-testid="addcomm-reason-custom"]').fill('e2e: comms gag flow');
            const apiSettled = page.waitForResponse(
                (r) => r.url().includes('/api.php') && r.request().method() === 'POST',
            );
            await page.locator('[data-testid="addcomm-submit"]').click();
            const apiBody = await (await apiSettled).json();
            expect(apiBody, 'gallery comms.add succeeded').toMatchObject({ ok: true });

            // ---- 3. /commslist with the gag visible --------------
            await page.goto('/index.php?p=commslist');
            const activeRow = page
                .locator('[data-testid="comm-row"]')
                .filter({ hasText: 'STEAM_0:1:7654321' });
            await expect(activeRow).toHaveCount(1);
            await expect(activeRow).toHaveAttribute('data-state', 'active');
            await page.screenshot({
                fullPage: true,
                path: resolve(outDir, 'flow-comms-gag-mute-list-active.png'),
            });

            // ---- 4. Trigger the unblock + capture the unblocked list
            // #1207 ADM-5/6 promoted the unmute affordance to a
            // `<button data-action="comms-unblock">`; the legacy GET
            // URL still ships as `data-fallback-href` so non-JS
            // callers + this gallery (which intentionally drives
            // the GET path to keep the screenshot deterministic
            // across runs) can navigate the same string the prior
            // anchor's `href` carried.
            const unmuteHref = await activeRow
                .locator('[data-testid="row-action-unmute"]')
                .getAttribute('data-fallback-href');
            expect(unmuteHref, 'unmute fallback href present').toBeTruthy();
            await page.goto(`${unmuteHref}&ureason=${encodeURIComponent('e2e: lifted')}`);

            await page.goto('/index.php?p=commslist');
            const unblockedRow = page
                .locator('[data-testid="comm-row"]')
                .filter({ hasText: 'STEAM_0:1:7654321' });
            await expect(unblockedRow).toHaveAttribute('data-state', 'unmuted');
            await page.screenshot({
                fullPage: true,
                path: resolve(outDir, 'flow-comms-gag-mute-list-unblocked.png'),
            });
        });
    }
});

/**
 * Per-flow gallery — admin ban lifecycle (#1124 Slice 4).
 *
 * Five frames, each its own deterministic test so a single failure
 * scopes to one PNG (the gallery rebuilds the DB per test):
 *
 *   1. addban-empty           — pristine "Add a ban" form
 *   2. list-after-add         — admin list with the new permanent ban
 *   3. editban-prefilled      — edit-ban form hydrated from the row
 *   4. list-with-unban-action — admin list pre-unban (the legacy
 *                                `confirm()` modal lived in sourcebans.js
 *                                — gone since #1123 D1; v2.0.0 unban is
 *                                a direct GET. The closest "modal/prompt
 *                                open" frame is the row showing the
 *                                unban affordance, so we capture that.)
 *   5. list-after-unban       — admin list with the row's state pill
 *                                flipped to `unbanned`
 *
 * Mobile chromium and the `dark` theme run the full matrix too, on the
 * same admin storageState (no anonymous context — the entire flow is
 * admin-only). Each test calls `truncateE2eDb()` so the captures are
 * hermetic and never depend on the order tests ran in.
 */
const FLOW_FIXTURE = {
    steam: 'STEAM_0:1:1234567',
    nickname: 'e2e-banned-player',
    initialReason: 'e2e: admin lifecycle test',
};

interface FlowFrame {
    name: string;
    /** Sets up the state to capture; resolves once the page is stable. */
    drive: (page: Page) => Promise<void>;
}

async function applyFlowTheme(page: Page, theme: Theme): Promise<void> {
    // Mirror the smoke gallery's pattern: pin sbpp-theme in
    // localStorage before the screenshot route loads, then wait for
    // theme.js's applyTheme to flip the `dark` class on <html>. We
    // can't `goto('/')` here because some frames (list-after-unban)
    // rely on a multi-step setup that already left the page on its
    // final URL — we set the storage value, hard-reload, and let
    // applyTheme re-run.
    await page.evaluate((mode: Theme) => {
        try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable; skip */ }
    }, theme);
    await page.reload();
    await page.waitForFunction((expected: Theme) => {
        const isDark = document.documentElement.classList.contains('dark');
        return expected === 'dark' ? isDark : !isDark;
    }, theme);
}

/** Add the fixture ban via the UI; the 'Ban added' toast is the
 *  deterministic success signal (same matrix the flow spec uses). */
async function addFixtureBan(page: Page): Promise<void> {
    await page.goto('/index.php?p=admin&c=bans');
    const form = page.locator('[data-testid="addban-form"]');
    await form.locator('[data-testid="addban-nickname"]').fill(FLOW_FIXTURE.nickname);
    await form.locator('[data-testid="addban-steam"]').fill(FLOW_FIXTURE.steam);
    await form.locator('[data-testid="addban-reason"]').selectOption({ value: 'other' });
    await form.locator('[data-testid="addban-reason-custom"]').fill(FLOW_FIXTURE.initialReason);
    await form.locator('[data-testid="addban-submit"]').click();
    await page
        .locator('.toast[data-kind="success"]')
        .filter({ hasText: /ban added/i })
        .waitFor({ state: 'visible' });
}

const FLOW_FRAMES: FlowFrame[] = [
    {
        name: 'addban-empty',
        async drive(page) {
            await page.goto('/index.php?p=admin&c=bans');
            await page.locator('[data-testid="addban-form"]').waitFor({ state: 'visible' });
        },
    },
    {
        name: 'list-after-add',
        async drive(page) {
            await addFixtureBan(page);
            await page.goto('/index.php?p=banlist');
            await page
                .locator('[data-testid="ban-row"]')
                .filter({ hasText: FLOW_FIXTURE.steam })
                .waitFor({ state: 'visible' });
        },
    },
    {
        name: 'editban-prefilled',
        async drive(page) {
            await addFixtureBan(page);
            await page.goto('/index.php?p=banlist');
            const row = page
                .locator('[data-testid="ban-row"]')
                .filter({ hasText: FLOW_FIXTURE.steam });
            await row.locator('[data-testid="row-action-edit"]').click();
            // Wait on the form being hydrated (selectLengthTypeReason
            // populates `editban-name`'s value via DOMContentLoaded);
            // the `toHaveValue` poll is the deterministic signal that
            // the tail script has finished running.
            await expect(page.locator('[data-testid="editban-name"]')).toHaveValue(FLOW_FIXTURE.nickname);
        },
    },
    {
        name: 'list-with-unban-action',
        async drive(page) {
            await addFixtureBan(page);
            await page.goto('/index.php?p=banlist');
            // Hover the row so the action cluster's :hover styles are
            // engaged for the screenshot. Doesn't change the asserted
            // state (the unban anchor is always visible for active
            // rows the admin can unban) but matches what a moderator
            // would see right before clicking.
            const row = page
                .locator('[data-testid="ban-row"]')
                .filter({ hasText: FLOW_FIXTURE.steam });
            await row.hover();
            await row.locator('[data-testid="row-action-unban"]').waitFor({ state: 'visible' });
        },
    },
    {
        name: 'list-after-unban',
        async drive(page) {
            await addFixtureBan(page);
            await page.goto('/index.php?p=banlist');
            const row = page
                .locator('[data-testid="ban-row"]')
                .filter({ hasText: FLOW_FIXTURE.steam });
            await row.locator('[data-testid="row-action-unban"]').click();
            // Wait on the row's data-state flipping to `unbanned` after
            // the unban GET round-trips and the page re-renders — same
            // deterministic check the spec uses.
            await expect(page.locator(`[data-testid="ban-row"][data-state="unbanned"]`)).toBeVisible();
        },
    },
];

test.describe('@screenshot flow-admin-ban-lifecycle', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    // Run desktop only — same constraint as the lifecycle spec
    // (`specs/flows/admin-ban-lifecycle.spec.ts`): both projects
    // (chromium + mobile-chromium) share a single `sourcebans_e2e`
    // schema, so the per-test `truncateE2eDb()` from project B races
    // project A's add-ban half-way through and one of the truncates
    // fails on the post-truncate re-seed (PK collision on the
    // re-inserted admin row 0). The flow's UI surface is the same
    // desktop chrome at mobile width — Slice 7's responsive gallery
    // (`specs/responsive/`) locks the mobile chrome separately, so we
    // don't need a duplicate frame here.
    test.skip(({ isMobile }) => isMobile, 'flow gallery is desktop only (DB race + Slice 7 covers mobile)');

    // Force serial mode within the project: every frame runs its own
    // truncate + addFixtureBan against the shared `sourcebans_e2e`
    // schema, and parallel workers from the same project would still
    // race their truncates against each other (the PK-0 admin row's
    // re-insert collides). `mode: 'serial'` pins all tests in this
    // describe to one worker, so the per-test reset is the only thing
    // touching the DB at any moment.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    for (const frame of FLOW_FRAMES) {
        for (const theme of THEMES) {
            test(`flow-admin-ban-lifecycle ${frame.name} ${theme}`, async ({ page }, testInfo) => {
                const viewport = viewportFor(testInfo.project.name);
                // Output `screenshots/<theme>/<viewport>/<route>.png`
                // (flat). `upload-screenshots.sh` walks the tree at
                // `mindepth=3 maxdepth=3` and emits one markdown row
                // per `<route>`; nesting frames under a per-flow
                // subdirectory hides them from the comment table, so
                // we encode the slice + frame in the filename instead
                // and let the per-PR/per-slice subdirectory the
                // upload script writes to (`pr-<PR>/<slug>/`) keep
                // gallery rows scoped by slice.
                const outPath = resolve(
                    __dirname,
                    '..',
                    'screenshots',
                    theme,
                    viewport,
                    `flow-admin-ban-lifecycle--${frame.name}.png`,
                );
                await mkdir(dirname(outPath), { recursive: true });

                // Setup runs in `light` (the chrome's first-paint
                // default). Switching to `dark` happens *after* the
                // flow setup completes via applyFlowTheme() so the
                // toast + navigation events the setup waits on aren't
                // disturbed by an in-flight reload.
                await frame.drive(page);
                await applyFlowTheme(page, theme);

                await page.screenshot({ fullPage: true, path: outPath });
                expect(outPath).toMatch(/\.png$/);
            });
        }
    }
});

