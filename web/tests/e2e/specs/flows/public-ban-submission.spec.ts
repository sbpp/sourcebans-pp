/**
 * Public ban-submission lifecycle (#1124 Slice 3, "flow-public-submission").
 *
 *   1. Anonymous visitor submits a report via /index.php?p=submit.
 *   2. Admin sees the submission in the moderation queue at
 *      /index.php?p=admin&c=bans.
 *   3. Admin clicks the row's "Ban" action → BansSetupBan prefills
 *      the Add Ban form above the queue. Admin picks a reason and
 *      clicks the submit button → BansAdd inserts the ban and the
 *      handler flips every submission for that SteamID to
 *      `archiv = '3'` (so the row leaves the queue).
 *   4. The ban shows up on the public /index.php?p=banlist as a row
 *      identified by the SteamID.
 *
 * The shared interactions (filling the public form, finding the
 * queue row, approving via the Add Ban form, locating the new ban
 * in the public list) live in `pages/SubmitBanFlow.ts` so the
 * `flow-public-submission` screenshot block can replay the same
 * three pages deterministically without forking the contract.
 *
 * Selector discipline (#1123 testability hooks)
 * ---------------------------------------------
 *   - Every primary selector is a `data-testid` from the locked hooks
 *     in page_submitban.tpl, page_admin_bans_submissions.tpl,
 *     page_admin_bans_add.tpl, page_bans.tpl. `hasText` filters
 *     disambiguate when more than one node matches (e.g. the success
 *     toast shares its container with future warning toasts; the
 *     queue row's outer `<details>` body would re-match the SteamID).
 *   - Waits anchor on terminal attributes / locator state, not
 *     `setTimeout` / `waitForTimeout` / `networkidle`. See helper
 *     docs in SubmitBanFlow.ts for the rationale on each anchor.
 *
 * Documented divergence from the issue's literal text
 * ---------------------------------------------------
 *   - The issue describes the approve action as `row-action-approve`,
 *     but the actual hook in page_admin_bans_submissions.tpl is
 *     `row-action-ban` — clicking it loads the submission's data
 *     into the Add Ban form (via `Actions.BansSetupBan`), and the
 *     admin then submits that form (via `Actions.BansAdd` with
 *     `fromsub=<subid>`) to actually insert the ban + flip the
 *     submission to `archiv=3` ("player has been banned"). This spec
 *     drives that real flow end-to-end via the UI; it does not
 *     introduce a new `row-action-approve` testid.
 *
 *   - The page handler `web/pages/page.submit.php` still emits
 *     `print "<script>ShowBox('Successful', …)</script>"` on success,
 *     but `ShowBox` was deleted in #1123 D1 (it lived in
 *     `web/scripts/sourcebans.js`). The script tag therefore fires a
 *     ReferenceError and no visible "thank you" toast lands in the
 *     sbpp2026 chrome. The deterministic terminal state we anchor on
 *     instead is the form-reset behaviour of page.submit.php (every
 *     captured POST value is set to `""` before re-rendering on
 *     success), so the BanReason textarea round-trips to empty. We
 *     flag this in the PR body so the missing visible feedback gets
 *     a follow-up; it doesn't block this slice's contract.
 *
 *   - The inline ban-from-submission handler in
 *     `page_admin_bans_submissions.tpl` originally referenced the
 *     legacy `window.applyBanFields` helper (deleted with
 *     sourcebans.js); the actual sbpp2026 helper is
 *     `window.__sbppApplyBanFields` (defined in admin.bans.php's
 *     tail script). The Ban-from-submission button was therefore a
 *     no-op in this theme. This slice fixes the reference (one tpl
 *     edit) so the UI flow the spec drives is the same flow a real
 *     admin lives in. Without that fix the BansSetupBan response
 *     would round-trip but the Add Ban form above the queue would
 *     stay empty, and the spec would have to reach past the buggy
 *     UI by typing the steam/nickname/reason itself — which is
 *     exactly the kind of "drive the API, not the UI" anti-pattern
 *     this slice exists to avoid.
 *
 * Cross-project parallelism
 * -------------------------
 * The flow mutates `:prefix_submissions` and `:prefix_bans`, so two
 * Playwright projects (chromium + mobile-chromium) running this spec
 * in parallel would race on `(SteamId)` uniqueness and trip BansAdd's
 * `already_banned` guard. We pin the spec to the desktop chrome via
 * `test.skip(({ isMobile }) => isMobile, …)` — the desktop path
 * covers the ban-state contract; the mobile chrome is exercised by
 * the screenshot block, which uses unique fixture data per
 * (theme × project) for the same reason.
 *
 * Why no `truncateE2eDb()` here
 * -----------------------------
 * The fixture's `:prefix_admins` row is the seeded `admin/admin`
 * account; `Sbpp\Tests\Fixture::truncateOnly()` walks every table
 * and re-seeds the admin at the end. There's a small window between
 * `TRUNCATE :prefix_admins` and the re-INSERT where the admin doesn't
 * exist, and any spec running in parallel that authenticates against
 * the panel (currently `smoke/login.spec.ts`) sees the seam and
 * fails its login. The Slice 0 PR (#1162) flagged this as the
 * "worker-scoped serialisation strategy" follow-up. Until that
 * helper lands, we get hermeticity from a per-test-run unique
 * SteamID — the panel only rejects re-submitting the *same* SteamID
 * with `already_banned`, so a unique seed sidesteps the guard
 * without touching the admin row at all. The cost is leftover rows
 * in `:prefix_bans` / `:prefix_submissions` between specs — fine
 * for the e2e DB (`globalSetup` does a full install at the start of
 * each `playwright test` invocation).
 */

