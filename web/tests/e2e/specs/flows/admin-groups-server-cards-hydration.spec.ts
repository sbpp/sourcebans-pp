/**
 * Flow: the admin Server Groups list (`?p=admin&c=groups`) renders
 * one `[data-testid="server-tile"]` per bound server inside each
 * server-group card, and the shared `web/scripts/server-tile-hydrate.js`
 * helper patches the live hostname into each tile's
 * `[data-testid="server-host"]` slot over the SSR `IP:port` fallback
 * (#1406).
 *
 * Pre-#1404 the per-card hydration surface rode a dead
 * `<script>LoadServerHostPlayersList(...)` blob feeder + a
 * `<div id="servers_{$group.gid}">` slot + literal admin-facing
 * "Servers populate via the legacy ... hook." placeholder copy. #1404
 * dropped all three; this PR (#1406) is the additive replacement:
 * the page handler INNER-JOINs `:prefix_servers_groups` against
 * `:prefix_servers` to expose the per-group `servers` array, the
 * template renders one tile per bound server with the SSR `IP:port`
 * fallback, and the shared hydration helper auto-runs on first paint.
 *
 * What this locks in
 * ------------------
 *
 *  - The card body of a server group with N bound servers ships
 *    exactly N `[data-testid="server-tile"]` cells, each carrying
 *    `data-id="{sid}"` and the SSR `IP:port` fallback inside
 *    `[data-testid="server-host"]`. This is the no-JS / cache-cold
 *    operator surface.
 *  - When `Actions.ServersHostPlayers` is stalled per tile, the SSR
 *    fallback stays painted (the contract: hydration NEVER hides
 *    the SSR cell).
 *  - Once the stub resolves with a canned hostname per sid, each
 *    `[data-testid="server-host"]` flips to the live hostname.
 *  - Cards stay scoped to their parent
 *    `[data-testid="server-group-row"][data-id="<gid>"]` — a
 *    regression that fanned every server into one shared list would
 *    surface as the wrong tile count under the seeded group.
 *
 * The PHPUnit guard at `web/tests/integration/AdminServerGroupsServerCardsRenderTest.php`
 * pins the static contract (template ships testids, handler runs
 * the JOIN, hydration helper wires `[data-testid="server-host"]`).
 * This spec pins the runtime contract.
 *
 * The seeder shim
 * (`web/tests/e2e/scripts/seed-server-group-e2e.php` exposed via
 * `seedServerGroupWithServersE2e` in `fixtures/db.ts`) is what
 * provides a `:prefix_groups (type=3)` row + N `:prefix_servers`
 * rows + N `:prefix_servers_groups` wiring rows in one shot. There
 * is no JSON action that wires a server into a server group's
 * membership, so the spec can't drive the seed entirely through
 * `sb.api.call(...)` — the shim is the narrow shape that fills the
 * gap without coupling to the master-detail UI's form-post chrome.
 *
 * No `setTimeout` waits — every assertion anchors on a Playwright
 * auto-wait surface (locator visibility, text content, attribute
 * value), per the AGENTS.md "Anti-patterns" rule.
 */

import { expect, test } from '../../fixtures/auth.ts';
import {
    deleteServerE2e,
    seedServerGroupWithServersE2e,
    truncateE2eDb,
    type ServerGroupSeedResult,
} from '../../fixtures/db.ts';

const ADMIN_GROUPS_ROUTE = '/index.php?p=admin&c=groups';

/**
 * Two canned hostnames the route stub will return — one per sid in
 * the seeded group. We use distinct values so the assertion can
 * verify the helper keyed by sid correctly (a regression that
 * applied one response to every tile would surface as both cells
 * showing the same hostname).
 */
const SEED_NAME = 'e2e-admin-groups-1406';
const STUB_HOSTNAMES: Record<number, string> = {};

interface HostPlayersStubOptions {
    /** Map sid -> hostname the stub should return. */
    hostnames: Record<number, string>;
    /**
     * Promise the stub awaits before responding. Lets the spec assert
     * the SSR fallback paint window before releasing the canned
     * envelopes. Mirrors `action-loading-indicator.spec.ts`'s stall
     * pattern.
     */
    release: Promise<void>;
}

