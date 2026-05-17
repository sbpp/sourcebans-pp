/**
 * Flow spec — issue #1403 follow-up: `admin.edit.comms.php` no
 * longer emits raw `<script>ShowBox(...)</script>` blobs on its
 * pre-render guard branches.
 *
 * Pre-fix `web/pages/admin.edit.comms.php` had five toast emission
 * sites (lines 16 / 20 / 33 / 143 / 148 in the issue body) all riding
 * the removed-at-#1123-D1 `ShowBox` helper. The fix routes every
 * site through `Sbpp\View\Toast::emit(...)`, which writes a
 * `<script type="application/json" class="sbpp-pending-toast">
 * …</script>` payload the chrome
 * (`web/themes/default/js/theme.js`'s `flushPendingToasts` drain)
 * consumes on `DOMContentLoaded`.
 *
 * **Scope** is the **pre-render guard branches** specifically — the
 * three branches that PageDie() before any form rendering and the
 * "block not found" branch that renders with a stub. These are the
 * ones an admin lands on by following a stale URL or by direct URL
 * editing; the JSON-side form-validation branch (the `$errorScript`
 * block at L181) is sister #1402's MooTools `$('id').innerHTML`
 * cleanup territory, NOT this PR's scope (see the issue body's
 * "Important wrinkle" — the `window.addEvent` wrapper on L181 is
 * #1402's; the ShowBox calls INSIDE it have all been swept by this
 * lift, leaving the form-field validation as a separate concern).
 *
 * Three tests below cover the three pre-render guard branches that
 * don't require building elaborate row state:
 *
 *   1. **URL key mismatch** (admin.edit.comms.php:15-23) — fires
 *      when an admin follows a stale edit URL whose `&key=` no
 *      longer matches their session's `banlist_postkey` (e.g. they
 *      reopened the panel in a new tab between clicks). Pre-fix
 *      the response body was a blank `<script>ShowBox(...)</script>`
 *      that crashed silently; post-fix the toast paints and the
 *      redirect to `?p=admin&c=comms` lands.
 *   2. **Missing `?id=`** (admin.edit.comms.php:24-32) — fires when
 *      the URL is missing the block id entirely (someone followed
 *      a broken email link or hand-edited the URL). Distinct emission
 *      shape from #1.
 *   3. **Non-existent block id** (admin.edit.comms.php:167-174) — id
 *      `99999` doesn't resolve to a row; the SELECT returns empty,
 *      `$res` is falsy, and the "There was an error getting details"
 *      toast fires before the (still-rendered) Renderer call. Unlike
 *      tests #1 and #2 this branch does NOT PageDie() — it falls
 *      through to Renderer which paints a stub form. The toast +
 *      footer assertions still hold; the post-toast redirect carries
 *      the user back to `?p=commslist` after the 1500ms settle.
 *
 * Each test asserts the three terminal properties shared with the
 * sister specs:
 *
 *   - The chrome footer (`footer.sbpp-footer`) IS attached — the
 *     v2.0 blank-page regression would fail this.
 *   - A `.toast[data-kind="error"]` element appears with the expected
 *     title + body.
 *   - NO uncaught `pageerror` events fire (no `ReferenceError:
 *     ShowBox is not defined` in the modern chrome).
 *
 * Test #4 is the wire-layer regression guard.
 *
 * Admin storage state — `admin.edit.comms.php` is reached via
 * `?p=admin&c=comms&o=edit` (the page-builder routes the `c=comms`
 * `o=edit` pair to `pages/admin.edit.comms.php` per the
 * `$adminRoutes['comms']` config in `web/includes/page-builder.php`).
 * The page-builder gate is
 * `ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS`;
 * the page handler's own check at L42 layers
 * `EditAllBans / EditOwnBans / EditGroupBans` on top. The seeded
 * `admin/admin` user carries `WebPermission::Owner` so both gates
 * pass. Default `auth.ts` storage state covers this.
 *
 * **#1402 overlap callout**: the `window.addEvent('domready', ...)`
 * block at L181 wraps the MooTools `$('steam.msg').innerHTML = …`
 * form-field validation error display. The `ShowBox` calls that
 * formerly lived inside that block have all been converted by this
 * lift (or never existed there in the first place — the
 * `$('id').innerHTML` calls are a separate MooTools surface). The
 * `window.addEvent` line itself is still present; removing it is
 * sister #1402's scope (the MooTools shim is gone since #1123 D1, so
 * the call silently throws `TypeError: window.addEvent is not a
 * function` — but that's the form-field validation surface, not the
 * ShowBox surface this lift targets). When #1402 lands, the
 * `window.addEvent` wrapper goes too AND the form-field validation
 * gets rewired through vanilla JS. Until then the wrapper is an
 * inert throw on rendered pages; we don't include it in this spec's
 * `consoleErrors` check by making the assertion specific to
 * `ReferenceError` and `Toast` / `ShowBox`-shaped errors. (The
 * `window.addEvent is not a function` TypeError is documented as
 * out-of-scope per the issue body's "Important wrinkle" section.)
 */

