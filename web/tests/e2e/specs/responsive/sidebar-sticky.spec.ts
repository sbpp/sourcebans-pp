/**
 * Responsive: main sidebar stays sticky-pinned at desktop (#1271).
 *
 * Desktop viewport (>=1025px) contract for the main app shell sidebar
 * (`<aside class="sidebar" id="sidebar">` rendered by
 * `web/themes/default/core/navbar.tpl`):
 *
 *   - The sidebar declares `position: sticky; top: 0; height: 100vh;
 *     align-self: flex-start;` inside `.app` (`display: flex`).
 *     `position: sticky` works only when the sticky containing block
 *     (`.app`) is taller than the sticky element (`.sidebar` =
 *     100vh). To keep the sidebar pinned at viewport y=0 across the
 *     ENTIRE scroll range — including scroll=document.scrollHeight —
 *     `.app` must extend to the full document height. The structural
 *     half of #1271's fix lives in `core/footer.tpl`:
 *     `<footer class="app-footer">` is rendered as the last flex
 *     column item of `<div class="main">`, INSIDE `.app`. Pre-fix
 *     the footer was a body-level sibling of `.app`, leaving the
 *     sticky containing block `footerHeight` short of the document
 *     and forcing the sidebar to release at the bottom — brand cut
 *     off, on barely-tall pages the entire scroll range was in the
 *     release phase and the sidebar appeared to track the scroll.
 *     The CSS half (`align-self: flex-start` on the sidebar) is
 *     defensive parity with the Pattern A inner rail (#1259) and is
 *     RETAINED but not load-bearing on its own; see the comment
 *     above `.sidebar` in `theme.css` for the full archaeology.
 *   - The contract is desktop-only. At <=1024px the sidebar swaps to
 *     `position: fixed` drawer mode (`responsive/sidebar.spec.ts`
 *     covers that shape on iPhone-13).
 *
 * Choice of test pages: two surfaces exercise complementary parts of
 * the contract.
 *
 *   - **`?p=admin&c=bans`** (the canonical tall page) rides the
 *     multi-section page-level ToC layout (Add a ban form + Ban
 *     protests + Ban submissions + Import bans + Group ban) and is
 *     reliably taller than the 720px desktop viewport even on the
 *     bare e2e seed (the forms and section chrome are structural,
 *     not row-count-driven). On a tall page the strict assertion is
 *     "sidebar.top === 0 at scroll = document.scrollHeight" — the
 *     pre-fix bug presented here as the brand area shifting up by
 *     `footerHeight` (~75px) at the very bottom; the post-fix
 *     contract is that sticky never releases.
 *   - **`?p=admin&c=audit`** with an empty audit log (the bare e2e
 *     seed) is the BARELY-tall surface where the pre-fix bug was
 *     most visually obvious. With the footer outside `.app`,
 *     `docHeight - viewport` was ≈ `footerHeight`, putting the
 *     entire scroll range in the sticky-release phase — the sidebar
 *     would visibly track the scroll from any non-zero scrollY. On
 *     the post-fix layout, `.app`'s `min-height: 100vh` with the
 *     footer pushed to the bottom by `margin-top: auto` collapses
 *     the doc to exactly viewport height, so there's no scroll at
 *     all and the assertion degrades to "sidebar.top === 0 at
 *     scroll = 0". The probe still has value because it's the
 *     surface that pre-fix drove rumblefrog to file the issue —
 *     keeping it under test means a future refactor that
 *     re-introduces a small `docHeight - viewport` gap (e.g.
 *     ships a tall hero banner that's outside `.app`) would fail
 *     here even though admin-bans would still pass.
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

    test('sidebar stays pinned at viewport y=0 across the entire scroll range of admin-bans', async ({ page }) => {
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

        // Helper: read the sticky `<aside class="sidebar">` chrome's
        // bounding box and round to whole pixels. The aside is the
        // sticky element; the nav inside it inherits sticky pinning
        // when the aside is pinned. We measure the aside directly
        // because the strict assertion is on its `top` (the contract
        // is "sidebar at viewport y=0", not "nav at viewport y=N").
        const sidebarTopAt = async (scrollY: number) => {
            await page.evaluate((sy) => window.scrollTo(0, sy), scrollY);
            // One frame for sticky to resolve in Chromium.
            await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => resolve(undefined))));
            return await page.evaluate(() => {
                const el = document.getElementById('sidebar');
                return el ? Math.round(el.getBoundingClientRect().top) : null;
            });
        };

        // Mid-scroll: tightest historical guard — pre-#1278's purely
        // CSS-side `align-self: flex-start` was already enough for
        // sticky to pin at viewport y=0 in the middle of the scroll
        // range (the pre-fix bug only manifested at the very bottom
        // and on barely-tall pages). Keep the assertion at scroll=500
        // as a regression guard against gross sticky breakage —
        // e.g. a future ancestor that sets `transform` / `filter` /
        // `overflow: hidden` and silently kills the sticky containing
        // block.
        const midTop = await sidebarTopAt(500);
        expect(midTop, 'sidebar must report a bounding-box top once mounted').not.toBeNull();
        expect(midTop, `sidebar must be sticky-pinned at viewport y=0 mid-scroll (got ${midTop})`).toBe(0);

        // Bottom-of-page: the actual #1271 regression guard. Pre-fix
        // the footer was a body-level sibling of `.app`, so `.app`'s
        // height fell short of the document by `footerHeight` (~75px
        // on the dev seed). Sticky correctly released as `.app`'s
        // bottom edge entered the viewport, sliding the sidebar up
        // and chopping the brand area off the top. Post-fix the
        // footer lives inside `.main`/`.app`, `.app`'s height matches
        // the document, and sticky never releases — sidebar.top must
        // remain exactly 0 at scroll = document.scrollHeight.
        //
        // Sub-pixel tolerance: Chromium reports sub-pixel coordinates
        // for sticky elements when the scroll position lands on a
        // fractional `.app` end (e.g. `appHeight = 2120.5`). The
        // tolerance of ±1 swallows the rounding without admitting
        // the pre-fix `~-75` regression.
        const docHeightLive = await page.evaluate(() => document.documentElement.scrollHeight);
        const bottomTop = await sidebarTopAt(docHeightLive);
        expect(
            bottomTop,
            `sidebar must remain pinned at viewport y=0 at scroll=document.scrollHeight (${docHeightLive}px); pre-#1271 the footer-outside-.app layout drove this to roughly -footerHeight (got ${bottomTop})`,
        ).toBeGreaterThanOrEqual(-1);
        expect(
            bottomTop,
            `sidebar must remain pinned at viewport y=0 at scroll=document.scrollHeight (got ${bottomTop})`,
        ).toBeLessThanOrEqual(1);

        // Belt-and-suspenders: the brand area
        // (`[data-testid="sidebar-brand"]` — the "S SourceBans++"
        // header at the top of the sidebar) is the user-visible
        // canary for the pre-fix regression. If sticky drifts up by
        // `footerHeight`, the brand is the first thing to scroll off
        // the top. Assert the brand is in the viewport at
        // scroll=BOTTOM. (`toBeInViewport()` requires ≥0%
        // intersection — together with the strict `top` check above,
        // this catches both "sidebar fully off-screen" and "sidebar
        // pinned but brand cut off" failure modes.)
        await expect(page.locator('[data-testid="sidebar-brand"]')).toBeInViewport();

        // First nav link is `[data-testid="nav-home"]` (always
        // rendered for any logged-in user — the navbar's "Public"
        // section emits Home unconditionally). It sits at the top
        // of the nav (just below the brand); a regression that
        // pinned the sidebar but pushed the nav off-screen would
        // be caught here.
        await expect(page.locator('[data-testid="nav-home"]')).toBeInViewport();
    });

    test('barely-tall page (audit log on the bare e2e seed) does not regress to "sidebar tracks scroll"', async ({ page }) => {
        // The audit-log surface is the one rumblefrog originally
        // flagged: with the footer outside `.app`, the bare e2e
        // seed produced `docHeight = viewport + footerHeight`, so
        // the entire scroll range was in the sticky-release phase
        // and the sidebar visibly drifted with the scroll.
        //
        // Post-fix, `.app`'s `min-height: 100vh` + the footer-as-
        // last-flex-item layout collapses `docHeight` to exactly
        // `viewport` on this seed (no scroll at all). The
        // bottom-walk assertion is therefore CONDITIONAL: it only
        // fires when the page is scrollable. This catches a narrow
        // band of regressions:
        //   - Future refactor moves the footer back outside `.app`
        //     while leaving `min-height: 100vh` in place →
        //     `docHeight = viewport + footerHeight`, scroll fires,
        //     strict `top === 0` at bottom fails.
        //   - Future refactor adds a hero banner / docked alert /
        //     anything that lives outside `.app` and pushes
        //     `docHeight` above `viewport` → same shape.
        //
        // It does NOT catch a regression where the footer goes back
        // outside AND `min-height: 100vh` is also shaved off so
        // `.app` collapses to its content height (which on the
        // bare-seed audit page can fit in 720px). That joint
        // regression presents as `docHeight === viewport` and the
        // bottom-walk silently no-ops here. The admin-bans guard
        // above is the load-bearing tall-page invariant — it fires
        // regardless of footer placement, because admin-bans is
        // structurally taller than the viewport on its own. Treat
        // this audit-page test as the surface-level memorial of
        // rumblefrog's original report, not as a complete
        // regression suite. If a future contributor wants the
        // joint-regression case covered, they should seed a row
        // into `:prefix_log` from the e2e `Fixture` so the audit
        // page is reliably scrollable.
        await page.goto('/index.php?p=admin&c=audit');
        // Brand visibility is the load anchor: the Audit Log page
        // chrome carries no testid we can wait on without coupling
        // to its content, but the navbar brand is rendered on every
        // logged-in route by `core/navbar.tpl`.
        await expect(page.locator('[data-testid="sidebar-brand"]')).toBeVisible();

        const { docHeight, viewport } = await page.evaluate(() => ({
            docHeight: document.documentElement.scrollHeight,
            viewport: window.innerHeight,
        }));

        // Always assert at scroll=0 — sticky and static both pin the
        // sidebar at viewport y=0 here, so this is the "still
        // mounted" smoke check.
        await page.evaluate(() => window.scrollTo(0, 0));
        const topAtZero = await page.evaluate(() => {
            const el = document.getElementById('sidebar');
            return el ? Math.round(el.getBoundingClientRect().top) : null;
        });
        expect(topAtZero, `sidebar must be at viewport y=0 at scroll=0 (got ${topAtZero})`).toBe(0);

        if (docHeight > viewport) {
            await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
            await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => resolve(undefined))));
            const topAtBottom = await page.evaluate(() => {
                const el = document.getElementById('sidebar');
                return el ? Math.round(el.getBoundingClientRect().top) : null;
            });
            expect(
                topAtBottom,
                `sidebar must remain pinned at viewport y=0 at scroll=document.scrollHeight on the audit page (docHeight=${docHeight}, viewport=${viewport}); pre-#1271 this scroll range was entirely in the sticky-release phase (got ${topAtBottom})`,
            ).toBeGreaterThanOrEqual(-1);
            expect(topAtBottom).toBeLessThanOrEqual(1);
            await expect(page.locator('[data-testid="sidebar-brand"]')).toBeInViewport();
        }
    });
});
