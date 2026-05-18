/**
 * Flow spec — issue #1409: `Sbpp\View\Toast::emit` gained an optional
 * 5th `?int $duration_ms` parameter. When the call site passes `0`,
 * the chrome (`web/themes/default/js/theme.js`'s `showToast`) does NOT
 * schedule an auto-dismiss timer — the only way the toast disappears
 * is the user clicking the X button. The semantic restores the v1.x
 * `ShowBox(text, title, redirect, bg, sticky=true)` 5-arg shape that
 * the #1403 mechanical lift dropped for fidelity.
 *
 * The follow-through is the 5 NOT-* destructive-action-failed branches
 * in `page.banlist.php` / `page.commslist.php` now passing `0` as the
 * 5th arg:
 *   - `page.banlist.php`   — "Player NOT Unbanned" / "Ban NOT Deleted"
 *   - `page.commslist.php` — "Player NOT UnGagged" × 2 + "Ban NOT Deleted"
 *
 * The **PHPUnit-side** `ToastEmitRegressionTest` (post-#1409) pins:
 *   - default omits `duration_ms` from the wire (no field, not null)
 *   - explicit 0 / explicit > 0 emit the field as an integer
 *   - negative values throw `\InvalidArgumentException`
 *   - all JSON_HEX_* + UTF-8 substitute guarantees preserved
 *   - the 5 NOT-* call sites all pass `duration_ms: 0`
 *
 * This **runtime-side** spec covers the chrome's three behaviors
 * end-to-end:
 *   1. The wire-format JSON blob the PHP-side emits for a NOT-* branch
 *      carries `duration_ms: 0` (round-trip check at the wire layer).
 *   2. The `[data-testid="toast"]` paints AND outlasts the default
 *      `SHOWTOAST_DEFAULT_DURATION` (~4000ms) — the regression the
 *      issue describes (toast disappearing before the operator
 *      finishes reading the severe-error confirmation).
 *   3. Clicking the `[data-toast-close]` button dismisses the toast
 *      (the X-button is the only escape hatch on a persistent toast).
 *
 * # How we trigger a real NOT-* branch
 *
 * The capital-NOT branch at `page.banlist.php` L159 (`'Player NOT
 * Unbanned'`) fires when the admin INNER JOIN at L72 returns empty
 * (`$res` is falsy) BUT the bans row itself exists (L83's `$row` is
 * truthy). That's the "orphan ban" shape — a bans row whose `aid`
 * points to an admin that no longer exists. We seed it in two steps:
 *
 *   a. `seedBanViaApi` mints a normal ban (aid = the seeded admin's
 *      aid). The full Smarty/JSON/CSRF/permission chain runs, so the
 *      bans row has every field set the way the production path
 *      shapes it (`ends`, `created`, `length=0`, `RemoveType IS
 *      NULL`, `steam_universe` joinable through the servers + mods
 *      tables).
 *   b. `orphanBanAidE2e` does a surgical UPDATE setting that bid's
 *      `aid` to 99999 (no admin row with that id exists; the shim
 *      asserts the absence to keep the scenario meaningful). The
 *      bans row is otherwise unchanged.
 *
 * The GET-fallback URL then sends the admin through L72 (empty
 * `$res`), past the Owner-perm guard at L75, past the existence
 * guard at L91 (`$row` is truthy), through the UPDATE at L105,
 * into the `else` branch at L146 — emit "Player NOT Unbanned" with
 * `duration_ms: 0`, the persistent path.
 *
 * # Auth + DB scope
 *
 * Default storage state (admin/admin, `WebPermission::Owner`) passes
 * the L75 Owner|Unban check; no per-describe override.
 *
 * `truncateE2eDb` is NOT called — the e2e DB is single-DB / `workers:
 * 1` per AGENTS.md "Playwright E2E specifics", and `seedBanViaApi`
 * tolerates the `already_banned` arm if a prior run left the row
 * behind. The orphan-aid shim is idempotent (UPDATEs to the same value
 * on a re-run, doesn't blow up). Each test uses a unique seeded steam
 * id to avoid cross-test interference.
 *
 * # Timer semantics
 *
 * One `waitForTimeout` is intentional and load-bearing here — AGENTS.md
 * "Playwright E2E specifics" notes the persistent-toast-after-N-ms
 * assertion is the canonical case where a timer wait is legitimate.
 * Specifically: we wait 4500ms (past the default 4000ms auto-dismiss
 * window) THEN assert the toast is STILL visible. A `toBeVisible`
 * assertion alone wouldn't catch the "auto-dismisses after the
 * default" regression because the toast paints immediately on
 * `DOMContentLoaded` and the assertion would pass before the timer
 * fires. The 4500ms gives a ~500ms safety margin over the
 * `SHOWTOAST_DEFAULT_DURATION` constant in `theme.js`.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { orphanBanAidE2e } from '../../fixtures/db.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

// Use distinct steam ids per-test so a Playwright retry on the same
// worker doesn't double-stomp the previous run's ban. The `seedBanViaApi`
// tolerance for `already_banned` keeps a single retry working but a
// shared steam id would coalesce two tests into the same bid.
//
// Two seeded sites, not three: the routine-toast test (reviewer
// Suggested #2) was reshaped to drive `window.SBPP.showToast(...)`
// directly from a stable panel surface instead of triggering a
// page-flow that carries a redirect, so it no longer needs a
// seeded ban / orphan-aid setup.
const STEAM_A = 'STEAM_0:0:14091409'; // mnemonic: 1409 for the issue number
const STEAM_C = 'STEAM_0:0:14091411';

const NICK_A  = '1409-persist-1';
const NICK_C  = '1409-persist-wire';

const NONEXISTENT_AID = 99999; // matches the orphan-ban-aid-e2e.php convention

/**
 * Visit the banlist as the seeded admin and extract the banlist
 * postkey from the freshest unban-button's `data-fallback-href`
 * (same shape `banlist-getfallback-toast.spec.ts` uses).
 * The postkey is regenerated per visit
 * (`page.banlist.php:setPostKey()`); we have to read it from the
 * live render.
 *
 * Also returns the seeded bid (extracted from the same href) so the
 * caller doesn't have to thread it separately — that's the row
 * whose unban will fire the NOT-* branch.
 */