import { expect, test } from '../../fixtures/auth.ts';
import {
    adminApprove,
    adminSubmissionRow,
    anonymousSubmit,
    banlistRow,
    newAnonymousContext,
    type SubmissionFixture,
} from '../../pages/SubmitBanFlow.ts';

test.describe('flow: public ban submission -> admin approve -> public list', () => {
    test.skip(
        ({ isMobile }) => Boolean(isMobile),
        'flow runs against the desktop chrome; mobile chrome is exercised by the @screenshot block',
    );

    test('anonymous submit -> admin approve -> ban shown publicly', async ({ page, browser }, testInfo) => {
        // Per-run unique SteamID. Date.now() gives ms-precision (the
        // gate spec runs in a single worker per project; collisions
        // would require <1ms apart which the truncate-free design
        // makes impossible) and stays inside Steam2's
        // `STEAM_0:Y:Z` shape (Y ∈ {0,1}; Z is any non-negative int).
        // testInfo.workerIndex disambiguates if Playwright ever
        // schedules two instances of this spec on different workers
        // (currently impossible — chromium pins to one worker by
        // default — but cheap insurance).
        const seed = `${Date.now()}${testInfo.workerIndex}`;
        const FIXTURE: SubmissionFixture = {
            steam: `STEAM_0:1:${seed}`,
            playerName: `e2e-submitter-${seed}`,
            reason: `e2e: public submission flow ${seed}`,
            reporterName: 'e2e-reporter',
            email: 'e2e-reporter@example.test',
        };

        // Stage 1 — anonymous visitor submits the form. We mint a
        // dedicated logged-out context so the public page never sees
        // the admin storage state Slice 0's global-setup baked into
        // the project default. `testInfo.project.use` carries the
        // device descriptor (viewport, user-agent, reduced-motion);
        // dropping `storageState` is the only delta from the project
        // default.
        const anonContext = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const anonPage = await anonContext.newPage();
            await anonymousSubmit(anonPage, FIXTURE);
        } finally {
            await anonContext.close();
        }

        // Stage 2 — switch to the admin context (the default `page`
        // fixture inherits playwright/.auth/admin.json minted by
        // global-setup). Verify the submission is queued, identified
        // by the SteamID we submitted. The row-level
        // `data-testid="submission-row"` is unique-per-DB-id, so we
        // anchor through the inner `submission-row-steam` cell to
        // disambiguate the unique seeded SteamID from any leftover
        // rows previous specs in this `playwright test` invocation
        // wrote (we don't truncate between tests — see file-level
        // "Why no truncateE2eDb()" comment).
        await page.goto('/index.php?p=admin&c=bans');
        const queueRow = adminSubmissionRow(page, FIXTURE.steam);
        await expect(queueRow).toBeVisible();
        await expect(queueRow.locator('[data-testid="submission-row-name"]')).toContainText(
            FIXTURE.playerName,
        );

        // Stage 3 — approve via the real "Ban" → fill Add Ban → submit
        // path. Helper waits on the success toast (the BansAdd
        // .then() emits `.toast[data-kind="success"]` synchronously)
        // before returning.
        await adminApprove(page, FIXTURE);

        // Stage 4 — the ban appears on the public ban list. We use
        // the same admin-authenticated `page` here because the public
        // banlist renders the same `data-testid="ban-row"` regardless
        // of viewer privilege; admin-only row-actions are gated
        // separately in the row body and don't affect identity /
        // SteamID rendering.
        await page.goto('/index.php?p=banlist');
        const newBan = banlistRow(page, FIXTURE.steam);
        await expect(newBan).toBeVisible();
        await expect(newBan).toContainText(FIXTURE.playerName);
    });
});
