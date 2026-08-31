/**
 * REST v1 end-to-end: mint a PAT on Your Account, then call `/api/v1.php`.
 *
 * PATH_INFO (`/api/v1.php/me`) is used so the spec works without the
 * Apache rewrite that needs a web-image rebuild.
 *
 * Pin to chromium. The flow mutates `:prefix_admins` and
 * `:prefix_api_tokens`. Mobile coverage does not add value.
 */
import { test, expect } from '../../fixtures/auth.ts';
import { MyAccountPage } from '../../pages/admin/MyAccount.ts';

test.describe('REST API v1', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test('mints a token, GET /me, PUT admin by Steam64, deactivate', async ({ page, request }) => {
        const account = new MyAccountPage(page);
        await account.goto();
        await expect(account.pageMounted).toBeVisible();
        await expect(account.tokensCard).toBeVisible();

        const tokenName = `e2e-rest-${Date.now()}`;
        await account.tokenName.fill(tokenName);
        await account.tokenCreate.click();
        await expect(account.tokenSecret).toHaveText(/^sbpp_pat_[0-9a-f]{64}$/);
        const secret = (await account.tokenSecret.textContent()) ?? '';

        const me = await request.get('/api/v1.php/me', {
            headers: { Authorization: `Bearer ${secret}` },
        });
        expect(me.status()).toBe(200);
        const meBody = await me.json();
        expect(meBody.data.name).toBe('admin');
        expect(typeof meBody.data.steam64).toBe('string');

        const steam64 = `76561198${String(Date.now()).padStart(9, '0').slice(-9)}`;
        const put = await request.put(`/api/v1.php/admins/${steam64}`, {
            headers: {
                Authorization: `Bearer ${secret}`,
                'Content-Type': 'application/json',
            },
            data: { name: `E2E Rest ${Date.now()}` },
        });
        expect(put.status(), await put.text()).toBe(201);
        const putBody = await put.json();
        expect(putBody.data.steam64).toBe(steam64);
        expect(putBody.data.enabled).toBe(true);
        expect(putBody.meta.rehash).toBeTruthy();

        const deact = await request.post(`/api/v1.php/admins/${steam64}/deactivate`, {
            headers: {
                Authorization: `Bearer ${secret}`,
                'Content-Type': 'application/json',
            },
            data: { reason: 'e2e deactivate' },
        });
        expect(deact.status(), await deact.text()).toBe(200);
        const deactBody = await deact.json();
        expect(deactBody.data.enabled).toBe(false);
    });

    test('POST /bans, anonymous GET, unban', async ({ page, request }) => {
        const account = new MyAccountPage(page);
        await account.goto();
        await expect(account.tokensCard).toBeVisible();

        const tokenName = `e2e-rest-ban-${Date.now()}`;
        await account.tokenName.fill(tokenName);
        await account.tokenCreate.click();
        await expect(account.tokenSecret).toHaveText(/^sbpp_pat_[0-9a-f]{64}$/);
        const secret = (await account.tokenSecret.textContent()) ?? '';

        const steam = `STEAM_0:1:${Date.now()}`;
        const created = await request.post('/api/v1.php/bans', {
            headers: {
                Authorization: `Bearer ${secret}`,
                'Content-Type': 'application/json',
            },
            data: {
                steam,
                name: 'E2E Rest Ban',
                reason: 'e2e rest ban',
                length: 0,
            },
        });
        expect(created.status(), await created.text()).toBe(201);
        const createdBody = await created.json();
        const bid = createdBody.data.id;
        expect(createdBody.data.steam).toBe(steam);
        expect(createdBody.data.state).toBe('permanent');
        expect(typeof createdBody.data.steam64).toBe('string');

        const anon = await request.get(`/api/v1.php/bans/${bid}`);
        expect(anon.status()).toBe(200);
        const anonBody = await anon.json();
        expect(anonBody.data.admin_name).toBeNull();

        const unban = await request.post(`/api/v1.php/bans/${bid}/unban`, {
            headers: {
                Authorization: `Bearer ${secret}`,
                'Content-Type': 'application/json',
            },
            data: { ureason: 'e2e unban' },
        });
        expect(unban.status(), await unban.text()).toBe(200);
        const unbanBody = await unban.json();
        expect(unbanBody.data.state).toBe('unbanned');
    });
});
