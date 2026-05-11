/**
 * Loading-indicator contract for the player drawer's in-flight states.
 *
 * The drawer surfaces two distinct loading windows the user has to be
 * able to *see* something happening for, not just notice a blank panel:
 *
 *   1. Initial drawer open — the click fires `Actions.BansDetail`. Until
 *      the envelope returns, the drawer paints `renderDrawerLoading()`
 *      (the `.skel` shimmer rows under `[data-testid="drawer-loading"]`).
 *      Pre-fix the function emitted `class="skeleton"` (singular), which
 *      had NO matching CSS rule — the shimmer divs rendered with zero
 *      background and the operator saw a literal blank pane for the
 *      100-1000ms `bans.detail` took to resolve. The CSS the markup
 *      was meant to hit has always been `.skel` (used by the banlist
 *      skeleton hook); the renderer just hadn't matched.
 *
 *   2. Lazy pane activation — clicking History / Comms / Notes for the
 *      first time fires the matching `*.player_history` / `notes.list`
 *      action. Pre-fix the panel started with bare "Loading…" text,
 *      which read as static copy. The shared `renderPaneSkeleton()`
 *      helper now drops the same `.skel` shimmer rows the drawer header
 *      uses so all four panes share one loading vocabulary.
 *
 * Both windows are gated by `page.route('**\/api.php', …)` stalls so the
 * assertion window doesn't depend on slow test infrastructure.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

const BANLIST_ROUTE = '/index.php?p=banlist';

const FIXTURE = {
    steam: 'STEAM_0:1:1207050',
    nickname: 'e2e-drawer-loading',
    reason: 'e2e: drawer-loading indicator',
    lengthMinutes: 60,
};

test.describe('flow: drawer loading indicator', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('initial drawer open paints visible .skel shimmer until bans.detail resolves', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'desktop is the canonical surface; the .skel rule is viewport-independent');

        const seeded = await seedBanViaApi(page, {
            steam: FIXTURE.steam,
            nickname: FIXTURE.nickname,
            reason: FIXTURE.reason,
            length: FIXTURE.lengthMinutes,
        });

        let releaseRoute: (() => void) | null = null;
        const routeStalled = new Promise<void>((resolve) => {
            void page.route('**/api.php', async (route) => {
                let body: unknown = null;
                try {
                    body = JSON.parse(route.request().postData() || '{}');
                } catch {
                    body = null;
                }
                const action = (body as { action?: string } | null)?.action;
                if (action !== 'bans.detail') {
                    await route.continue();
                    return;
                }
                resolve();
                await new Promise<void>((releaseInner) => {
                    releaseRoute = releaseInner;
                });
                await route.continue();
            });
        });

        await page.goto(BANLIST_ROUTE);

        const drawerRoot = page.locator('#drawer-root');
        await expect(drawerRoot).toHaveAttribute('data-drawer-open', 'false');

        const trigger = page
            .locator(`[data-testid="drawer-trigger"][data-drawer-href*="id=${seeded.bid}"]`)
            .first();
        await trigger.click();
        // Without page.route's stall, `bans.detail` resolves before we
        // can probe the skeleton; the await on `routeStalled` below is
        // the synchronisation point.

        await routeStalled;

        // ---- The contract: the skeleton paints visibly ----
        // [data-testid="drawer-loading"] is the header marker, and
        // [data-skeleton] tags every `.skel` block inside it. Both
        // are visible by virtue of `.skel`'s gradient + shimmer —
        // an opacity readback proves the regression that re-renames
        // to `class="skeleton"` would NOT pass (the unstyled class
        // has no background so the readback fails the bounding-box
        // assertion).
        const loadingHeader = drawerRoot.locator('[data-testid="drawer-loading"]');
        await expect(loadingHeader).toBeVisible();
        await expect(loadingHeader).toHaveAttribute('aria-busy', 'true');

        const skel = drawerRoot.locator('[data-skeleton]').first();
        await expect(skel).toBeVisible();
        // The block has to have a non-zero painted area — the prior
        // `.skeleton` typo rendered transparent so width was non-zero
        // but the *visual* skeleton was missing. A computed-style
        // probe against `background-image` catches both shapes: the
        // matching `.skel` rule sets a `linear-gradient(...)`, the
        // missing rule leaves it at the UA default `none`.
        const bg = await skel.evaluate((el) => getComputedStyle(el).backgroundImage);
        expect(
            bg,
            'skeleton block paints a linear-gradient shimmer (regression guard for the class="skeleton" typo)',
        ).toContain('linear-gradient');

        // Release bans.detail → drawer flips to renderDrawerBody.
        if (releaseRoute) releaseRoute();
        else throw new Error('releaseRoute was never wired');

        await expect(drawerRoot).not.toHaveAttribute('data-loading', /.+/);
        await expect(drawerRoot.locator('[data-testid="drawer-panel-overview"]')).toBeVisible();
        await expect(drawerRoot.locator('[data-testid="drawer-loading"]')).toHaveCount(0);
    });

    test('opening History pane paints .skel shimmer until bans.player_history resolves', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'desktop is the canonical surface; the .skel rule is viewport-independent');

        const seeded = await seedBanViaApi(page, {
            steam: FIXTURE.steam.replace(':1207050', ':1207051'),
            nickname: FIXTURE.nickname + '-pane',
            reason: FIXTURE.reason,
            length: FIXTURE.lengthMinutes,
        });

        // Let bans.detail through unstalled — we only want to stall
        // the pane-level fetch so the test's window is the pane skeleton,
        // not the drawer header skeleton (the previous test covers that).
        let releaseRoute: (() => void) | null = null;
        const routeStalled = new Promise<void>((resolve) => {
            void page.route('**/api.php', async (route) => {
                let body: unknown = null;
                try {
                    body = JSON.parse(route.request().postData() || '{}');
                } catch {
                    body = null;
                }
                const action = (body as { action?: string } | null)?.action;
                if (action !== 'bans.player_history') {
                    await route.continue();
                    return;
                }
                resolve();
                await new Promise<void>((releaseInner) => {
                    releaseRoute = releaseInner;
                });
                await route.continue();
            });
        });

        await page.goto(BANLIST_ROUTE);

        const drawerRoot = page.locator('#drawer-root');
        const trigger = page
            .locator(`[data-testid="drawer-trigger"][data-drawer-href*="id=${seeded.bid}"]`)
            .first();
        await trigger.click();
        await expect(drawerRoot).not.toHaveAttribute('data-loading', /.+/);

        // Click the History tab — fires bans.player_history.
        await drawerRoot.locator('[data-testid="drawer-tab-history"]').click();
        await routeStalled;

        // The pane was created with the placeholder skeleton; clicking
        // the tab activates the panel + the lazy loader flips
        // `data-loading="true"` on the panel itself.
        const panel = drawerRoot.locator('[data-testid="drawer-panel-history"]');
        await expect(panel).toBeVisible();
        await expect(panel).toHaveAttribute('data-loading', 'true');

        const emptyMarker = panel.locator('[data-pane-empty]');
        await expect(emptyMarker).toBeVisible();
        await expect(emptyMarker).toHaveAttribute('aria-busy', 'true');

        const skelInPanel = panel.locator('.skel').first();
        await expect(skelInPanel).toBeVisible();
        const bg = await skelInPanel.evaluate((el) => getComputedStyle(el).backgroundImage);
        expect(
            bg,
            'history pane skeleton paints a linear-gradient shimmer (the visual loading contract)',
        ).toContain('linear-gradient');

        // Release bans.player_history → pane swaps in the empty state
        // (the seeded ban is the player's only ban so the history list
        // is intentionally empty — the empty-state heading is the
        // post-load assertion).
        if (releaseRoute) releaseRoute();
        else throw new Error('releaseRoute was never wired');

        await expect(panel).not.toHaveAttribute('data-loading', /.+/);
        await expect(panel.locator('[data-testid="drawer-history-heading"]')).toBeVisible();
        await expect(panel.locator('[data-pane-empty]')).toHaveCount(0);
    });
});
