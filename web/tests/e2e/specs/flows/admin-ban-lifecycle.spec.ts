/**
 * Flow spec — admin ban lifecycle (#1124 Slice 4).
 *
 * End-to-end walkthrough of the moderation surface as a logged-in
 * admin: add a ban via the "Add a ban" form, find it in the public
 * ban list, edit the reason, unban it, and confirm the row's state
 * pill flips to `unbanned` in both the admin-visible and public
 * banlist views.
 *
 * Why one test, not five
 * ---------------------
 * Steps depend on data the previous step created (the new ban's
 * `bid`, the row's edit/unban hrefs that embed the per-session
 * `admin_postkey`). Splitting into separate `test()` blocks would
 * re-truncate the DB between them and force every step to redo the
 * earlier setup. Keeping the lifecycle as one linear test mirrors
 * the user-facing flow and keeps the trace artifact a single
 * scrubbable timeline on failure.
 *
 * Project gating
 * --------------
 * The harness ships two projects (chromium, mobile-chromium) sharing
 * a single `sourcebans_e2e` DB. A flow spec that mutates the bans
 * table races with itself if both projects run it concurrently
 * (truncate from project B wipes project A's add-ban half-way through
 * the flow). The mobile viewport doesn't add coverage for an
 * admin-only flow that's already exercised against the same chrome
 * markup at desktop width — Slice 7's responsive specs lock in the
 * mobile chrome separately. So we skip mobile-chromium here and run
 * the lifecycle exclusively on desktop.
 *
 * Selectors
 * ---------
 * Per #1123's "Testability hooks" rule, every interactive surface is
 * addressed via `data-testid` (or ARIA where the chrome already
 * exposes it). Visible text is only used as a `hasText` filter when
 * a primary attribute selector matches more than one node (e.g. the
 * single success toast among siblings sharing `data-kind`). No CSS
 * class chains, no `setTimeout` waits, no fallback to brittle role
 * + text combos.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const ADD_BAN_ROUTE = '/index.php?p=admin&c=bans';
const BAN_LIST_ROUTE = '/index.php?p=banlist';

const FIXTURE = {
    steam: 'STEAM_0:1:1234567',
    nickname: 'e2e-banned-player',
    initialReason: 'e2e: admin lifecycle test',
    editedReason: 'e2e: edited reason',
};

test.describe('flow: admin ban lifecycle', () => {
    // Mobile viewport doesn't add coverage for the admin-only flow
    // and would race the desktop project on the shared
    // `sourcebans_e2e` DB (truncateE2eDb in beforeEach is global, no
    // worker-scoped locking yet — see file-level "Project gating"
    // comment).
    test.skip(({ isMobile }) => isMobile, 'flow spec runs only on desktop chromium');

    // truncateE2eDb is the cheap reset (no DROP+CREATE); see
    // fixtures/db.ts. Per-test reset is required because the flow
    // mutates `:prefix_bans` heavily (insert -> update -> unban) and
    // a stale row from a prior run would either collide on the
    // duplicate-SteamID check in api_bans_add or bend assertions.
    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('add → list → edit → unban → confirm unbanned', async ({ page }) => {
        // ---- 1. Add ban ---------------------------------------------------
        // The "Add a ban" form is its own Pattern A section after
        // #1275 (`?p=admin&c=bans&section=add-ban`). Pre-#1239 the
        // page emitted a broken `<button onclick="openTab(...)">`
        // strip and stacked every pane below it (the JS handler was
        // dropped with sourcebans.js at #1123 D1); #1239 repaired
        // the navigation by riding the page-level ToC pattern
        // (admin-admins family); #1275 collapsed onto Pattern A so
        // each pane (Add a ban / Ban protests / Ban submissions /
        // Import bans / Group ban) is now its own URL with its own
        // server render — no shared scroll DOM. The default landing
        // (?p=admin&c=bans with no section) also routes to add-ban
        // (it's the first accessible section), so this spec's
        // ADD_BAN_ROUTE stays correct without a per-test rewrite.
        // Form fields are anchored on `addban-*` testids; the submit
        // button uses both `data-testid` and `data-action` because
        // the inline handler delegates on `[data-action="addban-submit"]`.
        await page.goto(ADD_BAN_ROUTE);
        const form = page.locator('[data-testid="addban-form"]');
        await expect(form).toBeVisible();

        await form.locator('[data-testid="addban-nickname"]').fill(FIXTURE.nickname);
        await form.locator('[data-testid="addban-steam"]').fill(FIXTURE.steam);
        // Default `addban-type` is "Steam ID" (value="0") and default
        // `addban-length` is "Permanent" (value="0"); both are the
        // first <option selected> in the markup so leaving them
        // untouched is intentional and avoids a drift-prone explicit
        // selectOption call.
        await form.locator('[data-testid="addban-reason"]').selectOption({ value: 'other' });
        await form.locator('[data-testid="addban-reason-custom"]').fill(FIXTURE.initialReason);

        await form.locator('[data-testid="addban-submit"]').click();

        // The inline handler fires sb.api.call(Actions.BansAdd) and
        // shows a `success` toast on the resolution path. The toast
        // matrix is:
        //   - api success + kickit enabled (default) + ShowKickBox
        //     undefined (sourcebans.js gone) → falls through to the
        //     'Ban added' toast (no message envelope).
        //   - api success + kickit disabled → message envelope
        //     surfaces the same 'Ban Added' title.
        // We anchor on `data-kind="success"` (the deterministic
        // attribute set by theme.js's showToast) and disambiguate
        // via the case-insensitive title the matrix produces.
        const successToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /ban added/i });
        await expect(successToast).toBeVisible();

        // ---- 2. List: find the new ban row -------------------------------
        // The ban list lives at ?p=banlist (the marquee page rendered
        // by page_bans.tpl in the v2.0.0 default theme). Admin row
        // actions (edit / unban) are gated on per-row booleans
        // computed from the admin's permission flags; the seeded
        // admin holds OWNER, so both actions are always present.
        await page.goto(BAN_LIST_ROUTE);

        // The row carries `data-id="{$ban.bid}"` and
        // `data-state="{$ban.state}"`. We anchor on the SteamID first
        // (unique because truncateE2eDb wiped the table just above)
        // then read `data-id` for follow-up navigations. `bid` is a
        // stable integer surfaced by the tpl precisely so tests
        // don't have to scrape the human-readable timestamp.
        const banRow = page
            .locator('[data-testid="ban-row"]')
            .filter({ hasText: FIXTURE.steam });
        await expect(banRow).toBeVisible();

        const bidAttr = await banRow.getAttribute('data-id');
        expect(bidAttr, 'ban row must expose a stable data-id (per #1123)').toBeTruthy();
        const bid = Number(bidAttr);
        expect(Number.isFinite(bid) && bid > 0).toBe(true);

        await expect(banRow).toContainText(FIXTURE.nickname);
        await expect(banRow).toContainText(FIXTURE.initialReason);
        await expect(banRow).toHaveAttribute('data-state', 'permanent');

        // ---- 3. Public list: same row visible -----------------------------
        // The same `?p=banlist` URL serves both the admin and
        // public-facing list (admin row actions are the only
        // delta). The "public list" assertion locks in that the new
        // ban surfaces to anonymous viewers too — at the row level,
        // not just inside the admin chrome. Re-navigating from the
        // admin context is sufficient: the rows are rendered server-
        // side from `:prefix_bans` regardless of the viewer's perms,
        // and the rendering pipeline doesn't filter by role for the
        // row body. (Slice 1 ships a logged-out page object;
        // duplicating that here would spawn a second context for no
        // marginal coverage, so we just re-read the same page.)
        const publicRow = page
            .locator(`[data-testid="ban-row"][data-id="${bid}"]`);
        await expect(publicRow).toBeVisible();
        await expect(publicRow).toContainText(FIXTURE.steam);

        // ---- 4. Edit: navigate via the row's edit anchor ------------------
        // The row's edit testid is a server-rendered <a> with the
        // session's per-admin postkey embedded in the URL. Clicking
        // it navigates to admin.edit.ban.php which renders the form
        // pre-filled from `:prefix_bans`.
        const editAnchor = publicRow.locator('[data-testid="row-action-edit"]');
        await expect(editAnchor).toBeVisible();

        // `Promise.all([waitForURL, click])` is the canonical
        // Playwright recipe for "click triggers navigation"; the
        // testid path is `?p=admin&c=bans&o=edit&id=<bid>&key=<...>`
        // so we anchor the URL match on the stable `o=edit` + `id=`
        // pair.
        await Promise.all([
            page.waitForURL(new RegExp(`o=edit&id=${bid}\\b`)),
            editAnchor.click(),
        ]);

        const editForm = page.locator('[data-testid="editban-form"]');
        await expect(editForm).toBeVisible();
        await expect(editForm.locator('[data-testid="editban-name"]')).toHaveValue(
            FIXTURE.nickname,
        );

        // The "other reason" branch toggles `#dreason` visible and
        // pre-fills `#txtReason`; we mirror the same toggle by
        // selecting Other and typing the new reason. The handler's
        // tail script wires `selectLengthTypeReason` to drive the
        // <select> but the `change` event dispatch from
        // selectOption() flips `#dreason` regardless.
        await editForm
            .locator('[data-testid="editban-reason"]')
            .selectOption({ value: 'other' });
        await editForm.locator('[data-testid="editban-reason-custom"]').fill(FIXTURE.editedReason);

        // The handler emits an inline `<script>` on success that
        // shows a `Ban updated` toast then redirects to ?p=banlist
        // after a 1.5s setTimeout. We don't wait on the redirect
        // (that's the in-page setTimeout, not a real user-action
        // terminal state); we wait on the toast, which lands
        // synchronously after the POST resolves. The form is
        // wrapped in `<form action="" method="post">`, so a click
        // on the submit button does a full page navigation — the
        // toast renders on the *redirected* page only when the
        // server fires its own client-side script. To cover both
        // paths deterministically we wait for the success toast
        // AND/OR the final URL.
        await editForm.locator('[data-testid="editban-submit"]').click();

        const updateToast = page
            .locator('.toast[data-kind="success"]')
            .filter({ hasText: /ban updated/i });
        await expect(updateToast).toBeVisible();
        // The handler's tail script schedules a window.location.href
        // = '?p=banlist'; rather than waitForTimeout, we wait on the
        // URL change deterministically — Playwright resolves
        // waitForURL the moment the location flips, no timer.
        await page.waitForURL(/\?p=banlist(?:&|$)/);

        // ---- 5. Confirm edited reason renders in the list -----------------
        const editedRow = page
            .locator(`[data-testid="ban-row"][data-id="${bid}"]`);
        await expect(editedRow).toBeVisible();
        await expect(editedRow).toContainText(FIXTURE.editedReason);
        await expect(editedRow).not.toContainText(FIXTURE.initialReason);

        // ---- 6. Unban: click the row's unban anchor -----------------------
        // The unban testid is a server-rendered <a> pointing at
        // `?p=banlist&a=unban&id=<bid>&key=<postkey>`. Per
        // page.banlist.php, GET to that URL flips the row's
        // `RemoveType` to 'U' and renders the same banlist page —
        // so the row's `data-state` switches to "unbanned" on the
        // post-unban render. There is no client-side modal in the
        // current chrome (the legacy `UnbanBan('…')` confirm() lived
        // in sourcebans.js, gone since v2.0.0); the click is a
        // direct GET.
        const unbanAnchor = editedRow.locator('[data-testid="row-action-unban"]');
        await expect(unbanAnchor).toBeVisible();
        await Promise.all([
            page.waitForURL(/[?&]a=unban\b/),
            unbanAnchor.click(),
        ]);

        // ---- 7. Confirm unbanned state in admin + public views ------------
        // The post-unban page is the same `?p=banlist` rendered with
        // the row's new state. The pill text is `Unbanned` (capital-
        // ised from the lowercase state string by the |capitalize
        // modifier in the tpl), and the row's `data-state` attribute
        // is the deterministic equivalent we assert on.
        const unbannedRow = page
            .locator(`[data-testid="ban-row"][data-id="${bid}"]`);
        await expect(unbannedRow).toBeVisible();
        await expect(unbannedRow).toHaveAttribute('data-state', 'unbanned');
        await expect(unbannedRow.locator('.pill')).toHaveText(/unbanned/i);

        // The unban-action affordance disappears after the row is
        // unbanned (the tpl gates `row-action-unban` on
        // `state != 'unbanned' && state != 'expired'`); locking that
        // in catches a regression where a re-unban would silently
        // double-write the RemoveType column.
        await expect(unbannedRow.locator('[data-testid="row-action-unban"]'))
            .toHaveCount(0);

        // ---- 8. Public list still shows the unbanned row ------------------
        // Default banlist filter is "show all" (no `hideinactive` in
        // the session); the unbanned row therefore stays visible to
        // anonymous viewers. Re-navigating to the same URL from the
        // admin context is sufficient — the rendering doesn't filter
        // by role for the row body (see step 3 comment).
        await page.goto(BAN_LIST_ROUTE);
        const publicAfterUnban = page
            .locator(`[data-testid="ban-row"][data-id="${bid}"]`);
        await expect(publicAfterUnban).toBeVisible();
        await expect(publicAfterUnban).toHaveAttribute('data-state', 'unbanned');
    });
});