async function extractFreshPostkeyAndBid(
    page: import('@playwright/test').Page,
    /** Filter to the seeded nickname so a sibling test's leftover row doesn't get unbanned. */
    nickname: string,
): Promise<{ postkey: string; bid: number }> {
    await page.goto('/index.php?p=banlist');

    // Filter the rows by the seeded nickname so we grab THIS test's
    // bid. `filter({ hasText })` is the canonical Playwright shape
    // for "rows containing this text"; the alternative `tr:has-text()`
    // CSS pseudo-class is also supported but `filter` reads cleaner.
    const row = page.locator('tr').filter({ hasText: nickname }).first();
    const hrefAttr = await row
        .locator('[data-action="bans-unban"]')
        .first()
        .getAttribute('data-fallback-href');

    if (!hrefAttr) {
        throw new Error(
            `extractFreshPostkeyAndBid: no [data-action="bans-unban"] under row containing nickname "${nickname}"`,
        );
    }
    const parsed = new URL('http://x/' + hrefAttr);
    const key = parsed.searchParams.get('key');
    const bidRaw = parsed.searchParams.get('id');
    if (!key || !bidRaw) {
        throw new Error(
            `extractFreshPostkeyAndBid: malformed data-fallback-href "${hrefAttr}"`,
        );
    }
    return { postkey: key, bid: Number.parseInt(bidRaw, 10) };
}

