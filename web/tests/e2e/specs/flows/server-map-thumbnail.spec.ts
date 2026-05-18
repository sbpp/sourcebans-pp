/**
 * Flow: the expanded public server card paints the map thumbnail
 * once the SourceQuery response lands (#1312).
 *
 * Background: v1.x SourceBans++ rendered an inline
 * `<img id="mapimg_{$server.sid}">` that JS patched once the per-card
 * UDP probe responded. The #1123 D1 redesign rebuilt
 * `page_servers.tpl` from the handoff card grid and dropped the
 * `<img>` slot — the `mapimg` URL still flowed through the JSON
 * envelope but had no DOM target. The fix restores the slot
 * (`<img data-testid="server-map-img" hidden>`) inside the players
 * panel and wires the shared hydration helper
 * (`web/scripts/server-tile-hydrate.js` — #1313 extracted the
 * inline initializer into this helper so the admin Server
 * Management list could reuse the wiring) to:
 *
 *   - Patch `src` from `r.data.mapimg`.
 *   - Unhide the slot on `load` (so the empty `src=""` placeholder
 *     never paints a broken-image icon during the loading window).
 *   - KEEP the slot hidden on `error` (so a missing file — e.g. a
 *     fork that ships without `nomap.jpg` — degrades to "no
 *     thumbnail" instead of a broken-image icon).
 *
 * This spec drives the runtime observable: seed a server row,
 * intercept the per-card `Actions.ServersHostPlayers` call so the
 * harness doesn't depend on a live UDP probe, and assert each path
 * (load → visible, error → still hidden, connect-error → never
 * rendered) against the `data-testid="server-map-img"` hook.
 *
 * The PHPUnit guard at `web/tests/integration/ServerMapImageRenderTest.php`
 * pins the static contract (the slot ships in the template, the
 * shared helper still wires `d.mapimg`); this spec pins the runtime
 * contract.
 *
 * No `setTimeout` waits — every assertion anchors on a terminal
 * attribute or visibility change Playwright auto-waits on, per the
 * AGENTS.md "Anti-patterns" rule.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

interface SeededServer {
    sid: number;
    ip: string;
    port: number;
}

interface ServerHostPlayersStub {
    /** Map name surfaced in the data row + alt-text-eligible payload. */
    map: string;
    /** Relative URL the hydration helper patches into the slot's `src`. */
    mapimg: string;
    /** Force `error: 'connect'` if true (mirrors the offline UDP path). */
    connectError?: boolean;
}

/**
 * Seed one enabled server via `Actions.ServersAdd`. Mirrors
 * `seedBanViaApi` in `fixtures/seeds.ts`: drives the same PHP
 * dispatcher (CSRF + permissions + handler) that production traffic
 * uses, so a future contract drift on `servers.add` is caught here
 * along with the actual surface under test.
 *
 * The seeded IP is in the documentation-only `192.0.2.0/24` block
 * (RFC 5737) — guarantees the live UDP probe — if anything ever
 * managed to escape the route mock — would route to nowhere.
 */
async function seedServerViaApi(page: import('@playwright/test').Page): Promise<SeededServer> {
    const ip   = '192.0.2.13';
    const port = 27015;

    await page.goto('/');
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
                // mid=1 (Half-Life 2 DM) is the first row data.sql seeds
                // into `:prefix_mods`, so it's always available without
                // a paired mod insert.
                mod:     1,
                enabled: true,
                group:   '0',
            });
        },
        { ip, port },
    );

    const env = envelope as { ok: boolean; data?: { sid?: number }; error?: { code: string; message: string } };
    if (!env.ok || env.data?.sid === undefined) {
        throw new Error(`seedServerViaApi: servers.add failed (ok=${env.ok}) — ${JSON.stringify(env)}`);
    }
    return { sid: env.data.sid, ip, port };
}

/**
 * Install a `page.route()` stub that intercepts the per-card
 * `Actions.ServersHostPlayers` JSON dispatcher call and answers
 * with a deterministic envelope.
 *
 * The dispatcher endpoint is `./api.php` (per `web/scripts/api.js`),
 * resolved against the page's base URL. Other actions on the page
 * are not intercepted — the public servers page only fires
 * `servers.host_players` from the shared hydration helper, so the
 * body inspection below safely passes through anything else (the
 * chrome's version check etc.) untouched.
 */
