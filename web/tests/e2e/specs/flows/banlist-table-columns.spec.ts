/**
 * Flow: banlist desktop table column geometry (#1207 PUB-1).
 *
 * Asserts the regression the audit caught — with realistic per-row
 * content the banlist's auto-layout used to:
 *   - clip the STATUS header to "STA…",
 *   - push the per-row pill partly off the right edge,
 *   - wrap the BANNED date over three lines.
 *
 * The fix in `web/themes/default/css/theme.css` pins
 * `.col-length / .col-banned / .col-admin / .col-status / .col-actions`
 * to `white-space: nowrap` and gives `.col-status` `width: 1%` so it
 * shrinks to its content. A `.table-scroll` overflow-x wrapper is the
 * fallback when the page width can't accommodate every column.
 *
 * Project gating
 * --------------
 * Desktop-only. The mobile chrome is `.ban-cards`, not the `<table>`,
 * and `responsive/banlist.spec.ts` already covers the table-vs-cards
 * switch. Re-running this spec under mobile-chromium would look up a
 * `.table` that's `display:none` and silently skip every column
 * assertion.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';
import { seedBanViaApi } from '../../fixtures/seeds.ts';

test.describe('flow: banlist desktop column geometry (#1207 PUB-1)', () => {
    test.skip(({ isMobile }) => isMobile, 'Desktop-only — mobile chrome is .ban-cards.');

    test.beforeEach(async ({ page }) => {
        await truncateE2eDb();
        await seedBanViaApi(page, {
            nickname: 'e2e-table-cols',
            steam: 'STEAM_0:1:1207001',
            reason: 'e2e: banlist column geometry seed',
        });
    });

    test('STATUS header reads in full and the BANNED cell is one line', async ({ page }) => {
        await page.goto('/index.php?p=banlist');

        const table = page.locator('#banlist-root .table');
        await expect(table).toBeVisible();

        // The STATUS header text is "Status" capitalised by the
        // template literal; the regression collapsed it to "STA…"
        // because the column took on a sub-natural width. Reading
        // the visible text is the most direct invariant: if the
        // column shrinks back the assertion goes red.
        const statusHeader = table.locator('th.col-status');
        await expect(statusHeader).toBeVisible();
        await expect(statusHeader).toHaveText(/^Status$/);
        const headerOverflow = await statusHeader.evaluate((el) => ({
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
        }));
        expect(
            headerOverflow.scrollWidth,
            'STATUS header must not visually clip its content',
        ).toBeLessThanOrEqual(headerOverflow.clientWidth + 1);

        // The seeded ban's BANNED cell holds an ISO timestamp like
        // "2026-05-05 23:05:18". Without the fix the `<time>`
        // element wraps to three lines (date, gap, time). The cell
        // itself stretches to the row's tallest sibling (the
        // PLAYER column carries a 28px avatar), so we measure the
        // `<time>` element directly — its scrollHeight is exactly
        // one line's worth when `white-space: nowrap` is in force.
        // Threshold: <2 * fontSize so a near-double-line wrap fails
        // even on themes that tweak line-height.
        const seededRow = table
            .locator('[data-testid="ban-row"]')
            .filter({ hasText: 'STEAM_0:1:1207001' });
        await expect(seededRow).toBeVisible();

        const bannedCell = seededRow.locator('td.col-banned');
        await expect(bannedCell).toBeVisible();
        const bannedTime = bannedCell.locator('time').first();
        await expect(bannedTime).toBeVisible();
        const bannedTimeMetrics = await bannedTime.evaluate((el) => {
            const cs = window.getComputedStyle(el);
            const fontSize = parseFloat(cs.fontSize);
            return {
                height: el.getBoundingClientRect().height,
                fontSize,
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
            };
        });
        expect(
            bannedTimeMetrics.height,
            `BANNED <time> must render on one line (got ${bannedTimeMetrics.height}px / fontSize ${bannedTimeMetrics.fontSize}px)`,
        ).toBeLessThan(bannedTimeMetrics.fontSize * 2);
        // And the cell doesn't introduce a horizontal scroll inside
        // the `<time>` element — i.e. `white-space: nowrap` keeps
        // the date in a single, fully-painted run rather than
        // overflowing past its parent.
        expect(
            bannedTimeMetrics.scrollWidth,
            'BANNED <time> content fits within its rendered width',
        ).toBeLessThanOrEqual(bannedTimeMetrics.clientWidth + 1);

        // The status pill itself stays inside its column's painted
        // box — i.e. it doesn't bleed off the right edge of the
        // table. The card around the table has `overflow: hidden`,
        // so a pill that overflows would actually be CLIPPED to the
        // viewer; the bounding-box check below catches the
        // pre-fix shape where the pill's right edge sat past the
        // table's right edge.
        const pill = seededRow.locator('td.col-status .pill');
        await expect(pill).toBeVisible();
        const tableBox = await table.boundingBox();
        const pillBox = await pill.boundingBox();
        expect(tableBox, 'table renders a bounding box').not.toBeNull();
        expect(pillBox, 'status pill renders a bounding box').not.toBeNull();
        // The card's inner `overflow: hidden` clips negative
        // overflow on either side; assert both edges to catch
        // a regression in either direction.
        expect(pillBox!.x).toBeGreaterThanOrEqual(tableBox!.x - 1);
        expect(pillBox!.x + pillBox!.width).toBeLessThanOrEqual(
            tableBox!.x + tableBox!.width + 1,
        );
    });

    test('the table is wrapped in .table-scroll for narrow-viewport overflow', async ({ page }) => {
        await page.goto('/index.php?p=banlist');

        // The wrapper is the runtime escape hatch for the rare case
        // where the natural column widths still exceed the panel
        // (1024-1100px viewport zone after the sidebar collapses).
        // Without it, a content-heavy row clips behind the card's
        // `overflow: hidden`. Assert the wrapper is in the DOM
        // immediately around the `<table>` so a future refactor
        // can't silently drop the safety net.
        const wrapper = page.locator('#banlist-root .table-scroll');
        await expect(wrapper).toBeVisible();
        await expect(wrapper.locator('> table.table')).toBeVisible();
        const isOverflowAuto = await wrapper.evaluate((el) =>
            window.getComputedStyle(el).overflowX,
        );
        // `overflow-x: auto` is the contract; CSS may resolve it as
        // "auto" verbatim. The "scroll" fallback is also acceptable
        // (some browsers serialize the keyword differently). What
        // we're catching is a regression that drops the wrapper
        // back to the default `visible`, which would re-clip the
        // overflow case the audit found.
        expect(['auto', 'scroll']).toContain(isOverflowAuto);
    });
});
