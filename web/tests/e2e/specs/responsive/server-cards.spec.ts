/**
 * Responsive: server card grid scales with screen size (#1316).
 *
 * Public servers list (`?p=servers`) and admin Server Management list
 * (`?p=admin&c=servers`) both render `:prefix_servers` rows as a card
 * grid hydrated by `web/scripts/server-tile-hydrate.js`. The grid is
 * a `repeat(auto-fill, minmax(<min>, 1fr))` layout — the column min
 * is the load-bearing knob that decides "how many cards per row at
 * a given viewport".
 *
 * Pre-#1316 the column min was 20rem (320px) — same width as a phone.
 * Both pages cap their content area at 1400px (the public page via
 * the inline `max-width:1400px` on its outer div, the admin page via
 * `.page-section`'s `max-width: 1400px`), so on EVERY viewport
 * >=1400px the available content area was identical: ~1352px after
 * the 1.5rem padding. With a 320px min the auto-fill packed 4
 * columns at ~338px each — same width as a phone, hostname truncated,
 * and the "31" 4K monitor" reporter saw zero benefit from their wide
 * display.
 *
 * #1316 bumps the column min to 28rem (448px) and moves the rule to
 * the named `.servers-grid` class in `theme.css`. Both surfaces share
 * the same class so a theme fork can override the value in one
 * place. This spec pins the visible contract:
 *
 *   1. The grid uses the `.servers-grid` class on both public and
 *      admin surfaces (catches a regression where one surface
 *      forgets the class or reverts to the old inline style).
 *   2. At desktop widths (1280-1920) each card is at least ~28rem
 *      wide. Pre-fix the cards were ~340px (well under 28rem).
 *   3. At <=768px the grid collapses to a single column so the
 *      one card per row fills the viewport. Pre-fix `auto-fill`
 *      could have dropped a 28rem-min card OFF the right of a
 *      narrow viewport; the mobile single-column override prevents
 *      that.
 *   4. No surface introduces horizontal page scroll at any viewport
 *      from 320px (smallest phone) to 3840px (4K).
 *
 * Project gating: chromium-only. The contract is purely a CSS-layout
 * contract (grid track count + tile bounding boxes), browser-shape-
 * agnostic; the mobile-chromium project's iPhone-13 device descriptor
 * adds no value here, and running both projects against the same
 * `sourcebans_e2e` DB drives the local cross-project truncate race
 * that AGENTS.md "Playwright E2E specifics" calls out (CI pins
 * `workers: 1` so it's safe in CI, but local dev defaults to
 * `workers: undefined` (cpu count) and the second project's
 * truncate-and-reseed wipes the seeded servers out from under the
 * first project's in-flight tile assertions). Mirrors the same
 * `chromium`-only gate from `server-refresh-debounce.spec.ts`. The
 * mobile single-column contract is exercised via
 * `page.setViewportSize({ width: 390, ... })` — Playwright resizes
 * the viewport in place and the CSS media query (`max-width: 768px`)
 * fires the same way it would on a real iPhone-13.
 */

import type { Page } from '@playwright/test';
import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';

const SERVERS_PUBLIC_ROUTE = '/index.php?p=servers';
const SERVERS_ADMIN_ROUTE = '/index.php?p=admin&c=servers';

/** 28rem at the panel's default 16px root font = 448px. */
const COLUMN_MIN_PX = 448;

/**
 * Seed three servers via `Actions.ServersAdd`. The IPs are RFC 5737
 * documentation addresses that never answer A2S, so the cards stay
 * in the loading→offline path and the JS-side hydrate-on-paint
 * contract doesn't race the layout assertions below (the layout
 * assertions are CSS-only; hydration is unrelated). Picked three
 * because that's enough to cover both "cards form a row" (>=2
 * columns at desktop) and "cards wrap onto multiple rows" (>=2
 * rows at narrow desktop) without needing a `data.sql` change.
 *
 * Order doesn't matter — the assertions don't anchor on a specific
 * tile.
 */
