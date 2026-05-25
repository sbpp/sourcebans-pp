/**
 * Flow: the public Server List header copy paints the live online-tile
 * count in `[data-online-num]` after the per-tile hydration responses
 * land (#1446).
 *
 * Pre-fix `summaryNode` in `web/scripts/server-tile-hydrate.js`
 * resolved the `[data-testid="servers-summary"]` element via
 * `container.querySelector(...)`, but the summary is inside the
 * page-level `<header>` while the hydration container is the sibling
 * `.servers-grid` `<div data-server-hydrate="auto">` — so the
 * descendant-only lookup returned `null` and `updateOnlineCount`
 * early-returned every time a tile flipped to `online`. The header
 * stayed frozen at "{N} configured · 0 online" regardless of how
 * many servers actually came back online. The reporter on V2 rc5
 * saw "5 configured · 0 online" with 5 healthy servers.
 *
 * What this locks in
 * ------------------
 *
 *   - Three tiles all flip to `online` → `[data-online-num]` reads
 *     `3`. This is the primary user-visible regression that #1446
 *     reopened.
 *   - A mixed batch (two online, one offline) → counter reads `2`
 *     (offline tiles must NOT bump the count).
 *   - Refreshing a single tile online→offline decrements the
 *     counter by one. The `prev !== 'online' && status === 'online'`
 *     +1 / `prev === 'online' && status !== 'online'` -1 ladder in
 *     `setStatus` is the load-bearing contract; this case proves it
 *     stays correct under in-place flips.
 *   - Every assertion anchors on a terminal attribute (`data-status`)
 *     or on a textContent value visible to the user — never on a
 *     `setTimeout` wait per AGENTS.md "Anti-patterns".
 *
 * Stub the per-tile `Actions.ServersHostPlayers` call via
 * `page.route` so the harness doesn't depend on a live UDP probe to
 * a documentation-only IP. Mirrors `server-map-thumbnail.spec.ts`'s
 * stub shape: the dispatcher endpoint match is by `url.pathname`
 * (`endsWith('/api.php')`) so absolute / relative / host-included
 * URLs all match uniformly, and non-`servers.host_players` POSTs
 * pass through untouched so the chrome's CSRF / version check
 * isn't disturbed.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

/**
 * Single URL-matcher reference shared between `page.route` and
 * `page.unroute` calls in this file. Playwright (≥1.59) compares
 * function matchers by reference for `unroute()` removal — passing a
 * fresh arrow function would silently leave the original handler in
 * place and rely on Playwright's LIFO route-resolution order for the
 * swap to appear to work. Capturing the matcher once at module scope
 * makes the `unroute()` call do what its caller reads as: actually
 * remove the previous handler.
 */
const apiUrlMatcher = (url: URL): boolean => url.pathname.endsWith('/api.php');

interface SeededServer {
    sid: number;
    ip: string;
    port: number;
}

/**
 * Per-sid response shape the stub routes through. `online` is the
 * happy path; `offline` simulates the `error: 'connect'` envelope
 * (`api_servers_host_players` returns this when the A2S probe times
 * out, mirroring the legacy UDP-poll behaviour).
 */
type SidResponse = { online: true; hostname: string } | { online: false };

/**
 * Seed N enabled servers via `Actions.ServersAdd`. RFC 5737
 * documentation IPs (203.0.113.0/24) — guaranteed never to answer
 * a real A2S probe even if the stub somehow misses the request.
 *
 * Returns the sids in insert order so the spec can keyword the
 * stub response per server.
 */
async function seedServersViaApi(
    page: import('@playwright/test').Page,
    count: number,
): Promise<SeededServer[]> {
    await page.goto('/');
    const results: SeededServer[] = [];

    for (let i = 0; i < count; i++) {
        // Bumping the last IP octet keeps each row unique on the
        // `:prefix_servers (ip, port)` index. Using port 27015 across
        // every seed means the index only differentiates on ip.
        const ip   = `203.0.113.${i + 1}`;
        const port = 27015;

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
                    // mid=1 (Half-Life 2 DM) is the first row data.sql
                    // seeds into `:prefix_mods`, so it's always
                    // available without a paired mod insert.
                    mod:     1,
                    enabled: true,
                    group:   '0',
                });
            },
            { ip, port },
        );

        const env = envelope as { ok: boolean; data?: { sid?: number }; error?: { code: string; message: string } };
        if (!env.ok || env.data?.sid === undefined) {
            throw new Error(
                `seedServersViaApi: servers.add failed for ${ip}:${port} (ok=${env.ok}) — ${JSON.stringify(env)}`,
            );
        }
        results.push({ sid: env.data.sid, ip, port });
    }

    return results;
}

