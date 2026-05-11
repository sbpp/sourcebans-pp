/**
 * #BANLIST-COMMENTS — per-ban admin-authored comments visibility on
 * the public banlist + commslist.
 *
 * v1.x rendered admin-authored per-row comments inline below each row
 * via the `mooaccordion` sliding panel (`web/scripts/sourcebans.js`).
 * That script was deleted at #1123 D1 ("Hard cutover: rename sbpp2026
 * to default, drop sourcebans.js"). The v2.0 rewrite of `page_bans.tpl`
 * kept the page handler's `commentdata` build but only emitted a silent
 * `<span>[N]</span>` count badge — no affordance, no inline body.
 * The actual comment text moved to the right-side player drawer
 * (`renderOverviewPane` / `[data-testid="drawer-comments"]`).
 *
 * The fix wires a native `<details data-testid="ban-comments-inline">`
 * disclosure into the desktop banlist (and the corresponding
 * `comm-comments-inline` into the commslist, where the regression was
 * worse — no drawer fallback at all). The drawer's comments section
 * continues to render the same data via `api_bans_detail`, so this
 * spec covers BOTH surfaces stay in sync.
 *
 * Coverage:
 *   - Desktop banlist: the inline disclosure renders for a ban with
 *     comments, defaults closed, opens to reveal the comment text.
 *   - Same drawer surface still renders the comment under
 *     `[data-testid="drawer-comments"]` when the row's drawer is opened.
 *   - Mobile banlist: the non-interactive count indicator
 *     (`[data-testid="ban-comments-count-mobile"]`) renders inside the
 *     `.ban-cards` wrapper. The drawer is the canonical mobile
 *     expansion path.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';
import { seedCommentsRawE2e } from '../../fixtures/db.ts';

const SEED_NICK_PREFIX = 'e2e-comments-target';
const SEED_REASON      = 'e2e comments seed reason';
const FIRST_COMMENT    = 'first comment from e2e';
const SECOND_COMMENT   = 'second comment from e2e';

/**
 * Per-subtest authid offset. See player-drawer.spec.ts's matching
 * comment for the rationale. The 5_553_000 base reserves a fresh
 * range outside the existing drawer/palette seeds so the parallel
 * chromium / mobile-chromium projects don't race on the shared
 * `sourcebans_e2e` DB.
 */
const SUBTEST_OFFSETS = {
    'inline-disclosure': 0,
    'drawer-mirror':     1,
    'mobile-count':      2,
} as const;

function uniqueSeed(
    testInfo: import('@playwright/test').TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset  = SUBTEST_OFFSETS[subtest];
    const account = 5_553_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick:  `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:1:${account}`,
    };
}

/**
 * Same shape as `player-drawer.spec.ts#seedOrLookup` — `bans.add`
 * reports `already_banned` on a Playwright retry under the same
 * authid; fall back to `bans.search` to recover the existing bid.
 */
