/**
 * Smoke spec for the public ban list (`/index.php?p=banlist`).
 *
 * Asserts:
 *
 *   1. Page mounts — the search input
 *      (`[data-testid="bans-search"]`) is visible. See
 *      `pages/BanList.ts` for the rationale on this anchor over
 *      `[data-testid="ban-row"]` (which only renders when the result
 *      set is non-empty) and over a hypothetical `banlist-table`
 *      (the desktop `<table>` is hidden below 769px on the
 *      mobile-chromium project).
 *   2. No JS errors land in the console.
 *   3. Zero critical-impact axe violations.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { BanListPage } from '../../pages/BanList.ts';

test.describe('smoke /bans', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const banlist = new BanListPage(page);
        await banlist.goto();
        await expect(banlist.searchInput()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
