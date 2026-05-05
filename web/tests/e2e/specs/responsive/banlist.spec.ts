/**
 * Responsive: ban list (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - At <=768px the desktop `.table` collapses, the mobile
 *     `.ban-cards` block (page_bans.tpl L204) becomes the canonical
 *     listing. Each card preserves the `[data-testid="drawer-trigger"]`
 *     hook so the drawer flow still works on mobile.
 *   - The sticky filter chip bar above the list keeps every chip
 *     reachable on mobile widths.
 *
 * Project gating: mobile-chromium only (see sidebar.spec.ts header
 * for the rationale on `beforeEach` skip vs. `describe.configure`).
 *
 * == Divergence from the issue's literal text ==
 *
 *   The brief's "filter chips wrap" subtest assumes flex-wrap, but
 *   page_bans.tpl wraps the chip group in `.scroll-x` (theme.css
 *   L291) — `overflow-x: auto; scrollbar-width: none`. At iPhone-13
 *   width the chip row's `scrollWidth` legitimately EXCEEDS its
 *   `clientWidth` because that's the design: chips horizontal-scroll
 *   inside a viewport-constrained container, they don't wrap. We
 *   therefore assert the OUTER chip-row container is bounded by the
 *   viewport (the user contract: "no chip silently lives at
 *   x=2000px") rather than chip-row `scrollWidth <= clientWidth`.
 *   Page-level `documentElement.scrollWidth <= clientWidth` IS now
 *   asserted: #1180 dropped the topbar's `min-width: 16rem` at
 *   <=1024px so the chrome no longer overflows iPhone-13. If a
 *   future redesign switches the chip row to `flex-wrap: wrap`,
 *   tighten the chip-container assertion below to
 *   `scrollWidth <= clientWidth + 1`.
 */

import type { Page } from '@playwright/test';
import { expect, test } from '../../fixtures/auth.ts';

const SEED_STEAM = 'STEAM_0:0:71000007';
const SEED_NICK = 'e2e-resp-banlist';

/**
 * Insert one ban via `bans.add` and tolerate the benign
 * `already_banned` collision when the same SteamID was seeded by an
 * earlier playwright invocation. Slice 0's globalSetup resets
 * `sourcebans_e2e`, but the panel itself runs against `sourcebans`
 * (the dev DB) — `web/docker/php/web-entrypoint.sh` bakes
 * `DB_NAME=sourcebans` into config.php at container start, and
 * `./sbpp.sh e2e`'s per-invocation env override only steers the
 * test runner's PHP shim, not Apache. Consequence: panel-side
 * inserts persist across `playwright test` invocations until the
 * dev DB is dropped. Tolerating `already_banned` keeps the spec
 * deterministic without a cross-DB reset surface that doesn't
 * exist yet.
 */
async function seedBan(page: Page, steam: string, nickname: string): Promise<void> {
    const env = await page.evaluate(
        async ({ steam: s, nickname: n }) => {
            const w = /** @type {any} */ (window);
            return await w.sb.api.call(w.Actions.BansAdd, {
                nickname: n,
                type: 0,
                steam: s,
                length: 0,
                reason: 'e2e/responsive seed',
            });
        },
        { steam, nickname },
    );
    if (env && env.ok === false && env.error?.code !== 'already_banned') {
        throw new Error(`bans.add seed failed: ${JSON.stringify(env)}`);
    }
}

test.describe('responsive: ban list', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('mobile collapses the table to card view, with seeded ban visible', async ({ page }) => {
        // Hit any page first so sb.api + Actions + the CSRF meta
        // tag are loaded; the JSON dispatcher rejects requests
        // without a CSRF token, and api.js scrapes it from the
        // current document.
        await page.goto('/');
        await seedBan(page, SEED_STEAM, SEED_NICK);

        await page.goto('/index.php?p=banlist');

        const desktopTable = page.locator('#banlist-root .table');
        const cards = page.locator('#banlist-root .ban-cards');

        // theme.css L266: `.table { display: none }` at <=768px.
        await expect(desktopTable).toBeHidden();
        await expect(cards).toBeVisible();

        // Card markup stamps `data-testid="drawer-trigger"` on every
        // anchor (page_bans.tpl L212). Look our seeded ban up by its
        // visible nickname inside the cards block — the row also
        // carries `data-id` if a future test wants the bid directly.
        const seededCard = cards.locator('[data-testid="drawer-trigger"]', { hasText: SEED_NICK });
        await expect(seededCard).toHaveCount(1);
        await expect(seededCard).toBeVisible();
    });

    test('filter chips fit in the viewport without overflowing the page', async ({ page }) => {
        await page.goto('/index.php?p=banlist');

        const chipBar = page.locator('#banlist-filters [role="group"][aria-label="Filter by status"]');
        await expect(chipBar).toBeVisible();

        // Every chip listed in the #1123 testability hooks contract
        // is present and within the page's horizontal extent.
        const chipIds = ['permanent', 'active', 'expired', 'unbanned'];
        for (const id of chipIds) {
            const chip = page.locator(`[data-testid="filter-chip-${id}"]`);
            await expect(chip).toHaveCount(1);
        }

        // Spirit of "no horizontal overflow": the chip row container
        // is bounded by the viewport. Chips inside may horizontally
        // scroll inside the `.scroll-x` wrapper (by design, see the
        // file-level divergence note); what the user contract gates
        // is that the OUTER row doesn't extend past the viewport,
        // which is what `.scroll-x` clamps it to.
        const vw = page.viewportSize()?.width ?? 0;
        const barBox = await chipBar.boundingBox();
        expect(barBox, 'chip bar must render a bounding box').not.toBeNull();
        expect(barBox!.width).toBeLessThanOrEqual(vw + 1);
        expect(barBox!.x).toBeGreaterThanOrEqual(-1);
        expect(barBox!.x + barBox!.width).toBeLessThanOrEqual(vw + 1);

        // Page-level: nothing in the chrome (topbar, sidebar drawer,
        // …) leaks past the viewport either. #1180 fixed the topbar
        // search min-width regression that previously left ~28px of
        // horizontal scroll on iPhone-13.
        await expect.poll(() => page.evaluate(() =>
            document.documentElement.scrollWidth - document.documentElement.clientWidth
        )).toBeLessThanOrEqual(1);

        // Every chip is reachable on mobile: Playwright's auto-scroll
        // brings horizontally-scrolled-out elements into view, so a
        // `.click()` succeeds on the rightmost chip and toggles state.
        const last = page.locator('[data-testid="filter-chip-unbanned"]');
        await last.click();
        await expect(last).toHaveAttribute('aria-pressed', 'true');
        await expect(page).toHaveURL(/[?&]state=unbanned(?:&|$)/);
    });
});
