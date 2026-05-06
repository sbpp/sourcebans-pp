/**
 * Player drawer (#1124, Slice 6 / #1165).
 *
 * Acceptance criteria from #1124:
 *   "click a row in /bans, drawer opens with player data, tabs
 *    (Overview / History / Comms / Notes) all populate, Esc closes."
 *
 * #1165 landed the four-tab UI (`renderDrawerBody` in
 * `web/themes/default/js/theme.js` is now a `role=tablist` with four
 * `role=tabpanel` panes lazy-loaded via `bans.player_history` /
 * `comms.player_history` / `notes.list`). This spec drives each tab
 * end-to-end:
 *   - Open trigger: `[data-drawer-href*="id=<bid>"]` on the row anchor
 *     (the marquee `page_bans.tpl` uses the legacy-handoff form;
 *     `theme.js#bidFromTrigger` extracts the bid from either
 *     `data-drawer-href`'s `?id=N` or the newer `data-drawer-bid`).
 *   - Open state:  `#drawer-root[data-drawer-open="true"]`.
 *   - Loaded state: `#drawer-root` no longer carries `data-loading="true"`
 *     once `bans.detail` settles.
 *   - Tabs: `[data-testid="drawer-tab-{overview|history|comms|notes}"]`
 *     each control `[data-testid="drawer-panel-..."]`. Overview is
 *     active on first paint (`aria-selected="true"`). Clicking another
 *     tab activates its panel (`hidden=false`) and lazy-loads its
 *     feed. Each panel exposes a `data-testid="drawer-{kind}-heading"`
 *     so we can assert the right pane is in front of the user
 *     without screen-scraping the body.
 *   - Close: Esc OR a click on `[data-drawer-close]`.
 *
 * The Notes tab is admin-only — `bans.detail` returns
 * `notes_visible: true` for admin callers, and the drawer renders the
 * fourth tab only in that case. The default storage state minted by
 * `fixtures/global-setup.ts` logs in as the seeded admin so all four
 * tabs are present.
 */

import { expect, test } from '../../../fixtures/auth.ts';
import { seedBanViaApi } from '../../../fixtures/seeds.ts';

const SEED_NICK_PREFIX = 'e2e-drawer-target';
const SEED_REASON = 'e2e drawer seed reason';

/**
 * Per-subtest authid offset. See command-palette.spec.ts's matching
 * comment for the full rationale; the short version is "no truncate +
 * unique-per-(subtest × project × worker) seeds keeps the parallel
 * chromium / mobile-chromium projects from racing on the shared
 * `sourcebans_e2e` DB."
 */
const SUBTEST_OFFSETS = {
    open: 0,
    esc: 1,
    xbutton: 2,
    tabs: 3,
    notes: 4,
    scrolllock: 5,
} as const;

function uniqueSeed(
    testInfo: import('@playwright/test').TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset = SUBTEST_OFFSETS[subtest];
    // 5_551_000 base keeps Slice 6's drawer seeds out of Slice 6's
    // palette seed range (5_550_***). Per-worker stride of 1_000 leaves
    // headroom for new subtests within the same slice.
    const account = 5_551_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick: `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:1:${account}`,
    };
}

