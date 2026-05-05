/**
 * Smoke: `/admin/myaccount` (`?p=account`).
 *
 * Note: the user-account page is NOT under `?p=admin`. The "Logged
 * in as <user>" link in the sidebar (navbar.tpl) routes here, and
 * the legacy admin index treated this as the canonical "my account"
 * surface. Asserts the page renders without console errors and 0
 * critical axe violations. Storage state is admin/admin (logged in
 * by default).
 *
 * Selector contract: see `pages/admin/MyAccount.ts` (pinned on the
 * `account-header` block which renders for any logged-in user).
 */
import { test, expect } from '../../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../../fixtures/axe.ts';
import { MyAccountPage } from '../../../pages/admin/MyAccount.ts';

test.describe('smoke /admin/myaccount', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const p = new MyAccountPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expectNoCriticalA11y(page, testInfo);
        expect(consoleErrors, `console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
    });
});
