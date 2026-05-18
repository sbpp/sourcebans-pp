/**
 * Smoke spec for the public servers list (`/index.php?p=servers`).
 *
 * Asserts:
 *
 *   1. Page mounts — `[data-testid="servers-summary"]` is visible.
 *      It's the always-rendered header copy (sits outside the
 *      `{if $server_list|@count == 0}` empty-state guard), so the
 *      smoke run on an empty seed still hits a deterministic
 *      visible anchor.
 *   2. No JS errors land in the console. The page's inline
 *      `sb.api.call(Actions.ServersHostPlayers, …)` per-tile probe
 *      only fires when at least one server exists — with the
 *      empty seed the loop is a no-op, so we don't have to wait
 *      out a UDP round trip per server.
 *   3. Zero critical-impact axe violations.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { ServerListPage } from '../../pages/ServerList.ts';

test.describe('smoke /servers', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const servers = new ServerListPage(page);
        await servers.goto();
        await expect(servers.summary()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