import { expect, test } from '../../fixtures/auth.ts';
import { seedCommViaApi } from '../../fixtures/seeds.ts';

const NONEXISTENT_BID = 99999;
const WRONG_KEY = 'definitely-not-the-real-postkey';
const SEED_STEAM = 'STEAM_0:0:14045107'; // arbitrary STEAM2 id, distinct from the sister specs'.
const SEED_NICK  = 'admin-edit-comms-postkey-source';

/**
 * Visit the commslist page as the seeded admin and extract the
 * shared `banlist_postkey` (the global session key bans + comms +
 * admin.edit.* surfaces share).
 *
 * The commslist's `setPostKey()` regenerates this every visit and
 * stamps it onto every row's unblock `data-fallback-href` (the
 * inline JS reads it when no JSON dispatcher is on the page).
 * Without a seeded comm row there's NO postkey-carrying URL on the
 * rendered chrome — the search form's CSRF token isn't the postkey,
 * and the sidebar / breadcrumb URLs don't carry it either. The
 * caller seeds a comm in `beforeEach` so this helper always finds
 * an unblock button to read from. Mirrors `extractCommslistPostkey`
 * in `commslist-getfallback-toast.spec.ts` for shape consistency.
 */
async function extractPostkey(
    page: import('@playwright/test').Page,
): Promise<string> {
    await page.goto('/index.php?p=commslist');
    // Wait for the unblock button to attach (the row might still
    // be paint-bound on slower CI runs). A short explicit timeout
    // beats `getAttribute` racing the DOM and silently returning
    // null. The beforeEach `seedCommViaApi` runs before this and
    // tolerates `already_blocked` (Playwright `workers: 1` shares
    // the DB across the suite, so the row may already exist from
    // a sibling spec's beforeEach), so the row is guaranteed to
    // be SOMEWHERE in the table — even if it's not on page 1
    // because a much-later test inserted a higher-id row that
    // pushed ours down. We rely on the default pagination width
    // (sb_settings::config.records_per_page, default 30) keeping
    // our authid above the fold in any realistic e2e run.
    const trigger = page.locator('[data-action="comms-unblock"]').first();
    await trigger.waitFor({ state: 'attached', timeout: 5_000 });
    const hrefAttr = await trigger.getAttribute('data-fallback-href');
    if (!hrefAttr) {
        throw new Error(
            'extractPostkey: no [data-action="comms-unblock"] on '
            + '/index.php?p=commslist — the beforeEach seed didn\'t land',
        );
    }
    const parsed = new URL('http://x/' + hrefAttr);
    const key = parsed.searchParams.get('key');
    if (!key) {
        throw new Error(
            `extractPostkey: no &key= in data-fallback-href "${hrefAttr}"`,
        );
    }
    return key;
}

