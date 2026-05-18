/**
 * Flow spec — issue #1310: Delete-group on the admin groups list
 * surfaces a success toast and navigates back to the list, with NO
 * `ReferenceError: applyApiResponse is not defined` in the console.
 *
 * What this locks in
 * ------------------
 * Pre-#1310, all three inline `then(function (r) { applyApiResponse(r); })`
 * callbacks in `web/themes/default/page_admin_groups_list.tpl` referenced
 * a global helper that was deleted wholesale at #1123 D1 along with
 * `web/scripts/sourcebans.js`. The actual server-side `groups.remove`
 * fired and completed, but the `.then(...)` callback threw an unhandled
 * `ReferenceError` before `window.SBPP.showToast` ever ran — so from the
 * operator's POV the page just hung with a red console error and no
 * confirmation that the group was deleted (see issue #1310 repro). #1310
 * replaces the three callbacks with the canonical inline response handler
 * already used by the sibling `page_admin_groups_add.tpl` (success/error
 * toast via `window.SBPP.showToast`, fall back to `sb.message.*` for
 * legacy themes, honour `data.reload` and — for handlers like
 * `groups.remove` whose envelope only carries `message.redir` — navigate
 * there after the toast so the master-detail editor stops pointing at
 * the row that just got deleted).
 *
 * The two acceptance criteria asserted below:
 *   1. Clicking Delete must NOT emit a `pageerror` (no `ReferenceError`).
 *   2. A `success` toast titled "Group Deleted" must surface, and the
 *      group row must be gone from the list after the post-delete redirect.
 *
 * Selectors
 * ---------
 * Per AGENTS.md "Selectors must use #1123's testability hooks":
 *   - `[data-testid="web-groups-section"]`  — page mount signal.
 *   - `[data-testid="group-list"]`          — left-rail row container.
 *   - `[data-testid="group-row"]`           — per-group anchor row.
 *   - `[data-testid="group-detail"]`        — right-pane editor form.
 *   - `[data-testid="group-delete"]`        — the Delete button.
 *   - `.toast[data-kind="success"]`         — toast wrapper (theme.js).
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The reference-error contract holds at
 * every viewport, but a flow spec mutating the shared `sourcebans_e2e`
 * DB races with itself across projects (`workers: 1` is the suite-wide
 * mitigation per AGENTS.md). The mobile chrome is structurally the same
 * `<button onclick="SbppGroupsDelete(...)">` shape; doubling it would
 * just retread the wire contract.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const GROUPS_LIST_ROUTE = '/index.php?p=admin&c=groups&section=list';

const FIXTURE = {
    groupName: 'e2e-delete-target',
};

test.describe('flow: admin groups delete (#1310 — applyApiResponse zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('Delete group surfaces a success toast and emits no ReferenceError', async ({ page }) => {
        // ---- 0. Capture every uncaught exception for the run -------------
        // The pre-#1310 bug throws at the `.then()` callback as an
        // uncaught Promise rejection that bubbles to `pageerror`.
        // We assert the array is empty at the end of the test.
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // ---- 1. Seed a web admin group via the JSON API ------------------
        // Use `Actions.GroupsAdd` so the seed path mirrors the live
        // dispatcher (CSRF + permissions + handler stack). type='1' is a
        // web admin group; bitmask=0 so we land at a known empty state.
        await page.goto('/');

        const seedEnvelope = await page.evaluate(async (groupName) => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            params: Record<string, unknown>,
                        ) => Promise<{
                            ok: boolean;
                            error?: { code: string; message: string };
                        }>;
                    };
                };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.GroupsAdd, {
                name: groupName,
                type: '1',
                bitmask: 0,
                srvflags: '',
            });
        }, FIXTURE.groupName);

        expect(
            seedEnvelope.ok,
            `groups.add must succeed: ${JSON.stringify(seedEnvelope)}`,
        ).toBe(true);

        // ---- 2. Navigate to the groups list -------------------------------
        // Master-detail auto-selects the only row (admin.groups.php
        // falls back to `web_group_list[0]` when ?gid= is missing or
        // unknown). The right-pane editor mounts with the Delete button
        // because the seeded admin/admin user has DeleteGroups.
        await page.goto(GROUPS_LIST_ROUTE);

        const detail = page.locator('[data-testid="group-detail"]');
        await expect(detail).toBeVisible();

        const seededRow = page
            .locator('[data-testid="group-row"]')
            .filter({ hasText: FIXTURE.groupName });
        await expect(seededRow).toHaveCount(1);

        // ---- 3. Click Delete + accept the native confirm() ----------------
        // The inline JS calls `window.confirm()` before issuing the
        // destructive call. Playwright dismisses dialogs by default;
        // wire an accept handler so the click resolves the API path.
        page.once('dialog', (d) => {
            void d.accept();
        });

        const deleteResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );

        await detail.locator('[data-testid="group-delete"]').click();

        const deleteResponse = await deleteResponsePromise;
        const deleteEnvelope = await deleteResponse.json();
        expect(
            deleteEnvelope.ok,
            `groups.remove must succeed: ${JSON.stringify(deleteEnvelope)}`,
        ).toBe(true);

        // ---- 4. Success toast appears ------------------------------------
        // The post-#1310 handler routes `r.data.message` through
        // `window.SBPP.showToast`. Anchor on `data-kind="success"`
        // (set by theme.js's `showToast`) plus a hasText filter on
        // the title the API returns ("Group Deleted").
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /group deleted/i });
        await expect(successToast).toBeVisible();

        // ---- 5. After the post-delete redirect, the group is gone --------
        // The handler's envelope has `message.redir =
        // 'index.php?p=admin&c=groups'` (no &gid=). The post-#1310
        // helper `setTimeout`s a navigation there 1500ms after the
        // toast; we wait on the URL change rather than a wall-clock
        // timer so a slower run doesn't flake.
        await page.waitForURL(/\?p=admin&c=groups(?!.*&gid=)/);

        const remainingRow = page
            .locator('[data-testid="group-row"]')
            .filter({ hasText: FIXTURE.groupName });
        await expect(remainingRow).toHaveCount(0);

        // ---- 6. Final regression assertion: no console errors ------------
        // Pre-#1310 this would contain
        // `ReferenceError: applyApiResponse is not defined` from the
        // unhandled Promise rejection at the `.then(...)` callback.
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
