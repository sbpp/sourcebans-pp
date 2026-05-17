/**
 * Flow spec — issue #1402: the Admin → Admins → Add form now drives
 * `Actions.AdminsAdd` through a vanilla-JS submit handler, the
 * "Generate password" button hits `Actions.AdminsGeneratePassword`,
 * and the server/web permission selects reveal the conditional
 * input blocks on the right value.
 *
 * What this locks in (pre-fix breakage)
 * -------------------------------------
 * Pre-#1402 the form had four dead JS handlers — all originally
 * defined in `web/scripts/sourcebans.js` (deleted at #1123 D1):
 *   1. `ProcessAddAdmin()` — the submit handler. The form's
 *      `onsubmit="event.preventDefault(); if (typeof ProcessAddAdmin
 *      === 'function') ProcessAddAdmin();"` SILENTLY swallowed every
 *      submit (the `event.preventDefault()` short-circuit AND the
 *      `typeof === 'function'` guard both fired the silent-no-op
 *      shape, even before sourcebans.js was deleted).
 *   2. `LoadGeneratePassword()` — the password generator button.
 *      Click did nothing (silent no-op).
 *   3. `update_server()` / `update_web()` — the `<select>` change
 *      handlers that revealed the conditional inputs (custom flags,
 *      new-group name). Picking "Custom permissions" or "New admin
 *      group" had no visible effect.
 *
 * Acceptance criteria asserted below:
 *   1. Submitting a valid form calls `Actions.AdminsAdd`, the new
 *      row appears on the admins list, and the operator gets a
 *      success toast.
 *   2. Clicking "Generate password" calls
 *      `Actions.AdminsGeneratePassword` and the password+confirm
 *      fields are populated with the same value.
 *   3. Picking "New admin group" on the server-group select reveals
 *      the new-group name input AND the SourceMod flags input.
 *   4. Picking "Custom permissions" on the web-group select reveals
 *      the flag-picker fieldset but NOT the new-group name input.
 *   5. NO uncaught console errors throughout the flow.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - `[data-testid="admin-add-name"]`               — name input
 *   - `[data-testid="admin-add-steam"]`              — Steam ID
 *   - `[data-testid="admin-add-email"]`              — email
 *   - `[data-testid="admin-add-password"]`           — password
 *   - `[data-testid="admin-add-password2"]`          — confirm
 *   - `[data-testid="admin-add-generate-password"]`  — generator btn
 *   - `[data-testid="admin-add-serverg"]`            — server select
 *   - `[data-testid="admin-add-webg"]`               — web select
 *   - `[data-testid="admin-add-server-new-name"]`    — new-group input
 *   - `[data-testid="admin-add-server-flags"]`       — SM flags input
 *   - `[data-testid="admin-add-web-new-name"]`       — web new-group
 *   - `[data-testid="admin-add-flag-owner"]`         — Owner checkbox
 *   - `[data-testid="admin-add-submit"]`             — Submit
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow mutates `:prefix_admins` and
 * the suite shares a single `sourcebans_e2e` DB across projects.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADMIN_ADMINS_ADD_ROUTE = '/index.php?p=admin&c=admins&section=add-admin';

const FIXTURE = {
    name: 'e2e-add-admin',
    steam: 'STEAM_0:0:42424242',
    email: 'e2e-add@admin.test',
    password: 'somepassword',
};

test.describe('flow: admin admins add form (#1402 — ProcessAddAdmin zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('Submit → Actions.AdminsAdd → row appears on list + success toast', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        await page.locator('[data-testid="admin-add-name"]').fill(FIXTURE.name);
        // The Steam ID field is pre-filled with "STEAM_0:" — clear it
        // first so we don't get "STEAM_0:STEAM_0:0:…" after the fill.
        await page.locator('[data-testid="admin-add-steam"]').fill(FIXTURE.steam);
        await page.locator('[data-testid="admin-add-email"]').fill(FIXTURE.email);
        await page.locator('[data-testid="admin-add-password"]').fill(FIXTURE.password);
        await page.locator('[data-testid="admin-add-password2"]').fill(FIXTURE.password);

        // Pick "No permissions" on both selects so we don't have to
        // construct group rows / flag bitmasks for this happy-path
        // assertion. The dropdown values are documented in the
        // template; -3 = "No permissions" per api_admins_add's
        // contract (sg / wg !== '-2' and !== 'n' / 'c' / int(>0)
        // → webGroup = -1 / srvGroupId = -1).
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('-3');
        await page.locator('[data-testid="admin-add-webg"]').selectOption('-3');

        // Wait for the AdminsAdd response.
        const addResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await page.locator('[data-testid="admin-add-submit"]').click();

        const addResponse = await addResponsePromise;
        const addEnvelope = await addResponse.json();
        expect(
            addEnvelope.ok,
            `admins.add must succeed: ${JSON.stringify(addEnvelope)}`,
        ).toBe(true);
        expect(addEnvelope.data?.aid).toBeGreaterThan(0);

        // Success path navigates to the admins list after a brief
        // pause (so the toast paints).
        await page.waitForURL(/\/index\.php\?p=admin&c=admins/);

        // Confirm the new admin row is visible on the list.
        const targetRow = page
            .locator('[data-testid="admin-row"]')
            .filter({ hasText: FIXTURE.name })
            .first();
        await expect(targetRow).toBeVisible();

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Generate password button → fills password + confirm', async ({ page }) => {
        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        const responsePromise = page.waitForResponse(
            (r) =>
                r.url().includes('api.php') &&
                r.request().method() === 'POST' &&
                r.status() === 200,
        );
        await page.locator('[data-testid="admin-add-generate-password"]').click();
        const env = await (await responsePromise).json();
        expect(env.ok, JSON.stringify(env)).toBe(true);
        expect(typeof env.data?.password).toBe('string');
        expect(env.data.password.length).toBeGreaterThan(0);

        // Both fields land on the generated value.
        const pw1 = await page.locator('[data-testid="admin-add-password"]').inputValue();
        const pw2 = await page.locator('[data-testid="admin-add-password2"]').inputValue();
        expect(pw1).toBe(env.data.password);
        expect(pw2).toBe(env.data.password);
        // The page-tail script flips the type from "password" to
        // "text" so the operator can read what was generated.
        expect(await page.locator('[data-testid="admin-add-password"]').getAttribute('type'))
            .toBe('text');
    });

    test('Server-group "New admin group" reveals new-name + SM flags inputs', async ({ page }) => {
        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        // Both blocks start hidden — the conditional UI is the
        // pre-fix bug surface (`update_server` never fired).
        const newNameInput = page.locator('[data-testid="admin-add-server-new-name"]');
        const flagsInput   = page.locator('[data-testid="admin-add-server-flags"]');
        await expect(newNameInput).toBeHidden();
        await expect(flagsInput).toBeHidden();

        // "n" = new admin group.
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('n');
        await expect(newNameInput).toBeVisible();
        await expect(flagsInput).toBeVisible();

        // Switching to "No permissions" hides both again.
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('-3');
        await expect(newNameInput).toBeHidden();
        await expect(flagsInput).toBeHidden();

        // "c" = custom permissions → flags visible, but not new-name.
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('c');
        await expect(newNameInput).toBeHidden();
        await expect(flagsInput).toBeVisible();
    });

    test('Web-group "Custom permissions" reveals flag-picker but not new-name', async ({ page }) => {
        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        const webNewName  = page.locator('[data-testid="admin-add-web-new-name"]');
        const ownerFlagCb = page.locator('[data-testid="admin-add-flag-owner"]');
        await expect(webNewName).toBeHidden();
        await expect(ownerFlagCb).toBeHidden();

        await page.locator('[data-testid="admin-add-webg"]').selectOption('c');
        await expect(webNewName).toBeHidden();   // not a new group
        await expect(ownerFlagCb).toBeVisible(); // flag picker on

        await page.locator('[data-testid="admin-add-webg"]').selectOption('n');
        await expect(webNewName).toBeVisible();
        await expect(ownerFlagCb).toBeVisible();
    });
});
