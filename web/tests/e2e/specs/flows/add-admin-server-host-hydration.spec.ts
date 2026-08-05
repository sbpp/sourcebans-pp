/**
 * Flow: hostname hydration on Add Admin Individual servers
 * data-multiselect options (option[data-server-host] page-tail).
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADD_ADMIN_ROUTE = '/index.php?p=admin&c=admins&section=add-admin';

interface SeededServer {
    sid: number;
    ip: string;
    port: number;
    hostname: string;
}

async function seedTwoServersViaApi(page: import('@playwright/test').Page): Promise<SeededServer[]> {
    const fixtures = [
        { ip: '192.0.2.21', port: 27015, hostname: 'e2e tile #1 — surf europe' },
        { ip: '192.0.2.22', port: 27016, hostname: 'e2e tile #2 — bhop usa' },
    ] as const;

    await page.goto('/');

    const seeded: SeededServer[] = [];
    for (const f of fixtures) {
        const envelope = await page.evaluate(
            async (args) => {
                const w = window as unknown as {
                    sb: {
                        api: {
                            call: (
                                action: string,
                                params: Record<string, unknown>,
                            ) => Promise<{
                                ok: boolean;
                                data?: { sid?: number };
                                error?: { code: string; message: string };
                            }>;
                        };
                    };
                    Actions: Record<string, string>;
                };
                return await w.sb.api.call(w.Actions.ServersAdd, {
                    ip:      args.ip,
                    port:    String(args.port),
                    rcon:    '',
                    rcon2:   '',
                    mod:     1,
                    enabled: true,
                    group:   '0',
                });
            },
            { ip: f.ip, port: f.port },
        );

        const env = envelope as {
            ok: boolean;
            data?: { sid?: number };
            error?: { code: string; message: string };
        };
        if (!env.ok || env.data?.sid === undefined) {
            throw new Error(
                `seedTwoServersViaApi: servers.add failed for ${f.ip}:${f.port} ` +
                `(ok=${env.ok}) — ${JSON.stringify(env)}`,
            );
        }
        seeded.push({ sid: env.data.sid, ip: f.ip, port: f.port, hostname: f.hostname });
    }

    return seeded;
}

async function stubHostPlayersForSeeded(
    page: import('@playwright/test').Page,
    seeded: SeededServer[],
    record: { sids: number[]; trunchints: Array<number | string | undefined> },
): Promise<void> {
    const bySid = new Map<number, SeededServer>(seeded.map((s) => [s.sid, s]));

    await page.route((url) => url.pathname.endsWith('/api.php'), async (route) => {
        const req = route.request();
        if (req.method() !== 'POST') {
            await route.continue();
            return;
        }
        let payload: {
            action?: string;
            params?: { sid?: number | string; trunchostname?: number | string };
        } = {};
        try {
            payload = JSON.parse(req.postData() ?? '{}');
        } catch {
            await route.continue();
            return;
        }
        if (payload.action !== 'servers.host_players') {
            await route.continue();
            return;
        }

        const sidParam = Number(payload.params?.sid);
        const match = Number.isFinite(sidParam) ? bySid.get(sidParam) : undefined;
        if (!match) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: false,
                    error: { code: 'not_found', message: 'no server for sid' },
                }),
            });
            return;
        }

        record.sids.push(match.sid);
        record.trunchints.push(payload.params?.trunchostname);

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: {
                    sid:        match.sid,
                    ip:         match.ip,
                    port:       match.port,
                    hostname:   match.hostname,
                    players:    0,
                    maxplayers: 24,
                    map:        'de_dust2',
                    mapfull:    'de_dust2',
                    mapimg:     'images/maps/de_dust2.jpg',
                    os_class:   'fab fa-linux',
                    secure:     true,
                    player_list: [],
                    can_ban:    false,
                },
            }),
        });
    });
}

test.describe('flow: Add Admin server access option hostname hydration', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e.',
        );
    });

    test('server options hydrate live hostnames on success and fall back to Offline on probe failure', async ({ page }) => {
        await truncateE2eDb();

        const seeded = await seedTwoServersViaApi(page);

        const record = { sids: [] as number[], trunchints: [] as Array<number | string | undefined> };
        await stubHostPlayersForSeeded(page, seeded, record);

        await page.goto(ADD_ADMIN_ROUTE);

        const serversSelect = page.locator('[data-testid="admin-add-servers"]');
        await expect(serversSelect).toHaveCount(1);

        for (const s of seeded) {
            const opt = serversSelect.locator(`option[data-sid="${s.sid}"]`);
            await expect(opt).toHaveCount(1);
            await expect(opt).toHaveAttribute('value', `s${s.sid}`);
            await expect(opt).toHaveAttribute('data-ip', s.ip);
            await expect(opt).toHaveAttribute('data-port', String(s.port));
        }

        for (const s of seeded) {
            const opt = serversSelect.locator(`option[data-sid="${s.sid}"]`);
            await expect(opt).toHaveText(`${s.hostname} (${s.ip}:${s.port})`);
        }

        const seededSids = seeded.map((s) => s.sid).sort((a, b) => a - b);
        const seenSids = [...record.sids].sort((a, b) => a - b);
        expect(seenSids).toEqual(seededSids);
        for (const hint of record.trunchints) {
            expect(Number(hint)).toBe(70);
        }

        await page.unroute((url) => url.pathname.endsWith('/api.php'));
        await page.route((url) => url.pathname.endsWith('/api.php'), async (route) => {
            const req = route.request();
            if (req.method() !== 'POST') {
                await route.continue();
                return;
            }
            let payload: { action?: string } = {};
            try {
                payload = JSON.parse(req.postData() ?? '{}');
            } catch {
                await route.continue();
                return;
            }
            if (payload.action !== 'servers.host_players') {
                await route.continue();
                return;
            }
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: false,
                    error: { code: 'host_unreachable', message: 'simulated UDP timeout' },
                }),
            });
        });

        await page.goto(ADD_ADMIN_ROUTE);

        for (const s of seeded) {
            const opt = page.locator(`[data-testid="admin-add-servers"] option[data-sid="${s.sid}"]`);
            await expect(opt).toHaveText(`Offline (${s.ip}:${s.port})`);
        }
    });
});
