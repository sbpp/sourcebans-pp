/**
 * Responsive: admin sub-section sidebar (#1259, replacing the
 * horizontal strip contract from #1207 ADM-8 / #1239; #1275 brought
 * admin-admins and admin-bans onto the same shape).
 *
 * iPhone-13 viewport contract for every Pattern A admin page
 * (servers / mods / groups / settings / admins / bans — every page
 * that subdivides its chrome via `?section=…` URLs):
 *
 *   - Pre-#1259 these routes rendered a horizontal `core/admin_tabs.tpl`
 *     pill strip (servers / mods / groups) AND a separate inline
 *     14rem `<nav>` block for settings. Two patterns, same routing
 *     shape, completely different chrome. #1259 unifies them on the
 *     Settings-style vertical sidebar partial `core/admin_sidebar.tpl`,
 *     mounted by `AdminTabs.php` whenever `$tabs` is non-empty.
 *   - Pre-#1275 admin-admins and admin-bans rode a parallel "Pattern B"
 *     page-level ToC (`page_toc.tpl`) that emitted `#fragment` URLs
 *     and scrolled within a single long-scroll DOM. #1275 collapsed
 *     them onto the same `?section=…` shape so the URL is the
 *     **only** sub-route nav contract on the panel.
 *   - At <=1024px the sidebar collapses to a `<details open>`
 *     accordion. The summary chrome carries a chevron that rotates
 *     180° on toggle.
 *   - At >=1024px the sidebar floats next to the content column as
 *     a sticky 14rem rail (`grid-template-columns: 14rem 1fr`).
 *     This file's project-gating is mobile-only — the desktop
 *     shape is exercised by the screenshot gallery and ad-hoc
 *     `chromium` runs of these specs.
 *   - The active link still carries `aria-current="page"` and
 *     reuses the shared `.sidebar__link[aria-current="page"]` rule
 *     — dark-pill in light theme, brand-orange in dark — so the
 *     highlight is single-source with the main app shell.
 *
 * Markup contract (`core/admin_sidebar.tpl`, mounted by
 * `AdminTabs.php` when `$tabs !== []`):
 *
 *   <div class="admin-sidebar-shell" data-testid="admin-sidebar-shell">
 *     <aside class="admin-sidebar"
 *            data-testid="admin-sidebar"
 *            aria-label="<sidebar label>">
 *       <details class="admin-sidebar__details" open>
 *         <summary class="admin-sidebar__summary">…</summary>
 *         <nav class="admin-sidebar__nav">
 *           <a class="sidebar__link admin-sidebar__link"
 *              href="?p=admin&c=…&section=…"
 *              data-testid="admin-tab-<slug>"
 *              [aria-current="page" if active]>…</a>
 *           …
 *         </nav>
 *       </details>
 *     </aside>
 *     <div class="admin-sidebar-content">
 *       <!-- page View(s) render here -->
 *     </div>
 *   </div>
 *
 * The per-link `data-testid="admin-tab-<slug>"` matches the legacy
 * `core/admin_tabs.tpl` strip's hook so any spec that anchored on
 * `admin-tab-<slug>` keeps working.
 *
 * Project gating: mobile-chromium only.
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('responsive: admin sub-section sidebar', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — see file-level comment.',
        );
    });

    test('admin-servers sidebar renders as an accordion at iPhone-13 width', async ({ page }) => {
        // `?p=admin&c=servers` is the canonical Pattern A page after
        // #1239 (#1259 lifted the strip onto the sidebar partial) —
        // two sections ("List servers", "Add new server") wired
        // through `?section=list|add`.
        await page.goto('/index.php?p=admin&c=servers');

        // The shell is the grid host; at <1024px it collapses to a
        // single column so the sidebar paints inline above the
        // content column.
        const shell = page.locator('[data-testid="admin-sidebar-shell"]');
        await expect(shell).toBeVisible();

        const sidebar = page.locator('[data-testid="admin-sidebar"]');
        await expect(sidebar).toBeVisible();

        // Sections rendered for an OWNER user (the seeded admin/admin
        // holds OWNER, so every permission-gated section is visible).
        const tabSlugs = ['list', 'add'];
        for (const slug of tabSlugs) {
            const tab = sidebar.locator(`[data-testid="admin-tab-${slug}"]`);
            await expect(tab).toBeAttached();
            await expect(tab).toBeVisible();
        }

        // The accordion summary is mobile-only chrome — visible at
        // <1024px, hidden at desktop.
        const summary = sidebar.locator('.admin-sidebar__summary');
        await expect(summary).toBeVisible();

        // The link list paints as a vertical stack — the second link
        // sits below the first by at least one row's worth of pixels.
        // The pre-#1259 shape was a horizontal strip where every link
        // shared the same y-axis (the failure mode this assertion
        // guards against).
        const firstTab = sidebar.locator(`[data-testid="admin-tab-${tabSlugs[0]}"]`);
        const secondTab = sidebar.locator(`[data-testid="admin-tab-${tabSlugs[1]}"]`);
        const firstBox = await firstTab.boundingBox();
        const secondBox = await secondTab.boundingBox();
        expect(firstBox, `${tabSlugs[0]} tab must render a bounding box`).not.toBeNull();
        expect(secondBox, `${tabSlugs[1]} tab must render a bounding box`).not.toBeNull();
        // The second link is below the first by at least 16px (one
        // padded row) and shares the same x-coordinate (vertical
        // stack). Allow 2px tolerance on x for sub-pixel layout.
        expect(secondBox!.y).toBeGreaterThan(firstBox!.y + 16);
        expect(Math.abs(secondBox!.x - firstBox!.x)).toBeLessThanOrEqual(2);

        // The shell stays inside the viewport — the accordion is a
        // single column at this width, no horizontal scroll.
        const vw = page.viewportSize()?.width ?? 0;
        const shellBox = await shell.boundingBox();
        expect(shellBox, 'admin-sidebar-shell wrapper must render a bounding box').not.toBeNull();
        expect(shellBox!.x).toBeGreaterThanOrEqual(-1);
        expect(shellBox!.x + shellBox!.width).toBeLessThanOrEqual(vw + 1);
    });

    test('admin-servers accordion can be toggled closed at iPhone-13 width', async ({ page }) => {
        // The mobile shape is `<details open>` — the link list is
        // visible by default and the user can collapse it via the
        // chevron summary. Verify the toggle closes the link list
        // without breaking the rest of the page.
        await page.goto('/index.php?p=admin&c=servers');

        const sidebar = page.locator('[data-testid="admin-sidebar"]');
        const details = sidebar.locator('.admin-sidebar__details');
        const summary = sidebar.locator('.admin-sidebar__summary');
        const firstLink = sidebar.locator('[data-testid="admin-tab-list"]');

        await expect(details).toHaveAttribute('open', '');
        await expect(firstLink).toBeVisible();

        await summary.click();

        // After collapsing, the `open` attribute is removed. The link
        // list is technically still in the DOM but `<details>` hides
        // its children so Playwright's visibility check returns false.
        await expect(details).not.toHaveAttribute('open', /.*/);
        await expect(firstLink).toBeHidden();

        // Toggle back open so any subsequent debugging artefacts
        // (screenshots / traces) capture the default state.
        await summary.click();
        await expect(details).toHaveAttribute('open', '');
        await expect(firstLink).toBeVisible();
    });

    /**
     * Assert the active sidebar link carries the brand-orange highlight
     * at the iPhone-13 viewport. Shared between the light and dark
     * theme tests — the active token differs by theme:
     *   - light: `var(--zinc-900)` (dark pill on light background)
     *   - dark:  `var(--brand-700)` (orange pill on dark background)
     * The R/G/B heuristic below tolerates either: "active link is
     * highlighted with a saturated colour, not the surrounding text
     * colour", which is what users see.
     * @param {import('@playwright/test').Page} page
     * @param {'light' | 'dark'} theme
     */
    async function assertActiveLinkIsHighlighted(
        page: import('@playwright/test').Page,
        theme: 'light' | 'dark',
    ): Promise<void> {
        // Scope by `[data-testid="admin-sidebar"]` (the new wrapper
        // hook for #1259), then pivot on the ARIA `[aria-current="page"]`
        // attribute — that's the load-bearing identifier for the
        // active section. Use `.first()` because the parent app
        // shell carries its own sidebar with active links too; we
        // want the admin sub-section sidebar.
        const activeLink = page.locator(
            '[data-testid="admin-sidebar"] [aria-current="page"]',
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
            // Brand orange family in dark: red dominates, blue is small.
            expect(r).toBeGreaterThan(180);
            expect(r).toBeGreaterThan(g);
            expect(r).toBeGreaterThan(b);
            expect(b).toBeLessThan(60);
        } else {
            // Zinc-900 in light: very dark, all three channels < 60.
            // The contrast contract is "active is dark on light" — any
            // saturated dark colour passes. Specifically rgb(24, 24, 27)
            // for `--zinc-900`, but tolerating drift up to ~60.
            expect(r).toBeLessThan(60);
            expect(g).toBeLessThan(60);
            expect(b).toBeLessThan(60);
        }
    }

    test('active link carries the highlighted background at iPhone-13 width (light)', async ({ page }) => {
        // The first section ("List servers", slug "list") is the
        // default-active one when the page lands on
        // `?p=admin&c=servers` without an explicit `&section=…` —
        // see admin.servers.php's "first accessible section is
        // active" fallback. It carries `aria-current="page"` per
        // admin_sidebar.tpl + AdminTabs.php's `$resolvedActive` path.
        await page.goto('/index.php?p=admin&c=servers');
        await assertActiveLinkIsHighlighted(page, 'light');
    });

    test('active link keeps the highlighted background under html.dark', async ({ page }) => {
        // The dark-theme override
        // (`html.dark .sidebar__link[aria-current="page"] { background:
        // var(--brand-700); … }` in theme.css) is the brand-orange
        // family. Seed the theme preference into localStorage before
        // the first navigation so theme.js's init-time
        // `applyTheme(currentTheme())` resolves to `html.dark` on
        // first paint (no toggle click needed, no race with the
        // post-paint click handler).
        await page.addInitScript(() => {
            try { localStorage.setItem('sbpp-theme', 'dark'); } catch (e) { /* ignore */ }
        });
        await page.goto('/index.php?p=admin&c=servers');
        await expect(page.locator('html')).toHaveClass(/(^|\s)dark(\s|$)/);
        await assertActiveLinkIsHighlighted(page, 'dark');
    });

    /*
     * #1275 — admin-admins and admin-bans now ride Pattern A. Cover
     * each route's sidebar with the same accordion / link-list / active-
     * link contract the canonical servers test asserts above. The
     * tests are parametric so adding a new Pattern A admin route is a
     * single-line entry, not a full spec copy.
     *
     * `slugs` lists the entries the AdminTabs sidebar exposes for an
     * OWNER user against the seeded e2e DB (every permission the
     * dispatcher will gate on is granted). Feature-flag-gated entries
     * (e.g. admin-bans's `group-ban`, gated by
     * `config.enablegroupbanning` which defaults to `'0'`) are NOT in
     * this list — they're conditionally absent on the default install
     * and a separate spec below covers the feature-gate contract.
     */
    /**
     * @typedef {Object} PatternASpec
     * @property {string} route   — ?p=admin&c=… URL slug
     * @property {string[]} slugs — `?section=` slugs the sidebar should expose
     * @property {string} active  — slug expected to carry `aria-current="page"`
     *                              when the page lands without an explicit
     *                              `&section=…` (the page-handler default)
     */
    /** @type {PatternASpec[]} */
    const patternARoutes = [
        { route: 'admins', slugs: ['admins', 'add-admin', 'overrides'], active: 'admins' },
        { route: 'bans',   slugs: ['add-ban', 'protests', 'submissions', 'import'], active: 'add-ban' },
    ];

    for (const spec of patternARoutes) {
        test(`admin-${spec.route} sidebar renders all sections at iPhone-13 width`, async ({ page }) => {
            await page.goto(`/index.php?p=admin&c=${spec.route}`);

            const sidebar = page.locator('[data-testid="admin-sidebar"]');
            await expect(sidebar).toBeVisible();

            for (const slug of spec.slugs) {
                const tab = sidebar.locator(`[data-testid="admin-tab-${slug}"]`);
                await expect(tab, `admin-${spec.route} sidebar must expose ?section=${slug}`).toBeAttached();
                await expect(tab).toBeVisible();
            }

            // The default-active section carries `aria-current="page"`
            // when the page lands without an explicit `&section=…`.
            // Pre-#1275 admin-admins and admin-bans had no active
            // signal at all — the page-level ToC scrolled to whatever
            // the IntersectionObserver picked up first, which raced
            // with the initial paint.
            const activeTab = sidebar.locator(`[data-testid="admin-tab-${spec.active}"]`);
            await expect(activeTab).toHaveAttribute('aria-current', 'page');
        });

        test(`admin-${spec.route} sidebar navigates between sections without scrolling`, async ({ page }) => {
            // Pattern A's contract is "click → new URL → server-rendered
            // page". Smoke that contract: navigate to a non-default
            // section and assert the URL changes + the active link
            // moves. Pre-#1275 admin-admins/admin-bans clicks landed
            // on a `#fragment` URL and the page didn't reload — same
            // chrome, completely different mental model.
            await page.goto(`/index.php?p=admin&c=${spec.route}`);

            // Pick the second non-default section (the first is the
            // default landing — clicking it would be a no-op for this
            // contract).
            const targetSlug = spec.slugs.find((s) => s !== spec.active) ?? spec.slugs[1];
            const sidebar = page.locator('[data-testid="admin-sidebar"]');
            const link = sidebar.locator(`[data-testid="admin-tab-${targetSlug}"]`);
            await expect(link).toBeVisible();
            await link.click();

            await expect(page).toHaveURL(new RegExp(`section=${targetSlug.replace(/-/g, '\\-')}`));
            await expect(sidebar.locator(`[data-testid="admin-tab-${targetSlug}"]`))
                .toHaveAttribute('aria-current', 'page');
        });
    }

    test('admin-bans group-ban tab is omitted when config.enablegroupbanning is off', async ({ page }) => {
        // The seeded e2e DB uses the fresh-install defaults from
        // `web/install/includes/sql/data.sql`, where
        // `config.enablegroupbanning` is '0'. AdminTabs filters out
        // any tab whose `config` field is falsy — verify the
        // group-ban link is not rendered (and consequently can't be
        // clicked) until the feature flag is flipped on. Pre-#1275
        // there was no per-section gate at all (the old toc just
        // skipped the section's render block when the flag was off
        // but kept a dead anchor in the rail).
        await page.goto('/index.php?p=admin&c=bans');

        const sidebar = page.locator('[data-testid="admin-sidebar"]');
        await expect(sidebar).toBeVisible();
        await expect(sidebar.locator('[data-testid="admin-tab-add-ban"]')).toBeVisible();
        await expect(sidebar.locator('[data-testid="admin-tab-group-ban"]')).toHaveCount(0);
    });
});
