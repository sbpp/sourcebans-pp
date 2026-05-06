/**
 * Issue #1207 ADM-10: admin home cards arrange as a 2-column grid at mobile.
 *
 * Pre-fix the admin landing's `.admin-cards` grid used
 * `repeat(auto-fill, minmax(15rem, 1fr))` universally. At iPhone-13
 * width (390px) only one 240px column fits the available content
 * area, so the 8 admin entry-point cards stack vertically and
 * produce ~680px of scroll for what should be 2-3 rows of tiles.
 *
 * Fix: a mobile-scoped media query in `web/themes/default/page_admin.tpl`
 * (`@media (max-width: 767.98px)`) overrides the grid to
 * `repeat(2, minmax(0, 1fr))` so the cards always render in 2
 * columns at <768px, with `gap: 0.75rem` to keep the tiles from
 * crowding each other. Tablet (768–1023px) and desktop (>=1024px)
 * keep the auto-fill behaviour above so wider viewports still get
 * 2-3-4 columns depending on available width.
 *
 * Selectors use the `data-testid="admin-card-<area>"` hooks the
 * template already emits (#1207 ADM-1's overrides slice and the
 * routing-truthiness spec lock them); no class-chain or
 * visible-text primary selectors per AGENTS.md.
 *
 * Per-project assertions: this spec splits into two non-overlapping
 * tests guarded by `testInfo.project.name`. The mobile assertion
 * locks the 2-column shape; the desktop assertion locks the
 * "didn't regress to 1-col" invariant by asserting >=3 cards on the
 * first visible row at the chromium project's Desktop Chrome
 * viewport (1280×720). Without the desktop counterpart a
 * future careless edit could collapse the desktop grid to 1-col
 * and the mobile spec wouldn't notice.
 */

import { test, expect } from '../../fixtures/auth.ts';

/**
 * Read the rendered top/left/width of every visible card on the
 * admin landing. Returned in DOM order (which matches the visual
 * order under the default `auto-flow: row`). The bounding boxes
 * are floating-point — callers tolerate a small (<= 5px) y-axis
 * variation when checking "same row" so sub-pixel layout differences
 * don't flake the assertion.
 */
async function readCardBoxes(
    page: import('@playwright/test').Page,
): Promise<Array<{ id: string; x: number; y: number; w: number; h: number }>> {
    return await page.evaluate(() => {
        return Array.from(
            document.querySelectorAll<HTMLElement>('[data-testid^="admin-card-"]'),
        )
            .filter((el) => {
                const r = el.getBoundingClientRect();
                return r.width > 0 && r.height > 0;
            })
            .map((el) => {
                const r = el.getBoundingClientRect();
                return {
                    id: el.getAttribute('data-testid') ?? '',
                    x: r.left,
                    y: r.top,
                    w: r.width,
                    h: r.height,
                };
            });
    });
}

/**
 * Group cards into visual rows. Two cards belong to the same row
 * when their top edges differ by less than `tolerance` pixels —
 * guarding sub-pixel layout differences (and the small extra padding
 * a focus ring might add) without admitting a stacked layout that
 * happens to differ by < 5px (the cards are >= ~120px tall in
 * either layout).
 */
function rowsOf(
    boxes: Array<{ x: number; y: number; w: number; h: number }>,
    tolerance = 5,
): Array<typeof boxes> {
    const rows: Array<typeof boxes> = [];
    for (const box of boxes) {
        const row = rows.find((r) => Math.abs(r[0].y - box.y) < tolerance);
        if (row) row.push(box);
        else rows.push([box]);
    }
    // Sort each row left-to-right so column-position assertions can
    // reason about adjacency without re-sorting per call site.
    for (const r of rows) r.sort((a, b) => a.x - b.x);
    return rows;
}

