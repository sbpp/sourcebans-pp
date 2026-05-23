/**
 * Flow: the kickit iframe (`pages/admin.kickit.php`) resolves its
 * `sb.api.call(...)` fetch against the panel-root `/api.php` (NOT
 * `/pages/api.php`) — the load-bearing fix for #1433 bugs 1 + 2.
 *
 * Background
 * ----------
 *
 * Pre-#1433 `web/scripts/api.js` shipped `endpoint: './api.php'` as
 * the load-bearing default. Document-relative resolution lands the
 * fetch on `/api.php` for top-level panel renders (the panel chrome's
 * pages all live at `/index.php?p=…` so `./api.php` resolves against
 * `/`), but the iframe-routed surfaces — `pages/admin.kickit.php` and
 * `pages/admin.blockit.php` — sit one directory deep. The iframe
 * document URL is `/pages/admin.kickit.php`, so the bare `./api.php`
 * resolved against the iframe document URL is `/pages/api.php`, which
 * Apache does not rewrite (`docker/apache/sbpp-prod.conf` exposes the
 * dispatcher only at `/api.php`). Every fetch from the iframe got
 * 404, api.js's fetch resolved to a `bad_response`-shaped envelope
 * (`{ ok: false, error: { code: 'bad_response', … } }`), the iframe's
 * load handler short-circuits on `!r.ok` with `return` (the silent
 * early-return path in `page_kickit.tpl`), and every row stayed at
 * the initial "Waiting…" text forever. Player was never kicked — the
 * #1433 user-reported symptom.
 *
 * Fix
 * ---
 *
 * api.js now resolves the endpoint against
 * `document.currentScript.src` (captured at script-load time — the
 * value is null inside async handlers/promises, so the IIFE caches
 * it at the top). `new URL('../api.php', SCRIPT_SRC).href` lands on
 * the panel-root `/api.php` for both top-level page renders AND for
 * iframe contexts AND for subdir installs. The static gate is
 * `web/tests/integration/ApiJsEndpointResolutionTest.php`; this
 * runtime gate is the end-to-end contract that proves the algebra
 * holds in a real browser context.
 *
 * What this spec asserts
 * ----------------------
 *
 *  - The kickit iframe (loaded directly at
 *    `/pages/admin.kickit.php?check=…&type=0`) fires its bootstrap
 *    `Actions.KickitLoadServers` POST to a URL whose pathname is
 *    exactly `/api.php` — NOT `/pages/api.php`.
 *  - The fetch resolves to a real envelope (not 404), so the load
 *    handler's silent early-return doesn't fire and rows transition
 *    away from the initial "Waiting…" text. We anchor on the
 *    `data-testid="kickit-status-0"` row's text change rather than
 *    on a `setTimeout`-shaped wait (per AGENTS.md
 *    "Anti-patterns" → no `waitForTimeout`).
 *
 * Implementation notes
 * --------------------
 *
 *  - We seed one enabled server via `Actions.ServersAdd` so the
 *    kickit handler renders at least one row. The seeded IP is in
 *    the RFC 5737 documentation block (`203.0.113.0/24`); no live
 *    A2S probe can ever answer, so the kickit RCON path would
 *    deterministically return `no_connect` — but we stub the
 *    handler anyway via `page.route` so the spec doesn't depend on
 *    UDP timing.
 *  - We intercept BOTH `kickit.load_servers` (the bootstrap call —
 *    the one whose URL we're proving) AND `kickit.kick_player` (the
 *    per-row follow-up). Stubbing both keeps the spec deterministic
 *    and short.
 *  - Critically, the route matcher is the `/api.php` pathname — we
 *    WANT to see the URL of every request that lands here. If the
 *    pre-#1433 regression returned, the request would target
 *    `/pages/api.php` and our matcher wouldn't fire. So we ALSO
 *    listen on `page.on('requestfinished', …)` for any POST whose
 *    URL pathname ends with `/api.php` and assert the captured
 *    pathname is exactly `/api.php` (not `/pages/api.php`). The
 *    pair of checks (route fires AND captured URL is panel-root)
 *    closes the contract from both sides.
 *  - The page-level `<script>` block calls
 *    `parent.document.getElementById('dialog-control')` / `srvkicker`
 *    — both are null when we load the page directly (no parent
 *    iframe wrapper). The script handles null returns defensively
 *    so we don't need to scaffold a fake parent.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const KICKIT_URL = '/pages/admin.kickit.php?check=STEAM_0%3A0%3A1&type=0';

test.describe('flow: kickit iframe API call lands on /api.php, not /pages/api.php (#1433)', () => {
    // Skip on mobile-chromium: the contract is browser-shape-agnostic
    // (the resolution lives in `web/scripts/api.js` and runs identically
    // on both form factors) and the second project would double the
    // truncate-and-reseed traffic against `sourcebans_e2e` per the
    // pattern documented in `server-refresh-debounce.spec.ts`. Local
    // dev runs `workers: undefined` and a mid-truncate Apache request
    // against the shared DB would race.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e.',
        );
    });

    test('KickitLoadServers POST targets the panel-root /api.php and rows update', async ({ page }) => {
        await truncateE2eDb();

        // Seed one enabled server so the kickit handler renders a row.
        // RFC 5737 documentation IP; no live A2S probe can answer (we
        // stub the JSON dispatcher response below anyway).
        await page.goto('/index.php?p=admin&c=servers&section=add');
        const addEnvelope = await page.evaluate(async () => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            params: Record<string, unknown>,
                        ) => Promise<{ ok: boolean; data?: { sid?: number } }>;
                    };
                };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.ServersAdd, {
                ip:      '203.0.113.1',
                port:    '27015',
                rcon:    'stub-rcon-password',
                rcon2:   'stub-rcon-password',
                // mid=1 (Half-Life 2 DM) is the first row data.sql seeds
                // into `:prefix_mods`, so it's always available without
                // a paired mod insert.
                mod:     1,
                enabled: true,
                group:   '0',
            });
        });
        expect(addEnvelope, 'servers.add envelope should round-trip ok').toMatchObject({ ok: true });
        const sid = (addEnvelope as { data: { sid: number } }).data.sid;

        // Capture every POST to *any* path ending in `/api.php`. The
        // `requestfinished` listener fires regardless of whether the
        // request was routed by `page.route` below — pre-#1433 the
        // request would target `/pages/api.php` and we want to see that
        // surface here too (so we can assert the contract is "URL ends
        // with /api.php, NOT /pages/api.php"). If we only listened on
        // `page.route(**\/api.php**)` we'd miss the regression.
        // We also capture the action name from the JSON body so the
        // post-load assertions can pin BOTH expected calls
        // (`kickit.load_servers` and `kickit.kick_player`) rather
        // than just "at least one POST to /api.php landed", which
        // would silently pass on a regression where load_servers
        // succeeded but kick_player went to /pages/api.php (or
        // vice versa).
        const apiPostUrls: string[] = [];
        const apiActions = new Set<string>();
        page.on('requestfinished', (req) => {
            if (req.method() !== 'POST') return;
            const path = new URL(req.url()).pathname;
            // Match any path that ends in `/api.php` OR `/pages/api.php`
            // — both are interesting for the contract assertion.
            if (path.endsWith('/api.php')) {
                apiPostUrls.push(req.url());
                try {
                    const body = JSON.parse(req.postData() ?? '{}');
                    if (typeof body?.action === 'string') {
                        apiActions.add(body.action);
                    }
                } catch {
                    // Malformed JSON body — not interesting for the
                    // action-tracking branch; the URL is still captured
                    // above for the pathname assertion.
                }
            }
        });

        // Stub the JSON dispatcher endpoint so the spec doesn't depend
        // on a real RCON round-trip. We match on the panel-root
        // `/api.php` pathname; if the pre-#1433 regression returned,
        // the iframe's fetch would target `/pages/api.php` and miss
        // this route entirely — Apache would 404 it and the JS would
        // see a `bad_response` envelope (the exact pre-fix symptom).
        // The `requestfinished` listener above catches BOTH paths, so
        // we'll still see the captured URL and the assertions below
        // can name which side broke.
        await page.route(
            (url) => url.pathname === '/api.php',
            async (route) => {
                const req = route.request();
                if (req.method() !== 'POST') {
                    await route.continue();
                    return;
                }
                let payload: { action?: string; params?: Record<string, unknown> } = {};
                try {
                    payload = JSON.parse(req.postData() ?? '{}');
                } catch {
                    await route.continue();
                    return;
                }

                if (payload.action === 'kickit.load_servers') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({
                            ok: true,
                            data: {
                                servers: [
                                    { num: 0, sid, has_rcon: true },
                                ],
                            },
                        }),
                    });
                    return;
                }
                if (payload.action === 'kickit.kick_player') {
                    // `not_found` — well-formed terminal envelope; the
                    // iframe's load handler flips row 0's status text
                    // to "Player not found." (matches the legacy
                    // copy in `page_kickit.tpl`'s `processRow` arm).
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({
                            ok: true,
                            data: {
                                status:   'not_found',
                                sid,
                                num:      0,
                                hostname: 'stub.example.com',
                                ip:       '203.0.113.1',
                                port:     '27015',
                            },
                        }),
                    });
                    return;
                }
                await route.continue();
            },
        );

        // Navigate directly to the iframe URL. The browser loads
        // `/pages/admin.kickit.php` as a top-level document, then
        // pulls in `../scripts/api.js` (resolves to `/scripts/api.js`),
        // and api.js's `resolveEndpoint()` returns
        // `http://<host>/api.php` (NOT `http://<host>/pages/api.php`).
        await page.goto(KICKIT_URL);

        // Anchor on the row's status text transitioning away from
        // the initial "Waiting…" placeholder. If api.js's endpoint
        // resolution were broken, this would never fire — the bare
        // `./api.php` would 404 against `/pages/api.php` and the
        // iframe's silent early-return would leave the row at
        // "Waiting…" indefinitely. Playwright auto-waits on the
        // matcher so no `setTimeout` is needed.
        const row0Status = page.locator('[data-testid="kickit-status-0"]');
        await expect(row0Status).toContainText('Player not found.');

        // Both API actions must have round-tripped (the kickit flow
        // is `kickit.load_servers` followed by `kickit.kick_player`
        // per row), and every URL must target the panel-root
        // `/api.php`. The action-set assertion is the tighter
        // contract — without it, a regression where load_servers
        // succeeded but kick_player went to `/pages/api.php` (or
        // vice versa) would silently pass the "at least one POST
        // landed on /api.php" check below.
        expect(
            Array.from(apiActions),
            `Expected both kickit.load_servers AND kickit.kick_player to POST to /api.php (saw: ${Array.from(apiActions).join(', ') || 'none'}). ` +
            `If only one of the two landed, the contract regressed on the action that's missing.`,
        ).toEqual(expect.arrayContaining(['kickit.load_servers', 'kickit.kick_player']));

        for (const url of apiPostUrls) {
            const path = new URL(url).pathname;
            expect(
                path,
                `Pre-#1433 regression: API call from kickit iframe should target /api.php (panel-root), not ${path}. ` +
                `If this fires on /pages/api.php, api.js's endpoint resolution has been "simplified" back to the broken literal — see ApiJsEndpointResolutionTest.`,
            ).toBe('/api.php');
        }
    });
});
