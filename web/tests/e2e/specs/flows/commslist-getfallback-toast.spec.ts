/**
 * Flow spec — issue #1403 follow-up: `page.commslist.php` no longer
 * emits raw `<script>ShowBox(...)</script>` blobs on its legacy
 * GET-fallback ungag / unmute / delete paths.
 *
 * Pre-fix `web/pages/page.commslist.php` had twelve toast emission
 * sites (lines 15 / 53 / 72 / 97 / 100 / 109 / 128 / 153 / 156 / 164
 * / 203 / 206 in the issue body) all riding the removed-at-#1123-D1
 * `ShowBox` helper. The fix routes every site through
 * `Sbpp\View\Toast::emit(...)`, which writes a
 * `<script type="application/json" class="sbpp-pending-toast">
 * …</script>` payload the chrome
 * (`web/themes/default/js/theme.js`'s `flushPendingToasts` drain in
 * the chrome IIFE) consumes on `DOMContentLoaded`.
 *
 * **Scope** mirrors the sister banlist spec: the **GET fallback**
 * specifically (`?a=ungag` / `?a=unmute` / `?a=delete`). The modern
 * JSON-dispatcher path (`Actions.CommsUnblock` via
 * `#comms-unblock-dialog`) already toasts correctly via the JSON
 * envelope's `message` field and is out of scope. The commslist
 * regression mattered more than the banlist's because the comm-list
 * row table doesn't expose a drawer fallback the way the bans table
 * does — when the legacy ShowBox crashed mid-output AND the page
 * body suppressed, the admin had no fallback affordance at all.
 *
 * Two tests below cover the two distinct toast-emitting branches
 * that don't require building elaborate row state:
 *
 *   1. **Missing `ureason` on ?a=ungag** (page.commslist.php:53)
 *      — fires after #1301 tightened the GET fallback to require a
 *      reason. An admin who hand-edits the URL and forgets `&ureason=`
 *      lands here; pre-fix the response body was a blank
 *      `<script>ShowBox(...)</script>` that crashed and the user
 *      couldn't tell whether the unblock actually happened. Post-fix
 *      the toast paints + the redirect to `?p=commslist` lands.
 *   2. **Bid that doesn't resolve to a live comm block on ?a=ungag**
 *      (page.commslist.php:77) — bid `99999` doesn't exist in the
 *      e2e DB (or any reasonable install). The handler can't find a
 *      row, fires the `Player Not UnGagged` toast, and PageDies.
 *      Distinct emission shape from #1: same wire format, different
 *      copy.
 *
 * Both tests assert the three terminal properties shared with the
 * lostpassword / protest / banlist specs:
 *
 *   - The chrome footer (`footer.sbpp-footer`) IS attached — the
 *     v2.0 blank-page regression would fail this.
 *   - A `.toast[data-kind="error"]` element appears with the expected
 *     title + body.
 *   - NO uncaught `pageerror` events fire (no `ReferenceError:
 *     ShowBox is not defined`).
 *
 * Test #3 is the wire-layer regression guard: hits the URL via
 * `page.request.get(...)` (same cookie jar as the page session) and
 * grep-asserts the response body neither carries `<script>ShowBox(`
 * NOR is missing the JSON blob. Mirror of the sister specs.
 *
 * Admin storage state — the GET fallback paths gate on
 * `WebPermission::Unban` / `WebPermission::DeleteBan`; the seeded
 * `admin/admin` user carries `WebPermission::Owner` so both pass.
 * Default `auth.ts` storage state covers this; no per-describe
 * override.
 *
 * Postkey extraction — the commslist's session-scoped
 * `banlist_postkey` (the global `$_SESSION` key the bans + comms
 * surfaces share) is regenerated per visit via the same
 * `setPostKey()` shape as the banlist's. Mirrors the sister
 * `extractBanlistPostkey()` helper in `banlist-getfallback-toast.spec.ts`
 * but reads off `[data-action="comms-unblock"][data-fallback-href]`
 * which is the canonical no-JS GET-fallback URL the template renders
 * for the inline JS to consume.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { seedCommViaApi } from '../../fixtures/seeds.ts';

const SEED_STEAM      = 'STEAM_0:0:14034318'; // arbitrary STEAM2 id, distinct from banlist spec's.
const SEED_NICK       = 'commslist-getfallback-victim';
const NONEXISTENT_BID = 99999;                // well above any realistic e2e DB sequence.

/**
 * Visit the commslist page as the seeded admin, then extract the
 * `banlist_postkey` (shared session key for bans + comms surfaces)
 * from the rendered `data-fallback-href` attribute on the first
 * comms-unblock row button. The session-scoped postkey is
 * regenerated per visit (per `page.commslist.php:setPostKey()`); we
 * have to read it from the live response, not hardcode it.
 *
 * Caller responsibility: the commslist must have at least one row
 * (i.e. a `seedCommViaApi(...)` ran beforehand) — otherwise the
 * unblock button is absent and the extraction fails.
 */