test.describe('#1207 ADM-10: admin home cards mobile grid', () => {
    test('mobile: cards render in a 2-column grid at iPhone-13 width', async ({ page }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only assertion — see file-level comment.',
        );

        await page.goto('/index.php?p=admin');

        // Anchor on a card every seeded ADMIN_OWNER admin renders;
        // `admin-card-bans` is the most permissively-gated tile and
        // the one `pages/admin/AdminHome.ts` uses as its mounted
        // signal, so it's available for any future non-OWNER fixture.
        await expect(page.locator('[data-testid="admin-card-bans"]')).toBeVisible();

        const boxes = await readCardBoxes(page);
        // The seeded admin holds ADMIN_OWNER, so every $can_<area>
        // boolean resolves to true and all 8 cards render. If a
        // future change adds/removes a card the count assertion
        // here forces a paired test update — better than silently
        // shipping a stale grid expectation.
        expect(boxes.length).toBe(8);

        const rows = rowsOf(boxes);

        // Every row must hold exactly 2 cards (the grid is
        // `repeat(2, minmax(0, 1fr))` at <768px). 8 cards / 2 cols
        // = 4 rows. Pinning the row count locks the layout shape;
        // pinning each row's width locks the "they're balanced
        // halves" invariant (`auto-fill` on a future regression
        // could leave a half-empty row).
        expect(rows.length, `cards must arrange into 4 rows of 2 (got ${rows.length})`).toBe(4);
        for (const row of rows) {
            expect(row.length, `each row must have 2 cards (got ${row.length})`).toBe(2);
        }

        // Column-position invariants on the first row:
        //   - the two cards are side-by-side (cardA.right <= cardB.left + small gap)
        //   - both cards have non-trivial width (>= 100px so a future
        //     `width: 0` regression fails this gate)
        const [cardA, cardB] = rows[0];
        expect(cardB.x).toBeGreaterThan(cardA.x + cardA.w - 1);
        expect(cardA.w).toBeGreaterThanOrEqual(100);
        expect(cardB.w).toBeGreaterThanOrEqual(100);

        // 44x44 tap-target floor (WCAG 2.1 AAA / Apple HIG / Material).
        // Same threshold the topbar palette-trigger spec locks.
        for (const box of boxes) {
            expect(box.w, `card ${box.id} width >= 44px`).toBeGreaterThanOrEqual(44);
            expect(box.h, `card ${box.id} height >= 44px`).toBeGreaterThanOrEqual(44);
        }

        // Page-level: the admin landing must not introduce horizontal
        // overflow at iPhone-13 width. Mirrors the topbar-CC1 spec's
        // body-overflow guard so a future card-padding bump doesn't
        // silently re-open the same scroll regression.
        await expect.poll(() => page.evaluate(() =>
            document.documentElement.scrollWidth - document.documentElement.clientWidth
        )).toBeLessThanOrEqual(1);
    });

    test('desktop: cards remain in a multi-column grid at Desktop Chrome width', async ({ page }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Desktop-only regression guard — see file-level comment.',
        );

        await page.goto('/index.php?p=admin');
        await expect(page.locator('[data-testid="admin-card-bans"]')).toBeVisible();

        const boxes = await readCardBoxes(page);
        expect(boxes.length).toBe(8);

        const rows = rowsOf(boxes);

        // Desktop Chrome's default viewport (devices['Desktop Chrome'])
        // is 1280×720. Available content width after the sidebar
        // (~256px) and admin-home padding (~24px each side) is
        // ~975px; with the existing `repeat(auto-fill, minmax(15rem, 1fr))`
        // rule (15rem = 240px) the first row holds 4 cards. We assert
        // >= 3 to leave headroom for sidebar-width drift between
        // chrome revisions; the regression we're guarding against
        // is "fell back to 1-col mobile stack on desktop", which a
        // >= 3 floor catches without coupling to the exact column
        // count the existing rule produces today.
        expect(
            rows[0].length,
            `desktop must keep multi-column layout (>= 3 on first row, got ${rows[0].length})`,
        ).toBeGreaterThanOrEqual(3);
    });
});