async function seedOrLookup(
    page: import('@playwright/test').Page,
    seed: { nick: string; steam: string; reason?: string },
): Promise<number> {
    try {
        const created = await seedBanViaApi(page, {
            nickname: seed.nick,
            steam:    seed.steam,
            reason:   seed.reason ?? SEED_REASON,
        });
        return created.bid;
    } catch (err) {
        if (!String(err).includes('already_banned')) throw err;
    }
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

test.describe('#BANLIST-COMMENTS: per-row comments visibility', () => {
    test('desktop banlist: inline disclosure renders + opens to show comment text', async ({ page }, testInfo) => {
        // Mobile chromium runs against the `.ban-cards` mobile wrapper
        // (the desktop `<table>` is `display:none` below 769px) and
        // the desktop `<details>` is hidden. Cover the mobile path
        // separately below; this subtest is desktop-only.
        test.skip(Boolean(testInfo.project.name === 'mobile-chromium'), 'desktop-only — mobile-chromium covered by the mobile-count subtest');

        const seed = uniqueSeed(testInfo, 'inline-disclosure');
        const bid  = await seedOrLookup(page, seed);
        await seedCommentsRawE2e([
            { type: 'B', bid, text: FIRST_COMMENT },
            { type: 'B', bid, text: SECOND_COMMENT },
        ]);

        await page.goto('/index.php?p=banlist');

        // The disclosure carries the bid in `data-bid` so a banlist
        // with many rows still anchors selection on the seeded one.
        const disclosure = page.locator(
            `[data-testid="ban-comments-inline"][data-bid="${bid}"]`,
        );
        await expect(disclosure).toBeVisible();

        // Default-collapsed — important so a banlist with comment-heavy
        // rows doesn't blow out the table height on first paint.
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(false);

        // Comment text is hidden until the disclosure is opened. We
        // anchor on the `<ul data-testid="ban-comments-list">` rather
        // than the `<li>`s themselves because the `<ul>` is the layout
        // shell and `[hidden]` from the closed `<details>` propagates.
        const commentsList = disclosure.locator('[data-testid="ban-comments-list"]');
        await expect(commentsList).toBeHidden();

        // Click the summary chip to open the disclosure. The summary
        // doubles as the count chip and the `<details>` toggle — same
        // shape `filters-details` uses on the advanced-search bar.
        const toggle = disclosure.locator('[data-testid="ban-comments-toggle"]');
        await expect(toggle).toBeVisible();
        await toggle.click();

        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(true);
        await expect(commentsList).toBeVisible();

        // Both seeded comments must reach the rendered list. The
        // `data-testid="ban-comment-text"` per-comment selector is the
        // contract surface for future work that wants to anchor on
        // a specific comment's body.
        const items = disclosure.locator('[data-testid="ban-comment-item"]');
        await expect(items).toHaveCount(2);
        await expect(disclosure.locator('[data-testid="ban-comment-text"]', {
            hasText: FIRST_COMMENT,
        })).toBeVisible();
        await expect(disclosure.locator('[data-testid="ban-comment-text"]', {
            hasText: SECOND_COMMENT,
        })).toBeVisible();
    });

    test('drawer mirrors the same comments under [data-testid="drawer-comments"]', async ({ page }, testInfo) => {
        // The fix preserves the drawer surface — `api_bans_detail`
        // still returns `comments_visible: true` + `comments: [...]`
        // for admin callers, and `renderOverviewPane` paints them
        // under the Overview pane's `<section data-testid="drawer-comments">`.
        // Pin both surfaces so a future regression in either lands on
        // a different failing assertion.
        test.skip(Boolean(testInfo.project.name === 'mobile-chromium'), 'drawer chrome covered by responsive/drawer.spec.ts on mobile; this subtest pins the desktop drawer mirror to the inline disclosure');

        const seed = uniqueSeed(testInfo, 'drawer-mirror');
        const bid  = await seedOrLookup(page, seed);
        await seedCommentsRawE2e([
            { type: 'B', bid, text: FIRST_COMMENT },
        ]);

        await page.goto('/index.php?p=banlist');

        // Open the drawer via the row anchor — same selector shape
        // player-drawer.spec.ts uses (matches BOTH the desktop-table
        // anchor and the mobile-card anchor; `.first()` lands on the
        // desktop one because it's first in DOM order, and the
        // desktop-only skip above keeps mobile from running this).
        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        const overview      = drawer.locator('[data-testid="drawer-panel-overview"]');
        const commentsBlock = overview.locator('[data-testid="drawer-comments"]');
        await expect(commentsBlock).toBeVisible();
        await expect(commentsBlock).toContainText(FIRST_COMMENT);
    });

    test('mobile banlist: non-interactive count indicator renders inside the card', async ({ page }, testInfo) => {
        // Inverse of the disclosure subtest — the mobile card is a
        // single `<a>`, so a nested `<details>` would be invalid HTML
        // (interactive content inside interactive content). The fix
        // emits a non-interactive `<div data-testid="ban-comments-count-mobile">`
        // instead; the drawer is the canonical mobile expansion path.
        test.skip(testInfo.project.name !== 'mobile-chromium', 'mobile-chromium-only — desktop covered by inline-disclosure subtest');

        const seed = uniqueSeed(testInfo, 'mobile-count');
        const bid  = await seedOrLookup(page, seed);
        await seedCommentsRawE2e([
            { type: 'B', bid, text: FIRST_COMMENT },
            { type: 'B', bid, text: SECOND_COMMENT },
        ]);

        await page.goto('/index.php?p=banlist');

        // The mobile card carries the same bid as the desktop row;
        // anchor on it via the `.ban-cards` wrapper so the test won't
        // accidentally pick up the desktop-only disclosure (which is
        // hidden by `display:none` on the parent table at this
        // viewport, but would still match a bare `[data-testid=…]`
        // selector and confuse `toBeVisible()`).
        const indicator = page
            .locator('.ban-cards [data-testid="ban-comments-count-mobile"]')
            .first();
        await expect(indicator).toBeVisible();
        // Plural label branch — the seed has two comments.
        await expect(indicator).toContainText('2');
        await expect(indicator).toContainText('comments');

        // The indicator MUST NOT be a `<details>` — defending the
        // HTML-validity contract from a future "let's just nest a
        // disclosure here too" regression. The card itself wraps
        // every cell in a single `<a data-drawer-href>`, so any
        // interactive content inside that anchor would be invalid.
        const tagName = await indicator.evaluate((el) => el.tagName);
        expect(tagName.toLowerCase()).toBe('div');
    });
});
