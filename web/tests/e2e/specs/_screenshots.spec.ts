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

import { test, expect } from '../fixtures/auth.ts';
import {
    adminApprove,
    anonymousSubmit,
    newAnonymousContext,
    type SubmissionFixture,
} from '../pages/SubmitBanFlow.ts';
import { seedBanViaApi } from '../fixtures/seeds.ts';
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

test.describe('@screenshot gallery', () => {
    test.skip(!process.env.SCREENSHOTS, '@screenshot only runs when SCREENSHOTS=1');

    for (const route of ROUTES) {
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
                        // Spin up a logged-out context so the chrome
                        // matches what an anonymous visitor would see.
                        ownContext = await browser.newContext({
                            ...testInfo.project.use,
                            storageState: { cookies: [], origins: [] },
                        });
                        activePage = await ownContext.newPage();
                    }

                    // Pin theme via localStorage BEFORE navigation:
                    // theme.js reads `localStorage['sbpp-theme']` on
                    // boot via applyTheme(currentTheme()), so a hit
                    // before that runs lands the right mode on first
                    // paint. We `goto('/')` first because origin
                    // localStorage requires a same-origin document.
                    await activePage.goto('/');
                    await activePage.evaluate((mode: Theme) => {
                        try { localStorage.setItem('sbpp-theme', mode); } catch (_e) { /* unavailable; skip */ }
                    }, theme);

                    await activePage.goto(route.path);

                    // Wait until theme.js's applyTheme has run and the
                    // resolved mode is reflected on <html>. We don't
                    // wait on a `[data-theme]` attribute (the chrome
                    // uses the `dark` class, not a data-attribute);
                    // see the file-level comment for the rationale.
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
