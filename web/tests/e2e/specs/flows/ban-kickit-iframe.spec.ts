/**
 * Flow: clicking "Add ban" on the admin form spawns a hidden
 * `#srvkicker` iframe pointing at `pages/admin.kickit.php` so the
 * banned player is actually KICKED from every connected server,
 * not just inserted as a DB row (#1441).
 *
 * Background
 * ----------
 *
 * `api_bans_add` returns a `kickit` envelope (`{check, type}`)
 * whenever `config.enablekickit` is on (the install default per
 * `web/install/includes/sql/data.sql` + `web/updater/data/130.php`).
 * The handler's contract is "the frontend MUST surface the kick
 * action somehow"; pre-#1441 the inline IIFE in
 * `web/themes/default/page_admin_bans_add.tpl` checked
 * `typeof window.ShowKickBox === 'function'` — a v1.x helper that
 * lived in `web/scripts/sourcebans.js`, deleted with the bulk file
 * at #1123 D1 (v2.0.0). The `typeof` test silently resolved to
 * `false` and the success branch fell through to a "Ban added"
 * toast while no live server ever kicked the banned player.
 *
 * Operators reported the symptom as "banning a player from the
 * panel doesn't kick them" — the DB carries the ban, every
 * subsequent connect attempt by the player IS rejected by the
 * SourceMod plugin's on-connect check, but the player's existing
 * session stays alive until their next disconnect. On a server
 * with a low player turnover (Counter-Strike rounds, TF2 12v12
 * pubs), this can mean the offending player griefs for another
 * 20-40 minutes before voluntarily disconnecting.
 *
 * Fix (#1441)
 * -----------
 *
 * Replace the dead `ShowKickBox` branch with the existing
 * comms.add -> blockit.php iframe pattern: synthesise a hidden
 * `#srvkicker` iframe pointing at `pages/admin.kickit.php?check=...
 * &type=...`. The iframe page enumerates enabled servers via
 * `Actions.KickitLoadServers` and fires `sm_kick` via rcon for
 * each via `Actions.KickitKickPlayer`. This mirrors the
 * blockit/gag flow one branch over (`page_admin_comms_add.tpl`
 * lines ~370-394) so the kick-on-ban and block-on-gag contracts
 * stay structurally symmetric.
 *
 * What this spec asserts
 * ----------------------
 *
 *  - kickit-enabled path: `Actions.BansAdd` returning a `kickit`
 *    envelope causes the page to create a hidden `<iframe
 *    id="srvkicker" style="display: none">` whose `src` is
 *    `pages/admin.kickit.php?check=<encoded-steam>&type=0`. The
 *    iframe document request fires at the URL we expect, AND the
 *    "Ban Added" success toast paints alongside it.
 *  - kickit-disabled path: `Actions.BansAdd` returning a
 *    `kickit: null` envelope (the `config.enablekickit = '0'`
 *    shape) does NOT create the iframe. The fallthrough toast
 *    still fires.
 *
 * Why this spec stays client-side-only
 * ------------------------------------
 *
 * The bug being fixed is purely in the parent template's
 * IIFE — "the JS that fires when bans.add returns a kickit
 * envelope". The iframe's INTERNAL behavior (its bootstrap
 * `Actions.KickitLoadServers` + `Actions.KickitKickPlayer`
 * round-trip) is exercised by `kickit-iframe.spec.ts` (the
 * topbar-context-menu surface that reaches the same
 * `pages/admin.kickit.php` document) and `KickitTest.php`
 * (PHPUnit), so this spec deliberately stubs
 * `pages/admin.kickit.php` at the network layer instead of
 * letting the iframe hit the live PHP / DB stack.
 *
 * The reason that matters: the live stack runs against the
 * shared `sourcebans_e2e` DB, and a sibling worker's
 * `truncateAndReseed()` (correctly serialized via the
 * `GET_LOCK`-backed named lock in `Sbpp\Tests\Fixture`) can
 * still wipe `:prefix_admins` between this test's parent-page
 * auth check and the iframe's auth check, leaving the iframe's
 * `UserManager::HasAccess()` call with `$this->admins[1]
 * === null` and the iframe document body reading "No Access"
 * (matching AGENTS.md "Playwright E2E specifics" → "missing
 * admin row during a reseed window -> `forbidden / No access`").
 * Stubbing the iframe URL decouples this spec from that race
 * surface AND keeps the iframe contract under our control —
 * the only thing we're asserting is "the parent JS spawned
 * an iframe with the right URL", which is the entire bug
 * surface.
 *
 *  - The route matcher uses `**\/api.php` (NOT
 *    `/pages/api.php`) so a regression that re-introduces the
 *    pre-#1433 endpoint resolution bug would surface here too —
 *    a broken endpoint resolver would target `/pages/api.php`,
 *    miss our route, and the parent form's `bans.add` POST
 *    would time out.
 */

