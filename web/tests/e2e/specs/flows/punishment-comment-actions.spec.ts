/**
 * #1544: every ban and comm block exposes Add comment, while each
 * existing comment exposes the author/owner-appropriate Edit/Delete
 * actions in both the desktop disclosure and player drawer.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { seedBanViaApi, seedCommViaApi } from '../../fixtures/seeds.ts';

test.describe('flow: punishment comment actions (#1544)', () => {
    test('ban Add comment flow restores per-comment Edit and Delete', async ({ page, isMobile }, testInfo) => {
        const seeded = await seedBanViaApi(page, {
            nickname: `e2e-ban-comment-actions-w${testInfo.workerIndex}-r${testInfo.retry}`,
            steam: `STEAM_0:1:${6_544_000 + testInfo.workerIndex * 100 + testInfo.retry}`,
            reason: 'e2e comment actions',
        });
        const commentPrefix = `ban comment action ${testInfo.workerIndex}`;
        const commentUrl = `https://example.com/comments?id=${testInfo.workerIndex}`;
        const comment = `${commentPrefix} &amp; literal<br/>${commentUrl}`;

        await page.goto('/index.php?p=banlist');
        const rowTestId = isMobile ? 'ban-card' : 'ban-row';
        const addTestId = isMobile
            ? 'row-action-comment-add-mobile'
            : 'row-action-comment-add';
        let row = page.locator(`[data-testid="${rowTestId}"][data-id="${seeded.bid}"]`);
        const add = row.locator(`[data-testid="${addTestId}"]`);
        await expect(add).toBeVisible();
        await add.click();

        const editor = page.locator('#banlist-comment-form');
        await expect(editor).toHaveAttribute('data-ctype', 'B');
        await expect(page.locator('[data-testid="comment-editor-back"]')).toHaveAttribute(
            'href',
            'index.php?p=banlist',
        );
        await expectNoCriticalA11y(page, testInfo, { include: ['#banlist-comment-form'] });
        await editor.locator('textarea[name="commenttext"]').fill(comment);

        await Promise.all([
            page.waitForURL((url) =>
                url.searchParams.get('p') === 'banlist'
                && !url.searchParams.has('comment'),
            ),
            editor.locator('[data-testid="comment-editor-submit"]').click(),
        ]);

        row = page.locator(`[data-testid="${rowTestId}"][data-id="${seeded.bid}"]`);
        if (!isMobile) {
            const disclosure = row.locator('[data-testid="ban-comments-inline"]');
            await disclosure.locator('[data-testid="ban-comments-toggle"]').click();
            const inlineText = disclosure.locator('[data-testid="ban-comment-text"]');
            await expect(inlineText).toContainText(`${commentPrefix} &amp; literal`);
            await expect(inlineText.locator('br')).toHaveCount(1);
            await expect(inlineText.locator('a')).toHaveAttribute('href', commentUrl);
            await expect(disclosure.locator('[data-testid="ban-comment-edit"]')).toBeVisible();
            await expect(disclosure.locator('[data-testid="ban-comment-delete"]')).toBeVisible();
        }

        await row.locator('[data-testid="drawer-trigger"]').click();
        const drawer = page.locator('#drawer-root');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);
        await expect(drawer.locator('[data-testid="drawer-comment-add"]')).toBeVisible();
        await expect(drawer.locator('[data-testid="drawer-comment-edit"]')).toBeVisible();
        await expect(drawer.locator('[data-testid="drawer-comment-delete"]')).toBeVisible();
        const drawerText = drawer.locator('[data-testid="drawer-comment-text"]');
        await expect(drawerText).toContainText(`${commentPrefix} &amp; literal`);
        await expect(drawerText.locator('br')).toHaveCount(1);
        await expect(drawerText.locator('a')).toHaveAttribute('href', commentUrl);
        await expect(drawerText.locator('a')).toHaveAttribute('rel', 'noopener noreferrer');
        await expectNoCriticalA11y(page, testInfo, { include: ['#drawer-root'] });
    });

    test('comm-block Add comment uses the shared editor and action set', async ({ page, isMobile }, testInfo) => {
        const seeded = await seedCommViaApi(page, {
            nickname: `e2e-comm-comment-actions-w${testInfo.workerIndex}-r${testInfo.retry}`,
            steam: `STEAM_0:0:${6_544_100 + testInfo.workerIndex * 100 + testInfo.retry}`,
            reason: 'e2e comm comment actions',
            type: 1,
        });
        const comment = `comm comment action ${testInfo.workerIndex}`;

        await page.goto('/index.php?p=commslist');
        const rowTestId = isMobile ? 'comm-card' : 'comm-row';
        const addTestId = isMobile
            ? 'row-action-comment-add-mobile'
            : 'row-action-comment-add';
        let row = page
            .locator(`[data-testid="${rowTestId}"]`)
            .filter({ hasText: seeded.steam });
        const cid = Number(await row.getAttribute('data-id'));
        expect(cid).toBeGreaterThan(0);

        const add = row.locator(`[data-testid="${addTestId}"]`);
        await expect(add).toBeVisible();
        await add.click();

        const editor = page.locator('#banlist-comment-form');
        await expect(editor).toHaveAttribute('data-ctype', 'C');
        await expect(page.locator('[data-testid="comment-editor-back"]')).toHaveAttribute(
            'href',
            'index.php?p=commslist',
        );
        await expectNoCriticalA11y(page, testInfo, { include: ['#banlist-comment-form'] });
        await editor.locator('textarea[name="commenttext"]').fill(comment);

        await Promise.all([
            page.waitForURL((url) =>
                url.searchParams.get('p') === 'commslist'
                && !url.searchParams.has('comment'),
            ),
            editor.locator('[data-testid="comment-editor-submit"]').click(),
        ]);

        row = page.locator(`[data-testid="${rowTestId}"][data-id="${cid}"]`);
        if (!isMobile) {
            const disclosure = row.locator('[data-testid="comm-comments-inline"]');
            await disclosure.locator('[data-testid="comm-comments-toggle"]').click();
            await expect(disclosure.locator('[data-testid="comm-comment-text"]')).toContainText(comment);
            await expect(disclosure.locator('[data-testid="comm-comment-edit"]')).toBeVisible();
            await expect(disclosure.locator('[data-testid="comm-comment-delete"]')).toBeVisible();
        }

        await row.locator('[data-testid="drawer-trigger"]').click();
        const drawer = page.locator('#drawer-root');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);
        await expect(drawer.locator('[data-testid="drawer-comment-add"]')).toBeVisible();
        await expect(drawer.locator('[data-testid="drawer-comment-edit"]')).toBeVisible();
        await expect(drawer.locator('[data-testid="drawer-comment-delete"]')).toBeVisible();
        await expectNoCriticalA11y(page, testInfo, { include: ['#drawer-root'] });
    });
});