/**
 * Install a `page.route()` stub that intercepts every
 * `Actions.ServersHostPlayers` POST and answers from a sid-keyed
 * table. Non-`servers.host_players` POSTs pass through untouched.
 *
 * `responses` keys the per-sid response; a sid the spec didn't
 * register falls through to `route.continue()` so any unexpected
 * call lands a real (failing) UDP probe — easier to triage than
 * a silently-stubbed surprise.
 */
async function stubHostPlayersBySid(
    page: import('@playwright/test').Page,
    responses: Map<number, SidResponse>,
): Promise<void> {
    await page.route(apiUrlMatcher, async (route) => {
        const req = route.request();
        if (req.method() !== 'POST') {
            await route.continue();
            return;
        }
        let payload: { action?: string; params?: { sid?: number } } = {};
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

        const sid = Number(payload.params?.sid ?? 0);
        const reply = responses.get(sid);
        if (!reply) {
            // Unregistered sid — fall through so the failure shape
            // is "real UDP timeout" not "silent stub miss".
            await route.continue();
            return;
        }

        if (!reply.online) {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({
                    ok:   true,
                    data: {
                        sid:      sid,
                        error:    'connect',
                        ip:       `203.0.113.${sid}`,
                        port:     27015,
                        is_owner: false,
                    },
                }),
            });
            return;
        }

        await route.fulfill({
            status:      200,
            contentType: 'application/json',
            body:        JSON.stringify({
                ok:   true,
                data: {
                    sid:         sid,
                    hostname:    reply.hostname,
                    players:     1,
                    maxplayers:  24,
                    map:         'de_dust2',
                    mapfull:     'de_dust2',
                    mapimg:      'images/maps/de_dust2.jpg',
                    os_class:    'fab fa-linux',
                    secure:      true,
                    player_list: [
                        { id: 0, name: 'tester', frags: 0, time: 0, time_f: '00:00' },
                    ],
                    can_ban: false,
                },
            }),
        });
    });
}

