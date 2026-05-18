/**
 * Responsive: ban list (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - At <=768px the desktop `.table` collapses, the mobile
 *     `.ban-cards` block (page_bans.tpl L204) becomes the canonical
 *     listing. Each card preserves the `[data-testid="drawer-trigger"]`
 *     hook so the drawer flow still works on mobile.
 *   - The sticky filter chip bar above the list wraps onto multiple
 *     rows (#1181, theme.css `<=768px`) so every chip is statically
 *     visible without a horizontal swipe. The chip container itself
 *     must therefore have NO internal horizontal overflow:
 *     `scrollWidth <= clientWidth + 1`.
 *   - Page-level `documentElement.scrollWidth <= clientWidth` is now
 *     asserted too — #1180 dropped the topbar's `min-width: 16rem`
 *     at <=1024px, so the chrome no longer overflows iPhone-13.
 *   - Chip click navigates rather than `history.replaceState`'ing
 *     (#1352): the chip is now a real `<a href="?p=banlist&state=…">`
 *     and the rowset is narrowed server-side BEFORE pagination, so
 *     pre-2.0 unbanned rows surface even on installs with thousands
 *     of bans. The `aria-pressed="true"` flip is server-rendered
 *     on the post-navigation paint; Playwright's lazy locators
 *     re-query against the new DOM, so the existing assertion shape
 *     still holds.
 *
 * Project gating: mobile-chromium only (see sidebar.spec.ts header
 * for the rationale on `beforeEach` skip vs. `describe.configure`).
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

        // #1181: at <=768px the chip row wraps via `flex-wrap: wrap`,
        // so the container must have NO internal horizontal scroll.
        // Allow a 1px tolerance for sub-pixel rounding.
        const overflow = await chipBar.evaluate((el) => ({
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
        }));
        expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);

        // And the row itself is bounded by the viewport horizontally —
        // no chip silently lives at x=2000px off-screen.
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

        // Every chip is reachable on mobile: with wrap in place the
        // rightmost chip renders within the viewport (possibly on a
        // second row), so `.click()` succeeds without horizontal
        // auto-scroll and toggles state. Post-#1352 the click triggers
        // a server-side navigation (the chip is now an anchor); the
        // lazy locator re-queries against the new DOM so the
        // active-state assertion still holds, and the URL contract
        // (`?…&state=unbanned`) is now what the SQL filter actually
        // narrowed on (vs. pre-#1352's history.replaceState that the
        // server ignored). The active marker switched from
        // `aria-pressed="true"` (only valid on role=button) to
        // `aria-current="true"` (canonical ARIA for "active item in
        // a navigation set" on `<a>`) — see the AGENTS.md row on
        // the banlist state filter for the rationale.
        const last = page.locator('[data-testid="filter-chip-unbanned"]');
        await last.click();
        await page.waitForURL(/[?&]state=unbanned(?:&|$)/);
        await expect(last).toHaveAttribute('aria-current', 'true');
    });
});
