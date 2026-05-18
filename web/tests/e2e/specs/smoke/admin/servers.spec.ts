/**
 * Smoke: `/admin/servers` (`?p=admin&c=servers&section=list`).
 *
 * Asserts the admin Server Management list renders without console
 * errors and 0 critical axe violations. Storage state is admin/admin
 * (logged in by default), so the route is accessible without a
 * per-spec login.
 *
 * Selector contract: see `pages/admin/AdminServers.ts` (pinned on
 * the always-rendered `server-list-section` wrapper).
 *
 * #1313 regression guard: the page must <script src> the shared
 * hydration helper (`web/scripts/server-tile-hydrate.js`). Without
 * it the per-tile Map / Players cells stay at the em-dash forever,
 * which is exactly what #1313 fixed. We don't seed a server here —
 * the empty-seed run is enough to lock the script include + the
 * page mount; per-tile hydration assertions need a seeded row and
 * live UDP, which is brittle in CI and unnecessary for a smoke
 * gate. The PHPUnit AdminServersListHydrationTest pins the per-tile
 * markup contract; this spec pins the script wiring + a11y only.
 */
import { test, expect } from '../../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../../fixtures/axe.ts';
import { AdminServersPage } from '../../../pages/admin/AdminServers.ts';

test.describe('smoke /admin/servers', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const p = new AdminServersPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        // #1313: shared hydration helper must be wired in. Counted via
        // the locator's count() — `toBeVisible()` is unreliable on a
        // <script> element (display:none by default).
        expect(await p.hydrationScript().count()).toBe(1);

        await expectNoCriticalA11y(page, testInfo);
        expect(consoleErrors, `console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
    });
});
