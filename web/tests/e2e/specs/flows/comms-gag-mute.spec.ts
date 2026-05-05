/**
 * Comms (gag/mute) flow — Slice 5 of #1124.
 *
 * End-to-end coverage of the admin comms-add → public list → unblock
 * loop. Single-context spec (storage-state admin); the "public" view
 * is exercised by hitting the same /commslist URL anonymous visitors
 * would.
 *
 * Selectors come from #1123's testability-hook contract:
 *
 *   - Add form (web/themes/default/page_admin_comms_add.tpl):
 *       data-testid="addcomm-form"
 *       data-testid="addcomm-{steam,nickname,type,length,reason,reason-custom,submit}"
 *   - List rows (web/themes/default/page_comms.tpl):
 *       data-testid="comm-row" with data-id="{cid}", data-type, data-state
 *       data-testid="row-action-unmute"
 *
 * Flow:
 *   1. truncateE2eDb() in beforeEach — fresh `sourcebans_e2e` per test.
 *   2. Goto /index.php?p=admin&c=comms (the gag/mute add form).
 *   3. Submit type=2 (Gag) for STEAM_0:1:7654321 with a custom reason.
 *   4. Wait on the JSON `comms.add` response (terminal state) rather
 *      than `sb.message.show`'s `#dialog-placement`: the new theme
 *      doesn't ship that legacy chrome element, so the dialog is a
 *      silent no-op. See `web/themes/default/page_youraccount.tpl`
 *      around the `showToast()` helper for the chrome contract; a
 *      proper toast for the comms-add success is a follow-up.
 *   5. Goto /index.php?p=commslist, assert the row is present and
 *      `data-state="active"` / `data-type="gag"`. Capture its `data-id`
 *      so subsequent navigations anchor on the cid (stable integer)
 *      regardless of list ordering.
 *   6. Re-visit /commslist as the same admin (the brief calls this
 *      "public list" — the route is public, the list visibility is
 *      identical for admin and anonymous users).
 *   7. Unblock by following the row's `row-action-unmute` href with
 *      `&ureason=` appended; the new theme exposes a direct anchor
 *      (the legacy JS `UnGag()` confirm prompt was removed when
 *      sourcebans.js went away in #1123 D1).
 *   8. Re-visit /commslist; assert the row's `data-state` flipped to
 *      "unmuted".
 *
 * Divergences from the brief (called out in the PR body):
 *   - The "row-action-unblock" data-testid the brief refers to is
 *     `row-action-unmute` in the actual template. We assert against
 *     what's there and don't re-tag the template (no missing-hook
 *     escalation needed).
 *   - The brief's step 3 says goto `/index.php?p=admin&c=comms` for
 *     the admin list. That route renders the add form, not a list.
 *     The list lives at `?p=commslist` for both admin and public; the
 *     test threads through the brief's intent on that URL.
 *   - There is no unblock confirmation modal in the new theme; the
 *     unmute_url is a direct anchor. We pass `ureason=` through the
 *     URL to honour the brief's "provide an unblock reason" step.
 *   - The success toast on `comms.add` is a no-op in the new theme
 *     (sb.message.show targets the legacy `#dialog-placement` shell
 *     which sbpp2026 doesn't render). The spec asserts on the API
 *     response rather than a missing UI element; PR body flags this
 *     as a follow-up for the comms-add page-tail JS to migrate to
 *     `window.SBPP.showToast`.
 */

import { test, expect } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const TARGET = {
    steam: 'STEAM_0:1:7654321',
    name: 'e2e-gag-target',
    reason: 'e2e: comms gag flow',
    // Form value matches the optgroup="Minutes" → `<option value="5">`.
    // 5 minutes keeps the row in `state="active"` for the duration of
    // the test (the API stores `length * 60` seconds and the row's
    // state derives from `ends > UNIX_TIMESTAMP()`).
    lengthMinutes: '5',
};

const UNBLOCK_REASON = 'e2e: lifted';

