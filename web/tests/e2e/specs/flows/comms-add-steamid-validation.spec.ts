/**
 * Flow spec — issue #1420: "Comms block steamID validation — no
 * notification on invalid steamID".
 *
 * Pre-fix the admin "Add a block" form's submit button was wired
 * via `onclick="ProcessBan();"` (a global defined inline in
 * `web/pages/admin.comms.php`). ProcessBan walked the form via
 * MooTools-era `$('id')` selectors (still working at runtime via
 * `web/scripts/sb.js`'s `global.$` compatibility shim that wraps
 * `document.getElementById`) and surfaced validation feedback
 * through `sb.message.show` / `sb.message.error`. Those helpers
 * paint into `#dialog-placement` / `#dialog-title` /
 * `#dialog-content-text` — DOM ids the v2.0 chrome doesn't render
 * anywhere. Net result on a malformed SteamID: the click fired,
 * the page-tail JS rejected with an "invalid SteamID" branch, and
 * `sb.message.show` silently no-op'd against a missing DOM target.
 * The operator hit submit again, the JSON API surfaced a 500 (the
 * raw SteamID got past the form-side check and reached
 * `SteamID::toSteam2()` which throws a generic `\Exception` on
 * unrecognised input, caught by `Api::handle`'s `Throwable`
 * fallback as a generic `server_error`). User-visible symptom:
 * "no notification".
 *
 * The fix has three parts (mirrored in this spec's three test
 * categories):
 *
 *   1. **Native HTML validation** is the first feedback surface.
 *      The `steam` input carries `required` + `pattern="…"` so
 *      the browser surfaces its native popover for empty / wrong-
 *      shape inputs BEFORE our submit handler runs. The form's
 *      `checkValidity()` short-circuits the IIFE. The API is
 *      NEVER called for inputs that fail native validation.
 *
 *   2. **Server-side structured rejection** is the load-bearing
 *      security gate. A curl-driven caller (or a third-party
 *      theme that strips the IIFE) that bypasses #1 still gets
 *      a structured `ApiError('validation', …, 'steam')` —
 *      `Sbpp\Api\ApiError` resolves to HTTP 400 with a
 *      `{ok:false, error:{code:'validation', field:'steam'}}`
 *      envelope, NOT a 500. Pre-fix the `SteamID::resolveInputID`
 *      throw escaped as `server_error`, the dev console showed
 *      `500 Internal Server Error`, and the operator had no
 *      actionable error message.
 *
 *   3. **Modern chrome toast feedback** replaces the dead
 *      `sb.message.show` path. The new IIFE in
 *      `page_admin_comms_add.tpl` routes success / error envelopes
 *      through `window.SBPP.showToast` (theme.js's `[data-testid="toast"]`
 *      paint). The legacy inline `<div id="steam.msg">` survives
 *      for screen readers + as a per-field anchor for the error
 *      message, but the visible feedback is the toast.
 *
 * Mirrors the test shape from `admin-edit-comms-toast.spec.ts` (the
 * other #1403-era comms-form regression spec): admin storage state,
 * single browser context, asserts the painted toast element via
 * `[data-testid="toast"][data-kind="error"]` (the kind-aware role
 * contract from #1409 is also enforced — the painted element
 * carries `role="alert"` for error toasts).
 *
 * **Out of scope** (deliberate):
 *   - The legacy GET fallback. `admin.comms.php` has no GET fallback
 *     for the add path — every submission rides `Actions.CommsAdd`
 *     through `sb.api.call`. The page itself only serves the form
 *     surface and a 302 to commslist after success.
 *   - The bans-equivalent native-validation lift (`page_admin_bans_add.tpl`
 *     also got `pattern` / `required` adds in this PR). That's
 *     covered by `web/tests/api/BansTest.php::testAddRejectsInvalidSteamIdShapeForType0`
 *     at the PHPUnit layer; the bans add form's pre-existing IIFE
 *     already routed `sb.api.call` errors through the modern toast,
 *     so the visible-feedback regression was strictly on the comms
 *     side. Adding a bans-form E2E spec for parity would be future
 *     work if the IIFE-side branch ever regresses.
 *
 * Admin storage state: the seeded `admin/admin` user carries
 * `WebPermission::Owner` which satisfies the form's
 * `ADMIN_OWNER|ADMIN_ADD_BAN` gate. Default storage state from
 * `fixtures/global-setup.ts` covers it.
 */

