/**
 * Shared helpers for the public ban-submission flow (#1124 Slice 3).
 *
 * The spec at `specs/flows/public-ban-submission.spec.ts` and the
 * `flow-public-submission` screenshot block in
 * `specs/_screenshots.spec.ts` both walk the same three pages —
 * public `/submit`, admin moderation queue, public `/banlist` — and
 * assert the same testability hooks. Lifting the page interactions
 * here keeps the contract in one place and lets the screenshot block
 * prep DB state through the real UI rather than a server-side shim.
 *
 * Selectors are pinned to #1123's testability hooks:
 *
 *   - `data-testid="submitban-*"` on the public form (page_submitban.tpl).
 *   - `data-testid="submission-row"` / `submission-row-steam` /
 *     `submission-row-name` / `row-action-ban` in the admin queue
 *     (page_admin_bans_submissions.tpl).
 *   - `data-testid="addban-*"` on the Add Ban form
 *     (page_admin_bans_add.tpl) — the moderation flow opens this form
 *     pre-populated via `Actions.BansSetupBan`.
 *   - `data-testid="ban-row"` on the public banlist (page_bans.tpl).
 *
 * All waits land on terminal attributes (form-state changes, toast
 * appearance, locator visibility) — never on `setTimeout` / network
 * idle. See AGENTS.md "Playwright E2E specifics" for the rule.
 */

import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test';

/**
 * Deterministic fixture for one trip through the flow. Every spec
 * that reuses these helpers passes a fresh fixture so re-runs against
 * a non-truncated DB still don't trip the page handler's
 * "already_banned" guard on the second pass.
 */
export interface SubmissionFixture {
    /** Steam2 ID — must satisfy SteamID::isValidID (STEAM_X:Y:Z). */
    readonly steam: string;
    /** Player nickname; required by page.submit.php. */
    readonly playerName: string;
    /** Free-text comments / reason; required by page.submit.php. */
    readonly reason: string;
    /** Submitter (reporter) display name. */
    readonly reporterName: string;
    /** Submitter email; required by FILTER_VALIDATE_EMAIL. */
    readonly email: string;
}

/**
 * Drive the public "Submit a ban request" form against a logged-out
 * page. Fills every required field, picks the "Other server" option
 * (value="0") so the server-side validator passes without a seeded
 * `:prefix_servers` row, submits, and asserts the success-side-effect:
 * the form re-renders with a cleared `BanReason` textarea.
 *
 * Why the textarea is the success anchor: page.submit.php resets
 * every captured POST value to `""` before re-rendering SubmitBanView
 * on success — so the Reason field round-trips to empty. A validation
 * bounce keeps the original text in place; that's the distinguishing
 * signal we anchor on. We deliberately avoid the SteamID input
 * because page.submit.php substitutes the literal `"STEAM_0:"` for an
 * empty SteamID, which collides with the initial-load default and
 * wouldn't flip on a round-trip.
 *
 * The form is `<form method="post" action="index.php?p=submit">` so
 * Submit triggers a full-page POST + reload. We wait on
 * `domcontentloaded` (the rerender is a server round-trip, not an
 * XHR) before asserting.
 */
export async function anonymousSubmit(page: Page, fx: SubmissionFixture): Promise<void> {
    await page.goto('/index.php?p=submit');

    await page.locator('[data-testid="submitban-steam"]').fill(fx.steam);
    await page.locator('[data-testid="submitban-name"]').fill(fx.playerName);
    await page.locator('[data-testid="submitban-reason"]').fill(fx.reason);
    await page.locator('[data-testid="submitban-reporter-name"]').fill(fx.reporterName);
    await page.locator('[data-testid="submitban-reporter-email"]').fill(fx.email);
    // value="0" is the static "Other server / Not listed here" option
    // page_submitban.tpl emits below the (possibly empty) tracked-server
    // list; page.submit.php gates on `SID == -1` so picking 0 keeps
    // us inside the success branch without depending on a seeded
    // :prefix_servers row.
    await page.locator('[data-testid="submitban-server"]').selectOption('0');

    await Promise.all([
        page.waitForLoadState('domcontentloaded'),
        page.locator('[data-testid="submitban-submit"]').click(),
    ]);

    await expect(page.locator('[data-testid="submitban-reason"]')).toHaveValue('');
    await expect(page).toHaveURL(/[?&]p=submit(?:&|$)/);
}

/**
 * Locate a pending submission row in the admin queue by SteamID.
 *
 * The admin bans page renders all sub-tabs as stacked `.tabcontent`
 * sections (no `swapTab` helper in sbpp2026 — sourcebans.js was
 * dropped at #1123 D1), so the queue is scrollable on the same
 * `?p=admin&c=bans` URL — no `&action=…` sub-query is needed.
 *
 * We anchor the filter on `submission-row-steam` (a unique-per-row
 * cell that carries the visible SteamID) rather than on the row's
 * outer `hasText` because the expanded `<details>` body would also
 * match the SteamID and double-count.
 */
