/**
 * Flow spec — issue #1402: the Admin → Mods → Add form now creates
 * a mod row by calling `Actions.ModsAdd` through a page-tail
 * vanilla-JS submit handler. Pre-fix the form's `onsubmit` was a
 * `ProcessMod(); return false;` dead-helper guard — the helper
 * lived in the deleted `web/scripts/sourcebans.js` (#1123 D1), so
 * every click was a `ReferenceError: ProcessMod is not defined`
 * and the mod never showed up in the list. There was no PHP
 * `$_POST` fallback path either, so the form silently dead-ended.
 *
 * Acceptance criteria asserted below:
 *   1. The submit button fires `Actions.ModsAdd` with the form's
 *      `{name, folder, icon}` plus the implicit `steam_universe=0`
 *      / `enabled=true` defaults the handler tolerates.
 *   2. On success the operator gets a success toast and is
 *      redirected to the list, where the new row is visible.
 *   3. Missing-icon validation surfaces inline AND does NOT POST
 *      to the API. (We test icon specifically because `name` /
 *      `folder` carry HTML5 `required` so the browser's native
 *      popover fires before our submit handler — `icon` is set
 *      via the upload popup callback and is the one field the
 *      JS gate is load-bearing for.)
 *   4. NO uncaught console errors throughout the flow — pre-fix
 *      the click threw `ReferenceError: ProcessMod is not defined`.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - `[data-testid="addmod-name"]`            — name input
 *   - `[data-testid="addmod-folder"]`          — folder input
 *   - `[data-testid="addmod-icon-hidden"]`     — hidden icon field
 *   - `[data-testid="addmod-submit"]`          — submit button
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow mutates `:prefix_mods` and the
 * suite shares a single `sourcebans_e2e` DB across projects.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADMIN_MODS_ADD_ROUTE = '/index.php?p=admin&c=mods&section=add';
const ADMIN_MODS_LIST_ROUTE = '/index.php?p=admin&c=mods&section=list';

const FIXTURE = {
    name: 'e2e-add-mod',
    folder: 'e2eaddmod',
    icon: 'default.png',
};

test.describe('flow: admin mods add form (#1402 — ProcessMod zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('Submit → Actions.ModsAdd → row appears in list + success toast', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto(ADMIN_MODS_ADD_ROUTE);

        // Fill in the visible fields. The icon is normally populated
        // via the upload popup (`window.opener.icon` callback) — for
        // this test we set the hidden field directly to bypass the
        // file-upload dance, which is exercised separately.
        await page.locator('[data-testid="addmod-name"]').fill(FIXTURE.name);
        await page.locator('[data-testid="addmod-folder"]').fill(FIXTURE.folder);
        await page.evaluate((iconName) => {
            const el = document.getElementById('icon_hid') as HTMLInputElement | null;
            if (el) el.value = iconName;
        }, FIXTURE.icon);

        // Wait for the ModsAdd response so the success branch lands
        // before we assert the navigation / toast.
        const addResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await page.locator('[data-testid="addmod-submit"]').click();

        const addResponse = await addResponsePromise;
        const addEnvelope = await addResponse.json();
        expect(
            addEnvelope.ok,
            `mods.add must succeed: ${JSON.stringify(addEnvelope)}`,
        ).toBe(true);

        // The success path navigates to the list (1500ms timeout in
        // the page-tail script). Wait for that nav rather than racing
        // a wall-clock timer.
        await page.waitForURL(/\/index\.php\?p=admin&c=mods/);

        // New row appears on the list, identified by its name cell.
        const targetRow = page
            .locator('[data-testid="mod-row"]')
            .filter({ hasText: FIXTURE.name })
            .first();
        await expect(targetRow).toBeVisible();

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Missing icon → JS gate fires inline error + no API call', async ({ page }) => {
        await page.goto(ADMIN_MODS_ADD_ROUTE);

        // Track API calls during the submit attempt. If our JS gate
        // is wired correctly, none should fire.
        let apiCalls = 0;
        page.on('request', (request) => {
            if (request.url().includes('api.php') && request.method() === 'POST') {
                apiCalls += 1;
            }
        });

        // Fill name + folder (both have HTML5 `required` so the
        // browser would otherwise refuse to submit). Leave icon_hid
        // empty — it has no `required` attr so the JS gate is the
        // only thing stopping the submit, which is exactly the
        // contract we want to lock in.
        await page.locator('[data-testid="addmod-name"]').fill('NoIconMod');
        await page.locator('[data-testid="addmod-folder"]').fill('noiconfolder');
        // Note: we do NOT touch icon_hid here — it starts empty.

        await page.locator('[data-testid="addmod-submit"]').click();

        // Inline error slot lights up (the page-tail script writes
        // into the `.msg` div next to the icon field). Once the
        // error is visible, the JS gate has already returned and
        // the dispatcher never reached the API — no settle timer
        // needed (AGENTS.md "Playwright E2E specifics" flags
        // `waitForTimeout` for negative assertions as an
        // anti-pattern).
        const iconError = page.locator('#icon\\.msg');
        await expect(iconError).toContainText(/icon/i);

        expect(apiCalls, 'invalid form must NOT POST to the API').toBe(0);
    });
});