async function extractCommslistPostkey(
    page: import('@playwright/test').Page,
): Promise<string> {
    await page.goto('/index.php?p=commslist');
    // `data-fallback-href` on the unblock button is the canonical
    // GET-fallback URL the template renders for the inline JS. Format:
    //   index.php?p=commslist&a=<ungag|unmute>&id=<cid>&key=<postkey>
    // We need the &key= value.
    const hrefAttr = await page
        .locator('[data-action="comms-unblock"]')
        .first()
        .getAttribute('data-fallback-href');
    if (!hrefAttr) {
        throw new Error(
            'extractCommslistPostkey: no [data-action="comms-unblock"] element on '
            + '/index.php?p=commslist — seed a comm via seedCommViaApi() first',
        );
    }
    const parsed = new URL('http://x/' + hrefAttr);
    const key = parsed.searchParams.get('key');
    if (!key) {
        throw new Error(
            `extractCommslistPostkey: no &key= in data-fallback-href "${hrefAttr}"`,
        );
    }
    return key;
}

/**
 * Same shape as the postkey helper but pulls the seeded cid (off
 * `data-bid` on the first comms-unblock button — the template aliases
 * cid to bid on the desktop row markup). Used for the L53 "missing
 * ureason" branch which needs a real cid to point at.
 */
async function extractSeededCid(
    page: import('@playwright/test').Page,
): Promise<string> {
    await page.goto('/index.php?p=commslist');
    const bid = await page
        .locator('[data-action="comms-unblock"]')
        .first()
        .getAttribute('data-bid');
    if (!bid) {
        throw new Error(
            'extractSeededCid: no [data-action="comms-unblock"][data-bid] on '
            + '/index.php?p=commslist',
        );
    }
    return bid;
}

test.describe('flow: commslist GET-fallback toast (#1403 ShowBox → Toast::emit)', () => {
    test.beforeEach(async ({ page }) => {
        // Seed a comm block so the L53 `ureason`-required branch has a
        // real cid to point at AND the postkey-extraction helper can
        // find an unblock button in the rendered table. `seedCommViaApi`
        // calls `Actions.CommsAdd`; on a re-run for the same authid it
        // would normally reject as `already_blocked`, but we tolerate
        // that here because:
        //   - Playwright's `workers: 1` means specs run serially
        //     against the shared `sourcebans_e2e` DB.
        //   - The first beforeEach run inserts the row; subsequent
        //     runs on the same worker (Playwright retry, repeat) hit
        //     `already_blocked` which throws.
        // We catch + ignore the duplicate-row case so the spec is
        // re-runnable. Mirrors `comms-drawer.spec.ts`'s tolerance shape.
        try {
            await seedCommViaApi(page, { nickname: SEED_NICK, steam: SEED_STEAM });
        } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            if (!msg.includes('already_blocked')) throw err;
        }
    });

    test('Missing ureason on ?a=ungag → "Unblock Reason Required" toast + chrome footer', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        const postkey = await extractCommslistPostkey(page);
        const cid     = await extractSeededCid(page);

        // Hit the GET fallback WITHOUT `&ureason=`. This is the
        // #1301-tightened "reason required" branch at L53.
        await page.goto(
            `/index.php?p=commslist&a=ungag&id=${cid}&key=${postkey}`,
        );

        // ---- 1. Chrome footer is attached (no blank page) ----------
        // Pre-fix `<script>ShowBox(...)` crashed mid-output, the body
        // suppressed, the footer never quite latched, blank white
        // page. Post-fix the JSON blob is inert text and the body +
        // footer both render cleanly through `PageDie()` -> render
        // path.
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 2. Toast paints with the L53 copy ---------------------
        // Tight timeout (1200ms) — the toast paints on
        // DOMContentLoaded; theme.js schedules the post-toast
        // redirect 1500ms later. Asserting within 1200ms makes the
        // flake window explicit and keeps the test deterministic.
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Unblock Reason Required' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText('must supply a reason');

        // ---- 3. NO uncaught console errors -------------------------
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Nonexistent cid on ?a=ungag → "Player Not UnGagged" toast', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        const postkey = await extractCommslistPostkey(page);

        // Cid 99999 has no row in the comms table — the L70 SELECT
        // returns empty and the L77 branch fires:
        // `Sbpp\View\Toast::emit('error', 'Player Not UnGagged', …)`.
        // Different copy from the missing-ureason test above, same
        // wire-format. Need a non-empty `&ureason=` so we get past
        // the L53 guard and reach the L77 branch.
        await page.goto(
            `/index.php?p=commslist&a=ungag&id=${NONEXISTENT_BID}&key=${postkey}&ureason=spec-asserts-the-not-found-branch`,
        );

        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Player Not UnGagged' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText(/already ungagged or not a valid block/i);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('No raw <script>ShowBox(...) blob is emitted on the GET-fallback (static guard at the wire layer)', async ({ page }) => {
        // Belt-and-braces mirror of the lostpassword / protest /
        // banlist specs' wire-layer assertion. The PHPUnit
        // `ToastEmitRegressionTest` is the canonical static gate;
        // this catches a runtime regression where a sibling helper
        // re-emits a raw `<script>ShowBox(...)` blob via a path the
        // grep doesn't see.
        const postkey = await extractCommslistPostkey(page);

        const response = await page.request.get(
            `/index.php?p=commslist&a=ungag&id=${NONEXISTENT_BID}&key=${postkey}&ureason=wire-layer-guard`,
        );
        const body = await response.text();
        expect(
            body.includes('<script>ShowBox('),
            'commslist GET-fallback response contains a raw <script>ShowBox(...) blob',
        ).toBe(false);
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'commslist GET-fallback response should carry the pending-toast JSON blob',
        ).toBe(true);
    });
});
