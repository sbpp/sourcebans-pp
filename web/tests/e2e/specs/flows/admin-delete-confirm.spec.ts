/**
 * Flow spec — issue #1352: the trash-can button on the Admin → Admins
 * row now opens a confirm + reason modal, then routes to the
 * `Actions.AdminsRemove` JSON action and removes the row in place.
 *
 * What this locks in
 * ------------------
 * Pre-#1352 the trash-can button on each row carried
 * `onclick="if (typeof RemoveAdmin === 'function') RemoveAdmin(...)"` —
 * a defensive guard against a v1.x sourcebans.js helper that was
 * deleted at #1123 D1. The `typeof X === 'function'` test silently
 * resolved to `false` and every click was a no-op (no console error,
 * no toast, no API call) — exactly the symptom the user reported.
 * The fix replaces the inline handler with the canonical
 * `data-action="admins-delete"` shape and ships a confirm + optional-
 * reason `<dialog id="admins-delete-dialog">` mirrored on
 * `#bans-unban-dialog` (#1301).
 *
 * Acceptance criteria asserted below:
 *   1. Clicking the trash button OPENS the dialog (regression: pre-fix
 *      it did nothing).
 *   2. Cancel closes the dialog without firing the API.
 *   3. Submitting with a reason calls `Actions.AdminsRemove` with
 *      `{aid, ureason}`, the row is removed in place, the count
 *      badge decrements, and a success toast surfaces.
 *   4. The audit-log entry includes the reason
 *      (`Admin (X) has been deleted. Reason: …`).
 *   5. NO uncaught console errors throughout the flow.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - `[data-testid="admin-row"][data-id="<aid>"]` — row.
 *   - `[data-testid="admin-action-delete"]`        — trash button.
 *   - `[data-testid="admins-delete-dialog"]`       — dialog.
 *   - `[data-testid="admins-delete-target"]`       — name slot.
 *   - `[data-testid="admins-delete-reason"]`       — textarea.
 *   - `[data-testid="admins-delete-cancel"]`       — Cancel.
 *   - `[data-testid="admins-delete-submit"]`       — Confirm.
 *   - `[data-testid="admin-count"]`                — count badge.
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow mutates `:prefix_admins` and
 * the suite shares a single `sourcebans_e2e` DB across projects
 * (`workers: 1` is the suite-wide mitigation per AGENTS.md). Mobile
 * coverage doesn't add value for an admin-only chrome that's
 * structurally identical at every viewport.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADMIN_ADMINS_ROUTE = '/index.php?p=admin&c=admins&section=admins';
const AUDIT_ROUTE = '/index.php?p=admin&c=audit';

const FIXTURE = {
    name: 'e2e-delete-target',
    steam: 'STEAM_0:0:8675309',
    email: 'e2e@delete.test',
    password: 'longpassword',
    deleteReason: 'e2e: left the team',
};

test.describe('flow: admin delete confirm modal (#1352 — RemoveAdmin zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('Trash button opens dialog → reason → confirm → row removed + audit reason', async ({ page }) => {
        // ---- 0. Capture every uncaught exception for the run -------------
        // Pre-#1352 the click was a silent no-op; if the new wiring
        // throws (missing testid, missing Actions constant, missing
        // SBPP.showToast, …) we want the spec to fail loudly with the
        // actual error rather than silently passing.
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // ---- 1. Seed an admin to delete via Actions.AdminsAdd -----------
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
                            data?: { aid?: number };
                            error?: { code: string; message: string };
                        }>;
                    };
                };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.AdminsAdd, {
                mask: 0,
                srv_mask: '',
                name: params.name,
                steam: params.steam,
                email: params.email,
                password: params.password,
                password2: params.password,
                server_group: 'c',
                web_group: 'c',
                server_password: '-1',
                web_name: '',
                server_name: '0',
                servers: '',
                single_servers: '',
            });
        }, FIXTURE);

        expect(
            seedEnvelope.ok,
            `admins.add must succeed: ${JSON.stringify(seedEnvelope)}`,
        ).toBe(true);
        const targetAid = Number(seedEnvelope.data?.aid);
        expect(Number.isFinite(targetAid) && targetAid > 0).toBe(true);

        // ---- 2. Navigate to the admin admins list -----------------------
        await page.goto(ADMIN_ADMINS_ROUTE);

        const targetRow = page.locator(
            `[data-testid="admin-row"][data-id="${targetAid}"]`,
        );
        await expect(targetRow).toBeVisible();
        await expect(targetRow).toContainText(FIXTURE.name);

        // The count badge text is `(<n>)`. Read the starting value so
        // we can assert it decrements by exactly one after the delete.
        const countBadge = page.locator('[data-testid="admin-count"]').first();
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
            '[data-testid="admin-action-delete"]',
        );
        await expect(deleteButton).toBeVisible();
        await deleteButton.click();

        const dialog = page.locator('[data-testid="admins-delete-dialog"]');
        await expect(dialog).toBeVisible();
        // The dialog must populate the target span with the row's
        // user name so the prompt copy is unambiguous.
        await expect(dialog.locator('[data-testid="admins-delete-target"]'))
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
        await dialog.locator('[data-testid="admins-delete-cancel"]').click();
        // Give any in-flight network call a beat to surface (if it
        // exists, this beat is enough; if not, we proceed cleanly).
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

        const reasonInput = dialog.locator('[data-testid="admins-delete-reason"]');
        await reasonInput.fill(FIXTURE.deleteReason);

        // Wait for the AdminsRemove API response so we can read its
        // envelope and assert the row removal lands on success.
        const deleteResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes('api.php') &&
                response.request().method() === 'POST' &&
                response.status() === 200,
        );
        await dialog.locator('[data-testid="admins-delete-submit"]').click();

        const deleteResponse = await deleteResponsePromise;
        const deleteEnvelope = await deleteResponse.json();
        expect(
            deleteEnvelope.ok,
            `admins.remove must succeed: ${JSON.stringify(deleteEnvelope)}`,
        ).toBe(true);
        expect(deleteEnvelope.data.remove).toBe(`aid_${targetAid}`);

        // ---- 6. Row removed in place ------------------------------------
        await expect(targetRow).toHaveCount(0);
        // Dialog closes after success.
        await expect(dialog).toBeHidden();

        // Count badge decrements by exactly one (defensive — the
        // chrome's `decrementCount` reads the span's text and writes
        // back `(<n-1>)`).
        await expect(countBadge).toHaveText(`(${startingCount - 1})`);

        // Success toast surfaces. Anchor on `data-kind="success"` plus
        // a hasText filter on the title our handler emits ("Admin
        // deleted") to disambiguate from any sibling toasts.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /admin deleted/i });
        await expect(successToast).toBeVisible();

        // ---- 7. Audit log carries the reason ----------------------------
        // Navigate to the audit log and assert the most recent
        // "Admin Deleted" entry's body contains the reason. The audit
        // page's row body is `<div class="audit-row__detail">`.
        await page.goto(AUDIT_ROUTE);
        const auditDetail = page
            .locator('.audit-row__detail')
            .filter({ hasText: `Admin (${FIXTURE.name})` })
            .first();
        await expect(auditDetail).toBeVisible();
        await expect(auditDetail).toContainText(`Reason: ${FIXTURE.deleteReason}`);

        // ---- 8. Final: no uncaught console errors -----------------------
        // Pre-#1352 the click was silent (no error). Post-fix any
        // ReferenceError / TypeError / etc. in the new wiring should
        // fail this assertion BEFORE landing in user hands.
        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Optional reason: empty submit deletes the row + audit body has no Reason suffix', async ({ page }) => {
        // The reason field is OPTIONAL on the delete-admin surface
        // (vs required for bans-unban / comms-unblock). The dialog
        // must accept an empty submit and the audit-log entry must
        // omit the "Reason: …" suffix in that case — keeps the audit
        // body readable on the no-JS / no-dispatcher fallback path.
        await page.goto('/');

        const seedEnvelope = await page.evaluate(async () => {
            const w = window as unknown as {
                sb: { api: { call: (a: string, p: Record<string, unknown>) => Promise<{ ok: boolean; data?: { aid?: number } }> } };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.AdminsAdd, {
                mask: 0, srv_mask: '',
                name: 'e2e-no-reason', steam: 'STEAM_0:0:1112223',
                email: 'noreason@e2e.test',
                password: 'longpassword', password2: 'longpassword',
                server_group: 'c', web_group: 'c',
                server_password: '-1',
                web_name: '', server_name: '0',
                servers: '', single_servers: '',
            });
        });
        expect(seedEnvelope.ok).toBe(true);
        const aid = Number(seedEnvelope.data?.aid);

        await page.goto(ADMIN_ADMINS_ROUTE);
        const row = page.locator(`[data-testid="admin-row"][data-id="${aid}"]`);
        await expect(row).toBeVisible();

        await row.locator('[data-testid="admin-action-delete"]').click();
        const dialog = page.locator('[data-testid="admins-delete-dialog"]');
        await expect(dialog).toBeVisible();

        // Submit with the textarea blank — the optional-reason
        // contract should let this through.
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes('api.php') && r.request().method() === 'POST' && r.status() === 200,
        );
        await dialog.locator('[data-testid="admins-delete-submit"]').click();
        const env = await (await responsePromise).json();
        expect(env.ok, JSON.stringify(env)).toBe(true);

        await expect(row).toHaveCount(0);

        await page.goto(AUDIT_ROUTE);
        const auditDetail = page
            .locator('.audit-row__detail')
            .filter({ hasText: 'Admin (e2e-no-reason)' })
            .first();
        await expect(auditDetail).toBeVisible();
        // Bare audit body — no `Reason: …` suffix when the operator
        // didn't supply one.
        await expect(auditDetail).toHaveText('Admin (e2e-no-reason) has been deleted.');
    });
});
