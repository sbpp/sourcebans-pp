/**
 * Flow: per-row hostname hydration on the Add Admin page's
 * "Individual servers" access checkbox grid (#1405).
 *
 * Background
 * ----------
 * The Add Admin sub-route (`?p=admin&c=admins&section=add-admin`)
 * renders a multi-row per-server access checkbox grid (one row per
 * `:prefix_servers` entry). Each row used to show the bare
 * `IP:port` inside a `<span id="sa{$server.sid}">…</span>` that
 * the legacy v1.4.11 `<script>LoadServerHost('SID', 'id', 'saSID');…</script>`
 * blob async-replaced with the live hostname from each server's
 * A2S probe. `LoadServerHost` was deleted with `sourcebans.js` at
 * #1123 D1 (silent `ReferenceError` per server per page load) and
 * sister cleanup PR #1404 dropped the dead `<script>` blob + the
 * orphan View property.
 *
 * This issue (#1405) is the additive replacement: the per-row span
 * now carries `[data-testid="server-host"]` + a `data-fallback`
 * IP:port, the wrapping `<div>` opts into the shared
 * `web/scripts/server-tile-hydrate.js` helper via
 * `data-server-hydrate="auto"` + `data-trunchostname="40"`, and the
 * helper fires `Actions.ServersHostPlayers` per row to patch the
 * live hostname into the slot via `sb.setHTML`. The SSR-rendered
 * `IP:port` stays as the no-JS / cache-cold fallback (and
 * `data-fallback` lets the helper repaint it on probe failure so
 * the row never goes blank).
 *
 * The PHPUnit guard at
 * `web/tests/integration/AddAdminServerHostHydrationTest.php` pins
 * the static contract (the canonical testids ship in the template,
 * the View carries no orphan property, the legacy `id="sa…"` /
 * `LoadServerHost(` literals stay dead). This spec pins the runtime
 * contract: the bare IP:port renders pre-hydration, the helper
 * fires the JSON action per row, and the rows flip to the canned
 * hostname after the stubbed envelope resolves.
 *
 * Mirrors `server-map-thumbnail.spec.ts` for the stub shape (full
 * envelope mirror of `api_servers_host_players` — the helper
 * doesn't know it's talking to a stub) and `server-refresh-debounce.spec.ts`
 * for the "seed via the JSON API" + "anchor on terminal attributes"
 * primitives. No `setTimeout` waits — every assertion anchors on
 * what Playwright auto-waits on (visibility, attribute, text
 * content).
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). Per AGENTS.md "Playwright E2E
 * specifics" the suite shares a single `sourcebans_e2e` DB across
 * projects and `workers: 1` in CI — but the local default cpu-count
 * worker shape would still let a sibling spec's truncate race the
 * Apache reads this spec needs. The browser-shape coverage is
 * irrelevant here: the hydration helper is identical on both form
 * factors and the underlying `Actions.ServersHostPlayers` round-trip
 * is server-side.
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

/**
 * Seed two enabled servers via `Actions.ServersAdd`. Mirrors
 * `seedServerViaApi` in `server-map-thumbnail.spec.ts`: drives the
 * same PHP dispatcher (CSRF + permissions + handler) that production
 * traffic uses, so a future contract drift on `servers.add` is
 * caught here along with the actual surface under test.
 *
 * Both seeded IPs are in the documentation-only `192.0.2.0/24`
 * block (RFC 5737) — guarantees the live UDP probe (if anything
 * managed to escape the route mock) would route to nowhere. Two
 * rows so the spec asserts the helper fans out per-tile (one POST
 * per row, distinct hostnames per row) rather than coincidentally
 * happening to render right with a single row.
 */
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
                    // mid=1 (Half-Life 2 DM) is the first row data.sql
                    // seeds into `:prefix_mods`, so it's always available
                    // without a paired mod insert.
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

/**
 * Install a `page.route()` stub that intercepts every
 * `Actions.ServersHostPlayers` JSON dispatcher call and answers
 * with a per-sid deterministic envelope. The dispatcher endpoint is
 * `./api.php` (per `web/scripts/api.js`), resolved against the
 * page's base URL.
 *
 * Records the per-sid request payload + the trunchostname hint
 * (`hostnames` array) so the spec can assert the helper fans out
 * per-row AND forwards the `data-trunchostname="40"` opt-in. Other
 * actions on the page (CSRF, chrome bootstrap, palette) are passed
 * through untouched — only `servers.host_players` is intercepted.
 */
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

        // Mirror `api_servers_host_players`'s envelope shape exactly
        // (sid + ip + port + hostname + players + maxplayers + map +
        // mapfull + mapimg + os_class + secure + player_list +
        // can_ban). The hydration helper reads `r.data.hostname` for
        // the `[data-testid="server-host"]` slot; every other key is
        // present so the helper's other feature-detection branches
        // don't trip on undefined.
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

