/**
 * Smoke: `/admin/admins` (`?p=admin&c=admins`).
 *
 * Asserts the admins list renders without console errors and 0
 * critical axe violations. Storage state is admin/admin (logged in
 * by default), so the route is accessible without a per-spec login.
 *
 * Selector contract: see `pages/admin/AdminAdmins.ts` (pinned on
 * `admin-count`, the headcount span next to the Admins H1).
 */
import { test, expect } from '../../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../../fixtures/axe.ts';
import { AdminAdminsPage } from '../../../pages/admin/AdminAdmins.ts';

test.describe('smoke /admin/admins', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expectNoCriticalA11y(page, testInfo);
        expect(consoleErrors, `console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
    });
});
