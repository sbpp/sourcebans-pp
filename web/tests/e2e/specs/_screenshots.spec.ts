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
