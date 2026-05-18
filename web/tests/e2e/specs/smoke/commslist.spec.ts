/**
 * Smoke spec for the public comms list (`/index.php?p=commslist`).
 *
 * Asserts:
 *
 *   1. Page mounts — `[data-testid="comms-header"]` is visible.
 *      The `<header>` carries the testid in `page_comms.tpl`
 *      and is always rendered (no `?comment=` body-swap branch on
 *      this page).
 *   2. No JS errors land in the console.
 *   3. Zero critical-impact axe violations.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { CommsListPage } from '../../pages/CommsList.ts';

test.describe('smoke /comms', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const comms = new CommsListPage(page);
        await comms.goto();
        await expect(comms.header()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
