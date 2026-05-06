/**
 * Responsive: admin sub-tab strip (#1207 ADM-8).
 *
 * iPhone-13 viewport contract:
 *   - The intra-page admin tab strip ("Add a ban · Ban protests ·
 *     Ban submissions · Import bans" on `?p=admin&c=bans`, plus
 *     the matching strip on `&c=admins` / `&c=mods` / `&c=groups`
 *     / `&c=settings`) renders on a SINGLE horizontally-scrollable
 *     line at <=768px instead of wrapping onto multiple lines —
 *     wrapping was the source of the audit's "active-tab orange
 *     underline sits under the wrapped second line" complaint.
 *   - The active tab gets a chip-style background (orange in light,
 *     brand-500 in dark) so the active state is visible at a glance
 *     even while the underline is partly out of view.
 *   - Every tab is reachable via `scrollIntoViewIfNeeded` once the
 *     strip is the focused scrollable surface.
 *
 * Markup contract: `core/admin_tabs.tpl` renders
 *   <div class="admin-tabs flex gap-2 mb-4 items-center">
 *     <button data-testid="admin-tab-<name>" [aria-current="page" if active]>
 *       <name>
 *     </button>
 *     …
 *     <a class="admin-tabs__back" data-testid="admin-tab-back">…</a>
 *   </div>
 * The CSS at theme.css's #1207 block flips `flex-wrap: nowrap` +
 * `overflow-x: auto` and gives the active button the chip background.
 *
 * Project gating: mobile-chromium only.
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('responsive: admin tab strip', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('admin-bans tabs render on a single scrollable line at iPhone-13 width', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=bans');

        // `data-testid="admin-tabs"` is the primary hook on the
        // wrapper (#1123 contract); the `.first()` is defensive
        // because edit-* pages render an empty-tabs strip alongside
        // a populated one in some flows. See admin_tabs.tpl for the
        // markup.
        const strip = page.locator('[data-testid="admin-tabs"]').first();
        await expect(strip).toBeVisible();

        // The tabs the audit's "Add a ban · Ban protests · Ban
        // submissions · Import bans" call-out enumerates. `Group
        // ban` is gated on `Config::getBool('config.enablegroupbanning')`
        // and absent by default — we don't include it here so the
        // assert lands on the same tabs the issue documents.
        const tabIds = ['Add a ban', 'Ban protests', 'Ban submissions', 'Import bans'];
        const firstTab = strip.locator(`[data-testid="admin-tab-${tabIds[0]}"]`);
        const firstBox = await firstTab.boundingBox();
        expect(firstBox, `${tabIds[0]} tab must render a bounding box`).not.toBeNull();

        for (const id of tabIds.slice(1)) {
            const tab = strip.locator(`[data-testid="admin-tab-${id}"]`);
            await expect(tab).toBeAttached();
            const box = await tab.boundingBox();
            expect(box, `${id} tab must render a bounding box`).not.toBeNull();
            // All tabs share the same y-axis position — i.e. on a
            // single line. The pre-fix shape wrapped after ~2 tabs
            // and the second line was 30+ px lower. Allow a 2px
            // tolerance for sub-pixel layout differences.
            expect(Math.abs(box!.y - firstBox!.y)).toBeLessThanOrEqual(2);
        }

        // The strip itself fits the viewport (the inner content
        // CAN exceed it — that's what the horizontal scroll is for —
        // but the wrapper element still respects the viewport bounds).
        const vw = page.viewportSize()?.width ?? 0;
        const stripBox = await strip.boundingBox();
        expect(stripBox, 'admin-tabs wrapper must render a bounding box').not.toBeNull();
        expect(stripBox!.x).toBeGreaterThanOrEqual(-1);
        expect(stripBox!.x + stripBox!.width).toBeLessThanOrEqual(vw + 1);

        // The strip has horizontal scroll available — `scrollWidth`
        // is greater than `clientWidth` by definition once the tabs
        // exceed the viewport. The pre-fix shape had `scrollWidth ==
        // clientWidth` because flex-wrap relayed onto multiple lines
        // (no horizontal scroll).
        const overflow = await strip.evaluate((el) => ({
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
        }));
        expect(overflow.scrollWidth).toBeGreaterThanOrEqual(overflow.clientWidth);

        // Last tab is reachable after a horizontal scroll.
        const lastTab = strip.locator(`[data-testid="admin-tab-${tabIds[tabIds.length - 1]}"]`);
        await lastTab.scrollIntoViewIfNeeded();
        const lastBox = await lastTab.boundingBox();
        expect(lastBox, 'last tab must render a bounding box once scrolled into view').not.toBeNull();
        expect(lastBox!.x).toBeGreaterThanOrEqual(-1);
        expect(lastBox!.x + lastBox!.width).toBeLessThanOrEqual(vw + 1);
    });

    /**
     * Assert the active admin-tab carries the chip-style brand-orange
     * background at the iPhone-13 viewport. Shared between the light
     * and dark theme tests — both variants resolve to the brand orange
     * family (`--brand-600` in light = rgb(234, 88, 12); `--brand-500`
     * in dark = rgb(249, 115, 22)). The R/G/B heuristic below tolerates
     * both, which is the actual contract: "active tab is orange-ish
     * regardless of theme", not "active tab is exactly hex X".
     * @param {import('@playwright/test').Page} page
     */
    async function assertActiveTabIsBrandOrange(
        page: import('@playwright/test').Page,
    ): Promise<void> {
        // Scope by `[data-testid="admin-tabs"]` (the wrapper hook
        // added in #1123 testability-hooks pass), then pivot on the
        // ARIA `[aria-current="page"]` attribute — that's the
        // load-bearing identifier for "active tab" on this strip.
        const activeTab = page.locator('[data-testid="admin-tabs"] > [aria-current="page"]').first();
        await expect(activeTab).toBeVisible();

        const bgColor = await activeTab.evaluate((el) =>
            getComputedStyle(el).backgroundColor,
        );
        const match = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        expect(match, `unexpected background color: ${bgColor}`).not.toBeNull();
        const [, rStr, gStr, bStr] = match!;
        const r = parseInt(rStr, 10);
        const g = parseInt(gStr, 10);
        const b = parseInt(bStr, 10);
        // Brand orange family: red dominates, blue is small.
        expect(r).toBeGreaterThan(180);
        expect(r).toBeGreaterThan(g);
        expect(r).toBeGreaterThan(b);
        expect(b).toBeLessThan(60);
    }

    test('active tab carries the chip-style background at iPhone-13 width (light)', async ({ page }) => {
        // First tab ("Add a ban") is the default-active one when
        // the page lands on `?p=admin&c=bans` without a `&tab=…`
        // qualifier (AdminTabs.php's "first accessible tab is
        // active" fallback). It carries `aria-current="page"` per
        // admin_tabs.tpl + AdminTabs.php's `$resolvedActive` path.
        // The chip-style background at <=768px maps to `var(--brand-600)`
        // in light theme — that's the orange CTA token (#ea580c).
        await page.goto('/index.php?p=admin&c=bans');
        await assertActiveTabIsBrandOrange(page);
    });

    test('active tab keeps the chip-style background under html.dark', async ({ page }) => {
        // Slice 1 review finding 3: the dark-theme override
        // (`html.dark .admin-tabs > [aria-current="page"] { background: var(--brand-500); … }`
        // in theme.css) was previously unlocked — a token rename
        // would silently regress the dark variant. Seed the theme
        // preference into localStorage before the first navigation
        // so theme.js's init-time `applyTheme(currentTheme())`
        // resolves to `html.dark` on first paint (no toggle click
        // needed, no race with the post-paint click handler).
        await page.addInitScript(() => {
            try { localStorage.setItem('sbpp-theme', 'dark'); } catch (e) { /* ignore */ }
        });
        await page.goto('/index.php?p=admin&c=bans');
        await expect(page.locator('html')).toHaveClass(/(^|\s)dark(\s|$)/);
        await assertActiveTabIsBrandOrange(page);
    });
});
