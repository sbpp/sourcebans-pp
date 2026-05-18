/**
 * Comms-list drawer parity (#COMMS-DRAWER).
 *
 * The bans list opened a player-detail drawer on row click since #1124
 * (`data-drawer-href` on the player-name anchor → theme.js click
 * delegate → `bans.detail` → `renderDrawerBody`). The comms list
 * shipped without an equivalent surface — clicking a player name on
 * `?p=commslist` did nothing. This spec drives the parity fix:
 *
 *   - `data-drawer-cid` on the desktop player-name anchor and on the
 *     mobile-card summary anchor (`page_comms.tpl`),
 *   - `comms.detail` JSON action returning the same envelope shape
 *     as `bans.detail` but rooted at a comm-block id (`cid`),
 *   - kind-aware `loadDrawer(key)` / `renderOverviewPane` / pane
 *     loaders (theme.js): the drawer header reads "Comm #N", the
 *     Overview pane shows the focal block's `type_label` row, and
 *     the History / Comms tabs both fetch by `authid` so a
 *     comm-focal drawer still surfaces the player's full ban history.
 *
 * Sister spec: `flows/ui/player-drawer.spec.ts` (the marquee bans-side
 * coverage). Mobile parity for the comm-focal mobile-card click lives
 * under `responsive/drawer.spec.ts`; this spec gates desktop-only the
 * same way the player-drawer one does.
 *
 * Isolation strategy
 * ------------------
 * Mirror `player-drawer.spec.ts`: NO `truncateE2eDb()` between tests,
 * unique authids per (subtest × project × worker), and a `seedOrLookup`
 * helper that tolerates `already_blocked` so a Playwright retry on the
 * same worker reuses the existing row. The sister `comms-affordances`
 * spec DOES truncate (it tests state-changing flows where stale state
 * would corrupt assertions); doubling the truncate count by adding
 * truncates here would only widen the cross-file race window where a
 * concurrent worker's API call lands during another worker's truncate
 * → reseed gap and gets a `forbidden` cascade. The drawer-parity tests
 * are read-shaped (open the drawer, assert the chrome) so isolation by
 * authid namespacing is enough.
 */

import type { Page, TestInfo } from '@playwright/test';
import { expect, test } from '../../../fixtures/auth.ts';
import { seedCommViaApi } from '../../../fixtures/seeds.ts';

const SEED_NICK_PREFIX = 'e2e-comms-drawer';
const SEED_REASON = 'e2e comms drawer seed reason';

const SUBTEST_OFFSETS = {
    open: 0,
    tabs: 1,
    esc:  2,
} as const;

function uniqueSeed(
    testInfo: TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset = SUBTEST_OFFSETS[subtest];
    // Same shape as player-drawer.spec.ts: per-subtest authid offset
    // keeps the parallel chromium / mobile-chromium projects from
    // racing on the shared `sourcebans_e2e` DB. 5_553_000 base sits
    // adjacent to the existing 5_551_000 (player-drawer) and
    // 5_550_000 (palette) ranges without colliding.
    const account = 5_553_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick: `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:0:${account}`,
    };
}

/**
 * `comms.add` doesn't echo the new row's cid back (the JSON envelope
 * is `{reload: true, block: {steam, type, length}}` — see
 * `web/api/handlers/comms.php#api_comms_add`), so specs that need a
 * cid look it up off the rendered row's `data-id` attribute. The
 * desktop `<tr data-testid="comm-row">` and the mobile
 * `<div data-testid="comm-card">` both stamp the cid there, so the
 * same helper works for either chrome.
 *
 * Filters by SteamID (the rendered list shows the SteamID in every
 * row) — `nickname` collides across subtests because the unique-seed
 * scheme only varies the suffix per worker, but each worker / subtest
 * gets a unique authid, so SteamID is the right disambiguator.
 */
async function readCidFromCommslist(page: Page, steam: string): Promise<number> {
    const row = page
        .locator('[data-testid="comm-row"], [data-testid="comm-card"]')
        .filter({ hasText: steam })
        .first();
    await expect(row).toHaveCount(1);
    const cidAttr = await row.getAttribute('data-id');
    if (!cidAttr || !/^\d+$/.test(cidAttr)) {
        throw new Error(`readCidFromCommslist: row for ${steam} has no numeric data-id (got '${cidAttr}')`);
    }
    return parseInt(cidAttr, 10);
}

/**
 * Seed a comm-block, tolerating `already_blocked` from a previous
 * Playwright retry on the same worker. Mirrors the bans-side
 * `seedOrLookup` shape in `player-drawer.spec.ts` — the row exists
 * either way and the spec recovers the cid via
 * `readCidFromCommslist` after navigating to `?p=commslist`.
 */
