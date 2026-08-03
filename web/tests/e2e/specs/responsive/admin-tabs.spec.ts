/**
 * Responsive: admin section accordion in the main sidebar (#1490).
 *
 * Pre-#1490 Pattern A pages mounted a second rail
 * (`core/admin_sidebar.tpl` via AdminTabs). That double menu wasted
 * content width. Section links now nest under each admin category in
 * the main sidebar as a `<details class="sidebar__accordion">`.
 *
 * Contract:
 *   - Category with children → accordion open when `?c=` matches
 *   - Leaf links: `data-testid="nav-admin-<endpoint>-<slug>"`
 *   - Active leaf carries `aria-current="page"`
 *   - Feature-gated bans sections stay omitted when toggles are off
 *
 * Project gating: mobile-chromium only. Accordion links live inside
 * `#sidebar`, which is off-canvas until the hamburger opens it — every
 * test calls `openMobileSidebar` after navigation.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { openMobileSidebar } from '../../fixtures/sidebar.ts';

test.describe('responsive: admin sidebar accordion', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('admin-servers accordion renders nested section links at iPhone-13 width', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=servers');
        await openMobileSidebar(page);

        const accordion = page.locator('[data-testid="nav-admin-servers-accordion"]');
        await expect(accordion).toBeVisible();
        await expect(accordion).toHaveAttribute('open', '');

        const tabSlugs = ['list', 'add'];
        for (const slug of tabSlugs) {
            const tab = page.locator(`[data-testid="nav-admin-servers-${slug}"]`);
            await expect(tab).toBeAttached();
            await expect(tab).toBeVisible();
        }

        const summary = page.locator('[data-testid="nav-admin-servers"]');
        await expect(summary).toBeVisible();

        const firstTab = page.locator(`[data-testid="nav-admin-servers-${tabSlugs[0]}"]`);
        const secondTab = page.locator(`[data-testid="nav-admin-servers-${tabSlugs[1]}"]`);
        const firstBox = await firstTab.boundingBox();
        const secondBox = await secondTab.boundingBox();
        expect(firstBox, `${tabSlugs[0]} tab must render a bounding box`).not.toBeNull();
        expect(secondBox, `${tabSlugs[1]} tab must render a bounding box`).not.toBeNull();
        expect(secondBox!.y).toBeGreaterThan(firstBox!.y + 16);
        expect(Math.abs(secondBox!.x - firstBox!.x)).toBeLessThanOrEqual(2);

        await expect(page.locator('[data-testid="admin-sidebar-shell"]')).toHaveCount(0);
    });

    test('admin-servers accordion can be toggled closed at iPhone-13 width', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=servers');
        await openMobileSidebar(page);

        const details = page.locator('[data-testid="nav-admin-servers-accordion"]');
        const summary = page.locator('[data-testid="nav-admin-servers"]');
        const firstLink = page.locator('[data-testid="nav-admin-servers-list"]');

        await expect(details).toHaveAttribute('open', '');
        await expect(firstLink).toBeVisible();

        await summary.click();

        await expect(details).not.toHaveAttribute('open', /.*/);
        await expect(firstLink).toBeHidden();

        await summary.click();
        await expect(details).toHaveAttribute('open', '');
        await expect(firstLink).toBeVisible();
    });

    async function assertActiveLinkIsHighlighted(
        page: import('@playwright/test').Page,
        theme: 'light' | 'dark',
    ): Promise<void> {
        await openMobileSidebar(page);

        const activeLink = page.locator(
            '[data-testid="nav-admin-servers-accordion"] [aria-current="page"]',
        ).first();
        await expect(activeLink).toBeVisible();

        const bgColor = await activeLink.evaluate((el) =>
            getComputedStyle(el).backgroundColor,
        );
        const match = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        expect(match, `unexpected background color: ${bgColor}`).not.toBeNull();
        const [, rStr, gStr, bStr] = match!;
        const r = parseInt(rStr, 10);
        const g = parseInt(gStr, 10);
        const b = parseInt(bStr, 10);

        if (theme === 'dark') {
            expect(r).toBeGreaterThan(180);
            expect(r).toBeGreaterThan(g);
            expect(r).toBeGreaterThan(b);
            expect(b).toBeLessThan(60);
        } else {
            expect(r).toBeLessThan(60);
            expect(g).toBeLessThan(60);
            expect(b).toBeLessThan(60);
        }
    }

    test('active link carries the highlighted background at iPhone-13 width (light)', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=servers');
        await assertActiveLinkIsHighlighted(page, 'light');
    });

    test('active link keeps the highlighted background under html.dark', async ({ page }) => {
        await page.addInitScript(() => {
            try { localStorage.setItem('sbpp-theme', 'dark'); } catch (e) { /* ignore */ }
        });
        await page.goto('/index.php?p=admin&c=servers');
        await expect(page.locator('html')).toHaveClass(/(^|\s)dark(\s|$)/);
        await assertActiveLinkIsHighlighted(page, 'dark');
    });

    /**
     * @typedef {Object} PatternASpec
     * @property {string} route
     * @property {string[]} slugs
     * @property {string} active
     */
    /** @type {PatternASpec[]} */
    const patternARoutes = [
        { route: 'admins', slugs: ['admins', 'add-admin', 'overrides'], active: 'admins' },
        { route: 'bans',   slugs: ['add-ban', 'protests', 'submissions', 'import'], active: 'add-ban' },
    ];

    for (const spec of patternARoutes) {
        test(`admin-${spec.route} accordion renders all sections at iPhone-13 width`, async ({ page }) => {
            await page.goto(`/index.php?p=admin&c=${spec.route}`);
            await openMobileSidebar(page);

            const accordion = page.locator(`[data-testid="nav-admin-${spec.route}-accordion"]`);
            await expect(accordion).toBeVisible();

            for (const slug of spec.slugs) {
                const tab = page.locator(`[data-testid="nav-admin-${spec.route}-${slug}"]`);
                await expect(tab, `admin-${spec.route} accordion must expose ?section=${slug}`).toBeAttached();
                await expect(tab).toBeVisible();
            }

            const activeTab = page.locator(`[data-testid="nav-admin-${spec.route}-${spec.active}"]`);
            await expect(activeTab).toHaveAttribute('aria-current', 'page');
        });

        test(`admin-${spec.route} accordion navigates between sections without scrolling`, async ({ page }) => {
            await page.goto(`/index.php?p=admin&c=${spec.route}`);
            await openMobileSidebar(page);

            const targetSlug = spec.slugs.find((s) => s !== spec.active) ?? spec.slugs[1];
            const link = page.locator(`[data-testid="nav-admin-${spec.route}-${targetSlug}"]`);
            await expect(link).toBeVisible();
            await link.click();

            await expect(page).toHaveURL(new RegExp(`section=${targetSlug.replace(/-/g, '\\-')}`));
            // Post-nav the drawer may close; aria-current is on the
            // attached leaf regardless of off-canvas visibility.
            await expect(page.locator(`[data-testid="nav-admin-${spec.route}-${targetSlug}"]`))
                .toHaveAttribute('aria-current', 'page');
        });
    }

    test('admin-bans group-ban tab is omitted when config.enablegroupbanning is off', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=bans');
        await openMobileSidebar(page);

        const accordion = page.locator('[data-testid="nav-admin-bans-accordion"]');
        await expect(accordion).toBeVisible();
        await expect(page.locator('[data-testid="nav-admin-bans-add-ban"]')).toBeVisible();
        await expect(page.locator('[data-testid="nav-admin-bans-group-ban"]')).toHaveCount(0);
    });
});
