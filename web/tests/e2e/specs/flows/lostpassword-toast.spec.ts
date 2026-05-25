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
 * class="sbpp-pending-toast">…</script>` payload that the chrome
 * (`web/themes/default/js/theme.js`'s `flushPendingToasts` drain in
 * the chrome IIFE) consumes on `DOMContentLoaded`. The footer is now
 * reliably included by `PageDie()` (its `include_once TEMPLATES_PATH
 * . '/core/footer.php'` was always there — what changed is the toast
 * no longer crashes mid-output, so the body and footer both render).
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
 * The two error branches assert the same three terminal properties:
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
 * The third test (`Happy path: …`) is the marquee user-reported
 * regression from the issue body: an admin requests a password
 * reset, sees a blank white page, clicks Reset Password three
 * times "to make it work" while the actual reset email already
 * landed in their inbox the first time. The test seeds the SMTP
 * config (pointed at the dev stack's mailpit container), a known
 * `:prefix_admins.validate` token, and a `config.mail.from_email`,
 * then drives the success branch and asserts BOTH (a) the
 * "Password Reset" toast paints and (b) the password-reset email
 * lands in mailpit's inbox. The mailpit HTTP API
 * (`http://mailpit:8025` from inside the web container; the
 * worktree-local override remaps the host-published port to
 * `:10191` but the in-network service alias stays the same)
 * exposes a deterministic, queryable inbox so the test doesn't
 * depend on parsing arbitrary message-id strings.
 *
 * Logged-out viewport — `page.lostpassword.php:28-31` redirects any
 * logged-in visitor to `index.php` before the GET-validation branch
 * runs. The shared `auth.ts` fixture ships admin storage state by
 * default; we override per-describe (mirrors `smoke/login.spec.ts`,
 * `smoke/routing-truthiness.spec.ts`'s AUTH-1 block).
 */

import { expect, test } from '../../fixtures/auth.ts';
import { seedLostpasswordE2e, seedLostpasswordEnumAdminE2e } from '../../fixtures/db.ts';

// #1456 cross-test isolation: the marquee #1403 test seeds the
// `admin@example.test` row's `:prefix_admins.validate` column to a
// known token then GETs a URL keyed on it. The form-POST tests at
// the tail of this file call `api_auth_lost_password` which UPDATEs
// the matched admin's `validate` to a fresh random value. Two
// safeguards keep these from racing:
//
//   1. The form-POST tests seed (and exclusively target) a DEDICATED
//      admin row (`lostpw-enum-known@example.test`, see
//      `seedLostpasswordEnumAdminE2e`). Hitting the same `validate`
//      column as the marquee test would otherwise produce a cross-
//      project flake — Playwright runs the same spec under both
//      `chromium` and `mobile-chromium` IN PARALLEL by default, and
//      `test.describe.configure({ mode: 'serial' })` only constrains
//      within a single project's worker. The dedicated row sidesteps
//      the race at the data layer rather than the scheduler layer.
//   2. We ALSO mark the file `serial` so within-project ordering is
//      deterministic (CI runs `workers: 1` per AGENTS.md "Playwright
//      E2E specifics"; keeping the file in serial mode mirrors that
//      behaviour locally and protects against future regressions
//      where two tests within this file accidentally race on a
//      shared resource we hadn't anticipated).
test.describe.configure({ mode: 'serial' });

const SHORT_TOKEN = 'short';
const MISMATCH_TOKEN = '0000000000000000aaaa'; // 20 chars; the SELECT will not find a matching admin row.

/**
 * Mailpit HTTP API root. Inside the web container it's reachable as
 * `http://mailpit:8025` (the docker-compose service alias — same
 * whether the parent stack or the worktree-local parallel stack is
 * running, because the override only renames containers + remaps
 * host-published ports, NOT the service alias). The env override
 * is the host-mode escape hatch — the worktree-local
 * `docker-compose.override.yml` publishes mailpit's UI on
 * `:10191` for direct browsing, so a developer running
 * `npx playwright test` from the host (E2E_IN_CONTAINER unset)
 * can point at `http://localhost:10191`.
 */
const MAILPIT_BASE_URL = process.env.E2E_MAILPIT_BASE_URL
    ?? 'http://mailpit:8025';

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

    test('Happy path: password reset → "Password Reset" toast + chrome footer + email lands in mailpit (marquee #1403 user-reported regression)', async ({ page, request }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // ---- 0. Set up the SMTP / from-address config and a known
        //         validate token. The seeder is idempotent so re-runs
        //         (and CI's `retries: 1`) work even after a previous
        //         success consumed the validate token. -----------
        const { email, token } = await seedLostpasswordE2e();

        // Clear mailpit's inbox so the post-request assertion is
        // unambiguous. The DELETE endpoint is documented at
        // https://mailpit.axllent.org/docs/api-v1/messages — pre-fix
        // any prior test's mail would still be in the inbox and the
        // count check would have to skip the first N messages.
        const purge = await request.delete(`${MAILPIT_BASE_URL}/api/v1/messages`);
        expect(
            purge.ok(),
            `mailpit purge failed (status ${purge.status()}): ${await purge.text()}`,
        ).toBe(true);

        // ---- 1. Drive the success branch ------------------------------
        // Same shape the user hits when they click the "Reset password"
        // link in their inbox: the URL the email's
        // PasswordReset / PasswordResetSuccess templates build is
        // `?p=lostpassword&email=…&validation=<token>`. The page
        // handler rolls a new password, sends it via SMTP, then
        // (now) emits a `Toast::emit('info', 'Password Reset', …)`
        // and `PageDie()`s into the footer. Pre-fix the success
        // branch echoed `<script>ShowBox(...)</script>` and the
        // user got a blank page.
        await page.goto(
            `/index.php?p=lostpassword&email=${encodeURIComponent(email)}&validation=${token}`,
        );

        // ---- 2. Chrome footer is attached ----------------------------
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 3. Success toast paints with the expected copy ---------
        // The lift kept the legacy `'blue'` background-class fidelity
        // by mapping to `kind=info` (the rationale: this is a
        // confirmation, not a "yay it worked" — the user is being
        // told what to do next). A future ticket may flip this to
        // `kind=success`; either is acceptable here as the user-
        // visible signal is identical.
        const toast = page
            .locator('.toast[data-kind="info"], .toast[data-kind="success"]')
            .filter({ hasText: 'Password Reset' });
        await expect(toast).toBeVisible();
        await expect(toast).toContainText(/reset and sent to your email/i);

        // ---- 4. Email landed in mailpit ----------------------------
        // The mailer is asynchronous in the abstract, but the panel
        // calls `Mail::send` synchronously before emitting the toast
        // (`page.lostpassword.php` line 81 — the send call is
        // BEFORE the toast emit, and a send-failure branches into the
        // error toast at line 88 instead of falling through). So by
        // the time we see the success toast the SMTP transaction has
        // completed; mailpit holds the message in-memory and the
        // HTTP API returns it on the next request. We poll for ~5s
        // just in case mailpit's accept-to-list latency surprises.
        let landedTo: string | null = null;
        for (let attempt = 0; attempt < 10; attempt += 1) {
            const resp = await request.get(
                `${MAILPIT_BASE_URL}/api/v1/messages?limit=50`,
            );
            expect(resp.ok(), `mailpit list failed (${resp.status()})`).toBe(true);
            const body = await resp.json() as {
                messages?: Array<{ To?: Array<{ Address?: string }> }>;
            };
            const found = (body.messages ?? []).find((msg) =>
                (msg.To ?? []).some((addr) => addr.Address === email),
            );
            if (found) {
                landedTo = email;
                break;
            }
            await page.waitForTimeout(500);
        }
        expect(
            landedTo,
            `mailpit never received an email to ${email} within 5s — the panel handler's `
                + `\`Mail::send\` path did not actually land in the SMTP transport. `
                + `Either (a) \`Mailer::create()\` short-circuited (smtp.host/user/pass empty), `
                + `(b) the success branch failed silently, or (c) mailpit isn't reachable as `
                + `${MAILPIT_BASE_URL} from this test environment.`,
        ).toBe(email);

        // ---- 5. NO uncaught console errors --------------------------
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

/**
 * #1456 — form-POST flow privacy fix.
 *
 * The pre-fix shape surfaced an "Error: The email address you supplied
 * is not registered on the system" toast when the user submitted an
 * email that didn't match any admin row. That gave an unauthenticated
 * visitor a trivial one-request-per-address oracle: type any email,
 * read the toast title (Error vs Check E-Mail), conclude whether the
 * address is registered on the panel.
 *
 * The post-fix shape returns the SAME generic "Check E-Mail" toast
 * regardless of whether the email matched. These specs drive the
 * `<form id="lostpw-form">` in `page_lostpassword.tpl` end-to-end
 * (the `sb.api.call(Actions.AuthLostPassword)` round-trip + the
 * chrome's `window.SBPP.showToast` paint) so a regression that
 * accidentally re-introduces a branch-specific message — even one
 * driven from a client-side `if (res.error.code === 'not_registered')`
 * — fails the gate.
 *
 * The PHPUnit integration tests (`web/tests/api/AuthTest.php`) pin
 * the wire-shape contract on the server side. These E2E tests pin
 * the user-visible chrome behavior on the client side. Both halves
 * are necessary: a future template tweak could re-introduce a
 * branch-specific toast without changing the wire shape, and a
 * future handler refactor could leak the wire shape without
 * changing the chrome — only the pair of tests catches both axes.
 */
test.describe('flow: lostpassword form POST (#1456 user-enumeration leak)', () => {
    // Logged-out-only block — page.lostpassword.php redirects
    // authenticated visitors to /index.php before the form renders.
    test.use({ storageState: { cookies: [], origins: [] } });

    // Use a dedicated admin row for the "known email" arm so we
    // don't UPDATE `admin@example.test`'s validate column out from
    // under the marquee #1403 happy-path test. Cross-project (chromium
    // ⇄ mobile-chromium) Playwright runs are intrinsically parallel.
    // See the top-of-file comment + `seedLostpasswordEnumAdminE2e`
    // docblock for the full rationale. Idempotent shim — re-runs are
    // free, so the `beforeAll` cost is one INSERT IGNORE per project.
    let knownEmail: string;
    test.beforeAll(async () => {
        const seed = await seedLostpasswordEnumAdminE2e();
        knownEmail = seed.email;
    });

    test('Form submission with an unknown email shows the generic "Check E-Mail" toast (NOT an error)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=lostpassword');

        // The form is the static card the View renders — no GET
        // parameters means the GET-validation branch is skipped and
        // the page draws the form. Wait for the lucide mail icon
        // chrome to settle before driving the submit so the busy-
        // state assertions downstream don't race with the icon
        // paint.
        await expect(page.getByTestId('lostpw-email')).toBeVisible();

        await page.getByTestId('lostpw-email').fill('definitely-not-an-admin@example.test');
        await page.getByTestId('lostpw-submit').click();

        // The post-fix toast is the SAME envelope as the matched-
        // email branch: kind=info (mapped from the API's 'blue'),
        // title='Check E-Mail'. Anchor on the data-kind hook so
        // a sibling chrome surface (e.g. a CSRF error toast that
        // somehow snuck through) doesn't false-match.
        const toast = page
            .locator('.toast[data-kind="info"]')
            .filter({ hasText: 'Check E-Mail' });
        await expect(toast).toBeVisible();

        // Body wording IS asserted client-side here because the
        // body-copy contract is the whole point: a "we sent you
        // an email" wording would leak that the address exists,
        // an "address not registered" wording would leak that it
        // doesn't, and ONLY the conditional "if an account is
        // registered" wording is neutral. Spec the wording
        // explicitly so a future copy edit can't silently undo
        // the fix.
        await expect(toast).toContainText(/if an account is registered/i);

        // Crucially: NO error toast paints. If the regression
        // re-surfaces, the chrome would paint a kind=error toast
        // with "not registered" / "Error" in it. Assert the
        // absence loudly so a flaky-positive isn't possible.
        const errorToast = page.locator('.toast[data-kind="error"]');
        await expect(errorToast).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Form submission with a known email shows the SAME generic "Check E-Mail" toast (no oracle)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=lostpassword');
        await expect(page.getByTestId('lostpw-email')).toBeVisible();

        // Use the dedicated `lostpw-enum-known@example.test` row
        // seeded in `beforeAll` rather than `admin@example.test` so
        // we don't race the marquee #1403 happy-path test's
        // `validate`-token seed (the handler UPDATEs `validate` on
        // every match — see the top-of-file comment).
        await page.getByTestId('lostpw-email').fill(knownEmail);
        await page.getByTestId('lostpw-submit').click();

        // The toast HAS to look identical to the unknown-email
        // case above. We assert the same selector and the same
        // body wording — if the chrome started branching on
        // success-vs-failure ("Your reset email has been sent"
        // vs the neutral wording), the wording check would fail
        // here even though the kind matches.
        const toast = page
            .locator('.toast[data-kind="info"]')
            .filter({ hasText: 'Check E-Mail' });
        await expect(toast).toBeVisible();
        await expect(toast).toContainText(/if an account is registered/i);

        // Same loud absence assertion: no error toast paints.
        // Pre-fix this branch was the only one that COULD have
        // produced an error toast — the `mail_failed` envelope
        // surfaced via the handler's send-failure path. Without
        // a configured SMTP the e2e seed has nothing routable,
        // so pre-fix this test would have caught the
        // `mail_failed` -> Error toast leak. Post-fix it's a
        // dual gate: catches re-introduction of either
        // `not_registered` OR `mail_failed` user-visible
        // branching.
        const errorToast = page.locator('.toast[data-kind="error"]');
        await expect(errorToast).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
