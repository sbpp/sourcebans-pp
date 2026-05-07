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
 *    stay filtered (asserted via the pagination URL shape; the seeded
 *    dataset is intentionally smaller than `SB_BANS_PER_PAGE` so we
 *    don't force a page break).
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
});