async function seedCommOrAccept(
    page: Page,
    seed: { nick: string; steam: string; reason?: string; type?: 1 | 2; length?: number },
): Promise<void> {
    try {
        await seedCommViaApi(page, {
            nickname: seed.nick,
            steam: seed.steam,
            reason: seed.reason ?? SEED_REASON,
            type: seed.type ?? 1,
            length: seed.length ?? 60,
        });
    } catch (err) {
        if (!String(err).includes('already_blocked')) throw err;
    }
}

/**
 * Seed a ban under the supplied authid, tolerating `already_banned`
 * the same way `seedCommOrAccept` tolerates `already_blocked` — both
 * end states leave the row we want on disk. Used by the History-tab
 * subtest below.
 */
async function seedBanOrAccept(
    page: Page,
    seed: { nick: string; steam: string; reason: string },
): Promise<void> {
    await page.goto('/');
    const env = await page.evaluate(
        async ({ nick, steam, reason }) => {
            const w = window as unknown as {
                sb: { api: { call: (a: string, p: Record<string, unknown>) => Promise<{ ok: boolean; error?: { code: string } }>; }; };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.BansAdd, {
                nickname: nick,
                steam: steam,
                type: 0,
                ip: '',
                length: 0,
                reason,
            });
        },
        seed,
    );
    if (!env.ok && env.error?.code !== 'already_banned') {
        throw new Error(`seedBanOrAccept: bans.add failed (${env.error?.code ?? 'unknown'})`);
    }
}

