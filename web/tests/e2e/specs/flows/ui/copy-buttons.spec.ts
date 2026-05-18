/**
 * Copy buttons (#1308) — every `[data-copy]` surface on the panel
 * (banlist row's SteamID button, drawer's identity rows, future
 * history-list copy hooks, …) wires through the SAME document-level
 * COPY BUTTONS click delegate in `web/themes/default/js/theme.js`.
 *
 * Two regressions this spec locks in (the underlying bug report):
 *
 *   Defect A — banlist row's copy button was a dead button.
 *     The pre-#1308 template carried `onclick="event.stopPropagation()"`
 *     on the `<button data-copy=…>`. The document-level `[data-copy]`
 *     delegate listens on the bubble phase, so the element-level
 *     `stopPropagation` killed every click before it reached the
 *     handler — no toast, no clipboard write, no console error. The
 *     fix removes the inline `stopPropagation`. The desktop row's
 *     drawer trigger is the player-name anchor (`data-drawer-href`),
 *     not a row-level delegate, so a bubbling click from a sibling
 *     row-action button has nothing to confuse.
 *
 *   Defect B — drawer / row copy buttons silently lied on plain HTTP.
 *     The pre-#1308 delegate body was
 *     `if (navigator.clipboard) navigator.clipboard.writeText(value);
 *      showToast({kind:'success', title:'Copied to clipboard'});`.
 *     On non-secure contexts (plain HTTP, non-localhost — the typical
 *     self-hoster behind a TLS-terminating reverse proxy where the
 *     panel sees plain HTTP) `navigator.clipboard` is `undefined`,
 *     the writeText call is a silent no-op, and the success toast
 *     fires anyway — the user sees "Copied" but the clipboard is
 *     empty. The fix feature-detects both `navigator.clipboard` AND
 *     `window.isSecureContext`, drops to a hidden-textarea +
 *     `document.execCommand('copy')` fallback when either is missing,
 *     and chains `.then(success, fallback)` on the Promise so a
 *     rejected `writeText()` ends up on the same fallback path.
 *
 * Test runtime is over `localhost`, which IS a secure context per
 * the spec — `navigator.clipboard` is defined and `writeText`
 * resolves. Defect A is exercised by clicking the real button;
 * Defect B is exercised by patching `navigator.clipboard` to
 * `undefined` in the page context before the click, which forces
 * the delegate down the fallback path.
 */

import type { Page } from '@playwright/test';
import { expect, test } from '../../../fixtures/auth.ts';
import { seedBanViaApi } from '../../../fixtures/seeds.ts';

const SEED_NICK_PREFIX = 'e2e-copy-buttons';

/**
 * Per-subtest authid offset. Same shape every spec under this
 * directory uses (see `command-palette.spec.ts` /
 * `player-drawer.spec.ts` for the rationale): unique-per-(subtest ×
 * project × worker) seeds keep the parallel chromium /
 * mobile-chromium projects from racing on the shared
 * `sourcebans_e2e` DB without a `truncateE2eDb()` call between
 * specs.
 */
const SUBTEST_OFFSETS = {
    banlistRow: 0,
    drawerOverview: 1,
    fallback: 2,
} as const;

function uniqueSeed(
    testInfo: import('@playwright/test').TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset = SUBTEST_OFFSETS[subtest];
    // 5_553_000 base keeps these seeds out of slice 6's palette
    // (5_550_***) and drawer (5_551_***) ranges. Per-worker stride
    // of 1_000 leaves headroom for new subtests within #1308.
    const account = 5_553_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick: `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:1:${account}`,
    };
}

/**
 * Seed-or-lookup helper mirrors player-drawer.spec.ts: a Playwright
 * retry on the same worker hits `already_banned` (the prior attempt
 * left a permanent ban under this authid), so we tolerate that path
 * and look the bid up via `bans.search` instead of failing.
 */
async function seedOrLookup(
    page: Page,
    seed: { nick: string; steam: string },
): Promise<number> {
    try {
        const created = await seedBanViaApi(page, {
            nickname: seed.nick,
            steam: seed.steam,
            reason: 'e2e/copy-buttons seed',
        });
        return created.bid;
    } catch (err) {
        if (!String(err).includes('already_banned')) throw err;
    }
    const bid = await page.evaluate(async (steam) => {
        const w = window as unknown as {
            sb: {
                api: {
                    call: (
                        action: string,
                        params: Record<string, unknown>,
                    ) => Promise<{ ok: boolean; data?: { bans?: Array<{ bid: number; steam: string }> } }>;
                };
            };
            Actions: Record<string, string>;
        };
        const env = await w.sb.api.call(w.Actions.BansSearch, { q: steam, limit: 5 });
        const found = env?.data?.bans?.find((b) => b.steam === steam);
        return found?.bid ?? null;
    }, seed.steam);
    if (bid === null) {
        throw new Error(
            `seedOrLookup: bans.add reported already_banned for ${seed.steam} but bans.search couldn't find the row`,
        );
    }
    return bid;
}

