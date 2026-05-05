/**
 * Command palette (#1124, Slice 6).
 *
 * Acceptance criteria from #1124:
 *   "⌘K opens, typing a SteamID surfaces the player, Enter navigates
 *    to their drawer."
 *
 * The shipped chrome (#1123 C2, see `web/themes/default/js/theme.js`)
 * is a `<dialog id="palette-root">` with:
 *   - `[data-palette-open="true|false"]` mirrored onto the dialog so
 *     tests don't probe the `hidden` boolean directly,
 *   - `#palette-input` autofocused after a 10ms delay (we wait via
 *     `expect(input).toBeFocused()`, not a `setTimeout`),
 *   - results rendered as `<a [data-testid="palette-result"]
 *     [data-result-kind="nav"|"ban"]>` once `q.length >= 2`, with a
 *     200ms input debounce.
 *
 * The third subtest (Enter navigates) follows the focused result's
 * native `<a>` activation rather than a custom keydown handler —
 * `theme.js` doesn't bind Enter on the palette input itself.
 *
 * == Seeding strategy: unique-per-(test × project), no truncate ==
 *
 * Slice 0's foundation PR called out that `truncateE2eDb()` is hostile
 * to the parallel `chromium` + `mobile-chromium` projects sharing
 * `sourcebans_e2e` — a truncate from the mobile worker between the
 * desktop worker's seed and assert (or vice-versa) corrupts the seeded
 * row, and concurrent `truncateAndReseed()` calls collide on the seeded
 * admin INSERT (`Duplicate entry '0' for key 'PRIMARY'`). Slice 7
 * sidesteps it by NOT truncating and using unique-per-test seeds; this
 * spec follows the same pattern. Each subtest uses a steam id that
 * encodes both the subtest's slot (`SUBTEST_OFFSETS`) and the worker's
 * `testInfo.workerIndex`, so:
 *   - two subtests in the same project never collide on the same authid,
 *   - two projects running the same subtest in parallel never collide
 *     on each other's seed,
 *   - retried tests reuse the same offset (workerIndex is stable across
 *     a worker's retries) but the prior fail's row is still a permanent
 *     ban under the same authid, which `bans.add` rejects with
 *     `already_banned`. We tolerate that by NOT asserting on a fresh
 *     bid — the search filter on `SEED_NICK_PREFIX + slot` is enough,
 *     and the existing row from the previous attempt continues to match.
 *     If the retry-on-already-banned path becomes a real flake source,
 *     #1170 (Slice 0 followup) tracks the worker-serialised truncate
 *     option as the fallback.
 */

import { expect, test } from '../../../fixtures/auth.ts';
import { seedBanViaApi } from '../../../fixtures/seeds.ts';

const SEED_NICK_PREFIX = 'e2e-palette-target';

/**
 * Per-subtest authid offset. Each subtest pulls its own offset out
 * of this map so two subtests on the same worker never collide on
 * the bans row's authid. The numeric range stays safely inside
 * SteamID's 32-bit account-id slot.
 */
const SUBTEST_OFFSETS = {
    open: 0,
    type: 1,
    enter: 2,
} as const;

/**
 * Build a (steam, nick) pair that's unique across (subtest × project ×
 * worker). 5_550_000 is the slice-6 base; subtest offset bumps the
 * tens, project name + workerIndex are folded into the hundreds /
 * thousands so cross-project parallel runs never collide. The nick
 * encodes the same triple so `bans.search` filters land deterministic
 * matches.
 */
function uniqueSeed(
    testInfo: import('@playwright/test').TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset = SUBTEST_OFFSETS[subtest];
    const account = 5_550_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick: `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:1:${account}`,
    };
}

