/**
 * Filter-chip narrowing on the public comms list (`/index.php?p=commslist`)
 * — regression coverage for #1274.
 *
 * What this locks in
 * ------------------
 *
 *  - Each `[data-testid="filter-chip-<type>"]` chip submits its
 *    `?type=<value>` query and the SQL backend narrows the table to
 *    rows where `:prefix_comms.type` matches (`mute=1`, `gag=2`,
 *    `silence=3`). Pre-#1274 the chip click only flipped
 *    `aria-pressed`; the SQL builder branched only on the legacy
 *    `?advType=` advanced search, so the table stayed unchanged.
 *  - The `Active` chip submits `?state=active` and produces the same
 *    SQL filter as the existing "Hide inactive" session toggle. Both
 *    surfaces are wired through `$is_active_only` in the View, so
 *    their `aria-pressed` and the toggle button's
 *    pressed/label state stay consistent regardless of which was
 *    clicked first.
 *  - The `All` chip clears the type filter and brings every row back.
 *  - Pagination links carry `type=` / `state=` so paginated results
 *    stay filtered. Exercised end-to-end by the "pagination preserves
 *    chip filter" test: the larger `PAGINATION_FIXTURE` seeds enough
 *    rows to force a real page break, and the test drives page 1 →
 *    Next → page 2 → asserts the rows on page 2 are still narrowed
 *    by `type=mute` AND that the Prev/Next anchors thread the chip
 *    filter through their hrefs (the `$paginationLink` composition
 *    in `page.commslist.php`).
 *
 * Why we seed via the PHP shim instead of `Actions.CommsAdd`
 * ----------------------------------------------------------
 * `Actions.CommsAdd` accepts `type=1|2|3` but `type=3` does NOT write
 * a `:prefix_comms.type=3` row — it inserts BOTH a `type=1` and a
 * `type=2` row (the production "combined gag+mute" path; see
 * `web/api/handlers/comms.php`). The `silence` chip filters on
 * `CO.type=3` (a SourceComms-fork label the render path recognises;
 * see `$typeLabel` in `web/pages/page.commslist.php`), so we'd have
 * no rows to assert against. `seedCommsRawE2e` is the smallest path
 * that gives us a `type=3` row plus mixed states; it's e2e-only and
 * refuses any DB other than `sourcebans_e2e`.
 *
 * Selectors
 * ---------
 * Per AGENTS.md "Testability hooks": every assertion uses
 * `data-testid` (`filter-chip-{all,active,mute,gag,silence}`,
 * `comm-row`, `comm-card`, `comms-count`, `toggle-hide-inactive`)
 * plus `data-type` / `data-state` row attributes. No CSS class
 * chains, no visible-text primaries.
 */

import { test, expect } from '../../fixtures/auth.ts';
import {
    seedCommsRawE2e,
    truncateE2eDb,
    type CommsSeedRow,
} from '../../fixtures/db.ts';

const COMMSLIST_ROUTE = '/index.php?p=commslist';

// Distinct STEAM ids per row so `hasText` filters disambiguate
// without needing nicknames. A `9913` prefix puts these well
// outside any range the other comms specs use (`9912xxx`,
// `7654321`, …) so ordering between specs doesn't matter.
const FIXTURE: CommsSeedRow[] = [
    { steam: 'STEAM_0:0:9913001', nickname: 'e2e-1274-mute-active',     type: 'mute',    state: 'active'    },
    { steam: 'STEAM_0:0:9913002', nickname: 'e2e-1274-mute-permanent',  type: 'mute',    state: 'permanent' },
    { steam: 'STEAM_0:0:9913003', nickname: 'e2e-1274-mute-unmuted',    type: 'mute',    state: 'unmuted'   },
    { steam: 'STEAM_0:0:9913004', nickname: 'e2e-1274-gag-active',      type: 'gag',     state: 'active'    },
    { steam: 'STEAM_0:0:9913005', nickname: 'e2e-1274-gag-expired',     type: 'gag',     state: 'expired'   },
    { steam: 'STEAM_0:0:9913006', nickname: 'e2e-1274-silence-active',  type: 'silence', state: 'active'    },
    { steam: 'STEAM_0:0:9913007', nickname: 'e2e-1274-silence-unmuted', type: 'silence', state: 'unmuted'   },
];