test.describe('flow: persistent toast on NOT-* branch (#1409 `duration_ms: 0`)', () => {
    test('Player NOT Unbanned toast paints, outlasts SHOWTOAST_DEFAULT_DURATION, dismisses on X-button click', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // 1. Seed a normal ban (real chain — CSRF + permissions +
        //    handler — so the bans row's schema-shape is production-
        //    faithful).
        let seededBid: number;
        try {
            const seed = await seedBanViaApi(page, { nickname: NICK_A, steam: STEAM_A });
            seededBid = seed.bid;
        } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            if (!msg.includes('already_banned')) throw err;
            // Same shape as banlist-getfallback-toast: a prior run on
            // the same worker left the row; re-extract the bid from
            // the rendered banlist below.
            const { bid } = await extractFreshPostkeyAndBid(page, NICK_A);
            seededBid = bid;
        }

        // 2. Orphan the ban — UPDATE :prefix_bans.aid → 99999 so the
        //    capital-NOT branch fires at page.banlist.php:159.
        await orphanBanAidE2e(seededBid, NONEXISTENT_AID);

        // 3. Re-visit the banlist to mint a fresh `banlist_postkey`
        //    (the previous visit's key might have rolled). The orphan
        //    UPDATE doesn't change the bid, so we can read the same
        //    id from the now-orphaned row.
        const { postkey, bid } = await extractFreshPostkeyAndBid(page, NICK_A);
        expect(bid, 'orphan UPDATE should not change the bid').toBe(seededBid);

        // 4. Hit the GET-fallback unban URL. The INNER JOIN at L72
        //    is now empty (aid 99999 doesn't exist); the bans row
        //    still exists so the L91 empty-row branch doesn't fire;
        //    the L75 Owner-perm check passes (seeded admin holds
        //    `WebPermission::Owner`); the UPDATE at L105 runs; and
        //    the L146 `else` branch fires "Player NOT Unbanned" with
        //    `duration_ms: 0`.
        await page.goto(
            `/index.php?p=banlist&a=unban&id=${bid}&key=${postkey}&ureason=1409-persist-spec`,
        );

        // 5. The toast paints with the capital-NOT title. `data-kind`
        //    is the load-bearing attribute the chrome stamps (the
        //    `data-testid="toast"` was added in #1409 to match the
        //    AGENTS.md documented contract). Filter on the title
        //    text to disambiguate from the lowercase-Not branch's
        //    "Player Not Unbanned" toast (different code path, default
        //    duration). The capital-NOT title is the verbatim copy
        //    from page.banlist.php:159.
        const toast = page
            .locator('[data-testid="toast"]')
            .filter({ hasText: 'Player NOT Unbanned' });
        await expect(toast).toBeVisible({ timeout: 1500 });
        await expect(toast).toContainText(/There was an error unbanning/);
        await expect(toast).toHaveAttribute('data-kind', 'error');

        // 6. Wait past `SHOWTOAST_DEFAULT_DURATION` (4000ms; we add
        //    500ms safety margin). The single load-bearing timer wait
        //    in the spec — AGENTS.md "Playwright E2E specifics" notes
        //    the persistent-toast-after-N-ms assertion is the
        //    canonical legitimate use of `waitForTimeout`. A `toBeVisible`
        //    by itself wouldn't catch the auto-dismiss regression
        //    because the toast paints immediately.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(4500);

        // 7. The toast is STILL visible — this is the #1409 contract.
        //    A regression that drops `duration_ms: 0` from the call
        //    site (or drops the `if (durationMs > 0)` guard in
        //    `showToast`, falling through to `setTimeout(..., 0)`
        //    which auto-dismisses on the next tick) would fail
        //    HERE.
        await expect(
            toast,
            'toast should persist past SHOWTOAST_DEFAULT_DURATION (~4000ms) when emit'
            + ' passes duration_ms: 0 — this is the #1409 contract restoring v1.x'
            + ' ShowBox(..., sticky=true) semantics for severe-error confirmations',
        ).toBeVisible();

        // 8. Click the X button — the only escape hatch on a
        //    persistent toast. The chrome's document-level
        //    `data-toast-close` delegate handles the click.
        const dismissBtn = toast.locator('[data-toast-close]');
        await expect(dismissBtn).toBeVisible();
        await dismissBtn.click();

        // 9. Toast disappears. Use the toast-element-detached
        //    assertion (`toBeHidden` would still pass on a
        //    `display:none` toast; the chrome `remove()`s the
        //    element entirely).
        await expect(toast).toHaveCount(0, { timeout: 1500 });

        // 10. No uncaught console errors — same defensiveness shape
        //     as the sister `banlist-getfallback-toast` spec.
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Routine toast (no duration_ms override) STILL auto-dismisses — contract regression guard', async ({ page }) => {
        // Belt-and-braces: the 4000ms default behaviour must NOT
        // regress with the #1409 additions. A "we forgot to skip the
        // setTimeout call when durationMs is undefined" regression
        // would make EVERY toast persistent — the worst kind of
        // regression because every routine info / success surface
        // would suddenly require manual dismissal.
        //
        // **Why we don't drive a page-flow route here**: the obvious
        // shape (hit a 404-shaped GET-fallback like
        // `?p=banlist&a=unban&id=99999` that fires the lowercase-Not
        // branch) carries a non-null `$redirect`, so the chrome's
        // `flushPendingToasts` schedules a `window.location.href`
        // navigation ~1500ms after paint. The toast disappears
        // because the PAGE TEARS DOWN, not because the 4000ms timer
        // fired — a regression that bumped `SHOWTOAST_DEFAULT_DURATION`
        // to 10000ms would still silently pass that test because
        // the navigation happens well before the (broken) timer
        // would have. Reviewer Suggested #2 (post-PR #1414) caught
        // this: the test was nominally green but proved nothing
        // about the timer contract.
        //
        // The replacement isolates the contract under test. We:
        //   1. Navigate to a panel page that has no pending toasts
        //      and no pending redirects (the home dashboard with
        //      `?p=home` is the simplest stable surface — the
        //      seeded admin's storage state lands there directly).
        //   2. Drive `window.SBPP.showToast(...)` directly from the
        //      page, with NO `durationMs` option, so the chrome
        //      falls through to its `SHOWTOAST_DEFAULT_DURATION`
        //      default. (`window.SBPP.showToast` is the
        //      chrome-exposed wrapper documented in
        //      `web/scripts/globals.d.ts`; it's the same code path
        //      `flushPendingToasts` uses internally.)
        //   3. Assert the toast paints immediately.
        //   4. Wait ~3500ms — STILL inside the default ~4000ms
        //      window — and assert the toast is still visible.
        //      Catches a regression where `SHOWTOAST_DEFAULT_DURATION`
        //      was accidentally LOWERED to ~3000ms.
        //   5. Wait another ~1500ms (total 5000ms — well past the
        //      default 4000ms + a generous margin for slow CI
        //      runners) and assert the toast is GONE. Catches the
        //      "every toast is now persistent" regression (the
        //      worst case the contract guards against) AND the
        //      "default duration was bumped past 5000ms" regression.
        //
        // The test proves ONLY the timer contract: no page-flow
        // dependency, no redirect interference, no orphan-ban
        // setup. The single load-bearing `waitForTimeout` is the
        // 3500ms / 5000ms pair documented above; AGENTS.md
        // "Playwright E2E specifics" notes the auto-dismiss
        // timing assertion is the canonical case where a timer
        // wait is legitimate (along with the persistent-toast
        // sister assertion in the test above).
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // Land on a stable panel surface with no pending toasts /
        // redirects. The home dashboard renders for any logged-in
        // user; the seeded admin storage state takes us straight
        // through the login redirect on first visit.
        await page.goto('/index.php?p=home');

        // Pre-check: no toast on the page before we fire one.
        await expect(
            page.locator('[data-testid="toast"]'),
            'precondition: home dashboard should have no pending toasts before the manual `showToast` call'
            + ' — if there are leftover toasts here, the test environment is dirty and the timer'
            + ' assertion below is meaningless',
        ).toHaveCount(0);

        // Fire a routine toast directly through the chrome's
        // exposed API. `window.SBPP.showToast` is the documented
        // entry point (see `web/scripts/globals.d.ts`); calling
        // it with NO `durationMs` exercises the `=== undefined`
        // fall-through to `SHOWTOAST_DEFAULT_DURATION`. The
        // `evaluate` runs in the page context, so the chrome's
        // `theme.js` (already loaded by the home page) handles
        // the toast natively.
        await page.evaluate(() => {
            const sbpp = (window as unknown as { SBPP?: { showToast?: (opts: unknown) => void } }).SBPP;
            if (!sbpp || !sbpp.showToast) {
                throw new Error('window.SBPP.showToast is not exposed — chrome JS did not boot');
            }
            sbpp.showToast({
                kind: 'info',
                title: '1409 routine timer probe',
                body: 'This toast must auto-dismiss after the default 4000ms.',
            });
        });

        const toast = page
            .locator('[data-testid="toast"]')
            .filter({ hasText: '1409 routine timer probe' });
        await expect(toast).toBeVisible({ timeout: 1500 });

        // Wait ~3500ms — still well inside the default 4000ms
        // window with a ~500ms safety margin. The toast MUST
        // still be visible. Catches a regression that lowered
        // `SHOWTOAST_DEFAULT_DURATION` (e.g. to 3000ms during a
        // "make toasts disappear faster" tweak).
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(3500);
        await expect(
            toast,
            'routine toast must still be visible at ~3500ms (well within the SHOWTOAST_DEFAULT_DURATION ~4000ms'
            + ' window) — a regression that lowered the default below ~3500ms would fail HERE',
        ).toBeVisible();

        // Wait another ~1500ms (total ~5000ms post-paint), then
        // assert the toast has been removed by the auto-dismiss
        // timer. Total wait is ~1000ms past the default 4000ms
        // window — generous margin for slow CI runners but
        // tight enough that a regression bumping the default to
        // 6000ms+ fails here. A regression that disabled the
        // auto-dismiss timer entirely (e.g. dropped the
        // `if (durationMs > 0)` guard's `setTimeout` call when
        // `durationMs` is `undefined`) would ALSO fail here:
        // the toast would still be visible at 5000ms because
        // nothing schedules its removal.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(1500);
        await expect(
            toast,
            'routine toast (no `durationMs` option) MUST auto-dismiss within'
            + ' SHOWTOAST_DEFAULT_DURATION + a safety margin — a regression that disabled the timer'
            + ' for the undefined-durationMs case would make EVERY panel toast persistent and force'
            + ' users to click X on every routine info / success / warn confirmation',
        ).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Wire-format response carries duration_ms: 0 on the NOT-* branch (wire-layer contract)', async ({ page }) => {
        // Wire-layer assertion mirroring the sister
        // `banlist-getfallback-toast.spec.ts`'s
        // "No raw <script>ShowBox(...) blob" test: grep the
        // response body directly to assert the JSON wire format
        // carries the new field. Decoupled from the chrome's
        // rendering — even if `showToast` regressed, the PHP-side
        // contract would still pass this test, which is what we
        // want: it's the static gate at the wire layer.
        let seededBid: number;
        try {
            const seed = await seedBanViaApi(page, { nickname: NICK_C, steam: STEAM_C });
            seededBid = seed.bid;
        } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            if (!msg.includes('already_banned')) throw err;
            const { bid } = await extractFreshPostkeyAndBid(page, NICK_C);
            seededBid = bid;
        }
        await orphanBanAidE2e(seededBid, NONEXISTENT_AID);

        const { postkey, bid } = await extractFreshPostkeyAndBid(page, NICK_C);
        expect(bid).toBe(seededBid);

        const response = await page.request.get(
            `/index.php?p=banlist&a=unban&id=${bid}&key=${postkey}&ureason=1409-wire-layer-guard`,
        );
        const body = await response.text();

        // The pending-toast blob is present.
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'NOT-* branch response should carry the pending-toast JSON blob',
        ).toBe(true);

        // Extract the specific `Player NOT Unbanned` JSON payload
        // and assert `duration_ms: 0` is present. Loose regex (the
        // chrome's JSON encoder may reorder keys depending on PHP
        // version — JSON object key order is implementation-defined
        // but `json_encode` is stable per-version, so we match the
        // field-presence cheaply rather than asserting a positional
        // shape).
        const notUnbannedBlobMatch = body.match(
            /<script[^>]*class="sbpp-pending-toast"[^>]*>([^<]*Player NOT Unbanned[^<]*)<\/script>/,
        );
        expect(
            notUnbannedBlobMatch,
            'NOT-* branch response should carry a pending-toast blob containing the "Player NOT Unbanned" title',
        ).not.toBeNull();
        const blob = notUnbannedBlobMatch![1];

        // Parse the JSON and assert `duration_ms: 0` is the literal
        // shape on the wire.
        const data = JSON.parse(blob) as Record<string, unknown>;
        expect(data['kind']).toBe('error');
        expect(data['title']).toBe('Player NOT Unbanned');
        expect(
            data['duration_ms'],
            'NOT-* branch wire payload should carry duration_ms: 0 — this is the #1409 contract'
            + ' the call site in page.banlist.php restores. A regression that drops the 5th arg'
            + ' would silently re-enable the auto-dismiss timer and the operator would miss the failure.',
        ).toBe(0);

        // Persistent + redirect mutual-exclusion contract: a
        // persistent toast's wire payload MUST NOT carry a
        // `redirect` field, because the chrome's
        // `flushPendingToasts` would otherwise navigate ~1500ms
        // after paint, tearing down the toast before the operator
        // can read or dismiss it. The PHP call site in
        // `page.banlist.php` passes `null` for `$redirect` on the
        // NOT-* branch so the field is omitted entirely from the
        // payload (matches the `testToastEmitWireFormatStaysStable`
        // PHPUnit contract: helper omits `redirect` when caller
        // passed null/empty). Defence-in-depth: even if a future
        // call-site bug landed a redirect here, the chrome's
        // whole-drain inhibit (`flushPendingToasts` skips the
        // redirect setTimeout when ANY block had `duration_ms:
        // 0`) would still preserve the persistent semantic — but
        // we pin the call-site half of the contract here as the
        // primary gate; the chrome inhibit is belt-and-braces.
        expect(
            'redirect' in data,
            'NOT-* persistent branch must NOT carry a `redirect` field on the wire — persistent + redirect are mutually exclusive (the chrome\'s redirect setTimeout would tear down the toast before the operator can acknowledge). See AGENTS.md "Server-side toast emission" → "Redirect coalescing" → "Persistent + redirect mutual exclusion".',
        ).toBe(false);
    });
});