test.describe('comms-list drawer parity', () => {
    // Desktop-only gate: `page_comms.tpl` renders two anchors per
    // row — one inside the desktop `<table>` (display:none below
    // 769px) and one inside the mobile `.ban-cards` wrapper
    // (display:none at >=769px). Both carry
    // `[data-testid="drawer-trigger"][data-drawer-cid="..."]`. A
    // `.first()` selector lands on the desktop row in DOM order;
    // clicking that on a mobile viewport is rejected by Playwright's
    // actionability check because the parent table is hidden. The
    // mobile parity spec lives under `responsive/drawer.spec.ts`.
    test.skip(
        ({ isMobile }) => Boolean(isMobile),
        'flow runs against the desktop chrome; mobile chrome lives under responsive/drawer.spec.ts',
    );

    test('clicking a comms-list row opens the drawer with comm-block data', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'open');
        await seedCommOrAccept(page, {
            nick: seed.nick,
            steam: seed.steam,
            reason: SEED_REASON,
            type: 1,
            length: 60,
        });

        await page.goto('/index.php?p=commslist');
        const cid = await readCidFromCommslist(page, seed.steam);

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');

        // Anchor on `data-drawer-cid` so the selector survives the
        // template re-rendering the same data on different surfaces
        // (palette rows, drawer history items, etc.). `.first()` after
        // the desktop-only gate above always lands on the
        // `<tr data-testid="comm-row">`-side anchor.
        const rowTrigger = page
            .locator(`[data-testid="drawer-trigger"][data-drawer-cid="${cid}"]`)
            .first();
        await rowTrigger.click();

        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        // Header chip reads "Comm #N" (NOT "Ban #N"). The drawer's
        // `renderDrawerBody` branches on `drawerKind` for the chip
        // label; this assertion is the canonical regression catch
        // for "comm-focal drawer paints the ban-focal layout".
        const header = drawer.locator('.drawer__header');
        await expect(header).toContainText(`Comm #${cid}`);

        const overview = drawer.locator('[data-testid="drawer-panel-overview"]');
        await expect(overview).toBeVisible();

        // Overview pane uses `[data-testid="drawer-block"]` for the
        // focal-record `<dl>` (vs `[data-testid="drawer-ban"]` on the
        // bans-side path). The Type row is comm-focal-only — bans
        // don't have it.
        const blockGrid = overview.locator('[data-testid="drawer-block"]');
        await expect(blockGrid).toBeVisible();
        await expect(blockGrid.locator('dt', { hasText: 'Type' })).toBeVisible();
        await expect(blockGrid).toContainText('Mute');
        await expect(blockGrid.locator('dt', { hasText: 'Reason' })).toBeVisible();
        await expect(blockGrid).toContainText(SEED_REASON);

        // ID grid renders the player's SteamID exactly once.
        const idGrid = overview.locator('[data-testid="drawer-ids"]');
        await expect(idGrid).toBeVisible();
        await expect(idGrid).toContainText(seed.steam);

        // Pane chrome: Overview is active on first paint; the other
        // tabs start aria-selected=false. The Notes tab is admin-only
        // (admins see four tabs; public callers get three) — the
        // default storage state is admin so all four are present.
        await expect(drawer.locator('[data-testid="drawer-tab-overview"]')).toHaveAttribute('aria-selected', 'true');
        await expect(drawer.locator('[data-testid="drawer-tab-history"]')).toHaveAttribute('aria-selected', 'false');
        await expect(drawer.locator('[data-testid="drawer-tab-comms"]')).toHaveAttribute('aria-selected', 'false');
        await expect(drawer.locator('[data-testid="drawer-tab-notes"]')).toHaveAttribute('aria-selected', 'false');
    });

    test('History + Comms tabs lazy-load and exclude the focal record', async ({ page }, testInfo) => {
        // Drawer parity contract: comm-focal drawer's lazy panes call
        //   - `bans.player_history`  with `{authid: <steam>}` — no
        //     focal bid to exclude (the focal is a comm), and the
        //     handler matches Steam bans only.
        //   - `comms.player_history` with `{cid: <focal cid>}` — the
        //     handler resolves to authid and excludes the focal cid
        //     from the result so the Overview pane and the Comms tab
        //     don't render the same record twice (sister behaviour
        //     of the bans-focal History pane's `bid <> ?` exclusion).
        //
        // We seed three rows under the same authid: a focal comm, a
        // sibling comm, and a sibling ban. Both panes must show the
        // sibling rows AND must NOT show the focal record.
        const seed = uniqueSeed(testInfo, 'tabs');
        const SIBLING_COMM_REASON = 'sibling comm-block reason';
        const SIBLING_BAN_REASON  = 'sibling ban for comm-drawer history';

        await seedCommOrAccept(page, {
            nick: seed.nick,
            steam: seed.steam,
            reason: 'comm primary',
            type: 2, // gag
            length: 60,
        });
        await seedCommOrAccept(page, {
            nick: 'sibling-comm',
            steam: seed.steam,
            reason: SIBLING_COMM_REASON,
            type: 1, // mute (different from the focal's gag — keeps `already_blocked` keyed per type)
            length: 30,
        });
        await seedBanOrAccept(page, {
            nick: 'sibling-ban',
            steam: seed.steam,
            reason: SIBLING_BAN_REASON,
        });

        await page.goto('/index.php?p=commslist');
        // Both comms render now; we want the focal (the gag — `data-type="gag"`)
        // and not the sibling (type=mute). Filter on the row's
        // `data-type` so a future change in row order doesn't pick
        // the sibling instead.
        const focalRow = page
            .locator('[data-testid="comm-row"][data-type="gag"], [data-testid="comm-card"][data-type="gag"]')
            .filter({ hasText: seed.steam })
            .first();
        await expect(focalRow).toHaveCount(1);
        const focalCidAttr = await focalRow.getAttribute('data-id');
        if (!focalCidAttr || !/^\d+$/.test(focalCidAttr)) {
            throw new Error(`focal cid lookup failed for ${seed.steam} (got '${focalCidAttr}')`);
        }
        const focalCid = parseInt(focalCidAttr, 10);

        await page
            .locator(`[data-testid="drawer-trigger"][data-drawer-cid="${focalCid}"]`)
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        // History tab — the sibling ban shows; the (non-existent)
        // focal-as-ban must not render anything beyond it. The empty-
        // state copy is "No prior bans on file." for parity, but with
        // a sibling ban present we land in the populated state.
        const historyTab = drawer.locator('[data-testid="drawer-tab-history"]');
        const historyPanel = drawer.locator('[data-testid="drawer-panel-history"]');
        await expect(historyPanel).toBeHidden();
        await historyTab.click();
        await expect(historyTab).toHaveAttribute('aria-selected', 'true');
        await expect(historyPanel).toBeVisible();
        await expect(historyPanel.locator('[data-testid="drawer-history-list"]')).toBeVisible();
        await expect(historyPanel).toContainText(SIBLING_BAN_REASON);

        // Comms tab — the SIBLING comm shows; the FOCAL comm is
        // excluded server-side (the new `cid <> ?` clause in
        // `api_comms_player_history` when called with `{cid}`). This
        // is the regression catch for the focal-exclusion contract.
        const commsTab = drawer.locator('[data-testid="drawer-tab-comms"]');
        const commsPanel = drawer.locator('[data-testid="drawer-panel-comms"]');
        await commsTab.click();
        await expect(commsPanel).toBeVisible();
        await expect(commsPanel.locator('[data-testid="drawer-comms-list"]')).toBeVisible();
        await expect(commsPanel).toContainText(SIBLING_COMM_REASON);
        await expect(commsPanel).not.toContainText('comm primary');
    });

    test('Esc closes the open comm-focal drawer', async ({ page }, testInfo) => {
        const seed = uniqueSeed(testInfo, 'esc');
        await seedCommOrAccept(page, {
            nick: seed.nick,
            steam: seed.steam,
            reason: SEED_REASON,
            type: 1,
            length: 60,
        });

        await page.goto('/index.php?p=commslist');
        const cid = await readCidFromCommslist(page, seed.steam);
        await page
            .locator(`[data-testid="drawer-trigger"][data-drawer-cid="${cid}"]`)
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        await page.keyboard.press('Escape');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');
    });
});
