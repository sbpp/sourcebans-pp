/**
 * Flow spec — Web Admin Groups master-detail selection paints the
 * right pane from `#web-groups-catalog` without a full page reload.
 *
 * Selectors
 * ---------
 *   - `[data-testid="group-row"]`
 *   - `[data-testid="group-detail"]`
 *   - `[data-testid="group-detail-name"]`
 *   - `[data-testid="web-groups-catalog"]`
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const GROUPS_LIST_ROUTE = '/index.php?p=admin&c=groups&section=list';

const FIXTURE = {
    groupA: 'e2e-select-group-a',
    groupB: 'e2e-select-group-b',
};

async function seedWebGroup(page: import('@playwright/test').Page, name: string): Promise<void> {
    const envelope = await page.evaluate(async (groupName) => {
        const w = window as unknown as {
            sb: {
                api: {
                    call: (
                        action: string,
                        params: Record<string, unknown>,
                    ) => Promise<{
                        ok: boolean;
                        error?: { code: string; message: string };
                    }>;
                };
            };
            Actions: Record<string, string>;
        };
        return await w.sb.api.call(w.Actions.GroupsAdd, {
            name: groupName,
            type: '1',
            bitmask: 0,
            srvflags: '',
        });
    }, name);

    expect(envelope.ok, `groups.add must succeed: ${JSON.stringify(envelope)}`).toBe(true);
}

test.describe('flow: admin groups client-side selection', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('clicking another web group paints the detail pane without reloading', async ({
        page,
    }) => {
        await page.goto('/');
        await seedWebGroup(page, FIXTURE.groupA);
        await seedWebGroup(page, FIXTURE.groupB);

        await page.goto(GROUPS_LIST_ROUTE);

        await expect(page.locator('[data-testid="web-groups-catalog"]')).toBeAttached();
        await expect(page.locator('[data-testid="group-detail"]')).toBeVisible();

        const rowA = page.locator('[data-testid="group-row"]').filter({ hasText: FIXTURE.groupA });
        const rowB = page.locator('[data-testid="group-row"]').filter({ hasText: FIXTURE.groupB });
        await expect(rowA).toHaveCount(1);
        await expect(rowB).toHaveCount(1);

        await page.evaluate(() => {
            (window as unknown as { __sbppClientSelectMarker?: boolean }).__sbppClientSelectMarker =
                true;
        });

        await rowB.click();

        await expect(page.locator('[data-testid="group-detail-name"]')).toHaveText(FIXTURE.groupB);
        await expect(page).toHaveURL(/[?&]gid=\d+/);
        await expect(rowB).toHaveAttribute('aria-current', 'true');

        const stayed = await page.evaluate(
            () =>
                (window as unknown as { __sbppClientSelectMarker?: boolean })
                    .__sbppClientSelectMarker === true,
        );
        expect(stayed, 'clicking a group-row must not full-reload the document').toBe(true);

        await rowA.click();
        await expect(page.locator('[data-testid="group-detail-name"]')).toHaveText(FIXTURE.groupA);
        await expect(rowA).toHaveAttribute('aria-current', 'true');
    });
});