async function seedThreeServers(page: Page): Promise<void> {
    // Hit any page first so `sb.api` + `Actions` + the CSRF meta tag
    // are loaded; the JSON dispatcher rejects requests without a
    // CSRF token, and api.js scrapes it from the current document.
    await page.goto('/');
    const seeds = [
        { ip: '203.0.113.10', port: '27015' },
        { ip: '203.0.113.11', port: '27015' },
        { ip: '203.0.113.12', port: '27015' },
    ];
    for (const seed of seeds) {
        const env = await page.evaluate(async (s) => {
            const w = /** @type {any} */ (window) as {
                sb: { api: { call: (action: unknown, params: unknown) => Promise<unknown> } };
                Actions: { ServersAdd: unknown };
            };
            return await w.sb.api.call(w.Actions.ServersAdd, {
                ip: s.ip,
                port: s.port,
                rcon: '',
                rcon2: '',
                mod: 1,
                enabled: true,
                group: '0',
            });
        }, seed);
        // Tolerate `duplicate` if a previous run left the row
        // behind (truncate is the canonical path; this is just
        // belt-and-braces — the API surfaces `duplicate` from
        // `web/api/handlers/servers.php`'s IP:port pre-check).
        const e = env as { ok?: boolean; error?: { code?: string } };
        if (e && e.ok === false && e.error?.code !== 'duplicate') {
            throw new Error(`servers.add seed failed for ${seed.ip}: ${JSON.stringify(env)}`);
        }
    }
}

