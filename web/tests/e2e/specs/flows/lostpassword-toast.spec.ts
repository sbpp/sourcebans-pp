/**
 * Flow spec — issue #1403 marquee: `page.lostpassword.php` no longer
 * emits raw `<script>ShowBox(...)</script>` blobs.
 *
 * Before this lift `web/pages/page.lostpassword.php` echoed five
 * `<script>ShowBox(...)</script>` blocks on its various error /
 * success branches. `ShowBox` lived in `web/scripts/sourcebans.js`,
 * which was deleted at #1123 D1 (v2.0.0), so each blob threw
 * `ReferenceError: ShowBox is not defined` in the modern chrome.
 * Worse: every legacy caller ran upstream of `PageDie()`, which
 * renders the footer + `exit`s. The browser saw the page body
 * suppressed, the footer never quite latched, and the user got a
 * literal blank white page on top of the dropped toast. Real-world
 * pattern from the issue body: an admin requesting a password reset
 * sees a blank page and clicks Reset Password three times "to make
 * it work" — burning three validation tokens — while the actual
 * reset email already landed in their inbox the first time.
 *
 * The fix routes every site through `Sbpp\View\Toast::emit(...)`,
 * which writes a `<script type="application/json"
 * class="sbpp-pending-toast" data-testid="pending-toast">…</script>`
 * payload that the chrome (`web/themes/default/js/theme.js`'s
 * `flushPendingToasts` drain in the chrome IIFE) consumes on
 * `DOMContentLoaded`. The footer is now reliably included by
 * `PageDie()` (its `include_once TEMPLATES_PATH . '/core/footer.php'`
 * was always there — what changed is the toast no longer crashes
 * mid-output, so the body and footer both render).
 *
 * The two test cases below cover the two server-side branches the
 * lift converted that have no DB seed dependency:
 *
 *   1. **Invalid validation string** (`?email=X&validation=short`)
 *      — page.lostpassword.php:53 — triggers
 *      `Toast::emit('error', 'Error', 'Invalid validation string.')`
 *      and `PageDie()`. This is the "user clicked a malformed link"
 *      shape: no DB writes, the page returns quickly, the toast is
 *      the only signal.
 *   2. **Validation does not match** (`?email=X&validation=valid-shape-but-wrong`)
 *      — page.lostpassword.php:64 — same as above but exercises the
 *      DB lookup branch (we use an email + 16-char token combo that
 *      the seed DB has no row for; the SELECT returns empty and the
 *      "does not match" toast fires). This is the canonical case an
 *      attacker testing for valid emails would hit, and the case
 *      where the user-perceived "blank page" regression was worst.
 *
 * Both cases assert the same three terminal properties:
 *
 *   - The chrome footer (`footer.sbpp-footer`) IS attached — proves
 *     `PageDie()` rendered the chrome (the v2.0 blank-page regression
 *     would fail this).
 *   - A `.toast[data-kind="error"]` element appears with the expected
 *     body copy — proves the chrome JS picked up the JSON blob and
 *     surfaced the toast.
 *   - NO uncaught `ReferenceError` / other console errors fire —
 *     specifically catches a regression where the pending-toast
 *     bootstrap silently throws on a malformed payload (the
 *     `try/catch` in `flushPendingToasts` is what prevents this
 *     today; this assertion gates against a future "let's drop
 *     the try/catch" change).
 *
 * The successful "password reset and sent to email" branch (lines
 * 104-110, the marquee good-case from the issue body) requires a
 * working SMTP path, a real `:prefix_admins.validate` token seeded
 * for an email we know, and mailpit-side assertion that the email
 * landed. The setup overhead is non-trivial (mailpit isn't wired
 * into any e2e fixture today and the SMTP defaults in
 * `data.sql` are blank) and the toast-emission code path is
 * structurally identical to the error branches above. Keeping the
 * spec light avoids the mailpit-config plumbing while still gating
 * the specific regression #1403 caught.
 *
 * Logged-out viewport — `page.lostpassword.php:28-31` redirects any
 * logged-in visitor to `index.php` before the GET-validation branch
 * runs. The shared `auth.ts` fixture ships admin storage state by
 * default; we override per-describe (mirrors `smoke/login.spec.ts`,
 * `smoke/routing-truthiness.spec.ts`'s AUTH-1 block).
 */

