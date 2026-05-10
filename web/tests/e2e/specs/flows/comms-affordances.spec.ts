/**
 * Flow + responsive coverage for the comms list affordances slice
 * (#1207 ADM-5 / ADM-6).
 *
 * What this locks in
 * ------------------
 *
 *  - **ADM-5 (P1)** — Edit / Unmute / Remove buttons on active comm
 *    rows render *visibly by default* (not hover-only). The pre-fix
 *    `.row-actions { opacity: 0 }` rule was removed from `theme.css`
 *    in this slice; the assertions below treat "visible without
 *    hover" as a contract by reading `getComputedStyle(...).opacity`.
 *  - **ADM-6 (P2)** — Lifting an active block updates the row
 *    *in-place* (no full reload): the wrapper's `data-state` flips
 *    to `unmuted`, the status pill swaps `pill--active` →
 *    `pill--unmuted` and renders "Unmuted", the `Unmute` button
 *    is replaced by a `Re-apply` anchor, and a `success` toast
 *    confirms the action.
 *  - Same affordance set + in-place flip surfaces on the mobile
 *    card layout (`mobile-chromium` project; the `<table>` is
 *    `display:none` below 769px and the `.ban-cards` container
 *    takes over per `theme.css`'s responsive block).
 *  - Remove is a destructive action that goes through
 *    `Actions.CommsDelete` (slice introduces it), prompts a native
 *    `confirm()`, and on accept removes both the desktop `<tr>` and
 *    the mobile `<div>` mirrors of the row + decrements the visible
 *    count.
 *
 * Project gating
 * --------------
 * The spec runs on both `chromium` and `mobile-chromium` because
 * the visible-action contract has to hold on every viewport (#1207
 * was filed against both). `truncateE2eDb()` is parallel-project-
 * safe at workers=1 (the named-lock in `Sbpp\Tests\Fixture::
 * truncateAndReseed` serializes resets, and the projects run
 * sequentially under workers=1 in CI).
 *
 * Selectors
 * ---------
 * Per #1123, every assertion uses `data-testid` (`comm-row`,
 * `row-action-edit`, `row-action-unmute`, `row-action-delete`,
 * `row-action-reapply`) and the row's `data-state` attribute.
 * Toast disambiguation uses `data-kind` plus a `hasText`
 * filter — visible text is not a primary selector.
 *
 * Why we delegate seeding to the JSON API
 * ---------------------------------------
 * Same rationale as `fixtures/seeds.ts`: driving the actual
 * `Actions.CommsAdd` handler covers the full Smarty/CSRF/dispatcher
 * path on every run, and a regression to the wire format surfaces
 * here instead of silently producing a stale fixture.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const COMMSLIST_ROUTE = '/index.php?p=commslist';

const FIXTURE = {
    /** Distinct SteamID per finding so the two tests can run in either order without colliding. */
    steamUnmute: 'STEAM_0:1:9912001',
    steamRemove: 'STEAM_0:0:9912002',
    nicknameUnmute: 'e2e-adm5-unmute',
    nicknameRemove: 'e2e-adm6-remove',
    reasonUnmute: 'e2e: ADM-5/6 unmute affordance',
    reasonRemove: 'e2e: ADM-5/6 remove affordance',
    /** 60 minutes — keeps the row in `state="active"` for the spec's lifetime. */
    lengthMinutes: 60,
};

interface SeededComm {
    steam: string;
    nickname: string;
}

