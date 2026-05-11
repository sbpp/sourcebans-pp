/**
 * Loading-indicator contract for action buttons that fire
 * `sb.api.call(...)` without a page refresh. The motivating example
 * was the Comms-list "Confirm" button inside `#comms-unblock-dialog`:
 * pre-fix the click had no visible feedback for the 100-1000ms the
 * server took to respond, and users instinctively double-clicked
 * "to make it work" — queuing duplicate `Actions.CommsUnblock`
 * requests until the manual `disabled = true` line ran inside the
 * `.then` callback (which is too late, since the second click had
 * already fired during the in-flight network round-trip).
 *
 * The single-source contract this spec locks in
 * ---------------------------------------------
 * Every action button that issues `sb.api.call(...)` from a click
 * handler flips through `window.SBPP.setBusy(btn, true)` BEFORE
 * the API call leaves the page (so the disabled flag lands on
 * the first paint of the click) and `setBusy(btn, false)` after
 * the envelope is processed (so retries are possible on the
 * non-navigating failure paths). The CSS rule
 * `.btn[data-loading="true"]` paints the spinner + locks the
 * width; theme.js's `setBusy()` flips the attribute + the
 * `aria-busy` + the underlying `<button disabled>`.
 *
 * Why test on the Comms unblock dialog
 * ------------------------------------
 * It's the surface the user explicitly called out as the
 * motivating case, AND it exercises every load-bearing layer:
 *
 *   1. `<dialog>` confirm modal with a textarea-bound submit button
 *      (so we cover the form-submit path, not the row-button path).
 *   2. Inline `setBusy(...)` helper that wraps `window.SBPP.setBusy`
 *      with a `disabled`-only fallback (so the contract still holds
 *      when a third-party theme strips theme.js).
 *   3. `Actions.CommsUnblock` — the JSON action that actually mutates
 *      DB state. We stall it via `page.route(...)` so the loading
 *      state stays visible for the assertion window without depending
 *      on a slow test box.
 *   4. On success the row flips in-place (`data-state` → `unmuted`),
 *      the dialog closes, and the button is removed from the DOM
 *      with the rest of the dialog — so the success path is asserted
 *      implicitly by the row flip, NOT by re-reading the now-detached
 *      button's `data-loading` attribute.
 *
 * Per #1123 testability hooks: `data-testid="comms-unblock-submit"`
 * is the canonical selector for the Confirm button; `data-loading`
 * and `aria-busy` are the busy-state contract; the underlying
 * `disabled` flag is the load-bearing gate against double-clicks
 * regardless of CSS state.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const COMMSLIST_ROUTE = '/index.php?p=commslist';

const FIXTURE = {
    steam: 'STEAM_0:1:1207040',
    nickname: 'e2e-loading-indicator',
    reason: 'e2e: loading-indicator on unblock',
    /** 60 minutes — keeps the row `state="active"` for the spec. */
    lengthMinutes: 60,
};

interface SeededComm {
    steam: string;
    nickname: string;
}

/**
 * Seed an active gag via `Actions.CommsAdd` (same path
 * `comms-affordances.spec.ts` uses). The row is keyed to the
 * default admin's `aid`; `truncateE2eDb()` clears it between
 * tests.
 */
async function seedGagViaApi(
    page: import('@playwright/test').Page,
    seed: { steam: string; nickname: string; reason: string; length: number },
): Promise<SeededComm> {
    await page.goto('/');
    const envelope = await page.evaluate(async (args) => {
        const w = window as unknown as {
            sb: {
                api: {
                    call: (
                        action: string,
                        params: Record<string, unknown>,
                    ) => Promise<{ ok: boolean; data?: unknown; error?: { code: string; message: string } }>;
                };
            };
            Actions: Record<string, string>;
        };
        return await w.sb.api.call(w.Actions.CommsAdd, {
            steam: args.steam,
            nickname: args.nickname,
            type: 2,
            length: args.length,
            reason: args.reason,
        });
    }, seed);

    const env = envelope as { ok: boolean; error?: { message: string } };
    if (!env.ok) {
        throw new Error(`seedGagViaApi failed: ${JSON.stringify(env)}`);
    }
    return { steam: seed.steam, nickname: seed.nickname };
}

