/**
 * Flow spec — issue #1403 follow-up: `page.banlist.php` no longer
 * emits raw `<script>ShowBox(...)</script>` blobs on its legacy
 * GET-fallback unban / delete paths.
 *
 * Pre-fix `web/pages/page.banlist.php` had nine toast emission sites
 * (lines 54 / 89 / 127 / 133 / 139 / 147 / 195 / 201 / 207 in the
 * issue body) all riding the removed-at-#1123-D1 `ShowBox` helper.
 * The fix routes every site through `Sbpp\View\Toast::emit(...)`,
 * which writes a `<script type="application/json"
 * class="sbpp-pending-toast">…</script>`
 * payload the chrome (`web/themes/default/js/theme.js`'s
 * `flushPendingToasts` drain) consumes on `DOMContentLoaded`.
 *
 * **Scope** of this spec is the **GET fallback** specifically — the
 * `?a=unban` / `?a=delete` legacy entry points an admin reaches by
 * either (a) clicking an unban link from a pre-#1301 email digest,
 * (b) directly editing the URL bar, or (c) running the panel in a
 * no-JS browser. The modern JSON-dispatcher path (`Actions.BansUnban`
 * via `#bans-unban-dialog`) already toasts correctly via the JSON
 * envelope (`message` field) and is out of scope here per the issue
 * body. We only fix the GET fallback in #1403.
 *
 * The two tests below cover the two distinct toast-emitting branches
 * that don't require building elaborate row state:
 *
 *   1. **Missing `ureason`** (page.banlist.php:54) — fires after #1301
 *      tightened the GET fallback to require a reason. An admin who
 *      hand-edits the URL and forgets `&ureason=` lands here; pre-fix
 *      the response body was a blank `<script>ShowBox(...)</script>`
 *      that crashed and the user couldn't tell whether the unban
 *      actually happened. Post-fix the toast paints + the redirect to
 *      `?p=banlist` lands.
 *   2. **Bid that doesn't resolve to a live ban** (page.banlist.php:94)
 *      — bid `99999` doesn't exist in the e2e DB (or any reasonable
 *      install). The handler can't find a row, fires the
 *      `Player Not Unbanned` toast, and redirects. Distinct emission
 *      shape from #1: same wire format, different copy.
 *
 * Both tests assert the three terminal properties shared with the
 * lostpassword / protest specs:
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
 * NOR is missing the JSON blob. Mirror of the lostpassword /
 * protest specs' belt-and-braces shape.
 *
 * Admin storage state — the GET fallback paths gate on
 * `WebPermission::Unban` / `WebPermission::DeleteBan`; the seeded
 * `admin/admin` user carries `WebPermission::Owner` so both pass.
 * Default `auth.ts` storage state covers this; no per-describe
 * override.
 *
 * Truncation — these tests SEED a ban (via `seedBanViaApi`) so the
 * `?a=unban&id=BID&key=KEY` URL has a real target for the L54
 * `ureason`-required branch. We DON'T `truncateE2eDb` per-test
 * because the e2e DB is single-DB / `workers: 1` (per AGENTS.md
 * "Playwright E2E specifics") and `seedBanViaApi` accepts duplicates
 * via the `bans.add` handler's `already_banned` arm. The bid for the
 * "bid that doesn't exist" test is 99999, well above any realistic
 * sequence in the seed-or-fresh DB.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

const SEED_STEAM   = 'STEAM_0:0:14034317'; // arbitrary STEAM2 id; new authid each spec run is fine.
const SEED_NICK    = 'getfallback-victim';
const NONEXISTENT_BID = 99999;             // well above the e2e DB's seeded ban range.

/**
 * Visit the banlist page as the seeded admin, then extract the
 * `banlist_postkey` from a rendered `data-fallback-href` attribute
 * on the first unban / delete row button. The session-scoped postkey
 * is regenerated per visit (per `page.banlist.php:setPostKey()`); we
 * have to read it from the live response, not hardcode it.
 *
 * Caller responsibility: the banlist must have at least one row
 * (i.e. a `seedBanViaApi(...)` ran beforehand) — otherwise the unban
 * button is absent and the extraction fails.
 */
