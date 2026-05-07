/**
 * #1207 ADM-3 + ADM-4 — admin/admins page density rework, updated by
 * #1275 to assert Pattern A `?section=…` routing.
 *
 * Two findings, one slice:
 *
 *   - **ADM-3** — admin-admins used to stack search + admins list +
 *     add admin + overrides + add override on one long scroll. The
 *     pre-#1275 fix painted a sticky page-level ToC at >=1024px and
 *     an accordion at <1024px so users could jump directly between
 *     anchored sections. #1275 swapped that for Pattern A — each
 *     section is its own URL (`?section=admins|add-admin|overrides`).
 *     The chrome assertions stay (sidebar visible at desktop, link
 *     list intact at mobile, every link navigable) but the contract
 *     is now URL-driven instead of fragment-scroll-driven.
 *   - **ADM-4** — the advanced-search box used to ship 8 separate
 *     Search buttons, each submitting one filter at a time. The
 *     redesign collapses that to a single submit; admin.admins.php
 *     ANDs the populated filters server-side. Legacy
 *     `?advType=…&advSearch=…` URLs still narrow correctly via a
 *     compatibility shim in the page handler (see
 *     web/tests/integration/AdminAdminsSearchTest.php for the
 *     server-side AND-semantics lock).
 *
 * Selector discipline
 * -------------------
 * Anchors are `data-testid` from the locked hooks in
 * page_admin_admins_*.tpl, core/admin_sidebar.tpl,
 * box_admin_admins_search.tpl. Project-default storage state runs
 * the spec as the seeded admin/admin (owner). The Pattern A
 * navigation is asserted through URL changes + `aria-current="page"`
 * — never visible-text or class chains.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { AdminAdminsPage } from '../../pages/admin/AdminAdmins.ts';

test.describe('flow: admin/admins density rework (#1207 ADM-3, ADM-4 / #1275)', () => {

    // ----- ADM-3 (#1275) — sidebar navigates between sections ---------

    test('ADM-3 desktop: sidebar visible and links route between sections', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Sticky-sidebar contract is desktop-only.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expect(p.shell).toBeVisible();
        await expect(p.toc).toBeVisible();

        // Each Pattern A link must land on its own URL with the
        // matching section body in the DOM. Pre-#1275 every section
        // sat in one scroll-shell; now each section is a distinct
        // server render.
        for (const slug of ['admins', 'add-admin', 'overrides'] as const) {
            const link = p.tocLink(slug);
            await expect(link).toBeVisible();
            await link.click();

            await expect(page).toHaveURL(new RegExp(`section=${slug.replace(/-/g, '\\-')}(?:&|$)`));
            await expect(p.tocLink(slug)).toHaveAttribute('aria-current', 'page');
            // The active section's body MUST be in the DOM. The
            // `admins` section embeds the search box (under
            // `admin-admins-section-search`) above the list (under
            // `admin-admins-section-admins`) — assert on the latter
            // since the search testid lives inside both surfaces in
            // the rendered DOM.
            const sectionTestid = slug === 'admins' ? 'admin-admins-section-admins' : `admin-admins-section-${slug}`;
            await expect(page.locator(`[data-testid="${sectionTestid}"]`)).toBeVisible();
        }
    });

    // ----- ADM-3 (#1275) — mobile accordion sidebar -------------------

    test('ADM-3 mobile: accordion sidebar visible and link routes correctly', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile-chromium', 'Accordion contract is mobile-only.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expect(p.toc).toBeVisible();

        // The mobile chrome is `<details open>` so the link list is
        // visible without an extra tap. The summary contains the
        // configured sidebar label ("Admin sections" — assigned by
        // admin.admins.php's `new AdminTabs(...)` call).
        const summary = p.toc.locator('summary');
        await expect(summary).toBeVisible();

        // Tap "Add admin" — page navigates to its dedicated URL.
        await p.tocLink('add-admin').click();
        await expect(page).toHaveURL(/section=add-admin(?:&|$)/);
        await expect(p.tocLink('add-admin')).toHaveAttribute('aria-current', 'page');
        await expect(page.locator('[data-testid="admin-admins-section-add-admin"]')).toBeVisible();
    });

    // ----- ADM-4 — single-submit search -------------------------------

    test('ADM-4: single Search submit AND-combines filters in one navigation', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Filter contract is desktop-only; mobile reuses the same form.');

        const p = new AdminAdminsPage(page);
        await p.goto();

        // Exactly one Search submit + one reset link, sitting at the
        // bottom of the form (collapsing 8 per-row buttons was the
        // headline of ADM-4).
        await expect(p.searchSubmit).toHaveCount(1);
        await expect(p.searchReset).toHaveCount(1);

        // Pre-filter: confirm the seeded admin row is there before we
        // submit. This protects against a regression where the page
        // misroutes the request and "0 rows" lands as a false positive.
        await expect(p.adminCount).toContainText(/^\(\d+\)$/);
        const baselineCount = await p.adminRows.count();
        expect(baselineCount).toBeGreaterThanOrEqual(1);

        // Two filters at once: a deliberately not-matching login
        // ("zzznoadminmatchesthis") + a steam_match=1 (partial). The
        // login filter narrows the result list to zero — locking the
        // server-side AND contract — while still emitting both
        // filters on the wire so we can assert two-param submission.
        await p.searchInput('name').fill('zzznoadminmatchesthis');
        await page.locator('[data-testid="search-admins-steam-match"]').selectOption('1');

        // Single submit → single document navigation. Capture every
        // document-level request kicked off after we click submit so
        // we can assert exactly-one navigation (collapsing the
        // 8-button-per-row chrome into one).
        const requests: string[] = [];
        page.on('request', (req) => {
            if (req.resourceType() === 'document') requests.push(req.url());
        });
        await Promise.all([
            page.waitForURL((url) => url.searchParams.get('name') === 'zzznoadminmatchesthis'),
            p.searchSubmit.click(),
        ]);

        const url = new URL(page.url());
        expect(url.searchParams.get('name')).toBe('zzznoadminmatchesthis');
        expect(url.searchParams.get('steam_match')).toBe('1');
        // #1275 — the form carries `<input type="hidden" name="section" value="admins">`
        // so the post-submit URL keeps the user on the admins section.
        expect(url.searchParams.get('section')).toBe('admins');
        const submitNavs = requests.filter(
            (u) => u.includes('p=admin') && u.includes('c=admins') && u.includes('name=zzznoadminmatchesthis'),
        );
        expect(submitNavs).toHaveLength(1);

        // The result must show 0 rows — the login filter has no
        // matches, so AND with anything else is empty too. Locks the
        // server-side AND contract end-to-end. The PHPUnit suite
        // covers the matrix of multi-filter cases; we only need
        // ONE wire-level proof here that the form fires once and
        // the server narrows correctly.
        await expect(p.adminCount).toContainText('(0)');
        await expect(p.adminRows).toHaveCount(0);
    });

    // ----- ADM-4 — pre-fill from URL on revisit ------------------------

    test('ADM-4: filters round-trip through the URL on revisit', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Pre-fill contract is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await page.goto('/index.php?p=admin&c=admins&section=admins&name=admin&steamid=STEAM_0:0:0&steam_match=1');
        await expect(p.pageMounted).toBeVisible();

        // The form re-paints from `$active_filter_*` on the View
        // DTO; values land in the inputs without JS assistance.
        await expect(p.searchInput('name')).toHaveValue('admin');
        await expect(p.searchInput('steamid')).toHaveValue('STEAM_0:0:0');
        const matchSelect = page.locator('[data-testid="search-admins-steam-match"]');
        await expect(matchSelect).toHaveValue('1');
    });

    // ----- ADM-4 — Clear filters resets form state --------------------

    test('ADM-4: Clear filters wipes every populated field', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Reset link is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await page.goto('/index.php?p=admin&c=admins&section=admins&name=admin');
        await expect(p.searchInput('name')).toHaveValue('admin');

        await p.searchReset.click();
        await page.waitForURL((url) => !url.search.includes('name='));
        await expect(p.searchInput('name')).toHaveValue('');
    });

    // ----- ADM-3 (#1275) — Add admin CTA navigates to its section -----

    test('ADM-3: header "Add admin" CTA navigates to the add-admin section', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'In-page CTA is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await p.goto();

        const cta = page.locator('[data-testid="admin-add-cta"]');
        await expect(cta).toBeVisible();
        // Pre-#1275 this was `#add-admin` (anchor scroll within the
        // long-scroll page); now it's a Pattern A URL.
        await expect(cta).toHaveAttribute('href', /section=add-admin/);
        await cta.click();

        await expect(page).toHaveURL(/section=add-admin/);
        await expect(p.tocLink('add-admin')).toHaveAttribute('aria-current', 'page');
        await expect(page.locator('[data-testid="admin-admins-section-add-admin"]')).toBeVisible();
    });
});