const TOTAL_ROWS = FIXTURE.length;
const ACTIVE_OR_PERMANENT_ROWS = FIXTURE.filter(
    (r) => r.state === 'active' || r.state === 'permanent',
).length;

function rowsOfType(type: CommsSeedRow['type']): number {
    return FIXTURE.filter((r) => r.type === type).length;
}

// Matches `banlist.bansperpage` in `web/install/includes/sql/data.sql`,
// which is what the e2e DB's `data.sql` seeds, so the e2e
// `SB_BANS_PER_PAGE` constant resolves to 30. Hardcoded rather than
// read from PHP so the spec stays self-contained — if the fresh-install
// default ever changes, the assertions below fail with a clear count
// mismatch and we update both in lockstep.
const BANS_PER_PAGE = 30;
// Page 1 fills exactly with mute rows; page 2 holds the overflow.
// 35 mute lines up cleanly: 30 on page 1, 5 on page 2.
const PAGINATION_MUTE_ROWS = 35;
// A handful of non-mute rows to confirm the filter actually narrows
// the SQL — without them, "page 1 has 30 rows" could be satisfied by
// a no-op filter that just returned the first 30 of any type.
const PAGINATION_NOISE_ROWS = 3;

/**
 * Build a fixture dense enough to force a real page break when filtered
 * to `?type=mute`. 35 mute rows split across page 1 (30) + page 2 (5),
 * plus 3 gag rows that should NEVER appear under the mute chip — they
 * exist purely to prove the filter narrows even at fixture sizes that
 * exceed `SB_BANS_PER_PAGE`.
 */
function buildPaginationFixture(): CommsSeedRow[] {
    const rows: CommsSeedRow[] = [];
    for (let i = 0; i < PAGINATION_MUTE_ROWS; i += 1) {
        rows.push({
            steam: `STEAM_0:0:9914${String(i).padStart(3, '0')}`,
            nickname: `e2e-1274-pag-mute-${i}`,
            type: 'mute',
            state: 'active',
        });
    }
    for (let i = 0; i < PAGINATION_NOISE_ROWS; i += 1) {
        rows.push({
            steam: `STEAM_0:0:9914${String(100 + i).padStart(3, '0')}`,
            nickname: `e2e-1274-pag-gag-${i}`,
            type: 'gag',
            state: 'active',
        });
    }
    return rows;
}

