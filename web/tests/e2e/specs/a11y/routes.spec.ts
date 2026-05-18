/**
 * a11y axe scan — every route × light + dark theme (#1124 Slice 8).
 *
 * Contract from #1124 / #1123:
 *   - axe-core scan passes with **0 critical violations** on every page,
 *     in both light and dark mode.
 *   - The threshold is **critical**, sourced from the shared helper in
 *     `fixtures/axe.ts`. Do NOT lower it locally to make tests green —
 *     file follow-ups against the underlying #1123 testability patterns
 *     and `test.fixme()` the failing cell with the issue number.
 *
 * Project filter:
 *   This spec runs on the `chromium` project only. The `mobile-chromium`
 *   project shares the same markup (only the viewport differs) and axe's
 *   WCAG checks don't change with viewport — running on both would
 *   double the time without revealing different findings. We skip
 *   non-chromium projects via `test.skip()` inside each test.
 *
 * Theme handling:
 *   We pin the resolved theme via `localStorage['sbpp-theme']` BEFORE
 *   navigating to the route, so theme.js's boot-time `applyTheme()` lands
 *   the correct mode on first paint. We then wait for the resolved
 *   class on `<html>` (`<html class="dark">` for dark, no `dark` class
 *   for light) — see `_screenshots.spec.ts` for the rationale on why
 *   the chrome uses a class instead of `[data-theme]`.
 *
 * Logged-out routes:
 *   `/index.php?p=login` is the one route an anonymous visitor sees;
 *   we spin up a fresh context with empty storageState for it so the
 *   chrome matches what a logged-out visitor would render. Every other
 *   route relies on the storage state minted by `fixtures/global-setup.ts`.
 *
 * Note on the `protest` route:
 *   The route handler key is `?p=protest` (see
 *   `web/includes/page-builder.php`). The route name is `protest`.
 *
 * Known-bad cells:
 *   None — Slice 3 (#1175) landed `Database.php` PDO native-prepares
 *   on `main`, which resolved #1167 (PDO `LIMIT ?,?` rejected by MariaDB
 *   10.11 strict mode). Both `banlist dark` and `commslist dark` now
 *   render normally and are real assertions. The `FIXME_CELLS` table
 *   below is the documented escape hatch for any future cell that
 *   regresses against an unfixed issue — keep the shape, never lower
 *   the axe `critical` threshold to make a cell green.
 */

import { test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';

interface A11yRoute {
    name: string;
    path: string;
    /** false → use a logged-out context (empty storageState). */
    auth: boolean;
}

const ROUTES: A11yRoute[] = [
    // Public surface
    { name: 'login', path: '/index.php?p=login', auth: false },
    { name: 'home', path: '/', auth: true },
    { name: 'banlist', path: '/index.php?p=banlist', auth: true },
    { name: 'commslist', path: '/index.php?p=commslist', auth: true },
    { name: 'servers', path: '/index.php?p=servers', auth: true },
    { name: 'submit', path: '/index.php?p=submit', auth: true },
    { name: 'protest', path: '/index.php?p=protest', auth: true },
    // Admin surface
    { name: 'admin-home', path: '/index.php?p=admin', auth: true },
    { name: 'admin-bans', path: '/index.php?p=admin&c=bans', auth: true },
    { name: 'admin-admins', path: '/index.php?p=admin&c=admins', auth: true },
    { name: 'admin-groups', path: '/index.php?p=admin&c=groups', auth: true },
    { name: 'admin-settings', path: '/index.php?p=admin&c=settings', auth: true },
    { name: 'admin-audit', path: '/index.php?p=admin&c=audit', auth: true },
    { name: 'myaccount', path: '/index.php?p=account', auth: true },
];

const THEMES = ['light', 'dark'] as const;
type Theme = (typeof THEMES)[number];

/**
 * `test.fixme()` table — `${routeName} ${theme}` -> follow-up reference.
 * Per #1124's MUST-NOT-lower-threshold rule, every entry here is paired
 * with a filed issue. Update the message string when the upstream fix
 * lands and re-enable by removing the entry.
 *
 * Currently empty — every cell is a real assertion (#1167 was resolved
 * by Slice 3). Add an entry here (and file the follow-up issue first)
 * if a future regression makes a specific (route × theme) cell fail
 * for reasons unrelated to a11y proper.
 */
const FIXME_CELLS: Readonly<Record<string, string>> = {};

test.describe('a11y axe scan (0 critical)', () => {
    for (const route of ROUTES) {
        for (const theme of THEMES) {
            const cellKey = `${route.name} ${theme}`;
            test(cellKey, async ({ page, browser }, testInfo) => {
                test.skip(
                    testInfo.project.name !== 'chromium',
                    'a11y outcomes are markup-driven; viewport does not change WCAG findings',
                );

                const fixmeReason = FIXME_CELLS[cellKey];
                if (fixmeReason) {
                    test.fixme(true, fixmeReason);
                }

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

                    // Pin theme via localStorage BEFORE navigating to the
                    // target route. theme.js reads `localStorage['sbpp-theme']`
                    // on boot; setting it after a same-origin `goto('/')`
                    // ensures the next navigation paints in the right mode.
                    await activePage.goto('/');
                    await activePage.evaluate((mode: Theme) => {
                        try {
                            localStorage.setItem('sbpp-theme', mode);
                        } catch (_e) {
                            /* localStorage unavailable in this context; skip */
                        }
                    }, theme);

                    await activePage.goto(route.path);

                    // Wait for theme.js's class flip on <html>. The new
                    // theme uses `<html class="dark">` (set by
                    // `document.documentElement.classList.toggle('dark', dark)`),
                    // not `[data-theme="…"]`.
                    await activePage.waitForFunction((expected: Theme) => {
                        const isDark = document.documentElement.classList.contains('dark');
                        return expected === 'dark' ? isDark : !isDark;
                    }, theme);

                    // Wait for any [data-loading] / [data-skeleton] terminal
                    // attributes to clear — see `pages/_base.ts` for the
                    // canonical predicate. The marquee banlist (#1123 B2)
                    // keeps a dormant `<div data-skeleton hidden>`
                    // always-mounted so banlist.js can flip it visible
                    // during chip-filter re-renders without re-creating
                    // the node, so we treat any `[hidden]` skeleton as
                    // inert (only a *visible* skeleton means the page is
                    // still loading).
                    await activePage.waitForFunction(
                        () => !document.querySelector(
                            '[data-loading="true"], [data-skeleton]:not([hidden])',
                        ),
                    );

                    await expectNoCriticalA11y(activePage, testInfo);
                } finally {
                    if (ownContext) await ownContext.close();
                }
            });
        }
    }
});