test.describe('flow: Add Admin per-server access list hostname hydration (#1405)', () => {
    // Skip mobile-chromium at the `beforeEach` boundary so the
    // truncate inside the test never fires on that worker. The
    // browser-shape coverage is irrelevant here (the hydration
    // helper + `Actions.ServersHostPlayers` round-trip are identical
    // on both form factors); the file-level rationale + the
    // `server-refresh-debounce.spec.ts` precedent both name the
    // truncate-vs-Apache race against `sourcebans_e2e` as the load-
    // bearing reason for skipping. Without this skip, both
    // chromium and mobile-chromium workers would call
    // `truncateE2eDb` concurrently — the MySQL named lock in
    // `Sbpp\Tests\Fixture::truncateAndReseed` serializes the
    // truncates correctly, but the second truncate still wipes the
    // admin row mid-flight from the first worker's API calls and
    // we get `forbidden / No access` cascades on
    // `Actions.ServersAdd`. Skipping at `beforeEach` is the
    // canonical pattern — the skip evaluation runs BEFORE the
    // truncate so the mobile worker's beforeEach short-circuits.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e (see file-level comment).',
        );
    });

    // Single test consolidates two contracts (success-path hydration
    // + failure-branch fallback) so the seed-and-navigate setup fires
    // once and the file stays single-test under `fullyParallel: true`.
    // The contracts are linearly ordered (you have to hydrate
    // successfully first to prove the helper fires per row, then re-
    // route to the failure shape and assert the row stays informative)
    // and share the same two seeded servers + the same Add Admin page
    // render, so consolidation is the natural fit. See the
    // `server-refresh-debounce.spec.ts` file-level comment for the
    // canonical rationale.
    test('per-server access rows hydrate live hostnames on success and fall back to IP:port on probe failure', async ({ page }) => {
        await truncateE2eDb();

        const seeded = await seedTwoServersViaApi(page);

        // --- Contract 1: success-path hydration ---
        const record = { sids: [] as number[], trunchints: [] as Array<number | string | undefined> };
        await stubHostPlayersForSeeded(page, seeded, record);

        await page.goto(ADD_ADMIN_ROUTE);

        // 1.a: BOTH server rows are in the DOM under the canonical
        // testids the integration test pins.
        for (const s of seeded) {
            const tile = page.locator(`[data-testid="server-tile"][data-id="${s.sid}"]`);
            await expect(tile, `[data-testid="server-tile"][data-id="${s.sid}"] should be in the DOM`).toHaveCount(1);
        }

        // 1.b: each row's `data-fallback` is `<ip>:<port>` (pure SSR,
        // synchronous). This pins the no-JS / cache-cold path even
        // if the hydration helper never fires.
        for (const s of seeded) {
            const host = page
                .locator(`[data-testid="server-tile"][data-id="${s.sid}"]`)
                .locator('[data-testid="server-host"]');
            await expect(host, `data-fallback must be "<ip>:<port>"`).toHaveAttribute(
                'data-fallback',
                `${s.ip}:${s.port}`,
            );
        }

        // 1.c: the hostname slot eventually flips to the canned
        // hostname for every seeded row. Playwright auto-waits on
        // `toHaveText`; once the helper's per-row POST lands the
        // helper calls `sb.setHTML(d.hostname)` on the slot and the
        // text updates.
        for (const s of seeded) {
            const host = page
                .locator(`[data-testid="server-tile"][data-id="${s.sid}"]`)
                .locator('[data-testid="server-host"]');
            await expect(host, `row sid=${s.sid} should hydrate to canned hostname`).toHaveText(
                s.hostname,
            );
        }

        // 1.d: the helper fired EXACTLY one POST per seeded row (no
        // per-row over-fetch / amplification), and each POST
        // forwarded `trunchostname=40` per the template opt-in.
        // Without the `data-trunchostname="40"` opt-in the hint
        // would default to 70 (the public list's column budget),
        // which would silently let long hostnames overflow the Add
        // Admin's 18rem column.
        const seededSids = seeded.map((s) => s.sid).sort((a, b) => a - b);
        const seenSids = [...record.sids].sort((a, b) => a - b);
        expect(
            seenSids,
            'helper must fire one Actions.ServersHostPlayers POST per seeded row, no more, no less',
        ).toEqual(seededSids);
        // `trunchostname` is forwarded by the helper as either a
        // numeric `40` or the stringified `"40"` depending on how
        // the helper marshals attribute reads; the contract is "the
        // forwarded value coerces to 40", not the exact JS type.
        for (const hint of record.trunchints) {
            expect(
                Number(hint),
                `each POST must forward trunchostname=40 (saw ${JSON.stringify(hint)})`,
            ).toBe(40);
        }

        // --- Contract 2: failure-branch fallback ---
        // Swap the route stub from the success envelope to an
        // error envelope, then re-navigate so the helper fires
        // fresh per-row probes against the new stub. The row's
        // SSR `<ip>:<port>` text MUST remain readable — the
        // hydration helper's `!r.ok` branch (server-tile-hydrate.js
        // lines 414-417) sets `data-status="offline"` without
        // touching the host slot, so the SSR text stays. A
        // regression that blanked the slot on probe failure would
        // fail this assertion.
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

        // Every row's hostname slot must end at the IP:port fallback
        // (NOT blank). The terminal anchor is `data-status="offline"`
        // — set by the helper's `!r.ok` branch — so we wait on that
        // first, then assert the text. Without the status anchor the
        // text assertion could pass during the brief loading window
        // before the stub responds (the SSR text reads IP:port too)
        // and miss a regression that blanked the slot on probe
        // failure (the regression would only surface AFTER the
        // failure response landed). Anchoring on the post-failure
        // `data-status` attribute pins the right point in the
        // lifecycle.
        for (const s of seeded) {
            const tile = page.locator(`[data-testid="server-tile"][data-id="${s.sid}"]`);
            await expect(tile).toHaveAttribute('data-status', 'offline');
            const host = tile.locator('[data-testid="server-host"]');
            await expect(host).toHaveText(`${s.ip}:${s.port}`);
        }
    });
});