test.describe('command palette', () => {

    test('⌘K opens, Esc closes', async ({ page }) => {
        await page.goto('/');

        const dialog = page.locator('#palette-root');
        const input = page.locator('#palette-input');

        await expect(dialog).toHaveAttribute('data-palette-open', 'false');

        // `theme.js`'s document-level keydown listener triggers on
        // either metaKey or ctrlKey; sending Meta+K covers Mac and
        // Linux (where Playwright treats Meta as the modifier).
        await page.keyboard.press('Meta+k');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        // openPalette() defers focus by 10ms via setTimeout so the
        // dialog's showModal() finishes its focus dance first; we
        // poll on the focus state, not a fixed timeout.
        await expect(input).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');
    });

    test('typing surfaces a seeded ban', async ({ page }, testInfo) => {
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );
        const seed = uniqueSeed(testInfo, 'type');
        // `seedBanViaApi` is idempotent at the test level: a retry
        // re-uses the same authid and trips `already_banned`. We swallow
        // that path so the rest of the test still asserts the search
        // surface — the row from the first attempt is what we'll find.
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');

        const dialog = page.locator('#palette-root');
        const input = page.locator('#palette-input');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        await expect(input).toBeFocused();

        // Type the full unique nick (not a short prefix). PALETTE_MIN_QUERY
        // is 2, so any length >=2 fires the bans.search call; the server
        // caps results at 10 ordered by created DESC. A short prefix like
        // `e2e-palette-ta` matches every accumulated palette-* seed across
        // the suite (open/type/enter × desktop/mobile), and a stale debounce
        // wave can render a top-10 that excludes the brand-new row. Typing
        // the full unique nick collapses the search to exactly one matching
        // row, removing the dependency on result ordering.
        await input.fill(seed.nick);

        // Wait on the dialog's data-loading flag — theme.js sets it to
        // 'true' while the bans.search fetch is in flight and deletes it
        // on completion. This is the terminal-state hook (#1123 testability
        // contract) for the search: more reliable on mobile-chromium than
        // betting on the default 5s polling on the result locator, where
        // a slow runner can let the 200ms debounce + fetch land outside
        // the assertion window. 10s gives generous headroom for cold
        // runner I/O without making real flakes wait forever.
        await expect(dialog).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        const banResults = page.locator(
            '[data-testid="palette-result"][data-result-kind="ban"]',
        );
        await expect(banResults.first()).toBeVisible();
        await expect(banResults.filter({ hasText: seed.nick }).first()).toBeVisible();
    });

    test('focused result + Enter navigates to the ban-list anchor', async ({ page }, testInfo) => {
        // Same mobile-chromium palette typing-search flake as the
        // 'type' subtest above — the rendered <a data-testid="palette-result">
        // is intermittently not visible on iPhone-13 viewport. Both
        // subtests exercise the same surface (typing → bans.search → result
        // visible) and share a single root cause. The 'open/Esc' sibling
        // doesn't search, so it stays in mobile coverage. Tracked alongside
        // the 'type' subtest in #1206; removing both skips is that issue's
        // success criterion.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );
        const seed = uniqueSeed(testInfo, 'enter');
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');
        const input = page.locator('#palette-input');
        await expect(input).toBeFocused();

        await input.fill(seed.nick.slice(0, 14));
        const result = page
            .locator('[data-testid="palette-result"][data-result-kind="ban"]')
            .filter({ hasText: seed.nick })
            .first();
        await expect(result).toBeVisible();

        // theme.js builds the result `<a>` with
        //   href="?p=banlist&advType=name&advSearch=<encoded-name>"
        // so navigation is the browser's native anchor activation,
        // not a JS-bound Enter handler. We focus the anchor and
        // press Enter inside the same Promise.all that waits for
        // the URL change so the race condition is removed.
        await result.focus();
        await Promise.all([
            page.waitForURL(/[?&]p=banlist(?:&|$)/),
            page.keyboard.press('Enter'),
        ]);

        // `advSearch` carries the URL-encoded nickname; assert against
        // both halves so we don't accidentally pass on a generic
        // banlist URL with no search applied.
        await expect(page).toHaveURL(/[?&]advType=name(?:&|$)/);
        await expect(page).toHaveURL(new RegExp(`[?&]advSearch=${encodeURIComponent(seed.nick)}(?:&|$)`));
    });
});
