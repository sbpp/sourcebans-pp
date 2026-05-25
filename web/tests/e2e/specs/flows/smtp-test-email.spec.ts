/**
 * Flow spec — issue #1455: "SMTP - Add test email function".
 *
 * The admin Settings page (`?p=admin&c=settings&section=settings`)
 * now carries a "Send test email" affordance inside the SMTP card. The
 * pre-issue panel had no way to verify SMTP credentials short of
 * waiting for a real outbound mail (password reset, ban protest,
 * etc.) — operators routinely shipped broken SMTP into production
 * for days before noticing. This spec drives the affordance
 * end-to-end and asserts the marquee user-reported outcome from the
 * issue body: an admin types a recipient, clicks the button, and
 * the test email actually lands in their inbox (mailpit, in CI).
 *
 * Three scenarios:
 *
 *   1. **Happy path** — SMTP configured (via `seedLostpasswordE2e`,
 *      which sets `smtp.host=mailpit:1025` + a `from_email`), click
 *      the button, assert (a) the success toast paints and (b) a
 *      message lands in mailpit addressed to the operator's email.
 *      This is the contract the GitHub issue specifically asks for:
 *      "Test email function that is enabled when SMTP details have
 *      been completed."
 *
 *   2. **Disabled when SMTP is not configured** — fresh e2e DB
 *      (smtp.host / user / pass all empty per `data.sql`), the
 *      button must render as `disabled`. The corresponding
 *      server-side `smtp_not_configured` short-circuit is pinned by
 *      `SystemTest::testTestEmailRejectsWhenSmtpNotConfigured` —
 *      the spec here pins the client-side visibility contract so an
 *      operator on a freshly-installed panel never even gets to
 *      click a button that would only error out.
 *
 *   3. **Validation toast on bad recipient** — clear the recipient
 *      input, type something the browser's native `<input type=email>`
 *      validity check rejects, click the button, assert the
 *      `reportValidity()` popover fires (proxy: the input is invalid)
 *      and NO `sb.api.call` round-trip happened (proxy: no toast
 *      appears with success/failure copy). This pins the JS-side
 *      gate stays as the first layer of defence so a typo doesn't
 *      consume the 10s rate-limit slot.
 *
 * Selector contract:
 *   - `[data-testid="smtp-test-email"]` — the button (server-rendered
 *     with the disabled state at first paint based on the saved
 *     smtp.host / smtp.user / config.mail.from_email values).
 *   - `[data-testid="smtp-test-recipient"]` — the recipient input,
 *     pre-populated with the logged-in admin's email.
 */

import { expect, test } from '../../fixtures/auth.ts';
import {
    clearTestEmailThrottleE2e,
    seedLostpasswordE2e,
    truncateE2eDb,
} from '../../fixtures/db.ts';

/**
 * Mailpit HTTP API root. Mirrors `lostpassword-toast.spec.ts` so
 * the parallel-stack override (`docker-compose.override.yml`)
 * doesn't have to be threaded through the spec — inside the web
 * container `mailpit:8025` works regardless of the host-published
 * UI port.
 */
const MAILPIT_BASE_URL = process.env.E2E_MAILPIT_BASE_URL
    ?? 'http://mailpit:8025';

