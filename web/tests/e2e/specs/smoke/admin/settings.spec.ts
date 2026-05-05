/**
 * Smoke: `/admin/settings` (`?p=admin&c=settings`).
 *
 * Asserts the settings page renders without console errors and 0
 * critical axe violations. Storage state is admin/admin (logged in
 * by default), so the route is accessible without a per-spec login.
 *
 * Selector contract: see `pages/admin/AdminSettings.ts` (pinned on
 * the settings sub-nav, which sits outside the `$can_web_settings`
 * gate so the selector remains stable on the access-denied fallback).
 */
import { test, expect } from '../../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../../fixtures/axe.ts';
import { AdminSettingsPage } from '../../../pages/admin/AdminSettings.ts';

test.describe('smoke /admin/settings', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const p = new AdminSettingsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expectNoCriticalA11y(page, testInfo);
        expect(consoleErrors, `console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
    });
});