test.describe('flow: action-button loading indicator (comms unblock)', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('clicking Confirm flips the submit button to data-loading="true" until the API resolves', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'desktop is the canonical surface; mobile follows the same JS path');

        await seedGagViaApi(page, {
            steam: FIXTURE.steam,
            nickname: FIXTURE.nickname,
            reason: FIXTURE.reason,
            length: FIXTURE.lengthMinutes,
        });

        // Intercept the in-flight `Actions.CommsUnblock` call and hold
        // it open until we've asserted the busy state. We DO eventually
        // resolve the route — the success branch then flips the row
        // in-place and removes the dialog, which is the implicit
        // "loading state cleared" assertion. The `Promise<() => void>`
        // shape exposes the release function to the test body.
        let releaseRoute: (() => void) | null = null;
        const routeStalled = new Promise<void>((resolve) => {
            // Mark the route handler installed BEFORE the click so the
            // capture-side `routeStalled` await below has something to
            // pivot on. `page.route` is sync; the handler fires when
            // the matching request lands. The wire-format action name
            // is the dotted-lowercase id from `_register.php`
            // (`comms.unblock`), NOT the JS `Actions.CommsUnblock`
            // constant — `Actions.PascalName` is just a typo-safe
            // string alias for the same on-the-wire id.
            void page.route('**/api.php', async (route) => {
                let body: unknown = null;
                try {
                    body = JSON.parse(route.request().postData() || '{}');
                } catch {
                    body = null;
                }
                const action = (body as { action?: string } | null)?.action;
                if (action !== 'comms.unblock') {
                    await route.continue();
                    return;
                }
                resolve();
                // Wait for the test body to release us before letting
                // the real handler run. The await on `releasePromise`
                // is what gives the assertions their window.
                await new Promise<void>((releaseInner) => {
                    releaseRoute = releaseInner;
                });
                await route.continue();
            });
        });

        await page.goto(COMMSLIST_ROUTE);

        const row = page
            .locator('[data-testid="comm-row"]')
            .filter({ hasText: FIXTURE.steam });
        await expect(row).toHaveCount(1);
        await expect(row).toHaveAttribute('data-state', 'active');

        const unmuteBtn = row.locator('[data-testid="row-action-unmute"]');
        await unmuteBtn.click();

        const dialog = page.locator('[data-testid="comms-unblock-dialog"]');
        await expect(dialog).toBeVisible();

        await dialog
            .locator('[data-testid="comms-unblock-reason"]')
            .fill('e2e: loading-state assertion');

        const submitBtn = dialog.locator('[data-testid="comms-unblock-submit"]');
        // Pre-click the busy attributes are absent.
        await expect(submitBtn).not.toHaveAttribute('data-loading', 'true');

        await submitBtn.click();

        // Wait for the request to actually land on the network so we
        // know `setBusy(submitBtn, true)` has run on the renderer.
        // Without this await we'd race the click — a flake nobody
        // would diagnose for a year.
        await routeStalled;

        // ---- The contract: data-loading + aria-busy + disabled ----
        // All three attributes are the load-bearing surfaces:
        //   - `data-loading="true"`  drives the CSS spinner (visual).
        //   - `aria-busy="true"`     announces the state to AT users.
        //   - `disabled`             gates against double-clicks.
        // theme.js's `setBusy` is the only source-of-truth for this
        // triple; a regression that drops any one of them fails here.
        await expect(submitBtn).toHaveAttribute('data-loading', 'true');
        await expect(submitBtn).toHaveAttribute('aria-busy', 'true');
        await expect(submitBtn).toBeDisabled();

        // Release the API call. The success branch flips the row in
        // place + closes the dialog (which detaches the submit
        // button), so we assert against the row's post-flip state
        // rather than the now-gone button. Pre-fix this branch's
        // explicit `setBusy(submitBtn, false)` was needed for the
        // failure path; on success the dialog teardown handles
        // cleanup. Either way the operator sees the spinner clear.
        if (releaseRoute) releaseRoute();
        else throw new Error('releaseRoute was never wired by the route handler');

        await expect(row).toHaveAttribute('data-state', 'unmuted');
        await expect(dialog).toBeHidden();
    });

    test('an in-flight Confirm rejects the second click (disabled gate against double-submit)', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'desktop is the canonical surface; mobile follows the same JS path');

        await seedGagViaApi(page, {
            steam: FIXTURE.steam,
            nickname: FIXTURE.nickname,
            reason: FIXTURE.reason,
            length: FIXTURE.lengthMinutes,
        });

        // Same stall pattern as above; we want one outstanding
        // CommsUnblock that we control the lifetime of.
        let firstSeen = false;
        let releaseRoute: (() => void) | null = null;
        const routeStalled = new Promise<void>((resolve) => {
            void page.route('**/api.php', async (route) => {
                let body: unknown = null;
                try {
                    body = JSON.parse(route.request().postData() || '{}');
                } catch {
                    body = null;
                }
                const action = (body as { action?: string } | null)?.action;
                if (action !== 'comms.unblock') {
                    await route.continue();
                    return;
                }
                // Only the FIRST `comms.unblock` is allowed to reach
                // the stall; a second arrival is a bug — the
                // disabled gate should have blocked the click.
                if (firstSeen) {
                    throw new Error('Second comms.unblock request fired — double-submit gate broke');
                }
                firstSeen = true;
                resolve();
                await new Promise<void>((releaseInner) => {
                    releaseRoute = releaseInner;
                });
                await route.continue();
            });
        });

        await page.goto(COMMSLIST_ROUTE);

        const row = page
            .locator('[data-testid="comm-row"]')
            .filter({ hasText: FIXTURE.steam });
        await expect(row).toHaveCount(1);
        await row.locator('[data-testid="row-action-unmute"]').click();

        const dialog = page.locator('[data-testid="comms-unblock-dialog"]');
        await expect(dialog).toBeVisible();
        await dialog.locator('[data-testid="comms-unblock-reason"]').fill('e2e: double-click');

        const submitBtn = dialog.locator('[data-testid="comms-unblock-submit"]');
        await submitBtn.click();
        await routeStalled;

        // The button is disabled — Playwright's `.click()` waits for
        // actionability by default, so a naive second `.click()` here
        // would hang for 30s rather than dispatch. `{ force: true }`
        // bypasses the wait so we can prove the disabled gate
        // catches the click event even when actionability is forced.
        // The route handler's `if (firstSeen)` throw is the safety
        // net: if the click leaks through despite `disabled`, the
        // second request fires and we trip the throw.
        await submitBtn.click({ force: true });

        // Give the browser a tick to flush any queued click events
        // before we conclude no second request fired.
        await page.waitForTimeout(50);

        if (releaseRoute) releaseRoute();
        else throw new Error('releaseRoute was never wired by the route handler');

        await expect(row).toHaveAttribute('data-state', 'unmuted');
    });
});
