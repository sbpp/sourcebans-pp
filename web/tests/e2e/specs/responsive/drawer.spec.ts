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

    test('mobile drawer body fits within the viewport (CC-2)', async ({ page }) => {
        // CC-2: at <=768px the drawer's id values (long SteamIDs +
        // copy buttons) used to overflow horizontally; the fix
        // drops the label column to 4.5rem, lets values wrap
        // (`overflow-wrap: anywhere`), and turns the tab strip
        // into a horizontally-scrollable lane with snap points.
        // This spec asserts on the OBSERVABLE state of all three.
        await page.goto('/');
        await seedBan(page, SEED_STEAM, SEED_NICK);

        await page.goto('/index.php?p=banlist');
        const trigger = page
            .locator('#banlist-root .ban-cards [data-testid="drawer-trigger"]')
            .filter({ hasText: SEED_NICK });
        await trigger.click();

        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawerRoot).not.toHaveAttribute('data-loading', 'true');

        // Id grid: the SteamID copy button must be visible (i.e.
        // not pushed off-screen). The grid is rendered by theme.js
        // via `renderOverviewPane()` and carries `data-testid="drawer-ids"`.
        const idGrid = drawerRoot.locator('[data-testid="drawer-ids"]');
        await expect(idGrid).toBeVisible();

        // Each `<dd>` value carries its `[data-copy]` button right
        // alongside; if the value column overflowed the copy button
        // would land outside the drawer's width. Walk every copy
        // button, not just the first, so a regression on any single
        // id row falls out.
        const vw = page.viewportSize()?.width ?? 0;
        const copyButtons = idGrid.locator('[data-copy]');
        const count = await copyButtons.count();
        expect(count).toBeGreaterThan(0);
        for (let i = 0; i < count; i++) {
            const btn = copyButtons.nth(i);
            const box = await btn.boundingBox();
            expect(box, `copy button #${i} must render a bounding box`).not.toBeNull();
            expect(box!.x + box!.width).toBeLessThanOrEqual(vw + 1);
            expect(box!.x).toBeGreaterThanOrEqual(-1);
        }

        // Tab strip: at mobile width it scrolls horizontally rather
        // than wrapping. Every tab the suite cares about
        // (Overview / History / Comms / Notes — the seeded admin
        // sees all four because notes_visible=true) is reachable
        // either statically or via horizontal scroll.
        const tabIds = ['overview', 'history', 'comms', 'notes'];
        for (const id of tabIds) {
            const tab = drawerRoot.locator(`[data-testid="drawer-tab-${id}"]`);
            await expect(tab).toBeAttached();
        }
        // Tab buttons must not WRAP onto multiple rows — every tab's
        // y-axis position lines up with the first one.
        const firstTab = drawerRoot.locator('[data-testid="drawer-tab-overview"]');
        const firstBox = await firstTab.boundingBox();
        expect(firstBox, 'overview tab must render a bounding box').not.toBeNull();
        for (const id of tabIds.slice(1)) {
            const tab = drawerRoot.locator(`[data-testid="drawer-tab-${id}"]`);
            const box = await tab.boundingBox();
            expect(box, `${id} tab must render a bounding box`).not.toBeNull();
            // 2px tolerance for sub-pixel layout differences.
            expect(Math.abs(box!.y - firstBox!.y)).toBeLessThanOrEqual(2);
        }

        // Last tab ("Notes") becomes reachable after a horizontal
        // scroll on the strip. We `scrollIntoViewIfNeeded` (an
        // a11y-honouring scroll that respects scroll-snap) and
        // assert the resulting bounding box is within the viewport.
        const notesTab = drawerRoot.locator('[data-testid="drawer-tab-notes"]');
        await notesTab.scrollIntoViewIfNeeded();
        const notesBox = await notesTab.boundingBox();
        expect(notesBox, 'notes tab must render a bounding box once scrolled into view').not.toBeNull();
        expect(notesBox!.x).toBeGreaterThanOrEqual(-1);
        expect(notesBox!.x + notesBox!.width).toBeLessThanOrEqual(vw + 1);
    });

    test('mobile drawer locks page scroll behind the dimmed overlay (CC-2)', async ({ page }) => {
        // CC-2: "page scroll not locked behind the dimmed overlay" —
        // CSS `:has(#drawer-root[data-drawer-open="true"])` rule
        // sets `overflow: hidden` on <html> while the drawer is
        // open. Verify by reading the resolved overflow before/after.
        await page.goto('/');
        await seedBan(page, SEED_STEAM, SEED_NICK);
        await page.goto('/index.php?p=banlist');

        const htmlOverflowBefore = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        // Default `overflow` is `visible` (or `auto` if scrollbar-gutter
        // is in play, which the chrome explicitly sets) — anything
        // EXCEPT `hidden` is fine here, the assert is "hidden only
        // happens once the drawer opens".
        expect(htmlOverflowBefore).not.toBe('hidden');

        await page
            .locator('#banlist-root .ban-cards [data-testid="drawer-trigger"]')
            .filter({ hasText: SEED_NICK })
            .click();
        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawerRoot).not.toHaveAttribute('data-loading', 'true');

        const htmlOverflowOpen = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        expect(htmlOverflowOpen).toBe('hidden');

        // And the lock unwinds when the drawer closes — Esc is the
        // canonical close path also covered by player-drawer.spec.ts.
        await page.keyboard.press('Escape');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'false');
        const htmlOverflowAfter = await page.evaluate(() =>
            getComputedStyle(document.documentElement).overflow,
        );
        expect(htmlOverflowAfter).not.toBe('hidden');
    });

    test('Steam3 value renders as plain text without an auto-link (DET-1)', async ({ page }) => {
        // DET-1: mobile Safari + some Android Chromes auto-detect
        // colon-/digit-heavy strings as phone numbers and inject
        // <a href="tel:…"> wrappers, which carry the platform's
        // tap-to-dial color (the "pinkish highlight box" in the
        // audit). The fix is two layers:
        //   1. <meta name="format-detection" content="telephone=no…">
        //      in core/header.tpl is the canonical opt-out and the
        //      assertion below asserts both the meta tag IS present
        //      AND the rendered Steam3 `<dd>` is a single text node
        //      with no anchor descendant.
        //   2. CSS `.drawer a[href^="tel:"]` reset for the Android
        //      variants that ignore the meta tag (defensive belt-
        //      and-suspenders; not asserted here because we'd need
        //      to forcibly inject an anchor to verify, which would
        //      hide the regression we're protecting against).
        //
        // Coverage caveat (#1208 review finding 3): mobile-chromium
        // is Chrome devtools' mobile emulation, not a real iOS Safari
        // — it doesn't replicate the actual auto-link heuristic, so
        // the `.toHaveCount(0)` line below isn't exercising the
        // tap-to-dial bug end-to-end. The meta-tag regex match IS
        // load-bearing (a removal fails the regex), and the no-anchor
        // assertion guards against an upstream change that injects
        // an anchor literally. End-to-end iOS coverage would need a
        // device-real WebKit run, which the suite doesn't have a
        // gate for yet.
        await page.goto('/');
        await seedBan(page, SEED_STEAM, SEED_NICK);
        await page.goto('/index.php?p=banlist');

        // Meta tag is unconditional (set in core/header.tpl).
        const metaTag = page.locator('meta[name="format-detection"]');
        await expect(metaTag).toHaveAttribute('content', /telephone=no/);

        await page
            .locator('#banlist-root .ban-cards [data-testid="drawer-trigger"]')
            .filter({ hasText: SEED_NICK })
            .click();
        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawerRoot).not.toHaveAttribute('data-loading', 'true');

        // Steam3 row is a `<dt>Steam3</dt><dd>[U:1:N]</dd>` pair
        // inside `[data-testid="drawer-ids"]`. The `<dd>` must NOT
        // contain a `<a href="tel:…">` descendant — meta tag opted
        // the document out. The Steam3 `<dt>` only renders when
        // bans.detail returns a non-empty steam_id_3, which the
        // seeded ban does (it goes through SteamID's normalization
        // path).
        const idGrid = drawerRoot.locator('[data-testid="drawer-ids"]');
        await expect(idGrid).toBeVisible();
        const steam3Label = idGrid.locator('dt', { hasText: 'Steam3' });
        await expect(steam3Label).toBeVisible();
        // `dt + dd` selector lands on the <dd> immediately after
        // the matched <dt>. The <dd> contains the value text and a
        // copy button (`[data-copy]`) — the assertion is that there
        // is NO `<a>` descendant (which would mean an auto-detected
        // link slipped in).
        const steam3Value = idGrid.locator('dt:has-text("Steam3") + dd');
        await expect(steam3Value).toBeVisible();
        await expect(steam3Value.locator('a')).toHaveCount(0);
    });
});
