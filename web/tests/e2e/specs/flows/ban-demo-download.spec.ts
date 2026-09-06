/**
 * #1554: ban evidence downloads must be reachable from both the list row
 * and the modern player drawer. The endpoint itself is exercised by
 * capturing the browser download and checking its original filename.
 */

import { mkdir, rm, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

const DEMOS_DIR = resolve(process.cwd(), '../../demos');

test.describe('flow: ban demo download (#1554)', () => {
    test('row and drawer expose the attached demo download', async ({ page, isMobile }, testInfo) => {
        const filename = String(
            1_554_000_000 + testInfo.workerIndex * 100 + testInfo.retry,
        ).padStart(32, '0');
        const originalName = 'evidence-1554.dem';
        const path = resolve(DEMOS_DIR, filename);
        let seededBid = 0;

        await mkdir(DEMOS_DIR, { recursive: true });
        await writeFile(path, 'sourcebans++ e2e demo payload');

        try {
            const seeded = await seedBanViaApi(page, {
                nickname: `e2e-demo-1554-w${testInfo.workerIndex}-r${testInfo.retry}`,
                steam: `STEAM_0:1:${6_554_000 + testInfo.workerIndex * 10 + testInfo.retry}`,
                reason: 'e2e demo download',
                demoFile: filename,
                demoName: originalName,
            });
            seededBid = seeded.bid;

            await page.goto('/index.php?p=banlist');

            const rowTestId = isMobile ? 'ban-card' : 'ban-row';
            const downloadTestId = isMobile
                ? 'row-action-demo-download-mobile'
                : 'row-action-demo-download';
            const row = page.locator(`[data-testid="${rowTestId}"][data-id="${seeded.bid}"]`);
            const rowDownload = row.locator(`[data-testid="${downloadTestId}"]`);
            await expect(rowDownload).toBeVisible();
            await expect(rowDownload).toHaveAttribute(
                'href',
                `getdemo.php?type=B&id=${seeded.bid}`,
            );

            await row.locator('[data-testid="drawer-trigger"]').click();

            const drawer = page.locator('#drawer-root');
            await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
            await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

            const drawerDownload = drawer.locator('[data-testid="drawer-demo-download"]');
            await expect(drawerDownload).toBeVisible();
            await expect(drawerDownload).toHaveAttribute(
                'href',
                `getdemo.php?type=B&id=${seeded.bid}`,
            );
            await expectNoCriticalA11y(page, testInfo, { include: ['#drawer-root'] });

            const downloadPromise = page.waitForEvent('download');
            await drawerDownload.click();
            const download = await downloadPromise;
            expect(download.suggestedFilename()).toBe(originalName);
        } finally {
            if (seededBid > 0) {
                await page.evaluate(async (bid) => {
                    const w = window as unknown as {
                        sb?: { api?: { call: (action: string, params: Record<string, unknown>) => Promise<unknown> } };
                        Actions?: Record<string, string>;
                    };
                    if (w.sb?.api && w.Actions?.BansRemoveDemo) {
                        await w.sb.api.call(w.Actions.BansRemoveDemo, { bid });
                    }
                }, seededBid).catch(() => undefined);
            }
            await rm(path, { force: true });
        }
    });
});