test.describe('flow: admin.edit.comms toast (#1403 ShowBox → Toast::emit)', () => {
    test.beforeEach(async ({ page }) => {
        // Seed a comm block so the commslist renders at least one
        // row with a `data-fallback-href` carrying the session
        // postkey — `extractPostkey` reads from that. Playwright's
        // `workers: 1` means specs run serially against the shared
        // `sourcebans_e2e` DB; on a re-run (Playwright retry) the
        // same authid will already exist and `Actions.CommsAdd`
        // returns `already_blocked`. We tolerate that — the
        // postkey scraping doesn't care which run inserted the
        // row, only that one row exists. Mirrors
        // `commslist-getfallback-toast.spec.ts` / `comms-drawer.spec.ts`.
        try {
            await seedCommViaApi(page, { nickname: SEED_NICK, steam: SEED_STEAM });
        } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            if (!msg.includes('already_blocked')) throw err;
        }
    });

    test('URL key mismatch → "Possible hacking attempt" toast + chrome footer', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => {
            // Filter out the documented out-of-scope #1402 surface:
            // `window.addEvent is not a function` (MooTools shim
            // gone since #1123 D1). The wrapper block at L181 of
            // admin.edit.comms.php is sister #1402's territory.
            // Every other error type fails the test.
            if (!err.message.includes('window.addEvent')) {
                consoleErrors.push(err.message);
            }
        });

        // Hit the page with a deliberately wrong `&key=`. We don't
        // need a valid id here because the key check at L15 fires
        // FIRST (PageDie before the id check at L24).
        await page.goto(
            `/index.php?p=admin&c=comms&o=edit&id=1&key=${WRONG_KEY}`,
        );

        // ---- 1. Chrome footer is attached (no blank page) ----------
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 2. Toast paints with the L16 copy ---------------------
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Possible hacking attempt' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText('URL Key mismatch');

        // ---- 3. NO unexpected console errors -----------------------
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Missing ?id= → "No block id specified" toast + chrome footer', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => {
            if (!err.message.includes('window.addEvent')) {
                consoleErrors.push(err.message);
            }
        });

        const postkey = await extractPostkey(page);

        // No `&id=` in the URL. The key check at L15 passes (we
        // supply the real postkey), then the id check at L24 fires
        // because `!isset($_GET['id'])` is true. PageDies before
        // the SELECT.
        await page.goto(
            `/index.php?p=admin&c=comms&o=edit&key=${postkey}`,
        );

        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Error' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText('No block id specified');

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Non-existent block id → "There was an error getting details" toast (block not found branch)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => {
            if (!err.message.includes('window.addEvent')) {
                consoleErrors.push(err.message);
            }
        });

        const postkey = await extractPostkey(page);

        // Valid key + numeric id that doesn't exist. The key check
        // (L15) and id check (L24) both pass; the SELECT (L35)
        // returns an empty `$res`. The permission check (L42)
        // would short-circuit on the `$res['aid']` null arrays, BUT
        // the admin/admin user has `WebPermission::Owner` set which
        // satisfies the `HasAccess(Owner|EditAllBans)` arm without
        // needing `$res['aid']` to match — so it falls through to
        // the `if (!$res)` branch at L167.
        await page.goto(
            `/index.php?p=admin&c=comms&o=edit&id=${NONEXISTENT_BID}&key=${postkey}`,
        );

        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Error' });
        await expect(toast).toBeVisible({ timeout: 1200 });
        await expect(toast).toContainText(/error getting details|block has been deleted/i);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('No raw <script>ShowBox(...) blob is emitted (static guard at the wire layer)', async ({ page }) => {
        // Belt-and-braces mirror of the sister specs' wire-layer
        // assertion. Hits the URL-key-mismatch branch (the cheapest
        // of the three guards — no DB access, no permission check).
        const response = await page.request.get(
            `/index.php?p=admin&c=comms&o=edit&id=1&key=${WRONG_KEY}`,
        );
        const body = await response.text();
        expect(
            body.includes('<script>ShowBox('),
            'admin.edit.comms response contains a raw <script>ShowBox(...) blob',
        ).toBe(false);
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'admin.edit.comms response should carry the pending-toast JSON blob',
        ).toBe(true);
    });
});
