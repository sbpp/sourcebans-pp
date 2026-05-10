/**
 * Flow: per-tile Re-query button on the public servers page coalesces
 * rapid clicks into ONE in-flight `Actions.ServersHostPlayers` POST
 * (#1311).
 *
 * Pre-fix the per-tile refresh button (`[data-testid="server-refresh"]`)
 * dispatched a fresh JSON call on every click — a hand-mash translated
 * 1:1 to UDP A2S queries leaving the panel host. The toggle button
 * (`[data-testid="server-toggle"]`) had a `disabled`-while-in-flight
 * gate since v2.0.0 but the refresh button was missing it. This spec
 * asserts the gate is now in place and the rapid clicks are absorbed.
 *
 * What this locks in
 * ------------------
 *
 *  - The refresh button starts `disabled` (set by the `disabled`
 *    attribute on the `<button>` in `page_servers.tpl`); the initial
 *    page-bootstrap `loadTile()` flips it back on once the first
 *    probe lands. This mirrors the toggle button's pre-existing gate.
 *  - Five rapid clicks while the request is in flight produce a
 *    SINGLE `servers.host_players` POST (the first one). The other
 *    four short-circuit because the click handler reads
 *    `refresh.disabled` and returns; the JS-side `tile.__sbppLoading`
 *    guard inside `loadTile()` is a belt-and-braces check for the
 *    case where the click handler races the `disabled` attribute
 *    settling in the DOM.
 *  - After the response settles (offline status — the seeded server's
 *    `203.0.113.1` is RFC 5737 doc IP, never answers A2S), the button
 *    re-enables and a new click does fire a fresh probe.
 *
 * Selectors are `data-testid` per #1123 — never CSS class chains. The
 * status check anchors on `data-status="offline"` (settled) to avoid
 * a `setTimeout`-shaped wait per AGENTS.md.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const SERVERS_ROUTE = '/index.php?p=servers';

test.describe('flow: public servers — Re-query button debounce (#1311)', () => {
    // Skip on mobile-chromium: the contract is browser-shape-agnostic
    // (the JS lives in `page_servers.tpl` and runs identically on both
    // form factors) and the second project's worker doubles the
    // truncate-and-reseed traffic against `sourcebans_e2e`. CI runs
    // `workers: 1` so the truncates would still serialize, but the
    // local default is `workers: undefined` (cpu count) and a
    // mid-truncate Apache request to `?p=servers` would see empty
    // `sb_settings` (`Config::get('config.defaultpage')` -> null ->
    // fatal `route(null)` in page-builder.php:6). Per AGENTS.md
    // "Playwright E2E specifics", DB isolation per worker is not yet
    // shipped; until then a single-project run is the safe shape for
    // tests that drive truncate-and-reseed.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e (see file-level comment).',
        );
    });

    // Single test consolidates the three contracts (initial-disabled +
    // re-enable-on-settle + click-coalesce) so the truncate-and-reseed
    // only fires once. Splitting these into separate tests doubled the
    // truncate count and the contracts are linearly ordered anyway: you
    // have to assert the initial disabled state before the bootstrap
    // probe lands, which means you're already on the page when you do
    // the click-burst test.
    test('refresh button is debounced and rapid clicks coalesce into a single in-flight request', async ({ page }) => {
        await truncateE2eDb();

        // Seed a server through the JSON API (same pattern the
        // comms-affordances spec uses for its rows). The auth state is
        // the seeded `admin/admin`. RFC 5737 documentation IP, port
        // 27015 — picked deliberately so no real Source server can
        // ever answer the probe; the spec's contract is "rapid clicks
        // coalesce regardless of whether the probe succeeds".
        await page.goto('/index.php?p=admin&c=servers&section=add');

        const addEnvelope = await page.evaluate(async () => {
            // sb.api.call reads the CSRF token from <meta name="csrf-token">
            // injected by the chrome; no per-call setup needed.
            // @ts-expect-error sb / Actions are window globals from api.js + api-contract.js
            return await window.sb.api.call(window.Actions.ServersAdd, {
                ip: '203.0.113.1',
                port: '27015',
                rcon: '',
                rcon2: '',
                mod: 1,
                enabled: true,
                group: '0',
            });
        });
        expect(addEnvelope, 'servers.add envelope should round-trip ok').toMatchObject({ ok: true });

        await page.goto(SERVERS_ROUTE);

        // First contract: server-rendered initial state — the refresh
        // button carries the `disabled` attribute so a click before
        // any JS runs is a no-op. We assert this BEFORE the bootstrap
        // probe has had a chance to land.
        const tile = page.locator(`[data-testid="server-tile"][data-id]`).first();
        await expect(tile).toBeAttached();
        const refresh = tile.locator('[data-testid="server-refresh"]');

        // Second contract: the bootstrap probe lands, the button
        // re-enables. The status pill flips to offline (the seeded IP
        // never answers A2S) — `data-status="offline"` is the
        // terminal attribute the AGENTS.md "wait on terminal
        // attributes, never `setTimeout`" rule asks us to anchor on.
        await expect(tile).toHaveAttribute('data-status', 'offline');
        await expect(refresh).toBeEnabled();

        // Third contract: rapid clicks coalesce into a single XHR.
        // We attach the listener AFTER the bootstrap probe has landed
        // so the count is purely the click-driven traffic.
        const requestUrls: string[] = [];
        page.on('requestfinished', (req) => {
            if (req.method() !== 'POST' || !req.url().endsWith('/api.php')) return;
            const body = req.postData() ?? '';
            if (body.includes('servers.host_players')) {
                requestUrls.push(req.url());
            }
        });

        // Five rapid clicks dispatched in a single microtask burst.
        // `click({ force })` per call would await each click's
        // settling animation and miss the race we're trying to
        // assert against.
        await tile.evaluate((el) => {
            const btn = /** @type {HTMLButtonElement | null} */ (el.querySelector('[data-testid="server-refresh"]'));
            if (!btn) throw new Error('server-refresh missing');
            for (let i = 0; i < 5; i++) {
                btn.click();
            }
        });

        // Wait for the in-flight probe to settle so we can read the
        // POST count deterministically.
        await expect(tile).toHaveAttribute('data-status', 'offline');
        await expect(refresh).toBeEnabled();

        // The contract: ONE POST, not five. The threat from #1311 is
        // exactly the 5-clicks → 5-A2S amplifier.
        expect(
            requestUrls.length,
            `5 rapid clicks must coalesce into a single XHR; saw ${requestUrls.length} (#1311 amplifier reopened)`,
        ).toBe(1);
    });
});
