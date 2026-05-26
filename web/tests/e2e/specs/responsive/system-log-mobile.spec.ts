// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/**
 * Responsive: admin System Log sub-tab (#1462).
 *
 * iPhone-13 viewport contract for
 * `?p=admin&c=settings&section=logs`:
 *
 *   - The desktop `<table class="table table--clickable-rows"
 *     data-testid="logs-table">` collapses behind the global
 *     `.table { display: none }` rule at `<=768px` (the same rule
 *     that pairs with `.ban-cards` on the bans / comms lists). Pre-
 *     #1462 the System Log shipped NO paired mobile surface, so the
 *     chrome rendered (heading, "Clear log" button, pagination
 *     footer) while the rows themselves were silently hidden — the
 *     reporter's "no logs visible" symptom from the iPhone view.
 *   - The fix added a paired `<ul class="log-cards"
 *     data-testid="logs-cards">` that mirrors `$log_items` as native
 *     `<details>` disclosures. The `<details>` semantic carries
 *     keyboard reachability + screen-reader announcement out of
 *     the box; no `role="button"` / `tabindex="0"` / `onkeydown`
 *     handler dance is needed (the desktop table's a11y triple is
 *     unavoidable because `<tr>` has no native disclosure semantic,
 *     but the mobile surface gets to use the right tool for the job).
 *   - Both surfaces iterate the SAME `$log_items` (pinned by the
 *     companion `SystemLogMobileCardsRegressionTest`), so the
 *     visible row set is viewport-symmetric — the reporter's bug
 *     class ("mobile users see a different set of logs than
 *     desktop users") cannot recur.
 *
 * Why the test seeds via a dedicated SQL shim:
 *
 *   - `:prefix_log` is empty in the bare e2e seed — `data.sql`
 *     doesn't ship audit rows, and `Fixture::truncateAndReseed`
 *     truncates every table on every reset.
 *   - There is no JSON action that emits audit rows directly; they
 *     are side effects of authenticated panel writes. Driving e.g.
 *     `Actions.BansAdd` to produce a row would couple this spec to
 *     the bans-add audit message (refactor that message and the
 *     System Log mobile spec fails for the wrong reason).
 *   - The direct INSERT is the narrow shape the spec needs and
 *     mirrors `seed-comms-e2e.php`'s contract (silence-row rows
 *     `Actions.CommsAdd` never emits).
 *
 * Project gating: mobile-chromium only — the chrome behaviour is
 * viewport-keyed and there is no chromium-desktop assertion here.
 * The desktop surface is exercised by the screenshot gallery and
 * the integration test pins the structural contract on both sides.
 */

import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { expect, test } from '../../fixtures/auth.ts';
import {
    seedSystemLogE2e,
    truncateE2eDb,
    type SystemLogSeedRow,
} from '../../fixtures/db.ts';

const SYSTEM_LOG_URL = '/index.php?p=admin&c=settings&section=logs';

/**
 * The seeded log-row primary keys are captured in `beforeEach` so the
 * per-test locators key off `[data-id="<lid>"]` instead of `hasText:
 * <title>` substring matches. `hasText` is fuzzy — a substring match
 * against the title could pick up unrelated audit rows the admin
 * login round-trip emits or any future panel write that happens to
 * include the same prefix in its message. The template emits
 * `data-id="{$log.lid}"` on every `<details>` precisely so specs can
 * key off the primary key; the seeder returns the inserted `lid`s in
 * insert order so we never have to guess which seeded card is which.
 */
let seededLids: number[] = [];

/**
 * Three rows, one per `:prefix_log.type` letter, so the spec also
 * proves all three pill variants paint correctly in the card
 * summary. The titles double as fingerprints — a single
 * `hasText: SEED_ROWS[0].title` filter inside `[data-testid="log-card"]`
 * is enough to disambiguate the seeded rows from any stray entries.
 */
const SEED_ROWS: Required<Pick<SystemLogSeedRow, 'type' | 'title' | 'message' | 'function' | 'query' | 'host'>>[] = [
    {
        type: 'm',
        title: 'e2e #1462: info-row',
        message: 'mobile-spec info detail',
        function: 'tests/e2e/system-log-mobile',
        query: 'SELECT 1',
        host: '203.0.113.10',
    },
    {
        type: 'w',
        title: 'e2e #1462: warn-row',
        message: 'mobile-spec warning detail',
        function: 'tests/e2e/system-log-mobile',
        query: '',
        host: '203.0.113.11',
    },
    {
        type: 'e',
        title: 'e2e #1462: error-row',
        message: 'mobile-spec error detail',
        function: 'tests/e2e/system-log-mobile',
        query: '',
        host: '203.0.113.12',
    },
];

test.describe('responsive: admin System Log (#1462)', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — the desktop chrome is the legacy table; the cards are the new mobile mirror.',
        );
    });

    // Tests in this file all `truncateE2eDb()` + `seedSystemLogE2e`
    // in `beforeEach`. Without `serial` mode Playwright runs them in
    // parallel workers locally (CI pins `workers: 1`, see
    // playwright.config.ts), and worker B's truncate wipes worker
    // A's seeded rows mid-test → the page handler's `HasAccess`
    // gate fails on the briefly-missing `:prefix_admins` row during
    // the reseed window, the request redirects to login, and the
    // `[data-testid="logs-cards"]` locator times out. The named-
    // lock in `Sbpp\Tests\Fixture::truncateAndReseed` makes the
    // *reset* atomic per-process, but it does not span the gap
    // between the truncate-and-reseed and the per-test
    // seed/assertion phase; serial execution is the right
    // granularity for state-mutating flow specs. Same pattern as
    // `comms-affordances.spec.ts` and `server-cards.spec.ts`.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async () => {
        await truncateE2eDb();
        seededLids = await seedSystemLogE2e(SEED_ROWS);
        expect(
            seededLids,
            'seedSystemLogE2e must return one lid per seeded row so per-card locators can anchor on `[data-id="<lid>"]`',
        ).toHaveLength(SEED_ROWS.length);
    });

    test('desktop table is hidden and the mobile cards mirror the seeded rows', async ({ page }) => {
        await page.goto(SYSTEM_LOG_URL);

        const desktopTable = page.locator('[data-testid="logs-table"]');
        const cardsList = page.locator('[data-testid="logs-cards"]');

        // theme.css L1802: `.table { display: none }` at `<=768px`;
        // the matching `.log-cards { display: block }` rule next to it
        // flips the mobile surface on. The pre-#1462 regression was
        // "table hidden, cards don't exist, mobile users see nothing".
        await expect(desktopTable).toBeHidden();
        await expect(cardsList).toBeVisible();

        // Every seeded row must surface as a card. Anchor on
        // `[data-id="<lid>"]` (NOT `hasText: <title>`) — the lid is
        // the primary key, the title is mutable copy. A future panel
        // tweak that adds an audit row whose title starts with
        // "e2e #1462" (or vice versa) would silently break a
        // hasText-anchored locator; the lid is the structural
        // contract the integration test pins (`data-id="{$log.lid}"`
        // on every `<details>`).
        for (let i = 0; i < SEED_ROWS.length; i++) {
            const row = SEED_ROWS[i];
            const lid = seededLids[i];
            const card = cardsList.locator(`[data-testid="log-card"][data-id="${lid}"]`);
            await expect(card, `seeded lid ${lid} ("${row.title}") must paint as a mobile card`).toHaveCount(1);
            await expect(card).toBeVisible();
            // The title text still has to surface in the summary —
            // we just don't ANCHOR the locator on it. This catches
            // the inverse regression where the lid hook is present
            // but the title binding broke.
            await expect(card.locator('.log-card__title')).toContainText(row.title);
        }
    });

    test('mobile card expands to reveal the detail body via native <details>', async ({ page }) => {
        await page.goto(SYSTEM_LOG_URL);

        const cardsList = page.locator('[data-testid="logs-cards"]');
        await expect(cardsList).toBeVisible();

        // Anchor on the warn-row card via the lid captured in
        // beforeEach. Pre-toggle the `<details>` has no `open`
        // attribute — the disclosure body is collapsed and the
        // message detail is not visible. Click the summary to
        // expand; assert the `open` attribute lands AND the detail
        // text becomes visible. This is the "no JS required" contract
        // — the native `<summary>` click handler does the toggle for
        // us.
        const target = SEED_ROWS[1]; // warn-row
        const targetLid = seededLids[1];
        const card = cardsList.locator(`[data-testid="log-card"][data-id="${targetLid}"]`);
        await expect(card).toBeVisible();
        await expect(card).not.toHaveAttribute('open', /.*/);

        // The detail body is in the DOM but hidden by `<details>`'s
        // native collapsed state. Playwright's `toBeVisible` returns
        // false on the inner `<dl>` until the parent is `[open]`.
        const detailDd = card.locator('dd', { hasText: target.message });
        await expect(detailDd).toBeHidden();

        const summary = card.locator('summary.log-card__summary');
        await summary.click();

        await expect(card).toHaveAttribute('open', /.*/);
        await expect(detailDd).toBeVisible();

        // Toggle back closed so the spec leaves the page in its
        // initial state — keeps the trace artefact readable and
        // doesn't fight any future test that opens a different row.
        await summary.click();
        await expect(card).not.toHaveAttribute('open', /.*/);
        await expect(detailDd).toBeHidden();
    });

    test('mobile card opens via keyboard (Tab → Enter / Space on the native <summary>)', async ({ page }) => {
        // The whole justification for picking native `<details>` /
        // `<summary>` over a `<div role="button">` (per the docblock
        // on `SystemLogMobileCardsRegressionTest`) is keyboard
        // affordance for free — Tab lands on the summary, Enter /
        // Space toggles it, no inline `onkeydown` dance needed. The
        // pointer-event test above proves the click path; this test
        // proves the keyboard path, so a future stylesheet pass
        // that drops `outline: none` without a paired focus ring,
        // or a `tabindex="-1"` slipped onto the summary by a
        // well-meaning "remove visual noise" PR, fails here instead
        // of in a screen-reader user's lap.
        await page.goto(SYSTEM_LOG_URL);

        const cardsList = page.locator('[data-testid="logs-cards"]');
        await expect(cardsList).toBeVisible();

        const target = SEED_ROWS[0]; // info-row
        const targetLid = seededLids[0];
        const card = cardsList.locator(`[data-testid="log-card"][data-id="${targetLid}"]`);
        const summary = card.locator('summary.log-card__summary');
        const detailDd = card.locator('dd', { hasText: target.message });

        // Focus the summary directly. We deliberately do NOT chain
        // Tab-presses from `<body>` because the per-page Tab order
        // depends on the topbar chrome (search button, theme toggle,
        // sidebar links, …) and would silently break this spec
        // whenever the chrome gains or loses a tab stop. Focusing
        // the summary then asserting it accepted focus is the
        // load-bearing contract (Tab-reachability ⇔ focusability).
        await summary.focus();
        await expect(summary).toBeFocused();

        // Enter activates the disclosure.
        await expect(card).not.toHaveAttribute('open', /.*/);
        await page.keyboard.press('Enter');
        await expect(card).toHaveAttribute('open', /.*/);
        await expect(detailDd).toBeVisible();

        // Space also activates (toggles back closed) — the W3C
        // disclosure widget pattern accepts both keys.
        await page.keyboard.press(' ');
        await expect(card).not.toHaveAttribute('open', /.*/);
        await expect(detailDd).toBeHidden();
    });

    test('expanded mobile card surface has zero critical a11y violations', async ({ page }, testInfo) => {
        // axe-core sweep against the new mobile chrome. The default
        // `?p=admin&c=settings` axe sweep in `specs/a11y/routes.spec.ts`
        // covers the settings landing page only AND is gated to
        // desktop-chromium — so without this test the System Log
        // mobile cards would never get an axe pass. Run with one
        // card expanded so the inner `<dl>` body's content is in
        // scope alongside the closed `<details>` siblings; that's
        // the failure shape a `nested-interactive` or
        // `aria-allowed-attr` violation would manifest in (a future
        // copy-paste that nests a `<button>` inside the `<summary>`
        // or a `<details>` with a forbidden ARIA attribute).
        await page.goto(SYSTEM_LOG_URL);

        const cardsList = page.locator('[data-testid="logs-cards"]');
        await expect(cardsList).toBeVisible();

        // Expand the info-row card so axe scans the open body too.
        const targetLid = seededLids[0];
        const card = cardsList.locator(`[data-testid="log-card"][data-id="${targetLid}"]`);
        await card.locator('summary.log-card__summary').click();
        await expect(card).toHaveAttribute('open', /.*/);

        await expectNoCriticalA11y(page, testInfo);
    });

    test('page chrome does not overflow the iPhone-13 viewport', async ({ page }) => {
        await page.goto(SYSTEM_LOG_URL);

        // Page-level: nothing in the chrome leaks past the viewport
        // horizontally. The desktop table is hidden but if the
        // mobile cards were ever to ship with an off-screen element
        // (a too-wide pill, a long unbroken `query` string in the
        // detail body), this would catch it. Matches the banlist
        // responsive spec's contract — `documentElement.scrollWidth
        // <= clientWidth + 1`.
        await expect.poll(() => page.evaluate(() =>
            document.documentElement.scrollWidth - document.documentElement.clientWidth,
        )).toBeLessThanOrEqual(1);

        // The cards list itself stays within the viewport. The
        // bounding-box check guards against the failure mode where
        // the list silently lives at x=2000px off-screen (which
        // would let the viewport-level scroll check pass while the
        // user still sees nothing).
        const cardsList = page.locator('[data-testid="logs-cards"]');
        const vw = page.viewportSize()?.width ?? 0;
        const box = await cardsList.boundingBox();
        expect(box, 'mobile cards list must render a bounding box').not.toBeNull();
        expect(box!.x).toBeGreaterThanOrEqual(-1);
        expect(box!.x + box!.width).toBeLessThanOrEqual(vw + 1);
    });
});
