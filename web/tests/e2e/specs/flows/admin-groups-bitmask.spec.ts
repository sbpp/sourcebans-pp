/**
 * Flow spec — issues #1272 and #1436: web admin groups bitmask
 * round-trips as an unsigned 32-bit integer, and the Select all
 * control manages the whole permission grid.
 *
 * What this locks in
 * ------------------
 * `?p=admin&c=groups&section=list` renders a master-detail flag grid:
 * the right pane is one checkbox per web-permission flag from
 * `web/configs/permissions/web.json`. The grid's `change` listener
 * folds the OR-sum of the checked flags into `[data-testid="flag-bitmask"]`
 * on every change, and the form's submit handler folds the same OR-sum
 * into `web_flags` when calling `sb.api.call(Actions.GroupsEdit, ...)`.
 *
 * Pre-#1272 both compute paths used a bare `bitmask |= Number(value)`
 * loop. JS bitwise operators coerce to signed Int32 (ECMAScript
 * `ToInt32`), so the moment any flag with bit 31 set was OR-folded the
 * preview rendered a NEGATIVE number AND the API received a NEGATIVE
 * number. The schema (`flags INT(10)` SIGNED) was happy to store the
 * negative bit-pattern, so the bug round-tripped — visible in the badge
 * and in any introspection (audit log, third-party query). #1272 fixes
 * both halves: `>>> 0` in the JS to project the OR-sum back to its
 * unsigned-32-bit interpretation, and `INT UNSIGNED` for both
 * `:prefix_groups.flags` and `:prefix_admins.extraflags` so the on-disk
 * representation matches the spec'd flag range.
 *
 * Selectors
 * ---------
 * Per AGENTS.md "Selectors must use #1123's testability hooks":
 *   - `[data-testid="group-detail"]`     — the master-detail form
 *   - `[data-testid="flag-grid"]`        — the checkbox grid
 *   - `[data-testid="flag-<name>"]`      — per-flag checkbox
 *     (`<name>` is the lowercased ADMIN_-stripped key, see
 *     `web/pages/admin.groups.php` `$all_flags[]` build-up). For
 *     `ADMIN_UNBAN_GROUP_BANS` the testid is
 *     `flag-unban_group_bans`.
 *   - `[data-testid="group-flags-select-all"]` — the grid-wide
 *     checked / unchecked / indeterminate control.
 *   - `[data-testid="flag-bitmask"]`     — the live preview span
 *   - `[data-testid="group-save"]`       — the Save button
 *
 * Project gating
 * --------------
 * Mobile viewport doesn't add coverage for the desktop-shaped
 * master-detail grid (the bitmask preview text and the round-trip
 * happen identically; the chrome reflows but the contract is the
 * same), and a flow spec that mutates the shared `sourcebans_e2e` DB
 * races with itself across projects. Pin to chromium.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const GROUPS_LIST_ROUTE = '/index.php?p=admin&c=groups&section=list';

const FIXTURE = {
    groupName: 'e2e-bitmask-group',
    /**
     * `ADMIN_UNBAN_GROUP_BANS` from `web/configs/permissions/web.json`.
     * Bit 31 — the boundary case that flips the sign in JS Int32.
     */
    unbanGroupBansValue: 2147483648,
};