test.describe('flow: comms gag → list → unblock', () => {
    test.beforeEach(async ({}, testInfo) => {
        // Slice 0 harness gap: `truncateE2eDb()` truncates + reseeds
        // the shared `sourcebans_e2e` DB without an advisory lock, so
        // two workers (chromium + mobile-chromium) racing on
        // `beforeEach` for the same test fail with `1062 Duplicate
        // entry '0' for key 'PRIMARY'` when their seed inserts
        // interleave. State-mutating flow specs only need a single
        // browser context to assert the server-side state machine; the
        // visual/responsive coverage lives in `_screenshots.spec.ts`
        // (desktop only for the same reason) and the `responsive/`
        // specs. Pin to chromium until Slice 0's harness grows a
        // per-DB lock around `Fixture::truncateOnly()`.
        test.skip(
            testInfo.project.name !== 'chromium',
            'state-mutating flow; truncateE2eDb is not parallel-project-safe (Slice 0 follow-up)',
        );
        await truncateE2eDb();
    });

    test('admin gags a player, sees it on /commslist, then unblocks', async ({ page }) => {
        // ============================================================
        // 1. Open the admin comms-add form.
        // ============================================================
        await page.goto('/index.php?p=admin&c=comms');
        await expect(page.locator('[data-testid="addcomm-form"]')).toBeVisible();

        // ============================================================
        // 2. Fill + submit the gag.
        //
        // type=2 == "Gag (chat)" per the form's <option value="2">; the
        // API maps 1→mute, 2→gag, 3→silence (both rows). length is in
        // minutes — see the optgroup labels in the .tpl. The reason
        // <select> is a curated list; we pick "other" so we can drop
        // a freeform e2e marker into the textarea (changeReason()
        // toggles the textarea's #dreason container).
        // ============================================================
        await page.locator('[data-testid="addcomm-steam"]').fill(TARGET.steam);
        await page.locator('[data-testid="addcomm-nickname"]').fill(TARGET.name);
        await page.locator('[data-testid="addcomm-type"]').selectOption('2');
        await page.locator('[data-testid="addcomm-length"]').selectOption(TARGET.lengthMinutes);
        await page.locator('[data-testid="addcomm-reason"]').selectOption('other');
        await page.locator('[data-testid="addcomm-reason-custom"]').fill(TARGET.reason);

        // Wait for the JSON API to settle — that's the deterministic
        // terminal state in the new theme (the legacy `#dialog-placement`
        // chrome was dropped, see file-level docblock). The
        // setTimeout(2000) reload `ProcessBan()` schedules is the
        // page's own clock; we navigate explicitly below to avoid
        // waiting on it.
        const apiResponse = page.waitForResponse(
            (r) => r.url().includes('/api.php') && r.request().method() === 'POST',
        );
        await page.locator('[data-testid="addcomm-submit"]').click();
        const response = await apiResponse;
        expect(response.status(), 'comms.add returns 200').toBe(200);
        const body = await response.json();
        expect(body, 'comms.add envelope').toMatchObject({ ok: true });
        expect(body.data?.block?.steam, 'comms.add returns the block').toBe(TARGET.steam);

        // ============================================================
        // 3. Admin /commslist — the gag is in the table.
        //
        // Filter by SteamID text rather than scanning every row by
        // index; SteamIDs are unique on the table and the `comm-row`
        // testid + `data-id` anchor lets us capture the cid for later
        // navigations.
        // ============================================================
        await page.goto('/index.php?p=commslist');
        await expect(page.locator('[data-testid="comms-table"]')).toBeAttached();

        const adminRow = page
            .locator('[data-testid="comm-row"]')
            .filter({ hasText: TARGET.steam });

        await expect(adminRow).toHaveCount(1);
        await expect(adminRow).toHaveAttribute('data-type', 'gag');
        await expect(adminRow).toHaveAttribute('data-state', 'active');
        await expect(adminRow).toContainText(TARGET.name);

        const cid = await adminRow.getAttribute('data-id');
        expect(cid, 'comm row exposes a numeric data-id').toMatch(/^\d+$/);

        // ============================================================
        // 4. "Public" view — same /commslist URL.
        //
        // The brief asks for a public-list assertion; the route is
        // already public and the list contents are identical regardless
        // of who's looking (admin-only affordances are gated per-row
        // and don't change the row's existence). We re-visit so the
        // assertion is against a fresh page load rather than the
        // already-painted DOM.
        // ============================================================
        await page.goto('/index.php?p=commslist');
        const publicRow = page.locator(`[data-testid="comm-row"][data-id="${cid}"]`);
        await expect(publicRow).toBeVisible();
        await expect(publicRow).toHaveAttribute('data-type', 'gag');
        await expect(publicRow).toContainText(TARGET.steam);

        // ============================================================
        // 5. Unblock — follow the row's unmute anchor with `&ureason=`.
        //
        // The legacy `UnGag('id', 'key', …)` JS confirm prompt is gone
        // post-D1 (sourcebans.js removed); the new theme renders a
        // direct anchor whose href already carries the cid + the
        // session's `banlist_postkey`. The server reads `ureason`
        // from $_GET, so appending it on the client side lets us
        // record the brief's "e2e: lifted" reason without needing a
        // modal in the theme.
        // ============================================================
        const unmuteHref = await publicRow
            .locator('[data-testid="row-action-unmute"]')
            .getAttribute('href');
        expect(unmuteHref, 'unmute link exposes a key/cid query').toMatch(/[?&]a=ungag(?:&|$)/);
        expect(unmuteHref, 'unmute link carries banlist_postkey').toMatch(/[?&]key=[^&]+/);

        // URL is already query-string-encoded; appending another `&k=v`
        // pair is safe.
        const unmuteUrl = `${unmuteHref}&ureason=${encodeURIComponent(UNBLOCK_REASON)}`;
        await page.goto(unmuteUrl);

        // ============================================================
        // 6. Confirm — admin re-loads /commslist, row state is now
        //    "unmuted". The default list filter is `hide_inactive=false`
        //    so the row is still visible (just with a different state
        //    pill); the brief's "row gone" alternative would require
        //    flipping the hideinactive session toggle, which is out of
        //    scope for this slice.
        // ============================================================
        await page.goto('/index.php?p=commslist');
        const unblockedRow = page.locator(`[data-testid="comm-row"][data-id="${cid}"]`);
        await expect(unblockedRow).toBeVisible();
        await expect(unblockedRow).toHaveAttribute('data-state', 'unmuted');
        // The active-action affordance is gone now that the block has
        // been lifted — `unmute_url` is null in the View when the row
        // is no longer active, so the icon link disappears.
        await expect(unblockedRow.locator('[data-testid="row-action-unmute"]')).toHaveCount(0);
    });
});