/**
 * Install a `page.route` handler that intercepts
 * `Actions.ServersHostPlayers` per-tile calls and answers with a
 * deterministic envelope keyed by `params.sid`. Anything else (the
 * chrome's version check, lucide icons, theme.js, …) passes through
 * untouched.
 *
 * The stub stalls each matched request on the provided `release`
 * promise so the spec can assert the SSR fallback paint window
 * before letting the hydration helper run.
 */
async function stubHostPlayers(
    page: import('@playwright/test').Page,
    opts: HostPlayersStubOptions,
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

        const sid = Number(payload.params?.sid ?? 0);
        const hostname = opts.hostnames[sid];
        if (!hostname) {
            // Unknown sid — pass through so a future spec that seeds
            // additional servers won't have to extend this stub map.
            await route.continue();
            return;
        }

        // Stall the response until the test body releases the gate.
        // The hydration helper has already set the tile's `data-status`
        // to "loading" before the request leaves the page, so the SSR
        // `IP:port` cell stays painted in the meantime (the helper
        // never clears the cell before applying the response).
        await opts.release;

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: {
                    sid,
                    ip: '203.0.113.99',
                    port: 27015,
                    hostname,
                    players: 0,
                    maxplayers: 24,
                    map: 'de_dust2',
                    mapfull: 'de_dust2',
                    mapimg: 'images/maps/de_dust2.jpg',
                    os_class: 'fab fa-linux',
                    secure: true,
                    player_list: [],
                    can_ban: false,
                },
            }),
        });
    });
}