async function extractBanlistPostkey(
    page: import('@playwright/test').Page,
): Promise<string> {
    await page.goto('/index.php?p=banlist');
    // `data-fallback-href` on the unban button is the canonical
    // GET-fallback URL the template hands to the inline JS for the
    // "JS dispatcher absent" branch. Format:
    //   index.php?p=banlist&a=unban&id=<bid>&key=<postkey>
    // We just need the &key= value.
    const hrefAttr = await page
        .locator('[data-action="bans-unban"]')
        .first()
        .getAttribute('data-fallback-href');
    if (!hrefAttr) {
        throw new Error(
            'extractBanlistPostkey: no [data-action="bans-unban"] element found on /index.php?p=banlist '
            + '— seed a ban first via seedBanViaApi()',
        );
    }
    // `URL()` choke on the relative href; prepend a synthetic
    // origin so URL parsing works.
    const parsed = new URL('http://x/' + hrefAttr);
    const key = parsed.searchParams.get('key');
    if (!key) {
        throw new Error(
            `extractBanlistPostkey: no &key= in data-fallback-href "${hrefAttr}"`,
        );
    }
    return key;
}

test.describe('flow: banlist GET-fallback toast (#1403 ShowBox → Toast::emit)', () => {
    test.beforeEach(async ({ page }) => {
        // Seed a ban so the L54 `ureason`-required branch has a real
        // bid to point at AND the postkey-extraction helper can find
        // an unban button in the rendered table. `seedBanViaApi`
        // calls `Actions.BansAdd`; on a re-run for the same authid
        // it rejects as `already_banned`, but the prior insert is
        // what we wanted so we tolerate that case. AGENTS.md
        // "Playwright E2E specifics" pins `workers: 1` against the
        // shared `sourcebans_e2e` DB — sibling specs can leave the
        // row behind without trouble. Mirrors the `seedBanOrAccept`
        // tolerance shape in `player-drawer.spec.ts`.
        try {
            await seedBanViaApi(page, { nickname: SEED_NICK, steam: SEED_STEAM });
        } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            if (!msg.includes('already_banned')) throw err;
        }
    });

    test('Missing ureason on ?a=unban → "Unban Reason Required" toast + chrome footer', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        const postkey = await extractBanlistPostkey(page);

        // Read the seeded bid from the same data-fallback-href we
        // pulled the postkey from. Cleaner than threading the bid
        // out of `seedBanViaApi` separately; same source of truth.
        const hrefAttr = await page
            .locator('[data-action="bans-unban"]')
            .first()
            .getAttribute('data-fallback-href');
        const seededBid = new URL('http://x/' + (hrefAttr ?? '')).searchParams.get('id');
        if (!seededBid) {
            throw new Error('could not extract seeded bid from data-fallback-href');
        }

        // Hit the GET fallback WITHOUT `&ureason=`. This is the
        // #1301-tightened "reason required" branch at L54.
        await page.goto(
            `/index.php?p=banlist&a=unban&id=${seededBid}&key=${postkey}`,
        );

        // ---- 1. Chrome footer is attached (no blank page) ----------
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 2. Toast paints with the L54 copy ---------------------
        // Tight timeout (1200ms) — the toast paints on
        // DOMContentLoaded; theme.js schedules the post-toast
        // redirect 1500ms later. Asserting within 1200ms makes the
        // flake window explicit and keeps the test deterministic.
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Unban Reason Required' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText('must supply a reason');

        // ---- 3. NO uncaught console errors -------------------------
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Nonexistent bid on ?a=unban → "Player Not Unbanned" toast', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        const postkey = await extractBanlistPostkey(page);

        // Bid 99999 has no row in the bans table — the L83 SELECT
        // returns empty and the L94 branch fires:
        // `Sbpp\View\Toast::emit('error', 'Player Not Unbanned', …)`.
        // Different copy from the missing-ureason test above, same
        // wire-format. Need a non-empty `&ureason=` so we get past
        // the L53 guard and reach the L94 branch.
        await page.goto(
            `/index.php?p=banlist&a=unban&id=${NONEXISTENT_BID}&key=${postkey}&ureason=spec-asserts-the-not-found-branch`,
        );

        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Player Not Unbanned' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText(/already unbanned or not a valid ban/i);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('No raw <script>ShowBox(...) blob is emitted on the GET-fallback (static guard at the wire layer)', async ({ page }) => {
        // Belt-and-braces mirror of the lostpassword / protest specs'
        // wire-layer assertion. The PHPUnit
        // `ToastEmitRegressionTest` is the canonical static gate;
        // this catches a runtime regression where a sibling helper
        // re-emits a raw `<script>ShowBox(...)` blob via a path the
        // grep doesn't see.
        const postkey = await extractBanlistPostkey(page);

        const response = await page.request.get(
            `/index.php?p=banlist&a=unban&id=${NONEXISTENT_BID}&key=${postkey}&ureason=wire-layer-guard`,
        );
        const body = await response.text();
        expect(
            body.includes('<script>ShowBox('),
            'banlist GET-fallback response contains a raw <script>ShowBox(...) blob',
        ).toBe(false);
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'banlist GET-fallback response should carry the pending-toast JSON blob',
        ).toBe(true);
    });
});
