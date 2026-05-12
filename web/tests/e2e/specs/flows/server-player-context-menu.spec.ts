/**
 * Flow: right-click context menu on player rows in the public
 * servers list (`?p=servers`).
 *
 * Background: pre-v2.0.0 SourceBans++ shipped a MooTools-backed
 * right-click menu on player names ("View profile / Kick / Ban /
 * Mute / Gag"). #1306 deleted the helpers + the legacy hint copy
 * because the SourceQuery `GetPlayers()` UDP response doesn't
 * carry SteamIDs — every menu item would have shipped to the JS
 * side without the load-bearing identifier they needed.
 *
 * The restoration ships:
 *
 *   - A new `RconStatusCache` (mirrors `SourceQueryCache`'s
 *     atomic / on-disk / negative-cache shape) that pairs a per-
 *     server RCON `status` probe with the existing A2S probe.
 *   - `api_servers_host_players` now layers the RCON response's
 *     SteamIDs onto each player row in `player_list`, BUT only
 *     when the caller has `ADMIN_OWNER | ADMIN_ADD_BAN` AND
 *     per-server RCON access (`_api_servers_admin_can_rcon`).
 *   - `web/scripts/server-tile-hydrate.js` tags each `<li>` with
 *     `data-context-menu="server-player"` + `data-steamid` /
 *     `data-name` / `data-can-ban-player` / `data-server-sid`
 *     when a SteamID is present in the response.
 *   - `web/scripts/server-context-menu.js` is a fresh vanilla-JS
 *     IIFE that wires a single `document.addEventListener('contextmenu', …)`
 *     delegated on `[data-context-menu="server-player"]`.
 *
 * Selectors anchor on `data-testid` per AGENTS.md ("Testability
 * hooks"). No `setTimeout` waits — every assertion gates on the
 * presence of an element the JS sets or removes when its state
 * machine settles.
 *
 * This spec consciously stubs `Actions.ServersHostPlayers` instead
 * of driving the real handler — the latter would need an actual
 * RCON-reachable server, which is impractical in CI. The integration
 * tests (`web/tests/api/ServersTest.php` + `RconStatusCacheTest`)
 * cover the handler's gate end-to-end against the override hooks.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

interface SeededServer {
    sid: number;
    ip: string;
    port: number;
}

interface ContextMenuPlayer {
    name: string;
    steamid: string;
    frags: number;
    time_f: string;
}

/**
 * Same shape as `server-map-thumbnail.spec.ts`'s seed helper. The
 * IP is from RFC 5737's `192.0.2.0/24` documentation block so a
 * stray real probe (if our route stub ever misses) lands nowhere.
 */
async function seedServerViaApi(page: import('@playwright/test').Page): Promise<SeededServer> {
    const ip   = '192.0.2.14';
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
                rcon:    'rconpassword',
                rcon2:   'rconpassword',
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
 * Stub the `servers.host_players` dispatcher response so the
 * harness doesn't depend on a live UDP / RCON probe pair. The stub
 * mirrors what the real handler emits for an admin with full RCON
 * access on this server — SteamIDs on each player row + the
 * `can_ban_player` envelope flag.
 */
async function stubHostPlayers(
    page: import('@playwright/test').Page,
    seeded: SeededServer,
    players: ContextMenuPlayer[],
    canBanPlayer: boolean,
): Promise<void> {
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

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: {
                    sid:        seeded.sid,
                    ip:         seeded.ip,
                    port:       seeded.port,
                    hostname:   'e2e ctxmenu test server',
                    players:    players.length,
                    maxplayers: 24,
                    map:        'cp_dustbowl',
                    mapfull:    'cp_dustbowl',
                    mapimg:     'images/maps/nomap.jpg',
                    os_class:   'fab fa-linux',
                    secure:     true,
                    player_list: players.map((p, idx) => ({
                        id:      idx,
                        name:    p.name,
                        frags:   p.frags,
                        time:    600,
                        time_f:  p.time_f,
                        steamid: p.steamid,
                    })),
                    can_ban:        true,
                    can_ban_player: canBanPlayer,
                },
            }),
        });
    });
}

