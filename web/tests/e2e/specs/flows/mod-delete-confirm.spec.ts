/**
 * Flow spec — issue #1397: the trash-can button on the Admin → Mods
 * row now opens a confirm + optional-reason modal, then routes to the
 * `Actions.ModsRemove` JSON action and removes the row in place.
 *
 * What this locks in
 * ------------------
 * Pre-#1397 the trash-can button on each row carried
 * `onclick="RemoveMod(this.dataset.modName, this.dataset.modId);"` — an
 * UNGUARDED reference to a v1.x sourcebans.js helper deleted at #1123
 * D1. Unlike the admins-delete sister (#1352) the mods button lacked
 * even the defensive `typeof X === 'function'` guard, so every click
 * threw a loud `ReferenceError: RemoveMod is not defined` and the
 * delete never fired — exactly the symptom in the bug report.
 *
 * The fix mirrors the canonical admins-delete shape (#1352): replace
 * the inline handler with the canonical `data-action="mod-delete"`
 * shape and ship a confirm + optional-reason
 * `<dialog id="mod-delete-dialog">`.
 *
 * Acceptance criteria asserted below:
 *   1. Clicking the trash button OPENS the dialog (regression: pre-fix
 *      it threw ReferenceError).
 *   2. Cancel closes the dialog without firing the API.
 *   3. Submitting with a reason calls `Actions.ModsRemove` with
 *      `{mid, ureason}`, the row is removed in place, the count
 *      badge decrements, and a success toast surfaces.
 *   4. The audit-log entry includes the reason
 *      (`MOD (X) has been deleted. Reason: …`).
 *   5. NO uncaught console errors throughout the flow.
 *   6. Optional-reason variant: empty submit deletes the row and the
 *      audit body has no `Reason: …` suffix.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - `[data-testid="mod-row"][data-id="<mid>"]` — row.
 *   - `[data-testid="deletemod-btn"]`            — trash button.
 *   - `[data-testid="mod-delete-dialog"]`        — dialog.
 *   - `[data-testid="mod-delete-target"]`        — name slot.
 *   - `[data-testid="mod-delete-reason"]`        — textarea.
 *   - `[data-testid="mod-delete-cancel"]`        — Cancel.
 *   - `[data-testid="mod-delete-submit"]`        — Confirm.
 *   - `[data-testid="mod-count"]`                — count badge.
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow mutates `:prefix_mods` and the
 * suite shares a single `sourcebans_e2e` DB across projects
 * (`workers: 1` is the suite-wide mitigation per AGENTS.md). Mobile
 * coverage doesn't add value for an admin-only chrome that's
 * structurally identical at every viewport.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADMIN_MODS_ROUTE = '/index.php?p=admin&c=mods';
const AUDIT_ROUTE = '/index.php?p=admin&c=audit';

const FIXTURE = {
    name: 'e2e-delete-mod',
    folder: 'e2edeletemod',
    icon: 'default.png',
    deleteReason: 'e2e: retired this game',
};

test.describe('flow: admin mod delete confirm modal (#1397 — RemoveMod zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('Trash button opens dialog → reason → confirm → row removed + audit reason', async ({ page }) => {
        // ---- 0. Capture every uncaught exception for the run -------------
        // Pre-#1397 the click threw `ReferenceError: RemoveMod is not
        // defined`; this listener is what proves the new wiring
        // doesn't reintroduce any kind of unhandled error.
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // ---- 1. Seed a mod to delete via Actions.ModsAdd ----------------
        // Going through the JSON dispatcher mirrors the live add path
        // (CSRF + permissions + handler stack) and avoids racing with
        // `truncateE2eDb` over the auto-increment counter.
        await page.goto('/');
        const seedEnvelope = await page.evaluate(async (params) => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            payload: Record<string, unknown>,
                        ) => Promise<{
                            ok: boolean;
                            data?: { reload?: boolean };
                            error?: { code: string; message: string };
                        }>;
                    };
                };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.ModsAdd, {
                name: params.name,
                folder: params.folder,
                icon: params.icon,
                steam_universe: 0,
                enabled: true,
            });
        }, FIXTURE);

        expect(
            seedEnvelope.ok,
            `mods.add must succeed: ${JSON.stringify(seedEnvelope)}`,
        ).toBe(true);

        // ---- 2. Navigate to the admin mods list -------------------------
        await page.goto(ADMIN_MODS_ROUTE);

        // The `mods.add` envelope doesn't surface the new mid, so we
        // resolve it from the rendered DOM by matching the row whose
        // name cell contains our fixture name.
        const targetRow = page
            .locator('[data-testid="mod-row"]')
            .filter({ hasText: FIXTURE.name })
            .first();
        await expect(targetRow).toBeVisible();
        const targetMidAttr = await targetRow.getAttribute('data-id');
        const targetMid = Number(targetMidAttr);
        expect(Number.isFinite(targetMid) && targetMid > 0).toBe(true);

        // The count badge text is a bare digit. Read the starting value
        // so we can assert it decrements by exactly one after the delete.
        const countBadge = page.locator('[data-testid="mod-count"]').first();
        await expect(countBadge).toBeVisible();
        const startingCountText = (await countBadge.textContent()) ?? '';
        const startingCount = Number(startingCountText.replace(/[^0-9]/g, ''));
        expect(Number.isFinite(startingCount) && startingCount > 0).toBe(true);

        // ---- 3. Click the trash button — dialog must open ----------------
        // The delete button is inside `.row-actions` on the row's
        // last column. It must be visible at desktop width (no
        // hover-only affordance per AGENTS.md "Hover-only row-action
        // affordances" anti-pattern).
        const deleteButton = targetRow.locator(
            '[data-testid="deletemod-btn"]',
        );
        await expect(deleteButton).toBeVisible();
        await deleteButton.click();

        const dialog = page.locator('[data-testid="mod-delete-dialog"]');
        await expect(dialog).toBeVisible();
        // The dialog must populate the target span with the row's
        // mod name so the prompt copy is unambiguous.
        await expect(dialog.locator('[data-testid="mod-delete-target"]'))
            .toHaveText(FIXTURE.name);

        // ---- 4. Cancel closes without firing the API --------------------
        // We listen for any POST to /api.php during the cancel path;
        // there should be NONE (cancel is a pure DOM affordance).
        let apiCallsDuringCancel = 0;
        const onRequest = (request: import('@playwright/test').Request) => {
            if (request.url().includes('api.php') && request.method() === 'POST') {
                apiCallsDuringCancel += 1;
            }
        };
        page.on('request', onRequest);
        await dialog.locator('[data-testid="mod-delete-cancel"]').click();
        // Anchored on the dialog's hidden state, NOT a wall-clock
        // timer, so a slow run doesn't flake.
        await expect(dialog).toBeHidden();
        page.off('request', onRequest);
        expect(
            apiCallsDuringCancel,
            'Cancel must NOT fire any API call.',
        ).toBe(0);

        // The row is still there — cancel didn't delete anything.
        await expect(targetRow).toBeVisible();

        // ---- 5. Re-open + supply reason + confirm -----------------------
        await deleteButton.click();
        await expect(dialog).toBeVisible();

        const reasonInput = dialog.locator('[data-testid="mod-delete-reason"]');
        await reasonInput.fill(FIXTURE.deleteReason);

        // Wait for the ModsRemove API response so we can read its
        // envelope and assert the row removal lands on success.
        const deleteResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await dialog.locator('[data-testid="mod-delete-submit"]').click();

        const deleteResponse = await deleteResponsePromise;
        const deleteEnvelope = await deleteResponse.json();
        expect(
            deleteEnvelope.ok,
            `mods.remove must succeed: ${JSON.stringify(deleteEnvelope)}`,
        ).toBe(true);
        expect(deleteEnvelope.data.remove).toBe(`mid_${targetMid}`);

        // ---- 6. Row removed in place ------------------------------------
        await expect(targetRow).toHaveCount(0);
        // Dialog closes after success.
        await expect(dialog).toBeHidden();

        // Count badge decrements by exactly one (defensive — the
        // chrome's `decrementCount` reads the span's text and writes
        // back `n - 1`).
        await expect(countBadge).toHaveText(String(startingCount - 1));

        // Success toast surfaces. Anchor on `data-kind="success"` plus
        // a hasText filter on the title our handler emits ("Mod
        // deleted") to disambiguate from any sibling toasts.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /mod deleted/i });
        await expect(successToast).toBeVisible();

        // ---- 7. Audit log carries the reason ----------------------------
        // Navigate to the audit log and assert the most recent
        // "MOD Deleted" entry's body contains the reason. The audit
        // page's row body is `<div class="audit-row__detail">`.
        await page.goto(AUDIT_ROUTE);
        const auditDetail = page
            .locator('.audit-row__detail')
            .filter({ hasText: `MOD (${FIXTURE.name})` })
            .first();
        await expect(auditDetail).toBeVisible();
        await expect(auditDetail).toContainText(`Reason: ${FIXTURE.deleteReason}`);

        // ---- 8. Final: no uncaught console errors -----------------------
        // Pre-#1397 the click threw `ReferenceError: RemoveMod is not
        // defined`; this assertion catches any future ReferenceError /
        // TypeError / etc. in the new wiring before it reaches users.
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Optional reason: empty submit deletes the row + audit body has no Reason suffix', async ({ page }) => {
        // The reason field is OPTIONAL on the delete-mod surface
        // (vs required for bans-unban / comms-unblock). The dialog
        // must accept an empty submit and the audit-log entry must
        // omit the "Reason: …" suffix in that case — keeps the audit
        // body readable on the no-JS / no-dispatcher fallback path.
        await page.goto('/');

        const seedEnvelope = await page.evaluate(async () => {
            const w = window as unknown as {
                sb: { api: { call: (a: string, p: Record<string, unknown>) => Promise<{ ok: boolean }> } };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.ModsAdd, {
                name: 'e2e-no-reason-mod',
                folder: 'e2enoreasonmod',
                icon: 'default.png',
                steam_universe: 0,
                enabled: true,
            });
        });
        expect(seedEnvelope.ok).toBe(true);

        await page.goto(ADMIN_MODS_ROUTE);
        const row = page
            .locator('[data-testid="mod-row"]')
            .filter({ hasText: 'e2e-no-reason-mod' })
            .first();
        await expect(row).toBeVisible();

        await row.locator('[data-testid="deletemod-btn"]').click();
        const dialog = page.locator('[data-testid="mod-delete-dialog"]');
        await expect(dialog).toBeVisible();

        // Submit with the textarea blank — the optional-reason
        // contract should let this through.
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="mod-delete-submit"]').click();
        const env = await (await responsePromise).json();
        expect(env.ok, JSON.stringify(env)).toBe(true);

        await expect(row).toHaveCount(0);

        await page.goto(AUDIT_ROUTE);
        const auditDetail = page
            .locator('.audit-row__detail')
            .filter({ hasText: 'MOD (e2e-no-reason-mod)' })
            .first();
        await expect(auditDetail).toBeVisible();
        // Bare audit body — no `Reason: …` suffix when the operator
        // didn't supply one.
        await expect(auditDetail).toHaveText('MOD (e2e-no-reason-mod) has been deleted.');
    });
});