import { expect, test } from '../../fixtures/auth.ts';

const SHORT_TOKEN = 'short';
const MISMATCH_TOKEN = '0000000000000000aaaa'; // 20 chars; the SELECT will not find a matching admin row.

test.describe('flow: lostpassword toast (#1403 ShowBox → Toast::emit)', () => {
    // Per-describe override: log out for this whole block. The form
    // / GET-validation branch is logged-out-only — page.lostpassword.php
    // redirects authenticated visitors to /index.php before reaching
    // the toast-emitting branches. Mirrors the AUTH-1 block in
    // `smoke/routing-truthiness.spec.ts`.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('Invalid validation string → error toast + chrome footer (no blank page)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto(
            `/index.php?p=lostpassword&email=anyone@example.test&validation=${SHORT_TOKEN}`,
        );

        // ---- 1. Chrome footer is attached (the pre-#1403 blank page) ----
        // Pre-fix the page handler `echo`'d a `<script>ShowBox(...)</script>`
        // blob and called `PageDie()`. The ShowBox throw silently
        // detached the browser's incremental parse before the footer
        // rendered (the user saw blank white). Post-fix the JSON blob
        // is inert text and the footer renders cleanly.
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 2. Toast paints with the expected copy --------------------
        // The chrome's `flushPendingToasts` drainer picks up the JSON
        // blob on `DOMContentLoaded` and calls `showToast(...)`. The
        // rendered element carries `data-kind="error"` (mapped from
        // the helper's `'error'` kind) — anchor on that + a hasText
        // filter so a hypothetical sibling toast doesn't false-match.
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Invalid validation string' });
        await expect(toast).toBeVisible();

        // ---- 3. NO ReferenceError / other uncaught console errors ------
        // Pre-fix every page load threw `ReferenceError: ShowBox is
        // not defined`. Post-fix the page should load cleanly with no
        // script-level errors at all. We give the chrome a tick to
        // settle any post-DOMContentLoaded async work before
        // asserting (lucide icon mount etc. — none should error).
        await page.waitForLoadState('domcontentloaded');
        await expect(toast).toBeVisible();
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Mismatched validation → error toast + chrome footer (the marquee user-reported blank page)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // 20-char token — passes the length>=10 guard at L53 — but
        // there's no admin row with `email = … AND validate = …`,
        // so the SELECT returns empty and the page falls through to
        // the "validation string does not match" branch at L64.
        await page.goto(
            `/index.php?p=lostpassword&email=anyone-else@example.test&validation=${MISMATCH_TOKEN}`,
        );

        // Same three terminal checks as the short-token test —
        // verifying the longer error-message branch fires through
        // the same emission shape.
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: /validation string does not match/i });
        await expect(toast).toBeVisible();
        await expect(toast).toContainText('reset request');

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('No raw <script>ShowBox(...) blob is emitted (static regression guard at the wire layer)', async ({ page }) => {
        // Belt-and-braces: the PHPUnit `ToastEmitRegressionTest` is
        // the canonical static gate, but the runtime equivalent
        // catches a regression where the page handler stops calling
        // `Toast::emit` AND silently re-introduces a raw `<script>`
        // shape via a sibling helper. We compare the response body
        // against the literal v1.x pattern.
        const response = await page.goto(
            `/index.php?p=lostpassword&email=x@y.z&validation=${SHORT_TOKEN}`,
        );
        expect(response, 'page response was null').not.toBeNull();
        const body = await response!.text();
        expect(
            body.includes('<script>ShowBox('),
            'lostpassword response contains a raw <script>ShowBox(...) blob',
        ).toBe(false);
        // Sanity: the modern wire-shape IS present in the response
        // before theme.js drains it. (We probe the response body
        // directly so the drain step doesn't race.)
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'lostpassword response should carry the pending-toast JSON blob',
        ).toBe(true);
    });
});