test.describe('flow: server-player right-click context menu (#PLAYER_CTX_MENU)', () => {
    // Serial mode — every test in the describe truncates + seeds
    // `sourcebans_e2e`. Without `serial` Playwright runs the three
    // cases in parallel workers locally (`workers: undefined` in
    // `playwright.config.ts` defaults to CPU count), and worker B's
    // `truncateE2eDb()` wipes the row worker A's `seedServerViaApi`
    // just inserted, leaving the `servers.add` for worker C to race
    // against a half-truncated table — exact shape:
    // `Duplicate entry '1-0' for key 'PRIMARY'` on
    // `:prefix_servers_groups` (A inserts (1, 0), B truncates A's
    // server row but not before A's `_servers_groups` row landed,
    // then C tries to insert (1, 0) and collides). Mirrors the
    // `responsive/server-cards.spec.ts` serial guard for the same
    // reason. CI pins `workers: 1` so this only matters locally.
    test.describe.configure({ mode: 'serial' });

    // Single-project gate matches `server-refresh-debounce.spec.ts`'s
    // rationale: the contract is browser-shape-agnostic, and the
    // mobile-chromium project doubles the truncate-and-reseed traffic
    // against `sourcebans_e2e` (Apache can race the in-progress
    // truncate and observe an empty `sb_settings`).
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; chromium only.',
        );
    });

    test('renders all four admin items + closes on Escape', async ({ page }) => {
        await truncateE2eDb();
        const seeded = await seedServerViaApi(page);
        await stubHostPlayers(
            page,
            seeded,
            [
                { name: 'Alice',   steamid: 'STEAM_0:0:1234', frags: 12, time_f: '20:00' },
                { name: 'Charlie', steamid: '[U:1:99999]',    frags: 3,  time_f: '02:13' },
            ],
            /* canBanPlayer */ true,
        );

        await page.goto('/index.php?p=servers&s=0');

        const tile = page.locator('[data-testid="server-tile"]').first();
        await expect(tile).toHaveAttribute('data-status', 'online');
        await expect(tile).toHaveAttribute('data-expanded', 'true');

        const playerRow = tile.locator(
            '[data-testid="server-player"][data-context-menu="server-player"]',
        ).first();
        await expect(playerRow).toBeVisible();
        await expect(playerRow).toHaveAttribute('data-steamid', 'STEAM_0:0:1234');
        await expect(playerRow).toHaveAttribute('data-can-ban-player', 'true');

        // Right-click via dispatching `contextmenu` directly — Playwright's
        // `click({ button: 'right' })` issues a `mousedown` + `mouseup` +
        // `contextmenu` synthesised burst, but on linux-chromium the
        // browser sometimes swallows the contextmenu when the parent has
        // a `dragstart` listener (the tile's `.row` cells). Dispatching
        // the event directly anchors on the same event the listener
        // gates on.
        await playerRow.dispatchEvent('contextmenu');

        const menu = page.locator('[data-testid="server-context-menu"]');
        await expect(menu).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-profile"]')).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-copy"]')).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-kick"]')).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-ban"]')).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-block"]')).toBeVisible();

        // The kick / ban / block hrefs must carry the actual SteamID
        // — the menu's load-bearing payload.
        await expect(menu.locator('[data-testid="context-menu-kick"]'))
            .toHaveAttribute('href', /check=STEAM_0%3A0%3A1234/);
        await expect(menu.locator('[data-testid="context-menu-ban"]'))
            .toHaveAttribute('href', /steam=STEAM_0%3A0%3A1234/);
        await expect(menu.locator('[data-testid="context-menu-block"]'))
            .toHaveAttribute('href', /check=STEAM_0%3A0%3A1234/);

        // View profile builds a SteamID64 from the SteamID2:
        // `76561197960265728 + 2*Z + Y` -> 76561197960268196 for
        // `STEAM_0:0:1234` (Y=0, Z=1234) — 1234*2 + 0 + 76561197960265728.
        await expect(menu.locator('[data-testid="context-menu-profile"]'))
            .toHaveAttribute('href', 'https://steamcommunity.com/profiles/76561197960268196');

        // Escape closes the menu. Anchor on detachment — the menu's
        // close path removes the DOM node, not just toggles `hidden`.
        await page.keyboard.press('Escape');
        await expect(menu).toHaveCount(0);
    });

    test('omits the kick / ban / block items when can_ban_player=false', async ({ page }) => {
        await truncateE2eDb();
        const seeded = await seedServerViaApi(page);
        // Even though the row carries a SteamID (would surface only
        // in tests since the real handler gates the SteamID side-channel
        // on the same check), the JS-side gate `data-can-ban-player`
        // is the load-bearing client predicate. This case proves the
        // JS honours the flag — if a future API regression ever
        // leaked SteamIDs without setting `can_ban_player=true`, the
        // admin-only items still wouldn't appear.
        await stubHostPlayers(
            page,
            seeded,
            [{ name: 'Bob', steamid: 'STEAM_0:1:9999', frags: 5, time_f: '08:32' }],
            /* canBanPlayer */ false,
        );

        await page.goto('/index.php?p=servers&s=0');

        const tile = page.locator('[data-testid="server-tile"]').first();
        await expect(tile).toHaveAttribute('data-status', 'online');

        const playerRow = tile.locator(
            '[data-testid="server-player"][data-context-menu="server-player"]',
        ).first();
        await playerRow.dispatchEvent('contextmenu');

        const menu = page.locator('[data-testid="server-context-menu"]');
        await expect(menu).toBeVisible();
        // The public items are always present.
        await expect(menu.locator('[data-testid="context-menu-profile"]')).toBeVisible();
        await expect(menu.locator('[data-testid="context-menu-copy"]')).toBeVisible();
        // The admin items must not be rendered at all — not hidden,
        // not disabled, simply absent from the DOM.
        await expect(menu.locator('[data-testid="context-menu-kick"]')).toHaveCount(0);
        await expect(menu.locator('[data-testid="context-menu-ban"]')).toHaveCount(0);
        await expect(menu.locator('[data-testid="context-menu-block"]')).toHaveCount(0);
    });

    test('does NOT open on player rows without a steamid attribute', async ({ page }) => {
        await truncateE2eDb();
        const seeded = await seedServerViaApi(page);
        // No `steamid` field on the row (e.g. anonymous viewer's API
        // payload, or the admin-without-per-server-RCON-access path).
        // The hydration helper should leave `data-context-menu` off
        // the `<li>` and the contextmenu listener's `closest()`
        // filter should miss — native browser menu wins.
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
                    ok: true,
                    data: {
                        sid:        seeded.sid,
                        ip:         seeded.ip,
                        port:       seeded.port,
                        hostname:   'e2e ctxmenu no-steamid server',
                        players:    1,
                        maxplayers: 24,
                        map:        'cp_dustbowl',
                        mapfull:    'cp_dustbowl',
                        mapimg:     'images/maps/nomap.jpg',
                        os_class:   'fab fa-linux',
                        secure:     true,
                        player_list: [
                            // No `steamid` field — mirrors what an
                            // anonymous caller would see.
                            { id: 0, name: 'Dave', frags: 8, time: 300, time_f: '05:00' },
                        ],
                        can_ban:        false,
                        can_ban_player: false,
                    },
                }),
            });
        });

        await page.goto('/index.php?p=servers&s=0');

        const tile = page.locator('[data-testid="server-tile"]').first();
        await expect(tile).toHaveAttribute('data-status', 'online');

        // The row exists but should NOT carry the context-menu hooks.
        const playerRow = tile.locator('[data-testid="server-player"]').first();
        await expect(playerRow).toBeVisible();
        await expect(playerRow).not.toHaveAttribute('data-context-menu', 'server-player');

        // Dispatching a contextmenu against a row without the
        // attribute should leave the document menu un-rendered.
        await playerRow.dispatchEvent('contextmenu');
        await expect(page.locator('[data-testid="server-context-menu"]')).toHaveCount(0);
    });
});