/**
 * Seed a `gag` (type=2) row via `Actions.CommsAdd`. The handler
 * stores the row keyed by the caller's `aid`, so it inherits the
 * default-admin postkey we mint in `fixtures/global-setup.ts`. The
 * page must already be authenticated; we go through the home route
 * to make sure `window.sb.api` and `window.Actions` are mounted.
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

test.describe('flow: comms list affordances (#1207 ADM-5/ADM-6)', () => {
    // Three tests in this file all `truncateE2eDb()` in beforeEach
    // and seed independent rows. Without `serial` mode Playwright
    // runs them in parallel workers locally (CI pins `workers: 1`,
    // see playwright.config.ts), and worker B's truncate wipes
    // worker A's seeded row mid-test → flaky `toHaveCount(0)` and
    // `forbidden` from `comms.add` while the admin row is briefly
    // missing during the reseed window. The named-lock in
    // `Sbpp\Tests\Fixture::truncateAndReseed` makes the *reset*
    // atomic per-process, but it doesn't span the gap between the
    // truncate-and-reseed and the assertion; serial execution is
    // the right granularity for state-mutating flow specs.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('active row exposes visible Edit/Unmute/Remove on desktop and flips in-place on Unmute', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'desktop affordance contract — mobile is covered by the card spec below');

        await seedGagViaApi(page, {
            steam: FIXTURE.steamUnmute,
            nickname: FIXTURE.nicknameUnmute,
            reason: FIXTURE.reasonUnmute,
            length: FIXTURE.lengthMinutes,
        });

        await page.goto(COMMSLIST_ROUTE);

        const row = page
            .locator('[data-testid="comm-row"]')
            .filter({ hasText: FIXTURE.steamUnmute });
        await expect(row).toHaveCount(1);
        await expect(row).toHaveAttribute('data-state', 'active');

        // ---- ADM-5: visible without hover --------------------------------
        // Pre-fix the `.row-actions` container had `opacity:0` and
        // only became visible on `tbody tr:hover`. Reading
        // `getComputedStyle().opacity === '1'` *without* hovering
        // is the deterministic equivalent of "the action set is
        // discoverable" — a regression that re-introduces the
        // hover-only treatment fails this assertion immediately.
        const actions = row.locator('.row-actions');
        await expect(actions).toBeVisible();
        const opacity = await actions.evaluate(
            (el) => getComputedStyle(el).opacity,
        );
        expect(opacity, 'row-actions are visible without hover').toBe('1');

        const editBtn = row.locator('[data-testid="row-action-edit"]');
        const unmuteBtn = row.locator('[data-testid="row-action-unmute"]');
        const deleteBtn = row.locator('[data-testid="row-action-delete"]');
        await expect(editBtn).toBeVisible();
        await expect(unmuteBtn).toBeVisible();
        await expect(deleteBtn).toBeVisible();
        // The visible label is part of the affordance — pre-fix
        // these were icon-only links. Locking the text in catches
        // a regression that drops the `<span>` while keeping the
        // testid.
        await expect(unmuteBtn).toContainText(/ungag|unmute|lift/i);
        await expect(deleteBtn).toContainText(/remove/i);

        // ---- ADM-6: click Unmute → confirm modal → in-place flip + toast -
        // #1301: the Unmute button now opens
        // `#comms-unblock-dialog` and requires a non-empty reason
        // before firing `Actions.CommsUnblock`. The submit handler
        // resolves on success, then in-place:
        //   - flips `data-state` and `ban-row--*` class to `unmuted`
        //   - rewrites the status pill text to "Unmuted"
        //   - replaces the Unmute button with a Re-apply anchor
        //   - fires `window.SBPP.showToast` (success kind)
        //
        // No reload, no `setTimeout` to wait out — the browser
        // resolves the await inside the handler synchronously
        // relative to the toast/DOM update.
        await unmuteBtn.click();

        const unblockDialog = page.locator('[data-testid="comms-unblock-dialog"]');
        await expect(unblockDialog).toBeVisible();
        await unblockDialog
            .locator('[data-testid="comms-unblock-reason"]')
            .fill('e2e: lifting the gag');
        await unblockDialog.locator('[data-testid="comms-unblock-submit"]').click();

        await expect(row).toHaveAttribute('data-state', 'unmuted');
        // The status pill is the *last* `.pill` inside the row
        // (column 1 has the type pill, column 8 has the status
        // pill). We locate by the post-flip `pill--unmuted` class
        // — the type pill in column 1 also tracks the new state
        // class so both slots stay visually consistent.
        const statusPills = row.locator('.pill.pill--unmuted');
        await expect(statusPills).not.toHaveCount(0);
        await expect(row.getByText(/^\s*Unmuted\s*$/)).toBeVisible();

        // The Unmute button is gone, Re-apply takes its place.
        await expect(row.locator('[data-testid="row-action-unmute"]')).toHaveCount(0);
        const reapply = row.locator('[data-testid="row-action-reapply"]');
        await expect(reapply).toBeVisible();
        await expect(reapply).toContainText(/re-apply/i);
        // The Re-apply anchor goes through the existing
        // `comms.prepare_reblock` flow at `?p=admin&c=comms&rebanid=<bid>`.
        await expect(reapply).toHaveAttribute('href', /[?&]rebanid=\d+\b/);

        // Toast — anchor on the deterministic `data-kind` attribute
        // (set by theme.js's `showToast`) and a case-insensitive
        // title filter to disambiguate from any passive page chrome
        // toasts.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /block lifted/i });
        await expect(successToast).toBeVisible();
    });

    test('mobile card surfaces visible actions and flips Unmute in-place', async ({
        page,
        isMobile,
    }) => {
        test.skip(!isMobile, 'mobile card contract — desktop is covered by the table spec above');

        await seedGagViaApi(page, {
            steam: FIXTURE.steamUnmute,
            nickname: FIXTURE.nicknameUnmute,
            reason: FIXTURE.reasonUnmute,
            length: FIXTURE.lengthMinutes,
        });

        await page.goto(COMMSLIST_ROUTE);

        // The desktop `<table>` is `display:none` below 769px;
        // we anchor on the `comm-card` testid which is the mobile
        // wrapper.
        const card = page
            .locator('[data-testid="comm-card"]')
            .filter({ hasText: FIXTURE.steamUnmute });
        await expect(card).toHaveCount(1);
        await expect(card).toHaveAttribute('data-state', 'active');

        const editBtn = card.locator('[data-testid="row-action-edit-mobile"]');
        const unmuteBtn = card.locator('[data-testid="row-action-unmute-mobile"]');
        const deleteBtn = card.locator('[data-testid="row-action-delete-mobile"]');
        await expect(editBtn).toBeVisible();
        await expect(unmuteBtn).toBeVisible();
        await expect(deleteBtn).toBeVisible();

        // The summary anchor (filter-by-SteamID navigation) and
        // the action buttons are siblings inside `comm-card` —
        // pre-fix the entire card was a single `<a>` so action
        // buttons couldn't live inside it without producing
        // invalid nested-interactive HTML. The summary anchor's
        // dedicated testid lets us assert it still works as a
        // standalone navigation affordance.
        await expect(card.locator('[data-testid="comm-card-link"]')).toBeVisible();

        await unmuteBtn.click();

        // #1301: same confirm-modal contract as the desktop spec.
        const unblockDialog = page.locator('[data-testid="comms-unblock-dialog"]');
        await expect(unblockDialog).toBeVisible();
        await unblockDialog
            .locator('[data-testid="comms-unblock-reason"]')
            .fill('e2e: lifting the gag (mobile)');
        await unblockDialog.locator('[data-testid="comms-unblock-submit"]').click();

        await expect(card).toHaveAttribute('data-state', 'unmuted');
        await expect(card.locator('[data-testid="row-action-unmute-mobile"]')).toHaveCount(0);
        const reapply = card.locator('[data-testid="row-action-reapply-mobile"]');
        await expect(reapply).toBeVisible();
        await expect(reapply).toHaveAttribute('href', /[?&]rebanid=\d+\b/);

        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /block lifted/i });
        await expect(successToast).toBeVisible();
    });

    test('Remove action deletes the row in-place and decrements the count', async ({
        page,
        isMobile,
    }) => {
        // Run on desktop only — the destructive path is the same
        // wire call (`Actions.CommsDelete`) and the count
        // decrement lives in the same shared header. Mobile-card
        // visibility is covered by the test above; doubling the
        // delete on mobile would just retread the API contract.
        test.skip(isMobile, 'delete-flow desktop covers the API contract; mobile visibility is in the card spec');

        await seedGagViaApi(page, {
            steam: FIXTURE.steamRemove,
            nickname: FIXTURE.nicknameRemove,
            reason: FIXTURE.reasonRemove,
            length: FIXTURE.lengthMinutes,
        });

        await page.goto(COMMSLIST_ROUTE);

        const row = page
            .locator('[data-testid="comm-row"]')
            .filter({ hasText: FIXTURE.steamRemove });
        await expect(row).toHaveCount(1);

        const countBefore = await page
            .locator('[data-testid="comms-count"]')
            .textContent();
        const before = Number((countBefore || '').replace(/[^0-9]/g, ''));

        // The inline JS prompts `window.confirm` before issuing the
        // destructive call. Playwright dismisses dialogs by default;
        // wire an accept handler so the click resolves the API path.
        page.once('dialog', (d) => d.accept());

        await row.locator('[data-testid="row-action-delete"]').click();

        // Row is removed from the DOM; the matching `comm-card`
        // mirror is removed too (the JS targets both via
        // `rowsForBid`). We assert against the row anchor since
        // we're on desktop.
        await expect(row).toHaveCount(0);

        const countAfter = await page
            .locator('[data-testid="comms-count"]')
            .textContent();
        const after = Number((countAfter || '').replace(/[^0-9]/g, ''));
        expect(after).toBe(Math.max(before - 1, 0));

        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /block removed/i });
        await expect(successToast).toBeVisible();
    });
});