async function stubHostPlayers(
    page: import('@playwright/test').Page,
    seeded: SeededServer,
    stub: ServerHostPlayersStub,
): Promise<void> {
    // Match the dispatcher endpoint by the URL's pathname so the
    // matcher honours absolute, relative, host-included, and host-less
    // URLs uniformly. The string-glob form (`**\/api.php`) misses some
    // cross-origin shapes Playwright normalises differently — the
    // function form is the safe primitive.
    await page.route((url) => url.pathname.endsWith('/api.php'), async (route) => {
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

        // The dispatcher (web/api.php) wraps every successful handler
        // return in `{ ok: true, data: <handler-return> }` and the
        // helper above guards on `r.ok && r.data`. Match the envelope
        // shape exactly so the mock looks identical to a real API
        // response on the wire — the hydration helper doesn't know
        // it's talking to a stub.
        if (stub.connectError) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    data: {
                        sid:      seeded.sid,
                        ip:       seeded.ip,
                        port:     seeded.port,
                        error:    'connect',
                        is_owner: false,
                    },
                }),
            });
            return;
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: {
                    sid:        seeded.sid,
                    ip:         seeded.ip,
                    port:       seeded.port,
                    hostname:   'e2e map-img test server',
                    players:    1,
                    maxplayers: 24,
                    map:        stub.map,
                    mapfull:    stub.map,
                    mapimg:     stub.mapimg,
                    os_class:   'fab fa-linux',
                    secure:     true,
                    player_list: [
                        { id: 0, name: 'tester', frags: 0, time: 0, time_f: '00:00' },
                    ],
                    can_ban: false,
                },
            }),
        });
    });
}

test.describe('flow: expanded server card map thumbnail (#1312)', () => {
    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('paints the thumbnail when the bundled map image exists', async ({ page }) => {
        const seeded = await seedServerViaApi(page);

        // de_dust2.jpg ships in web/images/maps/ out of the box, so
        // the browser will resolve the URL to a real 200 + image
        // payload and the helper's `onload` branch unhides the slot.
        await stubHostPlayers(page, seeded, {
            map:    'de_dust2',
            mapimg: 'images/maps/de_dust2.jpg',
        });

        await page.goto(`/index.php?p=servers&s=0`);

        const tile = page.locator('[data-testid="server-tile"]').first();
        // Anchor on the terminal attribute the hydration helper
        // sets — Playwright auto-waits, no setTimeout needed.
        await expect(tile).toHaveAttribute('data-status', 'online');
        // `?p=servers&s=0` requests auto-expansion of index 0; the
        // initializer flips `data-expanded` once the response lands.
        await expect(tile).toHaveAttribute('data-expanded', 'true');

        const mapImg = tile.locator('[data-testid="server-map-img"]');
        await expect(mapImg).toBeVisible();
        await expect(mapImg).toHaveAttribute('src', /images\/maps\/de_dust2\.jpg$/);
    });

    test('keeps the slot hidden when the image fails to load', async ({ page }) => {
        const seeded = await seedServerViaApi(page);

        // Point at a path that will return 404 (no such file ships).
        // The helper's `onerror` handler must `setAttribute('hidden', '')`
        // so the slot stays invisible — pre-fix the broken-image
        // glyph would paint instead.
        await stubHostPlayers(page, seeded, {
            map:    'definitely_not_a_real_map',
            mapimg: 'images/maps/__sbpp_e2e_does_not_exist__.jpg',
        });

        await page.goto(`/index.php?p=servers&s=0`);

        const tile = page.locator('[data-testid="server-tile"]').first();
        await expect(tile).toHaveAttribute('data-status', 'online');

        // Slot must be in the DOM (so the `src` patch had a target),
        // but stay `hidden`. We assert the `hidden` attribute rather
        // than `not.toBeVisible()` so a future style refactor that
        // swaps `hidden` for `display: none` would have to update
        // this assertion deliberately rather than coast through.
        const mapImg = tile.locator('[data-testid="server-map-img"]');
        await expect(mapImg).toHaveCount(1);
        // `hidden` is reflected as a boolean attribute → the value
        // is the empty string when set. Polling here covers the
        // window between `onerror` firing and the DOM mutation
        // landing without a setTimeout.
        await expect(async () => {
            const isHidden = await mapImg.evaluate((el) => el.hasAttribute('hidden'));
            expect(isHidden, 'map-img must stay hidden when the image 404s').toBe(true);
        }).toPass();
    });

    test('does not surface the slot when the server is offline', async ({ page }) => {
        const seeded = await seedServerViaApi(page);

        // Force the connect-error branch in `applyData()`. The whole
        // map-img wiring sits below the early-return, so the slot
        // stays in its initial `hidden` state.
        await stubHostPlayers(page, seeded, {
            map:          '',
            mapimg:       '',
            connectError: true,
        });

        await page.goto(`/index.php?p=servers&s=0`);

        const tile = page.locator('[data-testid="server-tile"]').first();
        await expect(tile).toHaveAttribute('data-status', 'offline');

        const mapImg = tile.locator('[data-testid="server-map-img"]');
        await expect(mapImg).toHaveCount(1);
        const isHidden = await mapImg.evaluate((el) => el.hasAttribute('hidden'));
        expect(isHidden, 'offline tiles must keep the map-img slot hidden').toBe(true);
    });
});