test.describe('copy buttons', () => {
    test('banlist desktop row copy button writes the SteamID and toasts (#1308 Defect A)', async ({ page, context }, testInfo) => {
        // Mobile project skipped: the desktop `<table>` is `display:none`
        // at <=768px (theme.css `<=768px` block) and the mobile cards
        // block doesn't render a per-row copy button — it's a single
        // anchor wrapping the whole row. Mobile parity for copy
        // affordances rides the player drawer, covered by the
        // `drawerOverview` subtest below + the existing
        // `responsive/drawer.spec.ts` layout assertions.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'Banlist row copy button is desktop-only chrome (table hidden at <=768px); drawer copy is exercised separately.',
        );

        const seed = uniqueSeed(testInfo, 'banlistRow');
        await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');

        // Grant clipboard permissions. Chromium grants `clipboard-write`
        // implicitly for user-gesture writes but `clipboard-read` —
        // which we need to verify the value actually landed — requires
        // an explicit grant on the test origin. Mirrors the shape used
        // in `command-palette.spec.ts`'s Ctrl+Enter copy subtest.
        await context.grantPermissions(['clipboard-read', 'clipboard-write'], {
            origin: new URL(page.url()).origin,
        });

        // Find our seeded row in the desktop table. The row carries
        // `data-testid="ban-row"` and the copy button inside it carries
        // `data-testid="row-action-copy-steam"` (added in #1308 so we
        // can locate it without depending on the visible 📋 glyph or
        // the steam-id substring). The `:has-text` filter scopes the
        // selection to the seeded row regardless of pagination.
        const row = page.locator('[data-testid="ban-row"]', { hasText: seed.nick }).first();
        await expect(row).toBeVisible();

        const copyBtn = row.locator('[data-testid="row-action-copy-steam"]');
        await expect(copyBtn).toBeVisible();
        // Pre-#1308 the button carried `onclick="event.stopPropagation()"`
        // — the regression guard. Its absence is what unblocks the
        // document-level [data-copy] delegate. We assert on the
        // attribute directly so a re-introduction of the inline
        // handler (defensive copy-paste from the sibling Edit/Unban
        // anchors) fails this spec.
        await expect(copyBtn).not.toHaveAttribute('onclick', /.+/);

        await copyBtn.click();

        // Toast surface is shared across the panel; filter by the
        // success copy so a stray earlier toast doesn't false-positive.
        const toast = page.locator('.toast').filter({ hasText: 'Copied to clipboard' });
        await expect(toast).toBeVisible();

        // The clipboard actually carries the SteamID. The grantPermissions
        // call above is what makes this readback work outside Playwright's
        // default permission set.
        const clipboardValue = await page.evaluate(() => navigator.clipboard.readText());
        expect(clipboardValue).toBe(seed.steam);
    });

    test('drawer overview copy button writes the SteamID and toasts (#1308 Defect A regression for drawer)', async ({ page, context }, testInfo) => {
        // Desktop-only flow: the `<table>` row is `display:none` at
        // <=768px so the desktop drawer trigger isn't actionable on
        // mobile. The mobile drawer surface has its own coverage in
        // `responsive/drawer.spec.ts` and shares the same document
        // delegate, so the regression guard here is sufficient for
        // both viewports.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'Desktop drawer flow; mobile drawer chrome is covered by responsive/drawer.spec.ts.',
        );

        const seed = uniqueSeed(testInfo, 'drawerOverview');
        const bid = await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');
        await context.grantPermissions(['clipboard-read', 'clipboard-write'], {
            origin: new URL(page.url()).origin,
        });

        await page
            .locator(
                `[data-testid="drawer-trigger"][data-drawer-href*="id=${bid}"]`,
            )
            .first()
            .click();

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', /.+/);

        // Identity rows render as `<dt>SteamID</dt><dd>VALUE<button data-copy=VALUE></button></dd>`.
        // Pin the SteamID `<dd>` then locate the copy button inside —
        // the value column also carries Steam3, Community, IP rows so
        // a bare `[data-copy]` would match more than one node.
        const idGrid = drawer.locator('[data-testid="drawer-ids"]');
        await expect(idGrid).toBeVisible();
        const steamRow = idGrid.locator('dt:has-text("SteamID") + dd');
        await expect(steamRow).toBeVisible();

        const copyBtn = steamRow.locator('[data-copy]');
        await expect(copyBtn).toBeVisible();
        await copyBtn.click();

        const toast = page.locator('.toast').filter({ hasText: 'Copied to clipboard' });
        await expect(toast).toBeVisible();

        const clipboardValue = await page.evaluate(() => navigator.clipboard.readText());
        expect(clipboardValue).toBe(seed.steam);
    });

    test('non-secure context falls back to document.execCommand and toasts honestly (#1308 Defect B)', async ({ page }, testInfo) => {
        // Defect B reproducer: simulate the plain-HTTP self-hoster
        // setup by patching `navigator.clipboard` to `undefined` in
        // the page context BEFORE the click, then click the row's
        // copy button. The pre-#1308 delegate would silently no-op
        // the clipboard write but fire the success toast anyway. The
        // post-fix delegate detects the missing API and drops to the
        // hidden-textarea + `document.execCommand('copy')` fallback,
        // which the chromium runner DOES implement (deprecated but
        // shipping).
        //
        // We can't actually flip `window.isSecureContext` from JS
        // (read-only on the runtime), so we monkey-patch
        // `navigator.clipboard` instead — the delegate's gate is
        // `navigator.clipboard && window.isSecureContext`, and
        // wiping the former is enough to send execution through the
        // fallback branch. The real bug shape on plain HTTP is
        // `clipboard === undefined`; our monkey-patch reproduces
        // that exactly.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'Banlist row copy button is desktop-only chrome (table hidden at <=768px).',
        );

        const seed = uniqueSeed(testInfo, 'fallback');
        await seedOrLookup(page, seed);

        await page.goto('/index.php?p=banlist');

        // Patch the clipboard API to undefined so the delegate's
        // secure-context branch is bypassed. Use defineProperty
        // because `navigator.clipboard` is a getter on a read-only
        // descriptor — direct assignment silently fails on chromium.
        //
        // ALSO install a `document.execCommand` spy that records the
        // commands invoked on it. The original bug shape WAS the
        // unconditional success toast, so asserting the toast alone
        // doesn't distinguish the fix from the regression — the
        // pre-#1308 delegate also fires the success toast on this
        // exact path (clipboard undefined, no fallback). The spy
        // makes the assertion sharp: a 'copy' command must have been
        // dispatched against `document` for the test to count as a
        // pass, and that is the unique signal of the fix's fallback
        // branch firing. (A future delegate that drops the fallback
        // and just toasts would resurrect Defect B and this assertion
        // would catch it; the toast assertion below is now belt-and-
        // suspenders rather than the load-bearing check.)
        await page.evaluate(() => {
            Object.defineProperty(navigator, 'clipboard', {
                value: undefined,
                configurable: true,
            });
            const w = window as unknown as { __execCommandCalls: string[] };
            w.__execCommandCalls = [];
            const orig = document.execCommand.bind(document);
            // Cast to any so the spy can substitute for the legacy
            // variadic signature that the e2e tsconfig doesn't model.
            (document as { execCommand: (cmd: string) => boolean }).execCommand = function (cmd: string): boolean {
                w.__execCommandCalls.push(cmd);
                return orig(cmd);
            };
        });

        const row = page.locator('[data-testid="ban-row"]', { hasText: seed.nick }).first();
        await expect(row).toBeVisible();
        const copyBtn = row.locator('[data-testid="row-action-copy-steam"]');
        await expect(copyBtn).toBeVisible();

        await copyBtn.click();

        // Load-bearing assertion: the fallback path was actually taken.
        // The spy records every `document.execCommand(cmd, …)` call;
        // the fix dispatches `'copy'` against the hidden textarea, so
        // the spy must have captured at least one `'copy'` invocation.
        // Pre-#1308 (clipboard undefined branch silently no-ops then
        // toasts success) the spy stays empty and this assertion fails.
        const execCalls = await page.evaluate(
            () => (window as unknown as { __execCommandCalls: string[] }).__execCommandCalls,
        );
        expect(execCalls).toContain('copy');

        // Belt-and-suspenders: the fallback toast still fires on
        // success. The execCommand call is a synchronous, user-
        // gesture-scoped copy that chromium's headless runner honours,
        // so the toast still reads "Copied to clipboard". A future
        // regression that drops the honest "Couldn't copy" error
        // toast on the fallback's failure branch is locked in the
        // source comment for code-review catch; we don't simulate
        // execCommand failure here because chromium's headless runner
        // always succeeds on a focused selected textarea.
        const toast = page.locator('.toast').filter({ hasText: 'Copied to clipboard' });
        await expect(toast).toBeVisible();
    });
});