test.describe('player drawer', () => {
    // Desktop-only gate: the marquee `page_bans.tpl` renders two
    // anchors per ban — one inside the desktop `<table>` (display:
    // none below 769px) and one inside the mobile `.ban-cards`
    // wrapper (display: none at >=769px). A `.first()` selector on
    // the shared `[data-testid="drawer-trigger"]` always lands on
    // the desktop anchor (first in DOM order); clicking it fails on
    // mobile because the parent `<table>` carries display:none and
    // Playwright's actionability check rejects hidden targets. The
    // mobile drawer surface is already covered by
    // `specs/responsive/drawer.spec.ts`. Same recipe Slice 3
    // (public-ban-submission) and Slice 5 (comms-gag-mute) lock in.
    test.skip(({ isMobile }) => Boolean(isMobile), 'flow runs against the desktop chrome; mobile chrome is covered by specs/responsive/drawer.spec.ts');

    /**
     * Seed a ban for the current subtest. Looks up the existing row
     * via `bans.search` when `bans.add` reports `already_banned` (a
     * Playwright retry on the same worker reuses the same authid).
     * Returns the bid the rest of the spec keys off.
     */
    async function seedOrLookup(
        page: import('@playwright/test').Page,
        seed: { nick: string; steam: string; reason?: string },
    ): Promise<number> {
        try {
            const created = await seedBanViaApi(page, {
                nickname: seed.nick,
                steam: seed.steam,
                reason: seed.reason ?? SEED_REASON,
            });
            return created.bid;
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }
        // Retry path: the prior attempt left a row under this authid.
        // Look it up so we can still target the right row in the UI.
        const bid = await page.evaluate(async (steam) => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            params: Record<string, unknown>,
                        ) => Promise<{ ok: boolean; data?: { bans?: Array<{ bid: number; steam: string }> } }>;
                    };
                };
                Actions: Record<string, string>;
            };
            const env = await w.sb.api.call(w.Actions.BansSearch, { q: steam, limit: 5 });
            const found = env?.data?.bans?.find((b) => b.steam === steam);
            return found?.bid ?? null;
        }, seed.steam);
        if (bid === null) {
            throw new Error(
                `seedOrLookup: bans.add reported already_banned for ${seed.steam} but bans.search couldn't find the row`,
            );
        }
        return bid;
    }

    test('clicking a /bans row opens the drawer with player data', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'open');
        const bid = await seedOrLookup(page, { ...seed, reason: SEED_REASON });

        await page.goto('/index.php?p=banlist');

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');

        // The marquee template renders two anchors per ban — one inside
        // the desktop `<table>` and one inside the mobile `.ban-cards`
        // list. Both carry `[data-testid="drawer-trigger"]`. Locking
        // selection by ban id (encoded in `data-drawer-href`) plus
        // `.first()` makes this stable across both viewports — but we
        // gate to desktop-only above so `.first()` always lands on
        // the visible desktop-table anchor.
        const rowTrigger = page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first();
        await rowTrigger.click();

        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');

        // `bans.detail` is fired once the drawer mounts; the chrome
        // toggles `data-loading="true"` for the duration of the fetch.
        // Wait for the absence of the attribute (theme.js `delete`s
        // the dataset key on settle) rather than `=false`, because the
        // `delete` shape never sets a string value.
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        // Body assertions: the Overview pane (active on first paint)
        // contains the seeded SteamID, nickname, and reason. We anchor
        // on the `.drawer__ids` `<dl>` for SteamID (definition-list
        // pairs are the chrome's stable shape) and on `.drawer__ban`
        // for reason. The header carries the nickname.
        await expect(drawer.locator('.drawer__header')).toContainText(seed.nick);
        const overview = drawer.locator('[data-testid="drawer-panel-overview"]');
        await expect(overview).toBeVisible();
        const idGrid = overview.locator('.drawer__ids');
        await expect(idGrid).toBeVisible();
        // SteamID rows are `<dt>SteamID</dt><dd>STEAM_0:…</dd>` pairs.
        // `:has-text` plus the trailing `+ dd` would over-specify the
        // selector for jQuery-flavoured tests; Playwright's locator
        // with hasText on the `<dt>` and a `nth=1` step is the
        // idiomatic shape and survives DT/DD reorder.
        await expect(idGrid.locator('dt', { hasText: 'SteamID' })).toBeVisible();
        await expect(idGrid).toContainText(seed.steam);
        const banGrid = overview.locator('.drawer__ban');
        await expect(banGrid).toBeVisible();
        await expect(banGrid.locator('dt', { hasText: 'Reason' })).toBeVisible();
        await expect(banGrid).toContainText(SEED_REASON);

        // Tab chrome: Overview is active by default; the other three
        // start aria-selected=false. The tablist is keyboard-reachable
        // (button[role=tab] tabindex management is asserted by the
        // dedicated tabs subtest below).
        const tablist = drawer.locator('[data-testid="drawer-tablist"]');
        await expect(tablist).toBeVisible();
        await expect(drawer.locator('[data-testid="drawer-tab-overview"]')).toHaveAttribute('aria-selected', 'true');
        await expect(drawer.locator('[data-testid="drawer-tab-history"]')).toHaveAttribute('aria-selected', 'false');
        await expect(drawer.locator('[data-testid="drawer-tab-comms"]')).toHaveAttribute('aria-selected', 'false');
        // The seeded auth state is admin/admin so the Notes tab is
        // present (notes_visible=true on bans.detail). A non-admin
        // caller would see only three tabs; that path is covered by
        // `tests/api/BansTest::testDetailPublicViewHidesAdminFields`
        // at the API boundary so we don't double-cover it here.
        await expect(drawer.locator('[data-testid="drawer-tab-notes"]')).toHaveAttribute('aria-selected', 'false');
    });

    test('switching tabs reveals each pane with the right content', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'tabs');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');
        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        // History tab. The seeded ban is the only one for this player,
        // so the empty-state copy ("No prior bans on file.") matches
        // the issue's literal acceptance text and proves the lazy
        // fetch landed.
        const historyTab = drawer.locator('[data-testid="drawer-tab-history"]');
        const historyPanel = drawer.locator('[data-testid="drawer-panel-history"]');
        await expect(historyPanel).toBeHidden();
        await historyTab.click();
        await expect(historyTab).toHaveAttribute('aria-selected', 'true');
        await expect(historyPanel).toBeVisible();
        await expect(historyPanel.locator('[data-testid="drawer-history-heading"]')).toBeVisible();
        await expect(historyPanel).toContainText('No prior bans on file.');

        // Comms tab. The seed is a ban (no comm-block) so this also
        // hits the empty state. The empty-state copy is asserted
        // verbatim so a regression that drops the heading falls out.
        const commsTab = drawer.locator('[data-testid="drawer-tab-comms"]');
        const commsPanel = drawer.locator('[data-testid="drawer-panel-comms"]');
        await commsTab.click();
        await expect(commsTab).toHaveAttribute('aria-selected', 'true');
        await expect(historyTab).toHaveAttribute('aria-selected', 'false');
        await expect(commsPanel).toBeVisible();
        await expect(historyPanel).toBeHidden();
        await expect(commsPanel.locator('[data-testid="drawer-comms-heading"]')).toBeVisible();
        await expect(commsPanel).toContainText('No prior comm-blocks on file.');

        // Notes tab (admin-only). The default seeded auth state is
        // admin so the tab is present.
        const notesTab = drawer.locator('[data-testid="drawer-tab-notes"]');
        const notesPanel = drawer.locator('[data-testid="drawer-panel-notes"]');
        await notesTab.click();
        await expect(notesTab).toHaveAttribute('aria-selected', 'true');
        await expect(notesPanel).toBeVisible();
        await expect(notesPanel.locator('[data-testid="drawer-notes-heading"]')).toBeVisible();
        await expect(notesPanel.locator('[data-testid="drawer-notes-add"]')).toBeVisible();
        // Empty state — the seed is fresh so no notes have been added.
        await expect(notesPanel.locator('[data-testid="drawer-notes-empty"]')).toBeVisible();

        // Going back to Overview re-hides the others. The Overview
        // pane was server-populated on first paint (it's not lazy)
        // so this round-trip exercises the show/hide toggle without
        // re-fetching.
        const overviewTab = drawer.locator('[data-testid="drawer-tab-overview"]');
        const overviewPanel = drawer.locator('[data-testid="drawer-panel-overview"]');
        await overviewTab.click();
        await expect(overviewTab).toHaveAttribute('aria-selected', 'true');
        await expect(overviewPanel).toBeVisible();
        await expect(notesPanel).toBeHidden();
    });

    test('Notes tab persists across add + delete', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'notes');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');
        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        await drawer.locator('[data-testid="drawer-tab-notes"]').click();
        const notesPanel = drawer.locator('[data-testid="drawer-panel-notes"]');
        await expect(notesPanel.locator('[data-testid="drawer-notes-empty"]')).toBeVisible();

        // Type a note + submit. The handler returns the new row and the
        // pane re-fetches `notes.list` so the empty state goes away
        // and one item appears.
        const body = `e2e-note-${testInfo.workerIndex}-${Date.now()}`;
        await notesPanel.locator('textarea[name="body"]').fill(body);
        await notesPanel.locator('[data-testid="drawer-notes-submit"]').click();

        await expect(notesPanel.locator('[data-testid="drawer-notes-empty"]')).toBeHidden();
        const item = notesPanel.locator('[data-testid="drawer-notes-item"]').first();
        await expect(item).toBeVisible();
        await expect(item).toContainText(body);

        // Delete it. The trash button is per-row; after the round-trip
        // the empty state comes back.
        await item.locator('[data-notes-delete]').click();
        await expect(notesPanel.locator('[data-testid="drawer-notes-empty"]')).toBeVisible();
    });

    test('Esc closes the open drawer', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'esc');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');
        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        await page.keyboard.press('Escape');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');
    });

    test('opening the drawer locks page scroll on desktop too (#1207 CC-2)', async ({ page }, testInfo) => {
        // CC-2 + slice 1 review finding 2: the
        // `html:has(#drawer-root[data-drawer-open="true"]) { overflow: hidden; }`
        // rule fires at every viewport — desktop drawer is treated
        // as a modal-style surface (Linear / Vercel / Notion idiom),
        // so the lock symmetric across viewports keeps the
        // drawer-open contract consistent. The mobile-side assertion
        // lives in `responsive/drawer.spec.ts`; this is the desktop
        // counterpart so a viewport-conditional regression in either
        // direction lands on a different failing spec.
        const seed = uniqueSeed(testInfo, 'scrolllock');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');

        const overflowBefore = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        // `scrollbar-gutter: stable` on <html> means the resolved
        // value can be `visible` or `auto` depending on the browser
        // — the assertion is "not yet locked", not a literal value.
        expect(overflowBefore).not.toBe('hidden');

        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();
        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        const overflowOpen = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        expect(overflowOpen).toBe('hidden');

        // Lock unwinds on close — Esc is the canonical close path.
        await page.keyboard.press('Escape');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');
        const overflowAfter = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        expect(overflowAfter).not.toBe('hidden');
    });

    test('clicking the in-drawer close button collapses the same way', async ({ page }, testInfo) => {
        // The X button (`<button data-drawer-close>` inside the
        // drawer header, see `theme.js#renderDrawerBody`) is the
        // alternate close path; #1140's audit added the same data
        // attribute on both the backdrop and the header X. Cover the
        // click path alongside the keyboard path so a regression on
        // either lands on a different test.
        //
        // Selector pin: `.drawer button[data-drawer-close]` skips
        // the sibling `<div class="drawer-backdrop" data-drawer-close>`
        // (which would also close, but this test asserts the X
        // affordance is real).
        const seed = uniqueSeed(testInfo, 'xbutton');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');
        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        await drawer.locator('.drawer button[data-drawer-close]').click();
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');
    });
});