test.describe('flow: admin Server Groups per-card server tiles (#1406)', () => {
    // Skip on the second project for the same reason the sister specs
    // skip: `truncateE2eDb()` + the seed shell-out + the per-card
    // `Actions.ServersHostPlayers` fan-out would double the
    // truncate-and-reseed traffic against `sourcebans_e2e` and race
    // with parallel workers. CI runs `workers: 1` so the truncates
    // serialize on the named-lock anyway, but the local default uses
    // cpu count. The contract is browser-shape-agnostic.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e (see file-level comment).',
        );
    });

    test('the card body paints one server-tile per bound server with the SSR IP:port fallback, then flips to the live hostname after the API resolves', async ({ page, isMobile }) => {
        test.skip(isMobile, 'desktop is the canonical surface; the hydration helper is shape-agnostic');

        await truncateE2eDb();

        // Seed one server group with two bound servers. The shim
        // returns the new gid + per-server (sid, ip, port) tuples so
        // we can key the stub by sid and anchor the assertions on the
        // server-group row's data-id.
        const seed: ServerGroupSeedResult = await seedServerGroupWithServersE2e(SEED_NAME, [
            { ip: '203.0.113.10', port: 27015 },
            { ip: '203.0.113.20', port: 27016 },
        ]);
        expect(seed.gid, 'shim must return a numeric gid > 0').toBeGreaterThan(0);
        expect(seed.servers, 'shim must return both seeded servers').toHaveLength(2);
        const [firstSeeded, secondSeeded] = seed.servers;

        // Per-sid stubbed hostnames so we can prove the helper keyed
        // each response back to the right tile.
        STUB_HOSTNAMES[firstSeeded.sid]  = 'alpha-zero (live)';
        STUB_HOSTNAMES[secondSeeded.sid] = 'bravo-one (live)';

        // Install the stall + stub BEFORE navigating so the per-tile
        // requests the page emits on first paint hit our handler, not
        // the real upstream (which would `error: 'connect'` against
        // the documentation-range IPs anyway, but the stall is what
        // gives us a window to assert the SSR fallback).
        let releaseRoute: (() => void) | null = null;
        const releasePromise = new Promise<void>((resolve) => {
            releaseRoute = resolve;
        });
        await stubHostPlayers(page, { hostnames: STUB_HOSTNAMES, release: releasePromise });

        await page.goto(ADMIN_GROUPS_ROUTE);

        // Anchor on the seeded group's row, then scope every locator
        // below to its descendant tree so sibling fixture groups (if
        // any leak in) don't pollute the counts.
        const groupRow = page.locator(`[data-testid="server-group-row"][data-id="${seed.gid}"]`);
        await expect(groupRow, 'seeded group row must mount').toBeVisible();

        // Contract 1: card body ships exactly two `[data-testid="server-tile"]` cells.
        const tiles = groupRow.locator('[data-testid="server-tile"]');
        await expect(tiles, 'each seeded server should render one server-tile').toHaveCount(2);

        // Contract 2: each tile carries `data-id="{sid}"` matching the
        // seeded servers. Pin per-tile so a regression that swapped to
        // a static value or a different field fails fast.
        const firstTile  = groupRow.locator(`[data-testid="server-tile"][data-id="${firstSeeded.sid}"]`);
        const secondTile = groupRow.locator(`[data-testid="server-tile"][data-id="${secondSeeded.sid}"]`);
        await expect(firstTile,  'first seeded server must mount as a tile').toHaveCount(1);
        await expect(secondTile, 'second seeded server must mount as a tile').toHaveCount(1);

        // Contract 3: each `[data-testid="server-host"]` shows the SSR
        // `IP:port` fallback while the hydration call is in flight.
        // The helper has already set the tile's busy state but the
        // host slot keeps the SSR text until the response lands.
        const firstHost  = firstTile.locator('[data-testid="server-host"]');
        const secondHost = secondTile.locator('[data-testid="server-host"]');
        await expect(firstHost,  'first tile SSR fallback must show IP:port').toHaveText(`${firstSeeded.ip}:${firstSeeded.port}`);
        await expect(secondHost, 'second tile SSR fallback must show IP:port').toHaveText(`${secondSeeded.ip}:${secondSeeded.port}`);

        // The `data-fallback` attribute is the re-paint source the
        // helper falls back to on probe error. Pin it as a separate
        // assertion so a regression that drops the attribute (and
        // breaks the offline-recovery re-paint) fails here.
        await expect(firstHost,  'first tile must carry data-fallback for offline re-paint').toHaveAttribute('data-fallback', `${firstSeeded.ip}:${firstSeeded.port}`);
        await expect(secondHost, 'second tile must carry data-fallback for offline re-paint').toHaveAttribute('data-fallback', `${secondSeeded.ip}:${secondSeeded.port}`);

        // Contract 4: release the stub and assert each host slot
        // flips to the live hostname. Playwright's `toHaveText`
        // auto-waits, so we don't need a `setTimeout` between the
        // release and the assertion.
        if (releaseRoute) releaseRoute();
        else throw new Error('releaseRoute was never wired by the route handler');

        await expect(firstHost,  'first tile must flip to live hostname').toHaveText(STUB_HOSTNAMES[firstSeeded.sid]);
        await expect(secondHost, 'second tile must flip to live hostname').toHaveText(STUB_HOSTNAMES[secondSeeded.sid]);
    });

    test('a disabled server still renders as a tile (with the Disabled pill) but is skipped by the hydration probe', async ({ page, isMobile }) => {
        test.skip(isMobile, 'desktop is the canonical surface; the data-server-skip gate is shape-agnostic');

        await truncateE2eDb();

        // Seed one enabled + one disabled server bound to the same
        // group. The disabled server should still render (the
        // bound-but-disabled relationship is useful operator context)
        // but ride the `data-server-skip="1"` short-circuit so the
        // helper's loadTile() returns early before firing the per-tile
        // probe. Mirrors the sibling contract in
        // `page_admin_servers_list.tpl`.
        const seed: ServerGroupSeedResult = await seedServerGroupWithServersE2e(
            'e2e-admin-groups-1406-disabled',
            [
                { ip: '203.0.113.30', port: 27015 /* enabled defaults true */ },
                { ip: '203.0.113.40', port: 27016, enabled: false },
            ],
        );
        expect(seed.servers, 'shim must return both seeded servers').toHaveLength(2);
        const [enabledSeeded, disabledSeeded] = seed.servers;
        expect(enabledSeeded.enabled,  'first seeded server must be enabled').toBe(true);
        expect(disabledSeeded.enabled, 'second seeded server must be disabled').toBe(false);

        // Count `servers.host_players` requests per sid so we can
        // prove the probe fires exactly once (for the enabled tile)
        // and NEVER for the disabled tile. The hostname stub also
        // gates the assertions on a deterministic envelope so the
        // enabled tile's hostname flip is verifiable.
        const probeCallsBySid: Record<number, number> = {};
        const probedHostname = 'alpha-zero (live)';
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
            const sid = Number(payload.params?.sid ?? 0);
            probeCallsBySid[sid] = (probeCallsBySid[sid] ?? 0) + 1;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    data: {
                        sid, ip: '203.0.113.99', port: 27015,
                        hostname: probedHostname,
                        players: 0, maxplayers: 24,
                        map: '', mapfull: '', mapimg: '',
                        os_class: 'fab fa-linux', secure: true,
                        player_list: [], can_ban: false,
                    },
                }),
            });
        });

        await page.goto(ADMIN_GROUPS_ROUTE);

        const groupRow = page.locator(`[data-testid="server-group-row"][data-id="${seed.gid}"]`);
        await expect(groupRow, 'seeded group row must mount').toBeVisible();

        // Both tiles must surface in the DOM — disabled servers stay
        // visible per the documented operator-context contract.
        const tiles = groupRow.locator('[data-testid="server-tile"]');
        await expect(tiles, 'both seeded servers must render as tiles (the disabled one is not silently dropped)').toHaveCount(2);

        const enabledTile  = groupRow.locator(`[data-testid="server-tile"][data-id="${enabledSeeded.sid}"]`);
        const disabledTile = groupRow.locator(`[data-testid="server-tile"][data-id="${disabledSeeded.sid}"]`);

        // The disabled tile must carry `data-server-skip="1"` — this
        // is the load-bearing gate the hydration helper short-circuits
        // on. Without it the helper would fire a pointless probe
        // against a server the panel knows is offline by config.
        await expect(disabledTile, 'disabled tile must carry data-server-skip="1" so the helper skips it').toHaveAttribute('data-server-skip', '1');
        // The enabled tile must NOT carry the skip attribute — pin
        // both halves so a regression that emits skip="1" unconditionally
        // (and breaks hydration entirely) fails fast.
        await expect(enabledTile, 'enabled tile must NOT carry data-server-skip').not.toHaveAttribute('data-server-skip', '1');

        // Visible "Disabled" pill — the affordance that explains
        // WHY the disabled row stays at the SSR IP:port. Without
        // this an admin would reasonably wonder if the probe failed.
        const disabledPill = disabledTile.locator('[data-testid="server-disabled-tag"]');
        await expect(disabledPill, 'disabled tile must surface the Disabled pill').toBeVisible();
        await expect(disabledPill, 'pill copy must read "Disabled"').toHaveText('Disabled');
        // The enabled tile must NOT carry the pill.
        await expect(
            enabledTile.locator('[data-testid="server-disabled-tag"]'),
            'enabled tile must NOT carry the Disabled pill',
        ).toHaveCount(0);

        // The enabled tile's host slot must flip to the live hostname
        // — proves the helper DID fire for the enabled tile (and
        // implicitly that the disabled tile's skip didn't break the
        // enabled tile's hydration).
        await expect(
            enabledTile.locator('[data-testid="server-host"]'),
            'enabled tile must flip to live hostname',
        ).toHaveText(probedHostname);

        // The disabled tile's host slot must stay at the SSR IP:port
        // forever — the helper short-circuited before firing the
        // probe so nothing patched the inner-text.
        await expect(
            disabledTile.locator('[data-testid="server-host"]'),
            'disabled tile must stay at the SSR IP:port (helper skipped the probe)',
        ).toHaveText(`${disabledSeeded.ip}:${disabledSeeded.port}`);

        // Probe-call gate: exactly ONE `servers.host_players` POST
        // landed on the route, and it was for the enabled sid. The
        // disabled sid must have zero probes. This is the contract
        // that the `data-server-skip="1"` gate ACTUALLY saves
        // round-trips — without it the count would be 2.
        expect(
            probeCallsBySid[enabledSeeded.sid] ?? 0,
            'enabled tile should produce exactly one Actions.ServersHostPlayers probe',
        ).toBe(1);
        expect(
            probeCallsBySid[disabledSeeded.sid] ?? 0,
            'disabled tile MUST NOT trigger any Actions.ServersHostPlayers probe (data-server-skip gate)',
        ).toBe(0);
    });

    test('a dangling :prefix_servers_groups row pointing at a deleted server is silently dropped (INNER JOIN contract)', async ({ page, isMobile }) => {
        test.skip(isMobile, 'desktop is the canonical surface; the SQL contract is shape-agnostic');

        await truncateE2eDb();

        // Seed two servers bound to the group, then DELETE one via
        // the dedicated shim that bypasses `api_servers_remove`'s
        // cleanup cascade. The shim writes a raw
        // `DELETE FROM :prefix_servers WHERE sid = ?` and leaves
        // the `:prefix_servers_groups` row in place — which is what
        // produces the orphan condition the admin Server Groups
        // page's INNER JOIN is supposed to swallow.
        const seed: ServerGroupSeedResult = await seedServerGroupWithServersE2e(
            'e2e-admin-groups-1406-dangling',
            [
                { ip: '203.0.113.50', port: 27015 },
                { ip: '203.0.113.60', port: 27016 },
            ],
        );
        expect(seed.servers, 'shim must return both seeded servers').toHaveLength(2);
        const [doomedSeeded, survivingSeeded] = seed.servers;

        // Delete the first server. The `:prefix_servers_groups` row
        // pointing at its sid stays in place — the schema has no
        // ON DELETE CASCADE and the dispatcher's cleanup is the
        // ONLY production path that removes the membership row.
        const deleteResult = await deleteServerE2e(doomedSeeded.sid);
        expect(deleteResult.deleted, 'doomed server must have actually been deleted').toBe(1);

        // Stub `Actions.ServersHostPlayers` so the surviving tile's
        // probe gets a deterministic envelope — not the assertion
        // target here, but keeps the chrome quiet (no error toast
        // surfacing in the run).
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
            const sid = Number(payload.params?.sid ?? 0);
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    data: {
                        sid, ip: '203.0.113.99', port: 27015,
                        hostname: 'surviving-server (live)',
                        players: 0, maxplayers: 24,
                        map: '', mapfull: '', mapimg: '',
                        os_class: 'fab fa-linux', secure: true,
                        player_list: [], can_ban: false,
                    },
                }),
            });
        });

        await page.goto(ADMIN_GROUPS_ROUTE);

        const groupRow = page.locator(`[data-testid="server-group-row"][data-id="${seed.gid}"]`);
        await expect(groupRow, 'seeded group row must mount').toBeVisible();

        // Contract: the INNER JOIN against `:prefix_servers` drops
        // the dangling membership row, so the card body should
        // render EXACTLY ONE tile (the surviving server) — not the
        // orphan, not nothing, not a broken empty tile. The
        // doomed sid's tile MUST NOT mount because there is no
        // matching `:prefix_servers` row for the JOIN to resolve.
        const tiles = groupRow.locator('[data-testid="server-tile"]');
        await expect(
            tiles,
            'the dangling membership row must be silently dropped by the INNER JOIN — only the surviving server renders',
        ).toHaveCount(1);
        await expect(
            groupRow.locator(`[data-testid="server-tile"][data-id="${doomedSeeded.sid}"]`),
            'the deleted server must NOT surface as a tile (no orphan tile)',
        ).toHaveCount(0);
        await expect(
            groupRow.locator(`[data-testid="server-tile"][data-id="${survivingSeeded.sid}"]`),
            'the surviving server must render as the one remaining tile',
        ).toHaveCount(1);

        // The surviving tile's host slot still flips to the live
        // hostname — proves the JOIN's filter didn't accidentally
        // also drop the surviving server (a regression that
        // swapped INNER JOIN for an over-eager LEFT JOIN + WHERE
        // condition that nulled both rows would fail here).
        await expect(
            groupRow.locator(`[data-testid="server-tile"][data-id="${survivingSeeded.sid}"]`)
                    .locator('[data-testid="server-host"]'),
            'surviving tile must flip to live hostname (proves the JOIN kept the right row)',
        ).toHaveText('surviving-server (live)');
    });
});
