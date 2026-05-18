/**
 * Smoke: `/admin/groups` (`?p=admin&c=groups`).
 *
 * Asserts the groups page renders without console errors and 0
 * critical axe violations. Storage state is admin/admin (logged in
 * by default), so the route is accessible without a per-spec login.
 *
 * Selector contract: see `pages/admin/AdminGroups.ts` (pinned on
 * the master-detail wrapper `web-groups-section`).
 */
import { test, expect } from '../../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../../fixtures/axe.ts';
import { AdminGroupsPage } from '../../../pages/admin/AdminGroups.ts';

test.describe('smoke /admin/groups', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const p = new AdminGroupsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expectNoCriticalA11y(page, testInfo);
        expect(consoleErrors, `console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
    });
});
