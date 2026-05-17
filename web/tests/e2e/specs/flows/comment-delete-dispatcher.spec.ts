/**
 * Flow spec — issue #1402: the `comment-actions.js` dispatcher
 * (loaded from core/footer.tpl globally) wires a single
 * document-level click handler for `[data-action="comment-delete"]`
 * triggers across the panel (banlist comment editor, commslist
 * comment editor, admin moderation queues).
 *
 * What this locks in (pre-fix breakage)
 * -------------------------------------
 * Pre-#1402 the trash icon on each comment row carried the
 * onclick="RemoveComment(…)" inline-JS shape. `RemoveComment` was
 * defined in `web/scripts/sourcebans.js`, which was DELETED at
 * #1123 D1 — so clicks threw `ReferenceError: RemoveComment is not
 * defined` and the comment stayed put. The fix:
 *
 *   1. PHP page handlers (`page.banlist.php`, `page.commslist.php`,
 *      `admin.bans.php`) now render the trigger as
 *      `data-action="comment-delete" data-cid data-ctype data-page`
 *      rather than `onclick="RemoveComment(...)"`.
 *   2. `web/scripts/comment-actions.js` (new file) carries the
 *      single document-level event delegate that consumes those
 *      data attributes, prompts via `window.confirm`, and dispatches
 *      to `Actions.BansRemoveComment` via `sb.api.call`.
 *   3. The script is included globally in `core/footer.tpl` so the
 *      contract is symmetric across every page that renders a
 *      `delcomlink`.
 *
 * This spec is shaped as a synthetic-DOM regression: it loads a
 * panel page (Admin → Bans → Protests, which always renders
 * cleanly), injects a synthetic trigger into the page DOM (so the
 * test isn't coupled to a specific protests/comments fixture being
 * present in the seeded e2e DB), mocks the API response via
 * `page.route`, and asserts the dispatcher:
 *
 *   - fires the click handler on a `[data-action="comment-delete"]`
 *     trigger (proving the dispatcher loaded + is listening),
 *   - shows a `window.confirm` prompt before calling the API
 *     (proving the destructive-action gate),
 *   - sends the API call with cid + ctype + page extracted from
 *     the data attributes,
 *   - aborts when the user dismisses the confirm.
 *
 * Selectors per AGENTS.md "Testability hooks":
 *   - The synthetic trigger uses `data-testid="synth-delcomlink"`.
 *
 * Project gating
 * --------------
 * Pin to chromium (desktop). The flow is layout-agnostic so the
 * mobile project's no-op cost would be pure CI minutes.
 */

import { expect, test } from '../../fixtures/auth.ts';

const ADMIN_BANS_PROTESTS_ROUTE = '/index.php?p=admin&c=bans&section=protests';

test.describe('flow: comment-delete dispatcher (#1402 — RemoveComment zombie)', () => {
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    test('Trigger → confirm OK → Actions.BansRemoveComment with cid/ctype/page', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        // Intercept the BansRemoveComment call and stub a success
        // envelope. We don't want to mutate the e2e DB and the goal
        // is to assert the wire-format, not the DB write (which is
        // already pinned by `web/tests/api/BansTest.php`).
        //
        // sb.api.call (web/scripts/api.js) sends the JSON body as
        // `{ action, params }` — NOT `{ a, ...params }`. The dispatched
        // params (cid / ctype / page) live under the `params` key.
        const apiCalls: { cid: number; ctype: string; page: number }[] = [];
        await page.route('**/api.php', async (route) => {
            let body: { action?: string; params?: Record<string, unknown> } | null = null;
            try {
                body = JSON.parse(route.request().postData() || '{}');
            } catch {
                body = null;
            }
            if (body?.action === 'bans.remove_comment') {
                const params = body.params || {};
                apiCalls.push({
                    cid:   Number(params.cid),
                    ctype: String(params.ctype),
                    page:  Number(params.page),
                });
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ok: true,
                        data: { message: { title: 'Comment Removed', body: 'Comment removed' } },
                    }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(ADMIN_BANS_PROTESTS_ROUTE);

        // Auto-accept the window.confirm prompt the dispatcher
        // raises before firing the API.
        page.once('dialog', async (dialog) => {
            expect(dialog.type()).toBe('confirm');
            expect(dialog.message()).toMatch(/delete/i);
            await dialog.accept();
        });

        // Inject a synthetic trigger anywhere on the page. The
        // dispatcher is a document-level delegate, so the position
        // is irrelevant.
        await page.evaluate(() => {
            const a = document.createElement('a');
            a.setAttribute('href', '#');
            a.setAttribute('data-action', 'comment-delete');
            a.setAttribute('data-cid', '42');
            a.setAttribute('data-ctype', 'P');
            a.setAttribute('data-page', '0');
            a.setAttribute('data-testid', 'synth-delcomlink');
            a.textContent = 'Delete (synth)';
            document.body.appendChild(a);
        });

        await page.locator('[data-testid="synth-delcomlink"]').click();

        // The API call must have landed with the values from the
        // data attributes.
        await expect.poll(() => apiCalls.length).toBeGreaterThan(0);
        expect(apiCalls[0]).toEqual({ cid: 42, ctype: 'P', page: 0 });

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Trigger → confirm cancel → no API call', async ({ page }) => {
        let apiCalls = 0;
        await page.route('**/api.php', async (route) => {
            let body: { action?: string } | null = null;
            try {
                body = JSON.parse(route.request().postData() || '{}');
            } catch {
                body = null;
            }
            if (body?.action === 'bans.remove_comment') {
                apiCalls++;
            }
            await route.continue();
        });

        await page.goto(ADMIN_BANS_PROTESTS_ROUTE);

        // Dismiss the prompt.
        page.once('dialog', async (dialog) => {
            await dialog.dismiss();
        });

        await page.evaluate(() => {
            const a = document.createElement('a');
            a.setAttribute('href', '#');
            a.setAttribute('data-action', 'comment-delete');
            a.setAttribute('data-cid', '99');
            a.setAttribute('data-ctype', 'B');
            a.setAttribute('data-page', '1');
            a.setAttribute('data-testid', 'synth-delcomlink-cancel');
            a.textContent = 'Cancel test';
            document.body.appendChild(a);
        });

        await page.locator('[data-testid="synth-delcomlink-cancel"]').click();

        // The cancelled-confirm path returns synchronously from the
        // dispatcher (window.confirm → false → return without
        // touching the API). Playwright's click() awaits the click
        // event's handlers, so by the time the awaited click resolves
        // the dispatcher has already early-returned. No settle timer
        // needed (AGENTS.md "Playwright E2E specifics" flags
        // `waitForTimeout` for negative assertions as an anti-pattern).
        expect(apiCalls, 'cancelled confirm must NOT call the API').toBe(0);
    });

    test('Dispatcher loaded globally (comment-actions.js ships from footer)', async ({ page }) => {
        // Smoke check: navigate to a public page (not just admin)
        // and confirm the script reaches the document.
        await page.goto('/index.php?p=banlist');

        const scripts = await page.locator('script[src*="comment-actions.js"]').count();
        expect(scripts).toBeGreaterThan(0);
    });
});
