/**
 * Player drawer (#1124, Slice 6).
 *
 * Acceptance criteria from #1124:
 *   "click a row in /bans, drawer opens with player data, tabs
 *    (Overview / History / Comms / Notes) all populate, Esc closes."
 *
 * == Divergence from the issue's literal text ==
 *
 * The drawer that ships with #1123 (`web/themes/default/js/theme.js`,
 * `renderDrawerBody` ~line 422) is a SINGLE BODY: an id grid
 * (`.drawer__ids`), a ban grid (`.drawer__ban`), and an optional
 * comments section. There are no Overview / History / Comms / Notes
 * tabs.
 *
 * Rather than write a tab-switching test that asserts against UI that
 * doesn't exist (and would silently rot once tabs land), this spec
 * gates the present-day chrome contract:
 *   - Open trigger: `[data-drawer-href*="id=<bid>"]` on the row anchor
 *     (the marquee `page_bans.tpl` uses the legacy-handoff form;
 *     `theme.js#bidFromTrigger` extracts the bid from either
 *     `data-drawer-href`'s `?id=N` or the newer `data-drawer-bid`).
 *   - Open state:  `#drawer-root[data-drawer-open="true"]`.
 *   - Loaded state: `#drawer-root` no longer carries `data-loading="true"`
 *     once `bans.detail` settles.
 *   - Close: Esc OR a click on `[data-drawer-close]`.
 *
 * The four-tabs expansion is real product work (new server-side feeds
 * for History / Comms, a notes scratch pad, lazy-loaded panes) and is
 * tracked as the follow-up:
 *   #1165 — "Player drawer: implement Overview/History/Comms/Notes tabs"
 * When that lands, fold the tab-switching subtests in here in the
 * same PR (don't write them against the no-tabs chrome).
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

        // Body assertions: the rendered drawer contains the seeded
        // SteamID, nickname, and reason. We anchor on the
        // `.drawer__ids` `<dl>` for SteamID (definition-list pairs
        // are the chrome's stable shape) and on `.drawer__ban` for
        // reason. The header carries the nickname.
        await expect(drawer.locator('.drawer__header')).toContainText(seed.nick);
        const idGrid = drawer.locator('.drawer__ids');
        await expect(idGrid).toBeVisible();
        // SteamID rows are `<dt>SteamID</dt><dd>STEAM_0:…</dd>` pairs.
        // `:has-text` plus the trailing `+ dd` would over-specify the
        // selector for jQuery-flavoured tests; Playwright's locator
        // with hasText on the `<dt>` and a `nth=1` step is the
        // idiomatic shape and survives DT/DD reorder.
        await expect(idGrid.locator('dt', { hasText: 'SteamID' })).toBeVisible();
        await expect(idGrid).toContainText(seed.steam);
        const banGrid = drawer.locator('.drawer__ban');
        await expect(banGrid).toBeVisible();
        await expect(banGrid.locator('dt', { hasText: 'Reason' })).toBeVisible();
        await expect(banGrid).toContainText(SEED_REASON);
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
