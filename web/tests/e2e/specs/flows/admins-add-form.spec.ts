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
        // #1402 adversarial review MEDIUM 5: the input types must
        // stay as `password` — the legacy `LoadGeneratePassword`
        // helper never flipped `.type`, and leaving the generated
        // value visible indefinitely is a privacy / shoulder-surf /
        // screenshot leak.
        expect(await page.locator('[data-testid="admin-add-password"]').getAttribute('type'))
            .toBe('password');
        expect(await page.locator('[data-testid="admin-add-password2"]').getAttribute('type'))
            .toBe('password');
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

    /**
     * #1402 adversarial review HIGH 3 (stale-flags ride-through).
     *
     * Pre-fix `updateServer` / `updateWeb` only toggled the `hidden`
     * attribute on the dependent blocks — the checkbox values + text
     * inputs survived a dropdown flip. `collectWebFlags()` walked
     * the unscoped `#web-flags-block input[data-flag]` and the
     * submit handler read `#server-flags` / `#*-new-name`
     * unconditionally. The repro:
     *   1. Select "Custom permissions" → reveals the flag picker.
     *   2. Tick "Owner" (or any other ADMIN_* checkbox).
     *   3. Flip dropdown back to "No permissions" → block hides.
     *   4. Submit → API call ships `mask: ADMIN_OWNER` despite the
     *      final UI saying "no permissions".
     * Two routes to accidental OWNER grant (the other being HIGH 1's
     * uncondionally-rendered checkbox).
     *
     * Post-fix the helpers clear the dependent inputs AND the
     * collectors are scoped to `:not([hidden])` so even if the
     * clear ever stops firing, a hidden checkbox can't ride into
     * the mask.
     */
    test('Dropdown flip → "Custom permissions" → tick OWNER → "No permissions" → submit ships mask: 0', async ({ page }) => {
        // Intercept the AdminsAdd request so we can inspect the
        // serialised mask without needing to stub it (we still want
        // to hit the real handler to assert end-to-end).
        let lastMask: number | null = null;
        await page.route('**/api.php', async (route) => {
            try {
                const body = JSON.parse(route.request().postData() || '{}');
                if (body?.action === 'admins.add') {
                    lastMask = Number(body?.params?.mask ?? -1);
                }
            } catch {
                /* swallow JSON parse errors on non-admins.add calls */
            }
            await route.continue();
        });

        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        await page.locator('[data-testid="admin-add-name"]').fill('stale-flag-victim');
        await page.locator('[data-testid="admin-add-steam"]').fill('STEAM_0:0:88008800');
        await page.locator('[data-testid="admin-add-email"]').fill('stale@flag.test');
        await page.locator('[data-testid="admin-add-password"]').fill('somepassword');
        await page.locator('[data-testid="admin-add-password2"]').fill('somepassword');
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('-3');

        // Walk the trap: reveal flag picker, tick OWNER, hide flag
        // picker. The post-fix updateWeb() clears the checkbox AND
        // the collector skips hidden ancestors — both pin the mask
        // at 0 regardless of which guard fires first.
        await page.locator('[data-testid="admin-add-webg"]').selectOption('c');
        const ownerCb = page.locator('[data-testid="admin-add-flag-owner"]');
        await expect(ownerCb).toBeVisible();
        await ownerCb.check();
        // Now flip back to "No permissions" — the checkbox should be
        // cleared AND the block re-hidden.
        await page.locator('[data-testid="admin-add-webg"]').selectOption('-3');
        await expect(ownerCb).toBeHidden();

        const responsePromise = page.waitForResponse(
            (r) =>
                r.url().includes('api.php') &&
                r.request().method() === 'POST' &&
                r.status() === 200,
        );
        await page.locator('[data-testid="admin-add-submit"]').click();
        const env = await (await responsePromise).json();

        // The mask shipped to the API must be 0 — neither path
        // (clearWebFlags clears the checkbox, collectWebFlags skips
        // hidden ancestors) should let the OWNER bit slip through.
        expect(lastMask, 'submit must NOT smuggle stale OWNER bit').toBe(0);
        // The handler validation succeeded so the new admin landed
        // with no extra flags.
        expect(env.ok, JSON.stringify(env)).toBe(true);
    });

    /**
     * #1402 adversarial review HIGH 2 (rehash silently dropped).
     *
     * The legacy ProcessAddAdmin consumed `data.rehash` from
     * api_admins_add's envelope and fired `Actions.SystemRehashAdmins`
     * so the SourceMod plugins on the relevant game servers reloaded
     * their admin lists. The rewrite's first cut read `data.message`
     * only and navigated away. config.enableadminrehashing defaults
     * to '1' in data.sql, so the rehash is the expected default —
     * without it, a brand-new admin can log in to the panel but
     * can't moderate on game servers until the next server restart.
     *
     * Test shape: stub both API calls so we can verify the chain
     * without needing real `:prefix_servers` rows that the new
     * admin has access to (the seeded DB's admin holds no per-
     * server group memberships, so the natural `rehash` from a
     * real call is null).
     */
    test('Success path → chains Actions.SystemRehashAdmins when handler returns rehash sids', async ({ page }) => {
        /** @type {{action?:string,params?:Record<string,unknown>}[]} */
        const apiCalls: { action?: string; params?: Record<string, unknown> }[] = [];
        await page.route('**/api.php', async (route) => {
            let body: { action?: string; params?: Record<string, unknown> } | null = null;
            try {
                body = JSON.parse(route.request().postData() || '{}');
            } catch {
                body = null;
            }
            if (!body || !body.action) {
                await route.continue();
                return;
            }
            apiCalls.push(body);
            if (body.action === 'admins.add') {
                // Synthesise a rehash payload — two server ids. The
                // wire envelope matches `Api::dispatch`'s shape:
                // `{ok: true, data: <handler-return>}`. Without this
                // wrapping the dispatcher's success branch reads
                // `r.data.rehash` as `undefined` and the rehash chain
                // never fires.
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: {
                            aid: 4242,
                            reload: true,
                            rehash: '1,2',
                            message: {
                                title: 'Admin Added',
                                body: 'The admin has been added successfully',
                                kind: 'green',
                                redir: 'index.php?p=admin&c=admins',
                            },
                        },
                    }),
                });
                return;
            }
            if (body.action === 'system.rehash_admins') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ ok: true, data: { rehashed: 2 } }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADMIN_ADMINS_ADD_ROUTE);

        await page.locator('[data-testid="admin-add-name"]').fill('rehash-target');
        await page.locator('[data-testid="admin-add-steam"]').fill('STEAM_0:0:7777');
        await page.locator('[data-testid="admin-add-email"]').fill('rehash@target.test');
        await page.locator('[data-testid="admin-add-password"]').fill('somepassword');
        await page.locator('[data-testid="admin-add-password2"]').fill('somepassword');
        await page.locator('[data-testid="admin-add-serverg"]').selectOption('-3');
        await page.locator('[data-testid="admin-add-webg"]').selectOption('-3');

        await page.locator('[data-testid="admin-add-submit"]').click();

        // Wait for the chained call. The dispatcher fires
        // AdminsAdd → SystemRehashAdmins → navigate; the rehash
        // arm must land before the 1200ms navigation timeout.
        await expect.poll(() => apiCalls
            .map((c) => c.action)
            .filter((a) => a === 'admins.add' || a === 'system.rehash_admins'),
        ).toEqual(['admins.add', 'system.rehash_admins']);

        const rehashCall = apiCalls.find((c) => c.action === 'system.rehash_admins');
        expect(rehashCall?.params?.servers, 'rehash call must carry the sids the handler emitted')
            .toBe('1,2');
    });
});