test.describe('responsive: server card grid (#1316)', () => {
    // Skip on mobile-chromium for the cross-project DB-truncate race
    // reason documented in the file-level comment. The mobile single-
    // column contract is exercised by the last test below via
    // `page.setViewportSize`.
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e (see file-level comment).',
        );
    });

    // Four tests in this file all `truncateE2eDb()` + seed three
    // servers in beforeEach. Without `serial` mode Playwright runs
    // them in parallel workers locally (CI pins `workers: 1`, see
    // playwright.config.ts), and worker B's truncate wipes worker A's
    // seeded rows mid-test, leaving the cards-grid empty when the
    // assertion runs. Same shape as `comms-affordances.spec.ts`'s
    // serial guard. The named-lock in
    // `Sbpp\Tests\Fixture::truncateAndReseed` makes the *reset*
    // atomic per-process, but it doesn't span the gap between the
    // truncate-and-reseed and the per-test seed/assertion phase;
    // serial execution is the right granularity for state-mutating
    // flow specs.
    test.describe.configure({ mode: 'serial' });

    test('public + admin grids use the shared .servers-grid class', async ({ page }) => {
        // The class is the load-bearing single-source rule; if a
        // future template edit drops the class and falls back to
        // inline `style="grid-template-columns:..."` the bump to
        // 28rem silently reverts. Two `[class~=]` checks pin the
        // contract — one per surface.
        await truncateE2eDb();
        await seedThreeServers(page);

        await page.goto(SERVERS_PUBLIC_ROUTE);
        await expect(
            page.locator('[data-testid="servers-list"]'),
            'public servers list grid must carry .servers-grid (#1316)',
        ).toHaveClass(/(?:^|\s)servers-grid(?:\s|$)/);

        await page.goto(SERVERS_ADMIN_ROUTE);
        await expect(
            page.locator('[data-testid="server-grid"]'),
            'admin Server Management list grid must carry .servers-grid (#1316)',
        ).toHaveClass(/(?:^|\s)servers-grid(?:\s|$)/);
    });

    /**
     * Desktop card-width contract: at every viewport from 1280px up to
     * 4K, each card occupies AT LEAST `COLUMN_MIN_PX` (28rem). The
     * pre-#1316 min of 20rem (320px) packed cards into ~340px columns
     * even on a 31" 4K monitor — visibly narrower than 28rem and the
     * direct cause of the truncated-hostname symptom.
     *
     * The four widths cover the realistic monitor lineup:
     *   - 1280: typical laptop. Page-cap doesn't apply (viewport <
     *     1400px); content area = viewport - sidebar - padding.
     *   - 1920: typical desktop. Page-cap kicks in.
     *   - 2560: 1440p monitor.
     *   - 3840: 4K (the reporter's 31" panel).
     *
     * `setViewportSize` updates the layout in place; we re-fetch the
     * tile bounding box after each resize to get the fresh dimensions.
     */
    test('cards stay at least 28rem wide across desktop viewports (1280-3840px)', async ({ page }) => {
        await truncateE2eDb();
        await seedThreeServers(page);

        for (const route of [SERVERS_PUBLIC_ROUTE, SERVERS_ADMIN_ROUTE] as const) {
            await page.goto(route);

            const tile = page.locator('[data-testid="server-tile"]').first();
            await expect(tile).toBeVisible();

            // Walk widths in ascending order so the layout has a
            // monotonic resize history; descending would force the
            // browser to repeatedly re-pack a wider grid down.
            for (const width of [1280, 1920, 2560, 3840] as const) {
                await page.setViewportSize({ width, height: 900 });
                // One frame for the layout to settle in Chromium
                // (the resize triggers a sync layout pass; the rAF
                // here exists to flush any cascading sticky /
                // resize-observer work the chrome does on resize).
                await page.evaluate(() =>
                    new Promise((resolve) => requestAnimationFrame(() => resolve(undefined))),
                );

                const box = await tile.boundingBox();
                expect(
                    box,
                    `server tile must render a bounding box on ${route} at viewport ${width}px`,
                ).not.toBeNull();
                expect(
                    Math.round(box!.width),
                    `server tile must be at least ~${COLUMN_MIN_PX}px wide on ${route} at viewport ${width}px (got ${box!.width}); pre-#1316 the 20rem column min produced ~340px cards across the entire ${'>='}1280px range`,
                ).toBeGreaterThanOrEqual(COLUMN_MIN_PX - 1);

                // No horizontal page scroll at this width — the grid
                // never overflows, even on the narrowest desktop
                // viewport where two 28rem cards just barely fit.
                const overflow = await page.evaluate(() =>
                    document.documentElement.scrollWidth - document.documentElement.clientWidth,
                );
                expect(
                    overflow,
                    `${route} must not introduce horizontal page scroll at viewport ${width}px (got ${overflow}px overflow)`,
                ).toBeLessThanOrEqual(1);
            }
        }
    });

    test('admin grid hydration contract attributes survive the resize', async ({ page }) => {
        // Belt-and-braces against an accidental edit that drops one
        // of the hydration helper's required attributes when moving
        // off the inline style — `web/scripts/server-tile-hydrate.js`
        // walks `[data-server-hydrate="auto"]` containers and reads
        // `data-trunchostname` off them, plus `data-id` /
        // `data-testid="server-tile"` per tile. The class swap in
        // #1316 must not perturb any of these.
        await truncateE2eDb();
        await seedThreeServers(page);

        await page.goto(SERVERS_PUBLIC_ROUTE);
        const publicGrid = page.locator('[data-testid="servers-list"]');
        await expect(publicGrid).toHaveAttribute('data-server-hydrate', 'auto');
        await expect(publicGrid).toHaveAttribute('data-trunchostname', '70');
        await expect(publicGrid).toHaveAttribute('data-opened-index', /-?\d+/);

        await page.goto(SERVERS_ADMIN_ROUTE);
        const adminGrid = page.locator('[data-testid="server-grid"]');
        await expect(adminGrid).toHaveAttribute('data-server-hydrate', 'auto');
        await expect(adminGrid).toHaveAttribute('data-trunchostname', '70');

        // First tile on each surface still carries the data-id +
        // data-testid the hydration helper keys off (the helper
        // skips tiles without `data-id`).
        const firstTile = adminGrid.locator('[data-testid="server-tile"]').first();
        await expect(firstTile).toHaveAttribute('data-id', /^\d+$/);
    });

    test('grid collapses to a single column at narrow (iPhone-13-like) viewport', async ({ page }) => {
        // The mobile rule is `.servers-grid { grid-template-columns:
        // minmax(0, 1fr); }` at <=768px in `theme.css`. Without the
        // override, a 28rem (448px) min on a 390px iPhone-13
        // viewport would force the first card to overflow the right
        // of the page (auto-fill can't shrink the min below the
        // declared value). Bare `1fr` would also overflow because
        // it's shorthand for `minmax(auto, 1fr)` — the `auto`
        // minimum resolves to the card's min-content size, which
        // includes the `truncate` IP:port descendants and inflates
        // back past the 390px viewport. `minmax(0, 1fr)` is the
        // only shape that lets the track shrink to the container.
        // Verify the fallback is in effect.
        //
        // We resize the chromium project's viewport to 390x844 (the
        // iPhone-13 logical viewport) instead of running this test
        // on the mobile-chromium project — the contract is purely
        // CSS (grid track count + bounding box bounds) and the
        // cross-project parallelism would race the truncate. See
        // the file-level comment for the full rationale.
        await truncateE2eDb();
        await seedThreeServers(page);

        await page.setViewportSize({ width: 390, height: 844 });

        for (const route of [SERVERS_PUBLIC_ROUTE, SERVERS_ADMIN_ROUTE] as const) {
            await page.goto(route);
            // One frame for layout to settle after the navigation
            // (the resize was already done above; the goto triggers
            // a fresh layout pass that needs to flush before we
            // measure).
            await page.evaluate(() =>
                new Promise((resolve) => requestAnimationFrame(() => resolve(undefined))),
            );

            // The grid container's resolved
            // `grid-template-columns` reads as a single track at
            // <=768px. Browsers serialize `1fr` as a `<length>` in
            // px on Chromium (e.g. "390px") because the fr unit is
            // resolved to its computed pixel value at read time.
            // Anchor on the track count: a single-column layout
            // serializes to ONE space-separated value; a multi-
            // column layout serializes to N.
            const containerSelector = route === SERVERS_PUBLIC_ROUTE
                ? '[data-testid="servers-list"]'
                : '[data-testid="server-grid"]';
            const trackCount = await page.locator(containerSelector).evaluate((el) => {
                const cols = getComputedStyle(el).gridTemplateColumns.trim();
                return cols ? cols.split(/\s+/).length : 0;
            });
            expect(
                trackCount,
                `${route} must collapse to a single grid column at 390px width (got ${trackCount} tracks)`,
            ).toBe(1);

            // The first tile must fit inside the viewport
            // horizontally — pre-mobile-rule the 28rem column min
            // would have left ~58px of card overhang at 390px.
            const tile = page.locator('[data-testid="server-tile"]').first();
            await expect(tile).toBeVisible();
            const box = await tile.boundingBox();
            const vw = page.viewportSize()?.width ?? 0;
            expect(box, `first tile must render a bounding box on ${route}`).not.toBeNull();
            expect(
                box!.x,
                `first tile must not start off the left of the viewport on ${route} (got x=${box!.x})`,
            ).toBeGreaterThanOrEqual(-1);
            expect(
                box!.x + box!.width,
                `first tile must not extend past the viewport's right edge on ${route} (tile right=${box!.x + box!.width}, viewport=${vw})`,
            ).toBeLessThanOrEqual(vw + 1);

            // Page-level: nothing in the chrome leaks past the
            // viewport horizontally either.
            const overflow = await page.evaluate(() =>
                document.documentElement.scrollWidth - document.documentElement.clientWidth,
            );
            expect(
                overflow,
                `${route} must not introduce horizontal page scroll at 390px width (got ${overflow}px overflow)`,
            ).toBeLessThanOrEqual(1);
        }
    });
});
