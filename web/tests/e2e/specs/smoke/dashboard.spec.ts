/**
 * Smoke spec for the public dashboard (`/`).
 *
 * Asserts:
 *
 *   1. Page mounts — `[data-testid="dashboard-header"]` is visible.
 *   2. No JS errors land in the console (uncaught exceptions or
 *      `console.error` calls).
 *   3. Zero critical-impact axe violations on the default light
 *      context (light/dark coverage is in the @screenshot gallery;
 *      explicit dark-mode axe scanning is Slice 8's a11y sweep).
 *
 * The spec runs under the seeded admin's storage state (the default
 * project storage state) — the dashboard renders cleanly for both
 * authenticated and anonymous visitors, so a logged-out variant
 * isn't required for smoke. The login spec is the one exception
 * that opts back out of storage state.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { DashboardPage } from '../../pages/Dashboard.ts';

test.describe('smoke /', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const home = new DashboardPage(page);
        await home.goto();
        await expect(home.header()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