test.describe('flow: admin groups permission flags (#1272, #1436)', () => {
    // truncateE2eDb in beforeEach is global, no worker-scoped locking
    // yet. Keep this file serial now that it carries two mutating tests;
    // see `admin-ban-lifecycle.spec.ts` for the full reasoning.
    test.describe.configure({ mode: 'serial' });
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async ({ page }) => {
        await truncateE2eDb();
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

        expect(seedEnvelope.ok, `groups.add must succeed: ${JSON.stringify(seedEnvelope)}`).toBe(true);
    });

    test('bit-31 flag round-trips as an unsigned integer end-to-end', async ({ page }) => {
        // ---- 1. Navigate to the groups list section -----------------------
        // The newly-seeded group is the only row, so the master-detail
        // editor auto-selects it (admin.groups.php falls back to the
        // first row when `?gid=` is missing). The right-pane form
        // mounts with no flags checked because `bitmask: 0` was the
        // seed.
        await page.goto(GROUPS_LIST_ROUTE);

        const detail = page.locator('[data-testid="group-detail"]');
        await expect(detail).toBeVisible();

        const flagGrid = detail.locator('[data-testid="flag-grid"]');
        const bitmaskBadge = detail.locator('[data-testid="flag-bitmask"]');
        const unbanGroupBansCheckbox = flagGrid.locator('[data-testid="flag-unban_group_bans"]');

        await expect(flagGrid).toBeVisible();
        await expect(bitmaskBadge).toBeVisible();
        await expect(unbanGroupBansCheckbox).toBeVisible();

        // Initial state: badge reads `0 bitmask` (no flags checked).
        await expect(bitmaskBadge).toHaveText(/^0 bitmask$/);
        await expect(unbanGroupBansCheckbox).not.toBeChecked();

        // ---- 2. Toggle the bit-31 flag → live preview is unsigned ---------
        // Pre-#1272 this would have rendered `-2147483648 bitmask`. The
        // contract is "no minus sign + the spec'd unsigned value".
        await unbanGroupBansCheckbox.check();

        await expect(bitmaskBadge).toHaveText(`${FIXTURE.unbanGroupBansValue} bitmask`);
        // Defensive: pre-#1272 the badge would have a leading `-` on
        // bit-31 toggles. Assert it explicitly so a regression that
        // happens to numerically match (e.g. someone mis-types the
        // expected value) still fails on the sign separately.
        await expect(bitmaskBadge).not.toContainText('-');

        // ---- 3. Save → DB has the unsigned value --------------------------
        // The Save button posts `Actions.GroupsEdit` with
        // `web_flags: <SbppFoldFlags result>`. We wait on the
        // network response (the deterministic terminal state for
        // "save succeeded") rather than a UI toast — the toast
        // contract is locked in separately by
        // `admin-groups-delete.spec.ts` (#1310 — replacement for the
        // pre-#1310 dangling `applyApiResponse` reference); this
        // spec stays focused on the wire-level round-trip that
        // #1272 fixes.
        const saveButton = detail.locator('[data-testid="group-save"]');
        await expect(saveButton).toBeVisible();

        const editResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await saveButton.click();
        const editResponse = await editResponsePromise;
        const editEnvelope = await editResponse.json();
        expect(
            editEnvelope.ok,
            `groups.edit must succeed: ${JSON.stringify(editEnvelope)}`,
        ).toBe(true);

        // ---- 4. Reload → checkbox is still checked + badge persists -------
        // The acceptance criterion: "Save round-trip — pick a flag
        // combination that includes bit 31, Save, reload — the same
        // flags re-render checked AND the badge reads the same
        // unsigned number." Pre-#1272 the round-trip would either
        // surface a negative number or unflip the checkbox depending
        // on the exact sequence of (int)/PDO casts hit; the schema
        // widening is what locks the unsigned shape end-to-end.
        await page.goto(GROUPS_LIST_ROUTE);

        const reloadedDetail = page.locator('[data-testid="group-detail"]');
        await expect(reloadedDetail).toBeVisible();

        const reloadedBadge = reloadedDetail.locator('[data-testid="flag-bitmask"]');
        const reloadedCheckbox = reloadedDetail.locator('[data-testid="flag-unban_group_bans"]');

        await expect(reloadedBadge).toHaveText(`${FIXTURE.unbanGroupBansValue} bitmask`);
        await expect(reloadedBadge).not.toContainText('-');
        await expect(reloadedCheckbox).toBeChecked();
    });

    test('select all toggles every flag and stays synchronized', async ({ page }) => {
        await page.goto(GROUPS_LIST_ROUTE);

        const detail = page.locator('[data-testid="group-detail"]');
        const flagGrid = detail.locator('[data-testid="flag-grid"]');
        const selectAll = detail.locator('[data-testid="group-flags-select-all"]');
        const flags = flagGrid.locator('input[name="flags[]"]');
        const checkedFlags = flagGrid.locator('input[name="flags[]"]:checked');
        const bitmaskBadge = detail.locator('[data-testid="flag-bitmask"]');

        await expect(detail).toBeVisible();
        await expect(selectAll).toBeVisible();
        const flagCount = await flags.count();
        expect(flagCount).toBeGreaterThan(1);
        await expect(selectAll).not.toBeChecked();
        expect(await selectAll.evaluate((input) => (input as HTMLInputElement).indeterminate)).toBe(false);

        await selectAll.check();
        await expect(checkedFlags).toHaveCount(flagCount);
        await expect(selectAll).toBeChecked();
        expect(await selectAll.evaluate((input) => (input as HTMLInputElement).indeterminate)).toBe(false);

        const allFlagsMask = await flags.evaluateAll((inputs) => {
            let mask = 0;
            for (const node of inputs) {
                const input = node as HTMLInputElement;
                mask |= Number(input.dataset.flagValue || input.value);
            }
            return mask >>> 0;
        });
        await expect(bitmaskBadge).toHaveText(`${allFlagsMask} bitmask`);

        await flags.first().uncheck();
        await expect(selectAll).not.toBeChecked();
        expect(await selectAll.evaluate((input) => (input as HTMLInputElement).indeterminate)).toBe(true);

        await selectAll.check();
        await expect(checkedFlags).toHaveCount(flagCount);

        const editResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await detail.locator('[data-testid="group-save"]').click();
        const editEnvelope = await (await editResponsePromise).json();
        expect(
            editEnvelope.ok,
            `groups.edit must succeed: ${JSON.stringify(editEnvelope)}`,
        ).toBe(true);

        await page.goto(GROUPS_LIST_ROUTE);
        const reloadedDetail = page.locator('[data-testid="group-detail"]');
        const reloadedGrid = reloadedDetail.locator('[data-testid="flag-grid"]');
        const reloadedSelectAll = reloadedDetail.locator('[data-testid="group-flags-select-all"]');
        await expect(reloadedGrid.locator('input[name="flags[]"]:checked')).toHaveCount(flagCount);
        await expect(reloadedSelectAll).toBeChecked();
        expect(
            await reloadedSelectAll.evaluate((input) => (input as HTMLInputElement).indeterminate),
        ).toBe(false);

        await reloadedSelectAll.uncheck();
        await expect(reloadedGrid.locator('input[name="flags[]"]:checked')).toHaveCount(0);
        await expect(reloadedDetail.locator('[data-testid="flag-bitmask"]')).toHaveText('0 bitmask');
    });
});