import { expect, test } from '../../fixtures/auth.ts';

const ADD_BAN_ROUTE = '/index.php?p=admin&c=bans&section=add-ban';

const FIXTURE = {
    steam:    'STEAM_0:1:1441000',
    nickname: 'kickit-iframe-fixture',
    reason:   'Test reason for #1441 regression coverage',
} as const;

test.describe('flow: ban-add surfaces the kickit iframe (#1441)', () => {
    // Skip on mobile-chromium for the same reason as
    // `kickit-iframe.spec.ts`: the contract is browser-shape-agnostic
    // (lives in page_admin_bans_add.tpl's IIFE which runs identically
    // on both form factors).
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid doubling Apache contention.',
        );
    });

    test('kickit-enabled BansAdd response spawns hidden #srvkicker iframe with correct URL', async ({ page }) => {
        // Stub `pages/admin.kickit.php` at the network layer with
        // a tiny inert HTML body. Two contracts this gives us:
        //
        //  1. The iframe's HTTP request still fires AND its URL +
        //     query string are observable via `page.on('request')`
        //     and `page.on('response')`, so we can pin the exact
        //     URL the parent JS chose.
        //  2. The iframe never reaches `web/pages/admin.kickit.php`'s
        //     auth gate, so the test is completely insulated from
        //     the shared-DB truncate-and-reseed race that
        //     `Sbpp\Tests\Fixture` can't prevent across workers
        //     (the AGENTS.md `workers: 1` rule is the canonical
        //     answer, but we don't rely on it here — this spec is
        //     safe at any worker count).
        const iframeDocRequests: string[] = [];
        let kickitDocRequestUrl: string | null = null;
        await page.route('**/pages/admin.kickit.php**', async (route) => {
            const url = route.request().url();
            iframeDocRequests.push(url);
            if (kickitDocRequestUrl === null) {
                kickitDocRequestUrl = url;
            }
            await route.fulfill({
                status:      200,
                contentType: 'text/html; charset=UTF-8',
                // Tiny inert body — the parent JS never reads it,
                // the test never reads it, but a non-empty body
                // keeps the iframe in a "loaded" state so any
                // subsequent assertion that probes the iframe's
                // ready state behaves predictably.
                body:        '<!doctype html><html><head><title>kickit stub</title></head><body></body></html>',
            });
        });

        // Stub the parent form's `Actions.BansAdd`. Any other
        // action falls through to the real handler — but with
        // the iframe URL routed above, the iframe won't fire
        // any API calls at all (its inert body has no JS).
        let bansAddSeen = false;
        await page.route('**/api.php', async (route) => {
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

            if (body.action === 'bans.add') {
                bansAddSeen = true;
                await route.fulfill({
                    status:      200,
                    contentType: 'application/json',
                    body:        JSON.stringify({
                        ok:   true,
                        data: {
                            bid: 9999,
                            // `reload: false` keeps the parent
                            // page put for the duration of the
                            // test — the production reload-after-
                            // 2s contract is irrelevant to what
                            // we're asserting here AND would
                            // tear down the iframe before we
                            // could observe it. The reload
                            // semantics are pinned separately
                            // by `admin-ban-lifecycle.spec.ts`.
                            reload: false,
                            // `kickit` is the load-bearing envelope:
                            // its presence is what triggers the
                            // iframe spawn. Both `check` (the
                            // steam ID for Steam-type bans) and
                            // `type` (0 = Steam, 1 = IP) MUST be
                            // forwarded to the iframe URL via
                            // encodeURIComponent so a hostile
                            // value can't break out of the
                            // query string.
                            kickit: {
                                check: FIXTURE.steam,
                                type:  0,
                            },
                            message: null,
                        },
                    }),
                });
                return;
            }

            await route.continue();
        });

        await page.goto(ADD_BAN_ROUTE);
        const form = page.locator('[data-testid="addban-form"]');
        await expect(form).toBeVisible();

        await form.locator('[data-testid="addban-nickname"]').fill(FIXTURE.nickname);
        await form.locator('[data-testid="addban-steam"]').fill(FIXTURE.steam);
        // Default reason is the first dropdown option; we pick the
        // "other" path so the per-field reason custom input is the
        // value that lands on the server. This matches the existing
        // admin-ban-lifecycle.spec.ts shape.
        await form.locator('[data-testid="addban-reason"]').selectOption({ value: 'other' });
        await form.locator('[data-testid="addban-reason-custom"]').fill(FIXTURE.reason);

        await form.locator('[data-testid="addban-submit"]').click();

        // The kickit branch fires AFTER the stub responds. The
        // iframe creation is synchronous inside the `.then()`
        // callback, so the iframe element should exist as soon
        // as the api round-trip resolves.
        const iframe = page.locator('iframe#srvkicker');
        await expect(iframe).toBeAttached();

        // The iframe MUST be display: none — operators don't see
        // the kick-status table on the add-ban form (it's a
        // background side-effect). The check is via the inline
        // style attribute the JS sets, not the computed style
        // (which would also match a CSS-class-driven approach we
        // don't ship). Mirroring `page_admin_comms_add.tpl`'s
        // shape: `iframe.style.display = 'none'`.
        await expect(iframe).toHaveAttribute('style', /display:\s*none/);

        // The iframe URL must carry the encoded SteamID + type. We
        // anchor on the exact pattern so a regression that drops
        // either parameter (or swaps to a different query-string
        // shape that the kickit page doesn't consume) fails the
        // gate. `encodeURIComponent('STEAM_0:1:1441000')` ->
        // `STEAM_0%3A1%3A1441000`.
        const expectedSrcSuffix =
            `pages/admin.kickit.php?check=${encodeURIComponent(FIXTURE.steam)}&type=0`;
        await expect(iframe).toHaveAttribute(
            'src',
            new RegExp(`${expectedSrcSuffix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`),
        );

        // The iframe document request MUST have actually fired with
        // the URL we expect. This is the proof that the iframe was
        // mounted (not just attached as a DOM node with a src
        // attribute that the browser silently failed to fetch).
        await expect
            .poll(() => kickitDocRequestUrl, {
                message:
                    'The browser should have fired a GET against ' +
                    'pages/admin.kickit.php?check=...&type=0 to load the iframe document. ' +
                    'If this is null, the iframe was attached to the DOM but the browser ' +
                    'never tried to load its src — typically because the src URL is malformed.',
                timeout: 5_000,
            })
            .not.toBeNull();
        expect(kickitDocRequestUrl).toMatch(
            new RegExp(`${expectedSrcSuffix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`),
        );

        // The "Ban Added" toast paints inside the same kickit
        // branch (post-#1441 the literal title is 'Ban Added',
        // case-sensitive — matching comms.add's 'Block Added' AND
        // the kickit-disabled fallback's 'Ban Added', which was
        // standardised in the same PR per reviewer Finding #3).
        // The case-insensitive `/ban added/i` matcher is defensive
        // — any future renames (e.g., "Ban Created" / "Banned") will
        // surface here first; the iframe assertion above is the
        // strict pin for the kickit-enabled branch.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /ban added/i });
        await expect(successToast).toBeVisible();

        expect(bansAddSeen, 'Actions.BansAdd should have been called').toBe(true);
        // Strict-equality on the request count is the contract:
        // `setBusy(submitBtn, true)` is supposed to keep the
        // button busy through the kickit branch (matching the
        // comms.add shape — the operator can't queue a second
        // submit while rcon fans out at every server). A
        // regression that double-mounts the iframe (e.g., a
        // `.then()` chain that resolves twice, a `dispatchEvent`
        // re-firing the submit handler, a setBusy gap) MUST
        // surface here. `toBeGreaterThanOrEqual(1)` would have
        // silently passed.
        expect(
            iframeDocRequests.length,
            `pages/admin.kickit.php should be requested exactly once; saw ${iframeDocRequests.length}: ${JSON.stringify(iframeDocRequests)}`,
        ).toBe(1);
    });

    test('kickit-disabled BansAdd response does NOT spawn the iframe', async ({ page }) => {
        // Same iframe-route stub as the kickit-enabled test —
        // belt-and-suspenders so that if the iframe IS spawned in
        // error, the request is observable here too AND the
        // iframe still doesn't hit the live PHP stack.
        const iframeDocRequests: string[] = [];
        await page.route('**/pages/admin.kickit.php**', async (route) => {
            iframeDocRequests.push(route.request().url());
            await route.fulfill({
                status:      200,
                contentType: 'text/html; charset=UTF-8',
                body:        '<!doctype html><html><head><title>kickit stub</title></head><body></body></html>',
            });
        });

        let bansAddSeen = false;
        await page.route('**/api.php', async (route) => {
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
            if (body.action === 'bans.add') {
                bansAddSeen = true;
                // Mirror the kickit-disabled return shape from
                // `api_bans_add` — `kickit: null` + a message
                // envelope. The frontend's kickit branch MUST
                // skip on `kickit === null` and fall through to
                // the toast path.
                await route.fulfill({
                    status:      200,
                    contentType: 'application/json',
                    body:        JSON.stringify({
                        ok:   true,
                        data: {
                            bid:     9998,
                            reload:  false,
                            kickit:  null,
                            message: {
                                title: 'Ban Added',
                                body:  'The ban has been successfully added',
                                kind:  'green',
                                redir: 'index.php?p=admin&c=bans',
                            },
                        },
                    }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADD_BAN_ROUTE);
        const form = page.locator('[data-testid="addban-form"]');
        await expect(form).toBeVisible();

        await form.locator('[data-testid="addban-nickname"]').fill(FIXTURE.nickname);
        await form.locator('[data-testid="addban-steam"]').fill(FIXTURE.steam);
        await form.locator('[data-testid="addban-reason"]').selectOption({ value: 'other' });
        await form.locator('[data-testid="addban-reason-custom"]').fill(FIXTURE.reason);
        await form.locator('[data-testid="addban-submit"]').click();

        // The success toast must paint — proves the fallthrough
        // path actually ran instead of crashing on the kickit
        // branch's missing payload.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /ban added/i });
        await expect(successToast).toBeVisible();

        // The iframe MUST NOT be attached and the iframe
        // document URL MUST NOT be requested. Both halves are
        // pinned — `toHaveCount(0)` is the DOM-shape guard,
        // `iframeDocRequests.length === 0` is the network-shape
        // guard. A regression that mounts the iframe AFTER the
        // toast paints would fail the DOM guard; a regression
        // that fires the request without attaching the iframe
        // would fail the network guard.
        const iframe = page.locator('iframe#srvkicker');
        await expect(iframe).toHaveCount(0);
        expect(
            iframeDocRequests.length,
            `pages/admin.kickit.php must NOT be requested on the kickit-disabled path; saw ${iframeDocRequests.length}: ${JSON.stringify(iframeDocRequests)}`,
        ).toBe(0);

        expect(bansAddSeen).toBe(true);
    });
});