test.describe('flow: SMTP test email (#1455)', () => {
    test.beforeEach(async () => {
        // Each test owns the SMTP config state — the happy-path test
        // wants mailpit; the disabled-state test wants the blank
        // `data.sql` shape. Truncating + reseeding between tests
        // gives both a deterministic starting point. (The other
        // E2E specs that share the DB use the same pattern.)
        await truncateE2eDb();
        // The handler's 10s rate-limit cache is install-global (not
        // per-recipient or per-test). Parallel project profiles
        // (chromium + mobile-chromium) running the happy-path test
        // simultaneously trip each other's throttle window unless
        // we clear the cache file between attempts. The clear is
        // idempotent — no-op when the file isn't present, so the
        // disabled-state + validation arms pay nothing for the call.
        await clearTestEmailThrottleE2e();
    });

    test('Happy path: SMTP configured → button is enabled, click sends a real email to the operator', async ({ page, request }) => {
        // ---- 0. Seed mailpit-pointed SMTP creds + a non-empty
        //         from_email so the button renders enabled. The
        //         seeder also clears mailpit's inbox indirectly
        //         (we do it explicitly below for safety).
        const { email } = await seedLostpasswordE2e();

        // Clear mailpit so the assertion is unambiguous.
        const purge = await request.delete(`${MAILPIT_BASE_URL}/api/v1/messages`);
        expect(
            purge.ok(),
            `mailpit purge failed (status ${purge.status()}): ${await purge.text()}`,
        ).toBe(true);

        await page.goto('/index.php?p=admin&c=settings&section=settings');
        await page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]:not([hidden])'),
        );

        const button = page.locator('[data-testid="smtp-test-email"]');
        const recipient = page.locator('[data-testid="smtp-test-recipient"]');
        await expect(button).toBeVisible();
        // The seeded mailpit creds + the seeded `from_email` mean
        // the server-rendered first-paint state is enabled.
        await expect(button).toBeEnabled();
        // The recipient input is pre-populated with the logged-in
        // admin's email — they don't have to type their own
        // address to verify SMTP works.
        await expect(recipient).toHaveValue(email);

        // ---- 1. Drive the send. The button flips through
        //         data-loading=true while the JSON call is in
        //         flight (per the AGENTS.md "Loading state on
        //         action buttons" contract); we just wait for the
        //         success toast and the mailpit-side delivery.
        await button.click();

        const toast = page
            .locator('[data-testid="toast"][data-kind="success"]')
            .filter({ hasText: /test email sent/i });
        await expect(toast).toBeVisible();

        // ---- 2. The email actually landed in mailpit. Same
        //         polling shape as `lostpassword-toast.spec.ts` —
        //         the panel calls Mail::send synchronously before
        //         emitting the toast, so by the time the toast
        //         paints the SMTP transaction has completed; we
        //         poll for ~5s just in case mailpit's
        //         accept-to-list latency surprises.
        let landedTo: string | null = null;
        for (let attempt = 0; attempt < 10; attempt += 1) {
            const resp = await request.get(
                `${MAILPIT_BASE_URL}/api/v1/messages?limit=50`,
            );
            expect(resp.ok(), `mailpit list failed (${resp.status()})`).toBe(true);
            const body = await resp.json() as {
                messages?: Array<{ To?: Array<{ Address?: string }>; Subject?: string }>;
            };
            const found = (body.messages ?? []).find((msg) =>
                (msg.To ?? []).some((addr) => addr.Address === email),
            );
            if (found) {
                landedTo = email;
                // Sanity-check the subject — the handler emits
                // `[SourceBans++] SMTP test email`. If a future
                // PR drifts the subject and the recipient match
                // by accident, this assertion catches it.
                expect(found.Subject ?? '').toMatch(/SMTP test email/i);
                break;
            }
            await page.waitForTimeout(500);
        }
        expect(
            landedTo,
            `mailpit never received a test email to ${email} within 5s — `
            + `either the handler skipped Mail::send, the SMTP transaction `
            + `silently failed, or mailpit isn't reachable as ${MAILPIT_BASE_URL}.`,
        ).toBe(email);
    });

    test('Button is disabled when SMTP is not configured (fresh install state)', async ({ page }) => {
        // data.sql ships smtp.host/user/pass + config.mail.from_email
        // all blank (forcing the operator to configure them before
        // SMTP works in the modern panel). The truncate+reseed in
        // beforeEach restores that shape, so the button MUST render
        // disabled — clicking it would only ever land in the
        // `smtp_not_configured` error envelope.
        await page.goto('/index.php?p=admin&c=settings&section=settings');
        await page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]:not([hidden])'),
        );

        const button = page.locator('[data-testid="smtp-test-email"]');
        await expect(button).toBeVisible();
        await expect(button).toBeDisabled();

        // ---- The page-tail JS re-evaluates the disabled state as
        //      the operator edits the SMTP fields. Type valid
        //      values and assert the button enables — proves the
        //      live-derivation half of the contract works (the
        //      first-paint half is what we just asserted).
        await page.locator('#mail_host').fill('mailpit');
        await page.locator('#mail_user').fill('e2e');
        await page.locator('#mail_from_email').fill('noreply@example.test');
        // The button should now be enabled even though the form
        // isn't saved yet — the JS gate only cares about the
        // current input values. (The server-side handler still
        // reads the saved values from sb_settings, which is why
        // the help copy explicitly tells the operator to save
        // first; the disabled gate is a guard, not a contract.)
        await expect(button).toBeEnabled();
    });

    test('Validation: typo in recipient → native popover, no API round-trip', async ({ page }) => {
        // Need SMTP configured so the button is enabled (otherwise
        // it would never accept the click).
        await seedLostpasswordE2e();

        await page.goto('/index.php?p=admin&c=settings&section=settings');
        await page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]:not([hidden])'),
        );

        const button = page.locator('[data-testid="smtp-test-email"]');
        const recipient = page.locator('[data-testid="smtp-test-recipient"]');
        await expect(button).toBeEnabled();

        // Listen for any sb.api.call round-trip — none should fire
        // for a client-validation failure.
        const apiRequests: string[] = [];
        await page.route('**/api.php', (route, req) => {
            apiRequests.push(req.method() + ' ' + req.url());
            // Forward the request anyway so the page stays
            // functional in case the assertion below fires late.
            void route.continue();
        });

        await recipient.fill('not-an-email');
        await button.click();

        // The validation toast for "Enter a recipient" fires via
        // window.SBPP.showToast; the more reliable signal is that
        // no API call happened (which is the load-bearing contract:
        // a typo should never burn a rate-limit slot).
        await page.waitForTimeout(500); // grace window for a stray call
        expect(
            apiRequests.filter((r) => r.includes('test_email')),
            `Validation failure must not trigger a system.test_email round-trip. Got:\n${apiRequests.join('\n')}`,
        ).toEqual([]);

        // The native validation state should be invalid (browser's
        // FILTER_VALIDATE_EMAIL equivalent on `<input type=email>`).
        const isValid = await recipient.evaluate(
            (el) => (el as HTMLInputElement).checkValidity(),
        );
        expect(isValid, 'recipient input should fail native validity check after typing "not-an-email"').toBe(false);
    });
});