export function adminSubmissionRow(page: Page, steam: string): ReturnType<Page['locator']> {
    return page
        .locator('[data-testid="submission-row"]')
        .filter({ has: page.locator('[data-testid="submission-row-steam"]', { hasText: steam }) });
}

/**
 * Approve a submission via the live UI flow:
 *
 *   1. Click `[data-testid="row-action-ban"]` on the row → fires
 *      `Actions.BansSetupBan` and pre-populates the Add Ban form via
 *      `__sbppApplyBanFields` (admin.bans.php tail script).
 *   2. Wait until the Add Ban nickname input reflects the
 *      submission's player name — that's the deterministic terminal
 *      state for "Setup-ban succeeded; form is ready".
 *   3. Pick a reason from the static dropdown. Length defaults to
 *      Permanent (0), already selected.
 *   4. Click `[data-testid="addban-submit"]` → fires `Actions.BansAdd`
 *      with `fromsub=<subid>`, which inserts the ban + flips the
 *      submission to `archiv=3` (the api_bans_add handler updates
 *      every submission with the matching SteamId, so the row leaves
 *      the queue).
 *   5. Wait for the success toast: theme.js's showToast emits a
 *      `.toast[data-kind="success"]` element synchronously inside
 *      the BansAdd `.then()` handler. The visible title is "Ban added"
 *      (the kickit-disabled fallback string from
 *      page_admin_bans_add.tpl — server-side `'message' => null` when
 *      `config.enablekickit=1` and ShowKickBox is undefined in
 *      sbpp2026, so the inline script falls through to the literal
 *      'Ban added' default). We disambiguate via `hasText` while
 *      keeping `[data-kind="success"]` as the primary attribute
 *      selector.
 *
 * `reasonOption` defaults to "Inappropriate Language" because it's a
 * legacy-locked option in the static `<optgroup label="Behavior">`
 * inside page_admin_bans_add.tpl — no DB seed required.
 */
export async function adminApprove(
    page: Page,
    fx: Pick<SubmissionFixture, 'steam' | 'playerName'>,
    opts: { reasonOption?: string } = {},
): Promise<void> {
    const reasonOption = opts.reasonOption ?? 'Inappropriate Language';

    await page.goto('/index.php?p=admin&c=bans');

    const row = adminSubmissionRow(page, fx.steam);
    await expect(row).toBeVisible();

    await row.locator('[data-testid="row-action-ban"]').click();

    // Terminal state for setup-ban: __sbppApplyBanFields writes the
    // submission's name into #nickname after the BansSetupBan promise
    // resolves; that's the deterministic post-setup signal. The Add
    // Ban form lives ABOVE the submissions queue on the same admin
    // page (each tab is rendered as a stacked `.tabcontent` div in
    // sbpp2026 — see "no swapTab" comment above), so the input is
    // already in the DOM when the click fires.
    const nicknameInput = page.locator('[data-testid="addban-nickname"]');
    await expect(nicknameInput).toHaveValue(fx.playerName);

    // listReason starts at "" (the placeholder option) and must be
    // set explicitly; the inline submit handler validates that
    // `reason` is non-empty before dispatching BansAdd.
    await page.locator('[data-testid="addban-reason"]').selectOption(reasonOption);

    await page.locator('[data-testid="addban-submit"]').click();

    const toast = page.locator('.toast[data-kind="success"]').filter({ hasText: 'Ban added' });
    await expect(toast).toBeVisible();
}

/**
 * Locate a public ban-list row by SteamID.
 *
 * page_bans.tpl emits both a desktop `<tr data-testid="ban-row">` and
 * a mobile `<a class="ban-row">` card — only the desktop one carries
 * the `data-testid`, and theme.css hides the table below 769px. At
 * iPhone-13 (390px) the testid'd nodes are present in the DOM but
 * `display:none`; specs that need to assert on the *visible* card
 * view should locate `.ban-cards .ban-row[data-id]` instead. The
 * desktop spec calls `expect(...).toBeVisible()` and is therefore
 * pinned to chromium (see `test.skip(({isMobile})=>...)` at the spec
 * level).
 */
export function banlistRow(page: Page, steam: string): ReturnType<Page['locator']> {
    return page.locator('[data-testid="ban-row"]').filter({ hasText: steam });
}

/**
 * Spin up a logged-out browser context that inherits the project's
 * device descriptor (viewport, user agent, reducedMotion, …) but
 * drops the storage state Slice 0's global-setup minted for
 * `admin/admin`.
 *
 * Test code must always call `await ctx.close()` — Playwright's
 * fixture cleanup only tears down contexts it minted, not contexts
 * the test created via `browser.newContext`.
 */
export async function newAnonymousContext(
    browser: Browser,
    projectUse: Record<string, unknown>,
): Promise<BrowserContext> {
    return browser.newContext({
        ...projectUse,
        storageState: { cookies: [], origins: [] },
    });
}