test.describe('flow: comms filter chips narrow the table (#1274)', () => {
    // Single-context state-mutating spec. truncateE2eDb()'s reset
    // is per-process atomic, but a sibling spec's reset still wipes
    // our seeded fixture mid-spec. Pin to chromium (matching
    // `comms-gag-mute.spec.ts`'s rationale) and keep the seed inside
    // beforeEach so each test re-seeds — the chrome below never
    // mutates the seeded rows so re-seeding is cheap and means a
    // sibling spec running in parallel (workers > 1, local-dev only;
    // CI pins workers=1) can't race us between tests within our
    // describe.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'state-mutating flow; truncateE2eDb is not parallel-project-safe',
        );
        await truncateE2eDb();
        await seedCommsRawE2e(FIXTURE);
    });

    test('All chip + initial mount renders every seeded row', async ({ page }) => {
        await page.goto(COMMSLIST_ROUTE);

        const rows = page.locator('[data-testid="comm-row"]');
        await expect(rows).toHaveCount(TOTAL_ROWS);

        // The All chip is server-rendered pressed when no filter is
        // active. Confirms the template's `{if $filters.type == ''
        // && !$is_active_only}` branch picks the no-filter shape.
        const allChip = page.locator('[data-testid="filter-chip-all"]');
        await expect(allChip).toHaveAttribute('aria-pressed', 'true');

        // Sibling chips are NOT pressed.
        for (const chip of ['active', 'mute', 'gag', 'silence']) {
            await expect(
                page.locator(`[data-testid="filter-chip-${chip}"]`),
            ).toHaveAttribute('aria-pressed', 'false');
        }
    });

    test('Mute chip narrows to mute rows only', async ({ page }) => {
        await page.goto(COMMSLIST_ROUTE);
        await page.locator('[data-testid="filter-chip-mute"]').click();
        await page.waitForURL(/[?&]type=mute(?:&|$)/);

        const rows = page.locator('[data-testid="comm-row"]');
        await expect(rows).toHaveCount(rowsOfType('mute'));

        const types = await rows.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-type')),
        );
        expect(types.every((t) => t === 'mute'), `expected every row data-type=mute, got ${JSON.stringify(types)}`).toBe(true);

        await expect(
            page.locator('[data-testid="filter-chip-mute"]'),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.locator('[data-testid="filter-chip-all"]'),
        ).toHaveAttribute('aria-pressed', 'false');
    });

    test('Gag chip narrows to gag rows only', async ({ page }) => {
        await page.goto(COMMSLIST_ROUTE);
        await page.locator('[data-testid="filter-chip-gag"]').click();
        await page.waitForURL(/[?&]type=gag(?:&|$)/);

        const rows = page.locator('[data-testid="comm-row"]');
        await expect(rows).toHaveCount(rowsOfType('gag'));

        const types = await rows.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-type')),
        );
        expect(types.every((t) => t === 'gag'), `expected every row data-type=gag, got ${JSON.stringify(types)}`).toBe(true);

        await expect(
            page.locator('[data-testid="filter-chip-gag"]'),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    test('Silence chip submits ?type=silence (NOT ?state=silence) and narrows to type=3 rows', async ({ page }) => {
        // The pre-#1274 template had `name="state" value="silence"` on
        // this chip — a bug visible only as "the chip does nothing".
        // The waitForURL regex below would never match the legacy
        // shape, so a regression to `name="state"` fails the spec
        // immediately.
        await page.goto(COMMSLIST_ROUTE);
        await page.locator('[data-testid="filter-chip-silence"]').click();
        await page.waitForURL(/[?&]type=silence(?:&|$)/);
        // Defensive: ensure no `state=silence` param leaked back in.
        const url = new URL(page.url());
        expect(url.searchParams.get('state')).not.toBe('silence');

        const rows = page.locator('[data-testid="comm-row"]');
        await expect(rows).toHaveCount(rowsOfType('silence'));

        const types = await rows.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-type')),
        );
        expect(types.every((t) => t === 'silence'), `expected every row data-type=silence, got ${JSON.stringify(types)}`).toBe(true);

        await expect(
            page.locator('[data-testid="filter-chip-silence"]'),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    test('Active chip narrows to active+permanent rows AND mirrors the Hide-inactive toggle', async ({ page }) => {
        await page.goto(COMMSLIST_ROUTE);
        await page.locator('[data-testid="filter-chip-active"]').click();
        await page.waitForURL(/[?&]state=active(?:&|$)/);

        const rows = page.locator('[data-testid="comm-row"]');
        await expect(rows).toHaveCount(ACTIVE_OR_PERMANENT_ROWS);

        // Every row is either `active` or `permanent` (the chip's
        // SQL predicate `RemoveType IS NULL AND (length=0 OR
        // ends > UNIX_TIMESTAMP())` admits both).
        const states = await rows.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-state')),
        );
        expect(
            states.every((s) => s === 'active' || s === 'permanent'),
            `expected every row data-state in {active,permanent}, got ${JSON.stringify(states)}`,
        ).toBe(true);

        // The chip AND the toggle button BOTH report aria-pressed=true
        // off the same `$is_active_only` flag. Pre-fix the chip and
        // the toggle drifted: clicking the chip flipped only the
        // chip's aria-pressed; clicking the toggle flipped only the
        // toggle's. Now they share state.
        await expect(
            page.locator('[data-testid="filter-chip-active"]'),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.locator('[data-testid="toggle-hide-inactive"]'),
        ).toHaveAttribute('aria-pressed', 'true');

        // The toggle's URL drops BOTH the session AND the URL state
        // when going OFF — clicking it from a `?state=active` URL
        // navigates to `hideinactive=false` WITHOUT preserving
        // `state=active`, otherwise the chip would stay pressed
        // after the toggle was supposed to clear it.
        const toggleHref = await page
            .locator('[data-testid="toggle-hide-inactive"]')
            .getAttribute('href');
        expect(toggleHref, 'toggle exposes a hideinactive URL').toMatch(
            /hideinactive=false/,
        );
        expect(toggleHref ?? '', 'toggle URL does not re-leak state=active').not.toMatch(
            /[?&]state=active\b/,
        );
    });

    test('All chip after another filter restores the unfiltered count', async ({ page }) => {
        // Navigate to a filtered URL directly (no chip click — we want
        // to assert the All chip's *reset* behaviour, not a click chain).
        await page.goto(`${COMMSLIST_ROUTE}&type=mute`);
        await expect(
            page.locator('[data-testid="comm-row"]'),
        ).toHaveCount(rowsOfType('mute'));

        await page.locator('[data-testid="filter-chip-all"]').click();
        // The All chip submits `name="type" value=""` so the URL
        // arrives with `type=` (empty). We assert against the
        // resulting row count rather than the URL param's exact
        // shape so a future "drop the empty type=" optimisation
        // doesn't break the spec.
        await expect(
            page.locator('[data-testid="comm-row"]'),
        ).toHaveCount(TOTAL_ROWS);

        await expect(
            page.locator('[data-testid="filter-chip-all"]'),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    test('Pagination preserves the chip filter across page-1 → page-2 navigation', async ({ page }) => {
        // beforeEach already truncated + seeded the small FIXTURE;
        // re-truncate and re-seed with the larger pagination fixture
        // for this test only. Serial mode (describe.configure above)
        // means no other test in this file races the swap.
        await truncateE2eDb();
        await seedCommsRawE2e(buildPaginationFixture());

        await page.goto(`${COMMSLIST_ROUTE}&type=mute`);

        // Page 1 fills with exactly BANS_PER_PAGE mute rows. The
        // 3 noise gag rows MUST be absent, otherwise the SQL filter
        // didn't actually narrow — it'd just slice the first 30 of
        // mixed types.
        const rowsPage1 = page.locator('[data-testid="comm-row"]');
        await expect(rowsPage1).toHaveCount(BANS_PER_PAGE);
        const typesPage1 = await rowsPage1.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-type')),
        );
        expect(
            typesPage1.every((t) => t === 'mute'),
            `expected every page-1 row data-type=mute, got ${JSON.stringify(typesPage1)}`,
        ).toBe(true);

        // The Next anchor is server-rendered with an href because
        // BansEnd (30) < BanCount (35). Its href is the canonical
        // surface for `$paginationLink`'s composition — must thread
        // `type=mute` through alongside `page=2`.
        const nextLink = page.locator('a[data-testid="page-next"]');
        await expect(nextLink).toBeVisible();
        const nextHref = await nextLink.getAttribute('href');
        expect(nextHref, 'next link exposes an href').toBeTruthy();
        expect(nextHref ?? '', 'next link href advances to page=2').toMatch(/[?&]page=2(?:&|$)/);
        expect(nextHref ?? '', 'next link href carries type=mute').toMatch(/[?&]type=mute(?:&|$)/);

        await nextLink.click();
        // Order-tolerant URL wait: just confirm we landed on page=2.
        // The type=mute presence is asserted via URLSearchParams below
        // so the spec doesn't break if the param order ever changes.
        await page.waitForURL(/[?&]page=2(?:&|$)/);
        const landed = new URL(page.url());
        expect(landed.searchParams.get('type'), 'page=2 URL preserves type=mute').toBe('mute');

        // Page 2 holds the remaining mute rows (35 - 30 = 5), still
        // narrowed to type=mute. The noise gag rows continue to be
        // excluded by the chip filter — this is the criterion the
        // pre-fix backend silently violated.
        const rowsPage2 = page.locator('[data-testid="comm-row"]');
        await expect(rowsPage2).toHaveCount(PAGINATION_MUTE_ROWS - BANS_PER_PAGE);
        const typesPage2 = await rowsPage2.evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-type')),
        );
        expect(
            typesPage2.every((t) => t === 'mute'),
            `expected every page-2 row data-type=mute, got ${JSON.stringify(typesPage2)}`,
        ).toBe(true);

        // Prev link on page 2 round-trips the chip filter back to
        // page 1. Symmetric to the Next assertion above; together they
        // cover both edges of `$paginationLink`'s composition.
        const prevLink = page.locator('a[data-testid="page-prev"]');
        await expect(prevLink).toBeVisible();
        const prevHref = await prevLink.getAttribute('href');
        expect(prevHref, 'prev link exposes an href').toBeTruthy();
        expect(prevHref ?? '', 'prev link href returns to page=1').toMatch(/[?&]page=1(?:&|$)/);
        expect(prevHref ?? '', 'prev link href carries type=mute').toMatch(/[?&]type=mute(?:&|$)/);
    });
});
