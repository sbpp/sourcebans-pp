/**
 * #1509 — soft-retire (deactivate / reactivate), durable ban issuer
 * names after hard delete, and admins-list bulk select.
 *
 * Selectors use data-testid hooks only.
 */

import { expect, test } from '../../fixtures/auth.ts';
import {
    reassignBanIssuerE2e,
    seedWebGroupE2e,
    truncateE2eDb,
} from '../../fixtures/db.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

const ADMIN_ADMINS_ROUTE = '/index.php?p=admin&c=admins&section=admins';
const BANLIST_ROUTE = '/index.php?p=banlist';

type ApiWindow = {
    sb: {
        api: {
            call: (
                action: string,
                payload: Record<string, unknown>,
            ) => Promise<{
                ok: boolean;
                data?: Record<string, unknown>;
                error?: { code: string; message: string };
            }>;
        };
    };
    Actions: Record<string, string>;
};

async function addAdmin(
    page: import('@playwright/test').Page,
    params: { name: string; steam: string; email: string },
): Promise<number> {
    const env = await page.evaluate(async (p) => {
        const w = window as unknown as ApiWindow;
        return await w.sb.api.call(w.Actions.AdminsAdd, {
            mask: 0,
            srv_mask: '',
            name: p.name,
            steam: p.steam,
            email: p.email,
            password: 'longpassword',
            password2: 'longpassword',
            server_group: 'c',
            web_group: 'c',
            server_password: '-1',
            web_name: '',
            server_name: '0',
            servers: '',
            single_servers: '',
        });
    }, params);
    expect(env.ok, JSON.stringify(env)).toBe(true);
    const aid = Number(env.data?.aid);
    expect(aid).toBeGreaterThan(0);
    return aid;
}