import { test, expect } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const VALID_STEAM = 'STEAM_0:1:14202020';
const INVALID_STEAM = 'asdf';
const TARGET_NICK = 'e2e-1420-validation';

// `.serial` because every test in this describe runs `truncateE2eDb()`
// in `beforeEach`, and a sibling test's API call landing during another
// test's truncate-and-reseed window gets "forbidden" (the admin row
// was momentarily gone). The CI gate runs `workers: 1` per
// `playwright.config.ts` so this matches the production shape; the
// `.serial` keeps local-dev runs (default `workers: <cores>`) honest
// too. Mirrors how `comms-gag-mute.spec.ts` keeps a single
// state-mutating test per describe — the cleaner shape for >1 test
// is `.serial`.
test.describe.serial('flow: comms-add SteamID validation feedback (#1420)', () => {
    test.beforeEach(async ({}, testInfo) => {
        // Sibling spec (`comms-gag-mute.spec.ts`) documents the
        // workers:1 / truncateE2eDb-isn't-parallel-project-safe
        // constraint. We need a clean slate for the happy-path
        // assertion (the `[data-id="…"]` lookup downstream would
        // otherwise race with sibling specs' inserts), so pin to
        // chromium; mobile-chromium coverage isn't load-bearing
        // for "did the toast paint" (the visible-feedback assertion
        // is browser-engine-agnostic).
        test.skip(
            testInfo.project.name !== 'chromium',
            'state-mutating flow; truncateE2eDb is not parallel-project-safe',
        );
        await truncateE2eDb();
    });

    test('empty SteamID → browser-native popover; API not called', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        // Count POSTs to /api.php. The native validation should
        // SHORT-CIRCUIT the submit handler — the browser surfaces
        // its popover and we never reach `sb.api.call`. If this
        // counter ends > 0 the native-validation gate is broken.
        let apiCalls = 0;
        page.on('request', (req) => {
            if (req.url().includes('/api.php') && req.method() === 'POST') {
                apiCalls += 1;
            }
        });

        // Fill EVERY field except `steam` so the only thing the
        // browser can complain about is the empty SteamID.
        await page.locator('[data-testid="addcomm-nickname"]').fill(TARGET_NICK);
        await page.locator('[data-testid="addcomm-type"]').selectOption('2');
        await page.locator('[data-testid="addcomm-length"]').selectOption('5');
        await page.locator('[data-testid="addcomm-reason"]').selectOption('Obscene language');
        // `steam` is empty by default; explicit assertion so a future
        // template change that auto-fills it surfaces here, not
        // silently masks the regression.
        await expect(page.locator('[data-testid="addcomm-steam"]')).toHaveValue('');

        await page.locator('[data-testid="addcomm-submit"]').click();

        // Native validation predicate. `valueMissing === true` for
        // an empty `required` input — that's the property the
        // browser uses to drive its popover. The form-level
        // `checkValidity()` returns false because of this.
        const validity = await page.locator('[data-testid="addcomm-steam"]').evaluate(
            (el: Element) => {
                const input = el as HTMLInputElement;
                return {
                    valid: input.validity.valid,
                    valueMissing: input.validity.valueMissing,
                    patternMismatch: input.validity.patternMismatch,
                };
            },
        );
        expect(validity.valid, 'empty steam input is invalid (valueMissing)').toBe(false);
        expect(validity.valueMissing, 'empty steam input flagged as valueMissing').toBe(true);

        // The API must NOT have been hit — native validation is
        // the load-bearing client-side gate. Pre-#1420 the legacy
        // `ProcessBan()` walked past the empty input and POSTed
        // `steam=''` to the JSON action, which then errored.
        expect(apiCalls, 'API should NOT be called for an empty SteamID').toBe(0);
    });

    test('malformed SteamID ("asdf") → browser-native popover; API not called', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        let apiCalls = 0;
        page.on('request', (req) => {
            if (req.url().includes('/api.php') && req.method() === 'POST') {
                apiCalls += 1;
            }
        });

        await page.locator('[data-testid="addcomm-steam"]').fill(INVALID_STEAM);
        await page.locator('[data-testid="addcomm-nickname"]').fill(TARGET_NICK);
        await page.locator('[data-testid="addcomm-type"]').selectOption('2');
        await page.locator('[data-testid="addcomm-length"]').selectOption('5');
        await page.locator('[data-testid="addcomm-reason"]').selectOption('Obscene language');

        await page.locator('[data-testid="addcomm-submit"]').click();

        // `patternMismatch === true` for "asdf" against the
        // `STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}` regex. The
        // input is non-empty so `valueMissing === false`. The
        // form's `checkValidity()` still returns false because
        // the pattern fails.
        const validity = await page.locator('[data-testid="addcomm-steam"]').evaluate(
            (el: Element) => {
                const input = el as HTMLInputElement;
                return {
                    valid: input.validity.valid,
                    valueMissing: input.validity.valueMissing,
                    patternMismatch: input.validity.patternMismatch,
                };
            },
        );
        expect(validity.valid, 'malformed steam input is invalid (patternMismatch)').toBe(false);
        expect(validity.patternMismatch, 'malformed steam input flagged as patternMismatch').toBe(true);
        expect(validity.valueMissing, 'non-empty input is not valueMissing').toBe(false);

        expect(apiCalls, 'API should NOT be called for a malformed SteamID').toBe(0);
    });

    test('curl-style bypass: server-side rejects malformed SteamID with 400 + structured error (NOT 500)', async ({ page }) => {
        // Pre-#1420 a hostile/curl-driven caller that bypassed the
        // form's native validation got a generic 500 response
        // because `SteamID::resolveInputID` threw `\Exception` and
        // `Api::handle`'s `Throwable` fallback wrapped it as a
        // generic `server_error` envelope. The fix adds an
        // explicit `SteamID::isValidID($rawSteam)` gate inside
        // `api_comms_add` that throws `ApiError('validation', …,
        // 'steam')` — `Sbpp\Api\ApiError` resolves to HTTP 400
        // with a structured `{ok:false, error:{code:'validation',
        // field:'steam'}}` envelope. This test drives the bypass
        // path by calling `sb.api.call(Actions.CommsAdd, …)`
        // directly from a same-origin page (so the CSRF meta tag,
        // the api.js client, and the autogenerated Actions
        // registry are all in scope), the same trick
        // `fixtures/seeds.ts`'s `seedCommViaApi` uses.
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        const result = await page.evaluate(async (badSteam) => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            params: Record<string, unknown>,
                        ) => Promise<{
                            ok: boolean;
                            error?: { code: string; message: string; field?: string };
                        }>;
                    };
                };
                Actions: Record<string, string>;
            };
            // Mirror what the form's IIFE sends, but with `steam`
            // set to "asdf" — the same string the operator would
            // type into the input if native validation were off.
            const envelope = await w.sb.api.call(w.Actions.CommsAdd, {
                nickname: 'e2e-1420-bypass',
                type: 1,
                steam: badSteam,
                length: 0,
                reason: 'e2e: invalid steam id bypass',
            });
            return envelope;
        }, INVALID_STEAM);

        expect(result.ok, 'envelope ok=false for malformed steam').toBe(false);
        expect(result.error?.code, 'envelope error.code = validation (not server_error)').toBe(
            'validation',
        );
        expect(result.error?.field, 'envelope error.field = steam').toBe('steam');
        expect(
            result.error?.message,
            'envelope carries an actionable message',
        ).toMatch(/valid Steam ID|Community ID/i);
    });

    test('happy path: valid SteamID + reason → toast paints + row created', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        await page.locator('[data-testid="addcomm-steam"]').fill(VALID_STEAM);
        await page.locator('[data-testid="addcomm-nickname"]').fill(TARGET_NICK);
        await page.locator('[data-testid="addcomm-type"]').selectOption('2');
        await page.locator('[data-testid="addcomm-length"]').selectOption('5');
        await page.locator('[data-testid="addcomm-reason"]').selectOption('Obscene language');

        // Wait for the JSON API to settle BEFORE asserting on the
        // toast — the IIFE schedules a `setTimeout(...,2000)`
        // reload after success that would otherwise tear the
        // toast DOM down mid-assertion. The toast paints
        // synchronously off the `then` callback so it's visible
        // well before the 2-second reload fires.
        const apiResponse = page.waitForResponse(
            (r) => r.url().includes('/api.php') && r.request().method() === 'POST',
        );
        await page.locator('[data-testid="addcomm-submit"]').click();
        const response = await apiResponse;
        expect(response.status(), 'comms.add returns 200').toBe(200);
        const body = await response.json();
        expect(body, 'comms.add envelope').toMatchObject({ ok: true });

        // Success toast paints via `window.SBPP.showToast` —
        // [data-testid="toast"] is the chrome-rendered element
        // post-#1409 (kind-aware role contract: role="status" for
        // non-error kinds, role="alert" for error kinds). The
        // [data-kind="success"] anchor disambiguates from any
        // sibling info / warn toasts queued from upstream calls.
        const successToast = page
            .locator('[data-testid="toast"][data-kind="success"]')
            .filter({ hasText: 'Block Added' });
        await expect(successToast).toBeVisible({ timeout: 1500 });
        // role="status" is the polite live-region role for non-error
        // toasts per the chrome's kind-aware role contract.
        await expect(successToast).toHaveAttribute('role', 'status');
    });

    test('handler rejects bypass via toast on the form (single round-trip, structured error)', async ({ page }) => {
        // Companion to the curl-style bypass test above — drives
        // the same `sb.api.call` from inside the form's own page
        // (the operator's session context) and asserts the toast
        // surface paints. This is the "third-party theme strips
        // the IIFE's native-validation guard but keeps the chrome's
        // toast surface" failure mode; the server rejects with a
        // structured ApiError and the toast emits via the IIFE's
        // error branch.
        //
        // Why this is distinct from the curl-style test above:
        // that test asserts the envelope shape (the load-bearing
        // server-side contract); THIS test asserts the IIFE's
        // toast emission on the error envelope (the load-bearing
        // chrome-side contract for the operator-visible feedback
        // the reporter explicitly called out as missing). The
        // two together prove the end-to-end fix.
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        // Bypass native validation by stripping `pattern` +
        // `required` from the steam input via JS, then drive the
        // form submit normally. This is the literal "third-party
        // theme without the gate" shape — the IIFE still runs,
        // checkValidity() now passes (no `required` / `pattern`),
        // `sb.api.call(Actions.CommsAdd)` fires, the server
        // rejects with `ApiError('validation', …, 'steam')`, and
        // the IIFE's `r.ok === false` branch surfaces the error
        // through `window.SBPP.showToast`.
        await page.evaluate(() => {
            const el = document.getElementById('steam') as HTMLInputElement | null;
            if (el) {
                el.removeAttribute('required');
                el.removeAttribute('pattern');
            }
        });

        await page.locator('[data-testid="addcomm-steam"]').fill(INVALID_STEAM);
        await page.locator('[data-testid="addcomm-nickname"]').fill(TARGET_NICK);
        await page.locator('[data-testid="addcomm-type"]').selectOption('2');
        await page.locator('[data-testid="addcomm-length"]').selectOption('5');
        await page.locator('[data-testid="addcomm-reason"]').selectOption('Obscene language');

        await page.locator('[data-testid="addcomm-submit"]').click();

        // Error toast paints with the IIFE's "Block NOT Added"
        // title + the server-supplied message. `role="alert"`
        // is the assertive live-region role for error kinds
        // (kind-aware role contract from #1409 — error toasts
        // get role="alert" so screen-reader users get an
        // interrupting announcement on destructive-action
        // failure, in this case "the block didn't go through
        // because the SteamID was invalid").
        const errorToast = page
            .locator('[data-testid="toast"][data-kind="error"]')
            .filter({ hasText: 'Block NOT Added' });
        await expect(errorToast).toBeVisible({ timeout: 1500 });
        await expect(errorToast).toHaveAttribute('role', 'alert');
        await expect(errorToast).toContainText(/valid Steam ID|Community ID/i);

        // Per-field anchor (`#steam.msg`) also gets the error so
        // screen-reader users navigating by form-field labels
        // surface the error in context. Visible inline.
        const inlineErr = page.locator('#steam\\.msg');
        await expect(inlineErr).toBeVisible();
        await expect(inlineErr).toContainText(/valid Steam ID|Community ID/i);
    });
});
