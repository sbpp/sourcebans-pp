/**
 * Responsive: player drawer (#1124 Slice 7).
 *
 * iPhone-13 viewport contract:
 *   - The right-side drawer (.drawer) opens for a banlist row when
 *     the trigger fires `loadDrawer(bid)` (theme.js #drawer-root
 *     wiring at L266).
 *   - At mobile widths the drawer's CSS contract is
 *     `width: min(35rem, 100vw)` (theme.css L248). 35rem ~= 560px,
 *     so on a 390-wide iPhone-13 viewport the `min()` collapses to
 *     `100vw` — i.e. the drawer covers the full viewport width.
 *   - Terminal state for tests: `#drawer-root[data-drawer-open="true"]`
 *     is the open marker, `[data-loading]` clears once the
 *     `bans.detail` fetch settles. Both come from #1123's
 *     "Async loads expose a stable terminal state" rule.
 *
 * Project gating: mobile-chromium only.
 */

import type { Page } from '@playwright/test';
import { expect, test } from '../../fixtures/auth.ts';

const SEED_STEAM = 'STEAM_0:0:72000007';
const SEED_NICK = 'e2e-resp-drawer';

/**
 * See `specs/responsive/banlist.spec.ts` for why `already_banned`
 * is tolerated as a benign no-op (the dev DB the panel uses is
 * not reset between playwright invocations; the seeded row is the
 * one we want either way).
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
                reason: 'e2e/responsive drawer seed',
            });
        },
        { steam, nickname },
    );
    if (env && env.ok === false && env.error?.code !== 'already_banned') {
        throw new Error(`bans.add seed failed: ${JSON.stringify(env)}`);
    }
}

test.describe('responsive: drawer', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('drawer opens full-viewport-width on mobile', async ({ page }) => {
        await page.goto('/');
        await seedBan(page, SEED_STEAM, SEED_NICK);

        await page.goto('/index.php?p=banlist');

        // Trigger lives in the mobile cards block (page_bans.tpl
        // L209-212). theme.js's bidFromTrigger() accepts both
        // `data-drawer-bid` and `data-drawer-href` (the cards use
        // the latter); the click handler funnels through
        // loadDrawer(bid) the same way for both.
        const trigger = page
            .locator('#banlist-root .ban-cards [data-testid="drawer-trigger"]')
            .filter({ hasText: SEED_NICK });
        await expect(trigger).toHaveCount(1);
        await trigger.click();

        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'true');
        // [data-loading] clears once bans.detail returns. We wait
        // on the attribute disappearing rather than on a timer.
        await expect(drawerRoot).not.toHaveAttribute('data-loading', 'true');

        const drawer = drawerRoot.locator('.drawer');
        await expect(drawer).toBeVisible();

        const drawerBox = await drawer.boundingBox();
        expect(drawerBox, 'drawer must render a bounding box once open').not.toBeNull();
        const vw = page.viewportSize()?.width ?? 0;
        // CSS uses `min(35rem, 100vw)`; at 390px viewport the
        // 100vw branch wins. Allow a small CSS-rounding tolerance
        // (per the brief's `vw - 4` guidance) to absorb sub-pixel
        // layout differences across browsers.
        expect(drawerBox!.width).toBeGreaterThanOrEqual(vw - 4);
        // Sanity: it shouldn't render WIDER than the viewport
        // either — that would be a regression in the `min(...)` cap.
        expect(drawerBox!.width).toBeLessThanOrEqual(vw + 1);

        // Drawer is anchored to the right edge (theme.css L248
        // `position: fixed; right: 0`). At full-viewport width
        // that means it starts at x=0 too — assert the right edge
        // matches the viewport, which is the design contract that
        // doesn't depend on width arithmetic.
        expect(drawerBox!.x + drawerBox!.width).toBeGreaterThanOrEqual(vw - 4);
    });
});