test.describe('flow: admin deactivate + bulk (#1509)', () => {
    test.skip(({ isMobile }) => isMobile, 'desktop chromium only');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('deactivate → inactive chip → reactivate', async ({ page }) => {
        await page.goto('/');
        const aid = await addAdmin(page, {
            name: 'e2e-deactivate-me',
            steam: 'STEAM_0:0:150901',
            email: 'e2e-deactivate@test.local',
        });

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(row).toBeVisible();

        await row.locator('[data-testid="admin-action-deactivate"]').click();
        const dialog = page.locator('[data-testid="admins-deactivate-dialog"]');
        await expect(dialog).toBeVisible();
        await dialog.locator('[data-testid="admins-deactivate-reason"]').fill('e2e leave');

        const deactivateResp = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="admins-deactivate-submit"]').click();
        const body = await (await deactivateResp).json();
        expect(body.ok).toBe(true);
        expect(body.data?.enabled).toBe(0);

        await expect(row).toBeHidden();

        await page.goto(`${ADMIN_ADMINS_ROUTE}&view=inactive`);
        const inactiveRow = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(inactiveRow).toBeVisible();
        await expect(inactiveRow.locator('[data-testid="admin-inactive-badge"]')).toBeVisible();

        const reactivateResp = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await inactiveRow.locator('[data-testid="admin-action-reactivate"]').click();
        const reBody = await (await reactivateResp).json();
        expect(reBody.ok).toBe(true);
        expect(reBody.data?.enabled).toBe(1);
    });

    test('hard delete snapshots ban issuer name on banlist', async ({ page }) => {
        await page.goto('/');
        const issuerName = 'e2e-issuer';
        const aid = await addAdmin(page, {
            name: issuerName,
            steam: 'STEAM_0:0:150902',
            email: 'e2e-issuer@test.local',
        });

        const seeded = await seedBanViaApi(page, {
            nickname: 'AttrPlayer',
            steam: 'STEAM_0:1:150902',
        });
        await reassignBanIssuerE2e(seeded.bid, aid);

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(row).toBeVisible();
        await row.locator('[data-testid="admin-action-delete"]').click();
        const dialog = page.locator('[data-testid="admins-delete-dialog"]');
        await expect(dialog).toBeVisible();
        const deleteResp = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="admins-delete-submit"]').click();
        expect((await (await deleteResp).json()).ok).toBe(true);

        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto(BANLIST_ROUTE);
        const adminCell = page.locator('[data-testid="ban-row"] .col-admin').first();
        await expect(adminCell).toBeVisible();
        await expect(adminCell).toContainText(issuerName);
        await expect(adminCell).not.toContainText('deleted admin');
        await expect(adminCell).not.toContainText('Unknown');
    });

    test('bulk select → deactivate selected rows', async ({ page }) => {
        await page.goto('/');
        const aid1 = await addAdmin(page, {
            name: 'e2e-bulk-a',
            steam: 'STEAM_0:0:150903',
            email: 'e2e-bulk-a@test.local',
        });
        const aid2 = await addAdmin(page, {
            name: 'e2e-bulk-b',
            steam: 'STEAM_0:0:150904',
            email: 'e2e-bulk-b@test.local',
        });

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row1 = page.locator(`[data-testid="admin-row"][data-id="${aid1}"]`);
        const row2 = page.locator(`[data-testid="admin-row"][data-id="${aid2}"]`);
        await expect(row1).toBeVisible();
        await expect(row2).toBeVisible();

        await row1.locator('[data-testid="admin-row-select"]').check();
        await row2.locator('[data-testid="admin-row-select"]').check();

        const bar = page.locator('[data-testid="admins-bulk-bar"]');
        await expect(bar).toBeVisible();
        await expect(page.locator('[data-testid="admins-bulk-count"]')).toContainText('2');

        await page.locator('[data-testid="admins-bulk-deactivate"]').click();
        const dialog = page.locator('[data-testid="admins-bulk-deactivate-dialog"]');
        await expect(dialog).toBeVisible();

        const bulkResp = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="admins-bulk-deactivate-submit"]').click();
        const body = await (await bulkResp).json();
        expect(body.ok).toBe(true);
        expect(body.data?.op).toBe('deactivate');
        expect(body.data?.applied).toEqual(expect.arrayContaining([aid1, aid2]));

        await expect(row1).toBeHidden();
        await expect(row2).toBeHidden();
    });

    test('deactivate success chains SystemRehashAdmins when handler returns rehash sids', async ({ page }) => {
        await page.goto('/');
        const aid = await addAdmin(page, {
            name: 'e2e-rehash-deact',
            steam: 'STEAM_0:0:150906',
            email: 'e2e-rehash-deact@test.local',
        });

        const apiCalls: Array<{ action: string; params?: Record<string, unknown> }> = [];
        await page.route((url) => url.pathname.endsWith('/api.php'), async (route) => {
            const req = route.request();
            if (req.method() !== 'POST') {
                await route.continue();
                return;
            }
            let body: { action?: string; params?: Record<string, unknown> } = {};
            try {
                body = JSON.parse(req.postData() ?? '{}');
            } catch {
                await route.continue();
                return;
            }
            if (body.action === 'admins.deactivate') {
                apiCalls.push({ action: body.action, params: body.params });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: {
                            aid,
                            enabled: 0,
                            rehash: '11,22',
                            message: {
                                title: 'Admin deactivated',
                                body: 'stub',
                                kind: 'green',
                            },
                        },
                    }),
                });
                return;
            }
            if (body.action === 'system.rehash_admins') {
                apiCalls.push({ action: body.action, params: body.params });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ ok: true, data: { results: [] } }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(row).toBeVisible();
        await row.locator('[data-testid="admin-action-deactivate"]').click();
        const dialog = page.locator('[data-testid="admins-deactivate-dialog"]');
        await dialog.locator('[data-testid="admins-deactivate-submit"]').click();

        await expect.poll(() => apiCalls.map((c) => c.action)).toEqual([
            'admins.deactivate',
            'system.rehash_admins',
        ]);
        const rehashCall = apiCalls.find((c) => c.action === 'system.rehash_admins');
        expect(rehashCall?.params?.servers).toBe('11,22');
    });

    test('bulk deactivate chains SystemRehashAdmins when handler returns rehash sids', async ({ page }) => {
        await page.goto('/');
        const aid = await addAdmin(page, {
            name: 'e2e-rehash-bulk',
            steam: 'STEAM_0:0:150907',
            email: 'e2e-rehash-bulk@test.local',
        });

        const apiCalls: Array<{ action: string; params?: Record<string, unknown> }> = [];
        await page.route((url) => url.pathname.endsWith('/api.php'), async (route) => {
            const req = route.request();
            if (req.method() !== 'POST') {
                await route.continue();
                return;
            }
            let body: { action?: string; params?: Record<string, unknown> } = {};
            try {
                body = JSON.parse(req.postData() ?? '{}');
            } catch {
                await route.continue();
                return;
            }
            if (body.action === 'admins.bulk') {
                apiCalls.push({ action: body.action, params: body.params });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: {
                            op: 'deactivate',
                            applied: [aid],
                            skipped: [],
                            rehash: '33',
                            message: {
                                title: 'Admins deactivated',
                                body: '1 deactivated.',
                                kind: 'green',
                            },
                        },
                    }),
                });
                return;
            }
            if (body.action === 'system.rehash_admins') {
                apiCalls.push({ action: body.action, params: body.params });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ ok: true, data: { results: [] } }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await row.locator('[data-testid="admin-row-select"]').check();
        await page.locator('[data-testid="admins-bulk-deactivate"]').click();
        await page.locator('[data-testid="admins-bulk-deactivate-submit"]').click();

        await expect.poll(() => apiCalls.map((c) => c.action)).toEqual([
            'admins.bulk',
            'system.rehash_admins',
        ]);
        const rehashCall = apiCalls.find((c) => c.action === 'system.rehash_admins');
        expect(rehashCall?.params?.servers).toBe('33');
    });

    test('bulk select → assign web group', async ({ page }) => {
        await page.goto('/');
        const aid = await addAdmin(page, {
            name: 'e2e-bulk-group',
            steam: 'STEAM_0:0:150905',
            email: 'e2e-bulk-group@test.local',
        });
        const group = await seedWebGroupE2e('E2E Bulk Web');

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(row).toBeVisible();
        await row.locator('[data-testid="admin-row-select"]').check();

        await page.locator('[data-testid="admins-bulk-web-group"]').click();
        const dialog = page.locator('[data-testid="admins-bulk-web-group-dialog"]');
        await expect(dialog).toBeVisible();
        await dialog.locator('[data-testid="admins-bulk-web-group-select"]').selectOption(String(group.gid));

        const bulkResp = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="admins-bulk-web-group-submit"]').click();
        const body = await (await bulkResp).json();
        expect(body.ok).toBe(true);
        expect(body.data?.op).toBe('set_web_group');
        expect(body.data?.applied).toEqual([aid]);
    });
});
