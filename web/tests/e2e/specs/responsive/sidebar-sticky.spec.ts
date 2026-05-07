/**
 * Responsive: main sidebar stays sticky-pinned at desktop (#1271).
 *
 * Desktop viewport (>=1025px) contract for the main app shell sidebar
 * (`<aside class="sidebar" id="sidebar">` rendered by
 * `web/themes/default/core/navbar.tpl`):
 *
 *   - The sidebar declares `position: sticky; top: 0; height: 100vh;
 *     align-self: flex-start;` inside `.app` (`display: flex`). The
 *     explicit `height: 100vh` keeps the sidebar's cross-axis size at
 *     the viewport so sticky has room to operate; `align-self:
 *     flex-start` is defensive parity with the Pattern A inner rail
 *     (`.admin-sidebar`, which has `align-self: start` for the same
 *     reason — see #1259). #1271 added the parity so a future refactor
 *     that drops the explicit height (or wraps `.app` in a transformed
 *     / overflow-hidden ancestor that breaks the sticky containing
 *     block) cannot regress the chrome silently.
 *   - The contract is desktop-only. At <=1024px the sidebar swaps to
 *     `position: fixed` drawer mode (`responsive/sidebar.spec.ts`
 *     covers that shape on iPhone-13).
 *
 * Choice of test page: `?p=admin&c=bans` rides the multi-section
 * page-level ToC layout (Add a ban form + Ban protests + Ban
 * submissions + Import bans + Group ban) and is reliably taller than
 * the 720px desktop viewport even on the bare e2e seed (the forms
 * and section chrome are structural, not row-count-driven). The issue
 * also calls out `?p=admin&c=audit`, but the audit log on
 * `sourcebans_e2e` is empty by default and would not necessarily
 * exceed the viewport without extra seeding — the bans route is the
 * deterministic-tall surface for this assertion.
 *
 * Project gating: this whole describe runs only on `chromium`
 * (Desktop Chrome at 1280x720). The `mobile-chromium` project
 * (iPhone-13, viewport <1024px) renders the sidebar as a hidden
 * drawer and the assertion would not apply.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { AdminBansPage } from '../../pages/admin/AdminBans.ts';

test.describe('responsive: sidebar sticky at desktop', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Desktop-only contract — see file-level comment.',
        );
    });

    test('sidebar stays pinned to the viewport top after scrolling to the bottom of admin-bans', async ({ page }) => {
        const p = new AdminBansPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();

        // Sanity: the page has to be taller than the viewport for the
        // sticky test to be meaningful. If the page fits in the
        // viewport there's nothing to scroll past, and the assertion
        // would silently pass even on the broken pre-#1271 layout.
        const { docHeight, viewport } = await page.evaluate(() => ({
            docHeight: document.documentElement.scrollHeight,
            viewport: window.innerHeight,
        }));
        expect(
            docHeight,
            `admin-bans must be taller than the viewport (got docHeight=${docHeight}, viewport=${viewport})`,
        ).toBeGreaterThan(viewport + 200);

        // The main sidebar's <nav> is uniquely identified by its ARIA
        // role + name: the topbar's breadcrumb nav uses
        // aria-label="Breadcrumb" and the inner Pattern A admin
        // sidebar uses aria-label="<page> sections", so "Primary" is
        // unambiguous (see core/navbar.tpl). Anchoring on the role +
        // name pair satisfies AGENTS.md's "ARIA roles" testability
        // hook contract — never CSS class chains as the primary
        // selector.
        const sidebarNav = page.getByRole('navigation', { name: 'Primary' });
        await expect(sidebarNav).toBeVisible();
        await expect(sidebarNav).toBeInViewport();

        // Mid-scroll, well clear of the page top, the sticky contract
        // says the sidebar's bounding-box top must be at viewport y=0
        // (sticky-pinned to the viewport, not scrolling with the
        // document). The `aside.sidebar` chrome is the sticky element;
        // measure its top directly via the live DOM rect. This is the
        // tight regression guard — `toBeInViewport()` only requires
        // partial overlap and would tolerate a sidebar drifting tens
        // of pixels off-screen, but a fully-broken sticky layout
        // (sidebar scrolls with the page) drives the rect's top to
        // -scrollY which is unmistakable.
        await page.evaluate(() => window.scrollTo(0, 500));
        const midScrollTop = await page.evaluate(() => {
            const el = document.getElementById('sidebar');
            return el ? el.getBoundingClientRect().top : null;
        });
        expect(
            midScrollTop,
            'sidebar must report a bounding-box top once mounted',
        ).not.toBeNull();
        expect(
            Math.round(midScrollTop ?? -1),
            `sidebar must be sticky-pinned at viewport y=0 mid-scroll (got ${midScrollTop})`,
        ).toBe(0);

        // Scroll to the bottom of the page. A regression that
        // breaks the sticky contract entirely (sidebar scrolls with
        // the document) drives the rect's top to -scrollY, so by the
        // time the user reaches the bottom of admin-bans the sidebar
        // is far below the viewport. At the very bottom of `.app`
        // sticky correctly releases as the containing block runs out
        // of "room" — the sidebar follows `.app`'s bottom edge upward
        // — so the strict y=0 assertion above lives at mid-scroll,
        // while the bottom-of-page check below stays on
        // `inViewport()`: the sidebar must stay reachable, even if its
        // top drifts a few pixels above the viewport edge.
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));

        // Post-scroll: the sidebar must remain in the viewport. The
        // `<nav role="navigation" aria-label="Primary">` element is
        // inside the `<aside class="sidebar">` chrome; if the aside
        // is sticky-pinned at the top of the viewport, the nav inside
        // is in the viewport too.
        await expect(sidebarNav).toBeInViewport();

        // First nav link is `[data-testid="nav-home"]` (always
        // rendered for any logged-in user — the navbar's "Public"
        // section emits Home unconditionally). It sits at the top of
        // the sidebar; pre-#1271 it would scroll off the top of the
        // viewport and `toBeInViewport()` would fail.
        await expect(page.locator('[data-testid="nav-home"]')).toBeInViewport();
    });
});
