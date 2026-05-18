/**
 * Flow spec — issue #1403 follow-up: `page.protest.php` no longer
 * emits raw `<script>ShowBox(...)</script>` blobs.
 *
 * Pre-fix `web/pages/page.protest.php` had three toast emission
 * sites (lines 13 / 94 / 165 in the issue body) all riding the
 * removed-at-#1123-D1 `ShowBox` helper. The fix routes every site
 * through `Sbpp\View\Toast::emit(...)`, which writes a
 * `<script type="application/json" class="sbpp-pending-toast">
 * …</script>` payload the chrome
 * (`web/themes/default/js/theme.js`'s `flushPendingToasts` drain)
 * consumes on `DOMContentLoaded`.
 *
 * The two server-validated branches this spec exercises don't
 * require any DB seed beyond the install fixture (the form
 * submit gets rejected before any SQL writes happen) and both
 * fire the same emission call site (the L100-104 accumulated-
 * errors path). They map onto the two distinct shapes a real
 * appeals visitor would hit:
 *
 *   1. **Invalid Steam ID format** — submitting `notvalid` (or
 *      anything that fails `\SteamID\SteamID::isValidID()`) gates
 *      at L38 and the accumulated `$errors` carries
 *      `* Please type a valid STEAM ID.<br>`. The toast title is
 *      'Please fix the following'; the `<br>` separators are
 *      converted to spaces at the call site (see the L94-99
 *      comment in page.protest.php).
 *   2. **Format-valid but not-banned Steam ID** — submitting
 *      `STEAM_0:0:1234567` passes the L38 validation, the L42
 *      SELECT returns no rows, and the L46 branch fires:
 *      `* That Steam ID is not banned!<br>`. This is the canonical
 *      "user typed a SteamID that isn't in our ban list" shape —
 *      the same error envelope but a different message body —
 *      proving the conversion path doesn't tunnel a single static
 *      string.
 *
 * The successful "Your protest has been sent" branch (L175) requires
 * a real seeded ban row to protest against AND the mailer to fire
 * (the L159-172 `Mail::send` is on the same code path), neither of
 * which is wired into the e2e fixture today. Keeping the spec light
 * avoids that setup overhead while still gating the specific
 * regression #1403 caught (the validation-error branch is what real
 * users hit when an appeal is typo'd, and it was emitting the same
 * silent-blank-page that the marquee lostpassword regression
 * surfaced).
 *
 * Anonymous viewport — `web/pages/page.protest.php` doesn't gate on
 * `$userbank`; the public appeal form is reachable logged-out.
 * Mirrors the lostpassword spec's per-describe `storageState`
 * override.
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('flow: protest toast (#1403 ShowBox → Toast::emit)', () => {
    // Logged-out is the canonical caller shape for the appeal form
    // (the public banlist links anonymous viewers here). The form
    // works for logged-in admins too, but the regression #1403 caught
    // hit anonymous appeal-typoers hardest — they couldn't see why
    // their form submission silently dropped.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('Invalid Steam ID format → error toast + chrome footer (the post-submit blank page)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=protest');

        // ---- Fill the form with intentionally-malformed data --------
        // SteamID = `notvalid` fails `\SteamID\SteamID::isValidID()`
        // (the L38 gate). Every other field is filled with non-empty
        // values so the browser's native `required` constraint passes
        // and the form posts — the server is the test target, not the
        // HTML validator.
        await page.locator('[data-testid="protest-type"]').selectOption('0');
        await page.locator('[data-testid="protest-steam"]').fill('notvalid');
        await page.locator('[data-testid="protest-name"]').fill('Player X');
        await page.locator('[data-testid="protest-email"]').fill('appellant@example.test');
        await page.locator('[data-testid="protest-reason"]').fill(
            'I was banned for nothing.',
        );
        await page.locator('[data-testid="protest-submit"]').click();

        // ---- 1. Chrome footer is attached (the pre-#1403 blank page) ----
        // Pre-fix `<script>ShowBox(...)` crashed mid-output, the body
        // suppressed, the footer never quite latched, blank white
        // page. Post-fix the JSON blob is inert text and the body +
        // footer both render cleanly through `PageDie()` -> render
        // path. The footer is the cheapest deterministic marker.
        await expect(page.locator('footer.sbpp-footer')).toBeAttached();

        // ---- 2. Toast paints with the expected copy -----------------
        // L100-104 emits `('error', 'Please fix the following',
        // preg_replace('#<br\s*/?>#i', ' ', $errors))`. The body
        // carries the accumulated validation messages with `<br>`
        // separators flattened to spaces. We anchor on the title
        // (`Please fix the following`) and assert the SteamID-format
        // message text appears in the body — proves the wire format
        // round-trips correctly.
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Please fix the following' });
        await expect(toast).toBeVisible();
        await expect(toast).toContainText(/please type a valid steam id/i);

        // ---- 3. NO ReferenceError / other uncaught console errors ----
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Format-valid but not-banned Steam ID → error toast (different body, same emission contract)', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=protest');

        // SteamID `STEAM_0:0:1234567` passes `\SteamID\SteamID::isValidID`
        // (the L38 gate) but the `SELECT bid FROM :prefix_bans WHERE
        // authid = :authid AND RemovedBy IS NULL AND type = 0` at L42
        // finds no matching row in the fresh-install fixture, so the
        // L46 branch fires with `* That Steam ID is not banned!<br>`.
        // Distinct from test #1: same emission shape, different body.
        await page.locator('[data-testid="protest-type"]').selectOption('0');
        await page.locator('[data-testid="protest-steam"]').fill('STEAM_0:0:1234567');
        await page.locator('[data-testid="protest-name"]').fill('Player Y');
        await page.locator('[data-testid="protest-email"]').fill('appellant@example.test');
        await page.locator('[data-testid="protest-reason"]').fill(
            'My account got banned but I never played here.',
        );
        await page.locator('[data-testid="protest-submit"]').click();

        await expect(page.locator('footer.sbpp-footer')).toBeAttached();
        const toast = page
            .locator('.toast[data-kind="error"]')
            .filter({ hasText: 'Please fix the following' });
        await expect(toast).toBeVisible();
        await expect(toast).toContainText(/steam id is not banned/i);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('No raw <script>ShowBox(...) blob is emitted on a rejected submit (static guard at the wire layer)', async ({ page }) => {
        // Belt-and-braces mirror of the lostpassword spec's wire-layer
        // assertion. The PHPUnit `ToastEmitRegressionTest` is the
        // canonical static gate; this catches a regression where the
        // page handler stops calling `Toast::emit` AND silently
        // re-introduces a raw `<script>ShowBox(...)` blob via a sibling
        // helper. We need to drive a real POST (not just a GET) here
        // because the toast-emitting branches only fire after
        // `$_POST['subprotest']` arrives — a bare GET renders the
        // empty form with no toast in the response body. Playwright's
        // `page.request.post(...)` carries the same cookie jar as the
        // page session, so the server-side CSRF gate accepts the
        // request as long as we mint a token from a fresh page load.
        await page.goto('/index.php?p=protest');
        const csrfToken = await page
            .locator('input[name="csrf_token"]')
            .first()
            .inputValue();

        const formData = new URLSearchParams({
            csrf_token: csrfToken,
            subprotest: '1',
            Type: '0',
            SteamID: 'notvalid',
            IP: '',
            PlayerName: 'Player Z',
            EmailAddr: 'appellant@example.test',
            BanReason: 'Bad submission to exercise the validation branch.',
        });

        const response = await page.request.post('/index.php?p=protest', {
            data: formData.toString(),
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
        });
        const body = await response.text();
        expect(
            body.includes('<script>ShowBox('),
            'protest response contains a raw <script>ShowBox(...) blob',
        ).toBe(false);
        expect(
            body.includes('class="sbpp-pending-toast"'),
            'protest response should carry the pending-toast JSON blob',
        ).toBe(true);
    });
});