test.describe('flow: public servers — online-count counter (#1446)', () => {
    // Skip on mobile-chromium: the contract is browser-shape-agnostic
    // (the JS lives in `web/scripts/server-tile-hydrate.js` and runs
    // identically on both form factors), so the second project would
    // just double the runtime cost on the `sourcebans_e2e`
    // truncate-and-reseed cycles in `beforeEach` without adding
    // coverage. Mobile-specific layout has no bearing on the
    // `summaryNode` lookup — the helper resolves the same DOM
    // structure regardless of viewport.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to save runtime on a contract that runs identically on both form factors.',
        );
    });

    // Tests in this file each call `truncateE2eDb()` and then seed
    // their own servers; with `fullyParallel: true` at the harness
    // level, sibling tests in the same file would race the
    // truncate-vs-Apache window against each other on
    // `sourcebans_e2e` (test A is mid-`seedServersViaApi` ->
    // dispatcher reads `:prefix_admins` for the auth check, test
    // B's truncate wipes the table, test A's call gets a
    // `forbidden` cascade — exactly the failure shape `./sbpp.sh e2e`
    // hit on the first run of this spec). Serial mode at the
    // describe granularity keeps the truncate-and-reseed atomic
    // across sibling tests in the file, matching the pattern
    // `comms-affordances.spec.ts` documents in its file-level
    // comment.
    test.describe.configure({ mode: 'serial' });

    test('paints the online tile count once every tile resolves online', async ({ page }) => {
        await truncateE2eDb();
        const servers = await seedServersViaApi(page, 3);

        const responses = new Map<number, SidResponse>();
        servers.forEach((s, idx) => {
            responses.set(s.sid, { online: true, hostname: `e2e-server-${idx + 1}` });
        });
        await stubHostPlayersBySid(page, responses);

        await page.goto('/index.php?p=servers');

        // Wait until every tile has settled to `data-status="online"`.
        // The hydration helper sets this on the matching tile as the
        // per-tile response lands; the auto-wait inside `toHaveAttribute`
        // covers the race between the stub responding and the helper
        // patching the DOM.
        for (const s of servers) {
            const tile = page.locator(`[data-testid="server-tile"][data-id="${s.sid}"]`);
            await expect(tile).toHaveAttribute('data-status', 'online');
        }

        // The painted text-content lives inside `[data-online-num]`
        // which is itself nested in `[data-testid="servers-summary"]`.
        // Reading via the locator chain keeps the assertion
        // resilient to chrome changes that move the testid wrapper
        // around as long as the inner data-attribute stays stable.
        const onlineNum = page
            .locator('[data-testid="servers-summary"]')
            .locator('[data-online-num]');
        await expect(onlineNum).toHaveText(String(servers.length));
    });

    test('counts only online tiles in a mixed batch', async ({ page }) => {
        await truncateE2eDb();
        const servers = await seedServersViaApi(page, 3);

        const responses = new Map<number, SidResponse>();
        responses.set(servers[0].sid, { online: true,  hostname: 'e2e-server-a' });
        responses.set(servers[1].sid, { online: false });
        responses.set(servers[2].sid, { online: true,  hostname: 'e2e-server-c' });
        await stubHostPlayersBySid(page, responses);

        await page.goto('/index.php?p=servers');

        // Anchor on terminal attributes so the assertion waits out
        // both the online and the offline paths deterministically.
        await expect(page.locator(`[data-testid="server-tile"][data-id="${servers[0].sid}"]`)).toHaveAttribute('data-status', 'online');
        await expect(page.locator(`[data-testid="server-tile"][data-id="${servers[1].sid}"]`)).toHaveAttribute('data-status', 'offline');
        await expect(page.locator(`[data-testid="server-tile"][data-id="${servers[2].sid}"]`)).toHaveAttribute('data-status', 'online');

        const onlineNum = page
            .locator('[data-testid="servers-summary"]')
            .locator('[data-online-num]');
        await expect(onlineNum).toHaveText('2');
    });

    test('decrements the counter when an in-place refresh flips a tile from online to offline', async ({ page }) => {
        await truncateE2eDb();
        const servers = await seedServersViaApi(page, 2);

        // First load: both online.
        const responses = new Map<number, SidResponse>();
        responses.set(servers[0].sid, { online: true, hostname: 'e2e-online-1' });
        responses.set(servers[1].sid, { online: true, hostname: 'e2e-online-2' });
        await stubHostPlayersBySid(page, responses);

        await page.goto('/index.php?p=servers');

        const tile0 = page.locator(`[data-testid="server-tile"][data-id="${servers[0].sid}"]`);
        const tile1 = page.locator(`[data-testid="server-tile"][data-id="${servers[1].sid}"]`);
        await expect(tile0).toHaveAttribute('data-status', 'online');
        await expect(tile1).toHaveAttribute('data-status', 'online');

        const onlineNum = page
            .locator('[data-testid="servers-summary"]')
            .locator('[data-online-num]');
        await expect(onlineNum).toHaveText('2');

        // Swap the stub so the second tile's refresh response is
        // now offline. Pass the SAME `apiUrlMatcher` reference to
        // `page.unroute` so Playwright's reference-equality check
        // actually removes the original handler (a fresh arrow
        // function here would no-op the unroute and the LIFO
        // route resolution order would happen to mask it — see
        // the `apiUrlMatcher` docblock at the top of this file).
        await page.unroute(apiUrlMatcher);
        const flipped = new Map<number, SidResponse>();
        flipped.set(servers[0].sid, { online: true, hostname: 'e2e-online-1' });
        flipped.set(servers[1].sid, { online: false });
        await stubHostPlayersBySid(page, flipped);

        // Trigger the per-tile refresh on tile 1. The refresh button
        // re-uses `loadTile` which calls `setStatus(loading)` →
        // `setStatus(offline)` on the connect-error branch; the
        // ladder in `setStatus` decrements `onlineDelta` on the
        // `online → loading` transition and never re-increments
        // because the offline response keeps it off.
        const refresh1 = tile1.locator('[data-testid="server-refresh"]');
        await expect(refresh1).toBeEnabled();
        await refresh1.click();

        await expect(tile1).toHaveAttribute('data-status', 'offline');
        await expect(onlineNum).toHaveText('1');
    });
});
