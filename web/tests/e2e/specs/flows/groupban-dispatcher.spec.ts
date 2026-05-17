/**
 * Flow spec — issue #1402: the Admin → Bans → Group ban surface now
 * routes the three click affordances (single-URL submit, bulk-from-
 * friends submit, master select-all toggle) through a page-tail
 * event-delegated dispatcher (`data-action="groupban-…"`) that chains
 * `Actions.BansGroupBan` → `Actions.BansBanMemberOfGroup` rather than
 * the dead `LoadGroupBan` / `ProcessGroupBan` / `CheckGroupBan` /
 * `TickSelectAll` globals.
 *
 * What this locks in (pre-fix breakage)
 * -------------------------------------
 * Pre-#1402 the four globals lived in `web/scripts/sourcebans.js`
 * (deleted at #1123 D1). The `<button onclick="ProcessGroupBan();">`
 * single-URL submit reached `ProcessGroupBan`, which then called
 * `LoadGroupBan(...)` — and `LoadGroupBan` was undefined. Same shape
 * for `CheckGroupBan` (bulk) and `TickSelectAll` (select-all toggle).
 * Every click threw `ReferenceError: LoadGroupBan is not defined`
 * (or equivalent), the form did nothing, and no audit-log entry was
 * recorded.
 *
 * Acceptance criteria asserted below:
 *   1. Empty URL submit shows the inline `#groupurl.msg` error (the
 *      client-side gate that mirrors the legacy ProcessGroupBan
 *      check) and does NOT call the API.
 *   2. Valid URL submit calls `Actions.BansGroupBan` with the URL
 *      the operator typed, then chains to
 *      `Actions.BansBanMemberOfGroup` with the resolved group name.
 *   3. NO uncaught console errors — the pre-fix click threw
 *      `ReferenceError: LoadGroupBan is not defined` on the spot.
 *
 * We stub both API endpoints via `page.route` so the test doesn't
 * try to reach Steam's group-membership XML feed. The server-side
 * handlers are already covered by `web/tests/api/BansTest.php`; the
 * point of this spec is to assert the JS dispatcher is wired and
 * the wire format the dispatcher sends matches the handlers'
 * expectations.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - `[data-testid="groupban-url"]`     — Steam group URL input
 *   - `[data-testid="groupban-reason"]`  — reason textarea
 *   - `[data-testid="groupban-submit"]`  — single-URL submit
 *   - `#groupurl.msg`                    — inline error slot
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow is form-shaped and would be
 * pure CI minutes on mobile.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { setSettingE2e } from '../../fixtures/db.ts';

const ADMIN_BANS_GROUPBAN_ROUTE = '/index.php?p=admin&c=bans&section=group-ban';

test.describe('flow: admin bans group-ban dispatcher (#1402 — LoadGroupBan zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    // The group-ban surface ships behind config.enablegroupbanning,
    // which data.sql defaults to '0'. Flip it on for this file's tests
    // so the form renders, then revert in afterAll so sibling specs
    // (e.g. comms-filter-chips) don't pick up the toggle.
    test.beforeAll(async () => {
        await setSettingE2e('config.enablegroupbanning', '1');
    });
    test.afterAll(async () => {
        await setSettingE2e('config.enablegroupbanning', '0');
    });

    test('Empty URL submit → inline error + no API call', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        let apiCalls = 0;
        await page.route('**/api.php', async (route) => {
            let body: { action?: string } | null = null;
            try {
                body = JSON.parse(route.request().postData() || '{}');
            } catch {
                body = null;
            }
            if (body?.action === 'bans.group_ban' || body?.action === 'bans.ban_member_of_group') {
                apiCalls += 1;
            }
            await route.continue();
        });

        await page.goto(ADMIN_BANS_GROUPBAN_ROUTE);

        // Form must render (group banning is enabled by default in
        // the e2e seed). If the section is disabled, the test setup
        // is wrong — fail loudly.
        await expect(page.locator('[data-testid="groupban-form"]')).toBeVisible();

        // Click submit with the URL field empty.
        await page.locator('[data-testid="groupban-submit"]').click();

        const errSlot = page.locator('#groupurl\\.msg');
        await expect(errSlot).toBeVisible();
        await expect(errSlot).toContainText(/group link/i);

        // The inline error renders synchronously inside the click
        // handler — once `errSlot` is visible we know the dispatcher
        // already short-circuited. No need for a settle timer (which
        // is the canonical Playwright anti-pattern flagged by
        // AGENTS.md "Playwright E2E specifics").
        expect(apiCalls, 'empty URL submit must NOT call the API').toBe(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Valid URL submit → chains BansGroupBan → BansBanMemberOfGroup', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        type Captured = { action: string; params: Record<string, unknown> };
        const apiCalls: Captured[] = [];

        await page.route('**/api.php', async (route) => {
            let body: { action?: string; params?: Record<string, unknown> } | null = null;
            try {
                body = JSON.parse(route.request().postData() || '{}');
            } catch {
                body = null;
            }
            if (body?.action === 'bans.group_ban') {
                apiCalls.push({ action: body.action, params: body.params || {} });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: {
                            grpname: 'interwavestudios',
                            queue:   String((body.params || {}).queue ?? 'no'),
                            reason:  String((body.params || {}).reason ?? ''),
                            last:    String((body.params || {}).last ?? ''),
                            message: { title: 'Please Wait...', body: 'Banning…', kind: 'blue' },
                        },
                    }),
                });
                return;
            }
            if (body?.action === 'bans.ban_member_of_group') {
                apiCalls.push({ action: body.action, params: body.params || {} });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: {
                            grpurl: String((body.params || {}).grpurl ?? ''),
                            queue:  String((body.params || {}).queue ?? 'no'),
                            last:   String((body.params || {}).last ?? ''),
                            amount: { total: 5, banned: 5, before: 0, failed: 0 },
                        },
                    }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADMIN_BANS_GROUPBAN_ROUTE);
        await expect(page.locator('[data-testid="groupban-form"]')).toBeVisible();

        await page.locator('[data-testid="groupban-url"]')
            .fill('http://steamcommunity.com/groups/interwavestudios');
        await page.locator('[data-testid="groupban-reason"]')
            .fill('e2e — group-ban dispatcher regression');

        await page.locator('[data-testid="groupban-submit"]').click();

        // Both API calls must have fired in order.
        await expect.poll(() => apiCalls.length).toBeGreaterThanOrEqual(2);
        expect(apiCalls[0]?.action).toBe('bans.group_ban');
        expect(apiCalls[0]?.params.groupuri).toBe('http://steamcommunity.com/groups/interwavestudios');
        expect(apiCalls[0]?.params.isgrpurl).toBe('no');
        expect(apiCalls[0]?.params.queue).toBe('no');
        expect(apiCalls[0]?.params.reason).toBe('e2e — group-ban dispatcher regression');

        expect(apiCalls[1]?.action).toBe('bans.ban_member_of_group');
        // The second call uses the parsed group name from the
        // first response (the API handler's `grpname` echo).
        expect(apiCalls[1]?.params.grpurl).toBe('interwavestudios');

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Select-all toggle flips synthetic chkb_ checkboxes', async ({ page }) => {
        // The select-all toggle lives in the "from player" path
        // (when ?fid=… surfaces the group list), but the dispatcher
        // binds against `[data-action="groupban-select-all"]` at the
        // document level. We can exercise it by injecting a few
        // synthetic `chkb_<n>` checkboxes + a synthetic toggle button
        // — same shape the LoadGetGroups inline renderer produces.

        await page.goto(ADMIN_BANS_GROUPBAN_ROUTE);

        await page.evaluate(() => {
            // Three synthetic checkboxes with the legacy id shape the
            // dispatcher walks (`chkb_0`, `chkb_1`, …).
            for (let i = 0; i < 3; i++) {
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.id = 'chkb_' + i;
                cb.value = 'g' + i;
                document.body.appendChild(cb);
            }
            // The trigger — same shape the template emits in the
            // "from player" mode.
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'tickswitch';
            btn.setAttribute('data-action', 'groupban-select-all');
            btn.setAttribute('data-testid', 'synth-tickswitch');
            btn.textContent = '+';
            document.body.appendChild(btn);
        });

        // Initial state: all three boxes unchecked.
        for (let i = 0; i < 3; i++) {
            expect(await page.locator('#chkb_' + i).isChecked()).toBe(false);
        }

        // Click the toggle → all three become checked.
        await page.locator('[data-testid="synth-tickswitch"]').click();
        for (let i = 0; i < 3; i++) {
            expect(await page.locator('#chkb_' + i).isChecked()).toBe(true);
        }

        // Click again → all three become unchecked (toggle).
        await page.locator('[data-testid="synth-tickswitch"]').click();
        for (let i = 0; i < 3; i++) {
            expect(await page.locator('#chkb_' + i).isChecked()).toBe(false);
        }
    });
});
