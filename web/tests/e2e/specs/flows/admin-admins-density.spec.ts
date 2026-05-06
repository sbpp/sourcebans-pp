/**
 * #1207 ADM-3 + ADM-4 — admin/admins page density rework.
 *
 * Two findings, one slice:
 *
 *   - **ADM-3** — the admin/admins route stacks search + admins list
 *     + add admin + overrides + add override on one long scroll. We
 *     paint a sticky page-level ToC at >=1024px (anchor sidebar) and
 *     an accordion ToC at <1024px so users can jump directly to
 *     "Add admin" or "Overrides" without paging past the search and
 *     listing.
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
 * page_admin_admins_list.tpl, admin.admins.toc.tpl,
 * box_admin_admins_search.tpl. Project-default storage state runs
 * the spec as the seeded admin/admin (owner). Where this spec asserts
 * an "in viewport after click" relationship we use Playwright's
 * `boundingBox()` against the layout viewport rather than CSS
 * intersection observer probes — works for both the desktop scroll
 * shell and the mobile root scroll.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { AdminAdminsPage } from '../../pages/admin/AdminAdmins.ts';

test.describe('flow: admin/admins density rework (#1207 ADM-3, ADM-4)', () => {

    // ----- ADM-3 — desktop sticky anchor sidebar ----------------------

    test('ADM-3 desktop: sticky anchor sidebar visible and ToC links scroll to sections', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Sticky-sidebar contract is desktop-only.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expect(p.shell).toBeVisible();
        await expect(p.toc).toBeVisible();

        // Each ToC link must be present + anchored at the matching
        // section. Tap each and assert: URL.hash flips + the section
        // sits inside the layout viewport via boundingBox. We don't
        // pin the section to the top because the *last* section
        // (#add-override) can never reach the top — there isn't
        // enough document below it to scroll it up. The contract is
        // "scrolls to the right section", i.e. the section is now
        // intersecting the viewport, not "section is at y=64".
        for (const section of ['search', 'admins', 'add-admin', 'overrides', 'add-override'] as const) {
            const link = p.tocLink(section);
            await expect(link).toBeVisible();
            await link.click();

            await expect(page).toHaveURL(new RegExp(`#${section}$`));

            const target = p.section(section);
            // Wait for the anchor scroll to settle. `prefers-reduced-motion`
            // is set globally so this is essentially synchronous; we
            // still poll because the click + hash + scroll trio is
            // microtask-ordered and `boundingBox()` runs against the
            // post-paint geometry.
            await expect(target).toBeInViewport();

            const targetBox = await target.boundingBox();
            const viewport = page.viewportSize();
            expect(targetBox).not.toBeNull();
            expect(viewport).not.toBeNull();
            if (targetBox && viewport) {
                // The section's bounding box must overlap the viewport:
                // top edge above the bottom AND bottom edge below the top.
                expect(targetBox.y).toBeLessThan(viewport.height);
                expect(targetBox.y + targetBox.height).toBeGreaterThan(0);
            }
        }
    });

    // ----- ADM-3 — mobile accordion ToC -------------------------------

    test('ADM-3 mobile: accordion ToC visible and tapping a row scrolls', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile-chromium', 'Accordion contract is mobile-only.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();
        await expect(p.toc).toBeVisible();

        // The mobile chrome is `<details open>` so the link list is
        // visible without an extra tap. Pin the accordion summary
        // *contains* the canonical "On this page" label so we don't
        // tie the test to icon swaps. The summary itself must be
        // tappable (not the desktop `display: none`).
        const summary = p.toc.locator('summary');
        await expect(summary).toBeVisible();
        await expect(summary).toContainText(/On this page/i);

        // Tap "Add admin" — page must scroll the section into view.
        await p.tocLink('add-admin').click();
        await expect(page).toHaveURL(/#add-admin$/);
        await expect(p.section('add-admin')).toBeInViewport();
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
        await page.goto('/index.php?p=admin&c=admins&name=admin&steamid=STEAM_0:0:0&steam_match=1');
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
        await page.goto('/index.php?p=admin&c=admins&name=admin');
        await expect(p.searchInput('name')).toHaveValue('admin');

        await p.searchReset.click();
        await page.waitForURL((url) => !url.search.includes('name='));
        await expect(p.searchInput('name')).toHaveValue('');
    });

    // ----- ADM-3 — Add admin CTA in the page header anchors -----------

    test('ADM-3: header "Add admin" CTA scrolls to the Add admin section', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'In-page anchor is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await p.goto();

        const cta = page.locator('[data-testid="admin-add-cta"]');
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', '#add-admin');
        await cta.click();

        await expect(page).toHaveURL(/#add-admin$/);
        await expect(p.section('add-admin')).toBeInViewport();
    });
});
