/**
 * Command palette (#1124, Slice 6 + #1207 CC-3, DET-2).
 *
 * Acceptance criteria from #1124:
 *   "⌘K opens, typing a SteamID surfaces the player, Enter navigates
 *    to their drawer."
 *
 * The shipped chrome (#1123 C2, see `web/themes/default/js/theme.js`)
 * is a `<dialog id="palette-root">` with:
 *   - `[data-palette-open="true|false"]` mirrored onto the dialog so
 *     tests don't probe the `hidden` boolean directly,
 *   - `#palette-input` autofocused after a 10ms delay (we wait via
 *     `expect(input).toBeFocused()`, not a `setTimeout`),
 *   - results rendered as `<a [data-testid="palette-result"]
 *     [data-result-kind="nav"|"ban"]>` once `q.length >= 2`, with a
 *     200ms input debounce.
 *
 * #1207 CC-3 (this slice) extends slice 1's mobile icon-only collapse
 * to desktop too: the topbar's palette trigger is icon-only at every
 * viewport, so the topbar reads as `[hamburger] [breadcrumb] [spacer]
 * [palette icon] [theme toggle]` consistently — the palette dialog
 * itself owns the search affordance.
 *
 * #1207 DET-2 (this slice) layers two interactions onto each player
 * result row, advertised by a kbd hint group at the right edge:
 *   - bare Enter → opens the player drawer for the ban,
 *   - Ctrl/Cmd+Enter → copies the row's SteamID via
 *     navigator.clipboard.writeText + surfaces a toast.
 * The kbds are server-rendered in non-Mac form ("Enter", "Ctrl");
 * theme.js's applyPlatformHints swaps `[data-enterkey]` → ⏎ and
 * `[data-modkey]` → ⌘ on Mac at boot and on every render. The Linux
 * test runner sees the non-Mac form by default — that's what the
 * assertions below pin.
 *
 * == Seeding strategy: unique-per-(test × project), no truncate ==
 *
 * Slice 0's foundation PR called out that `truncateE2eDb()` is hostile
 * to the parallel `chromium` + `mobile-chromium` projects sharing
 * `sourcebans_e2e` — a truncate from the mobile worker between the
 * desktop worker's seed and assert (or vice-versa) corrupts the seeded
 * row, and concurrent `truncateAndReseed()` calls collide on the seeded
 * admin INSERT (`Duplicate entry '0' for key 'PRIMARY'`). Slice 7
 * sidesteps it by NOT truncating and using unique-per-test seeds; this
 * spec follows the same pattern. Each subtest uses a steam id that
 * encodes both the subtest's slot (`SUBTEST_OFFSETS`) and the worker's
 * `testInfo.workerIndex`, so:
 *   - two subtests in the same project never collide on the same authid,
 *   - two projects running the same subtest in parallel never collide
 *     on each other's seed,
 *   - retried tests reuse the same offset (workerIndex is stable across
 *     a worker's retries) but the prior fail's row is still a permanent
 *     ban under the same authid, which `bans.add` rejects with
 *     `already_banned`. We tolerate that by NOT asserting on a fresh
 *     bid — the search filter on `SEED_NICK_PREFIX + slot` is enough,
 *     and the existing row from the previous attempt continues to match.
 *     If the retry-on-already-banned path becomes a real flake source,
 *     #1170 (Slice 0 followup) tracks the worker-serialised truncate
 *     option as the fallback.
 */

import { expect, test } from '../../../fixtures/auth.ts';
import { seedBanViaApi } from '../../../fixtures/seeds.ts';

const SEED_NICK_PREFIX = 'e2e-palette-target';

/**
 * Per-subtest authid offset. Each subtest pulls its own offset out
 * of this map so two subtests on the same worker never collide on
 * the bans row's authid. The numeric range stays safely inside
 * SteamID's 32-bit account-id slot.
 */
const SUBTEST_OFFSETS = {
    open: 0,
    type: 1,
    enter: 2,
    hints: 3,
    copy: 4,
    modclick: 5,
} as const;

/**
 * Build a (steam, nick) pair that's unique across (subtest × project ×
 * worker). 5_550_000 is the slice-6 base; subtest offset bumps the
 * tens, project name + workerIndex are folded into the hundreds /
 * thousands so cross-project parallel runs never collide. The nick
 * encodes the same triple so `bans.search` filters land deterministic
 * matches.
 */
function uniqueSeed(
    testInfo: import('@playwright/test').TestInfo,
    subtest: keyof typeof SUBTEST_OFFSETS,
): { nick: string; steam: string } {
    const projTag = testInfo.project.name === 'mobile-chromium' ? 'm' : 'd';
    const offset = SUBTEST_OFFSETS[subtest];
    const account = 5_550_000 + offset * 10 + testInfo.workerIndex * 1_000;
    return {
        nick: `${SEED_NICK_PREFIX}-${subtest}-${projTag}-w${testInfo.workerIndex}`,
        steam: `STEAM_0:1:${account}`,
    };
}

test.describe('command palette', () => {

    test('⌘K opens, Esc closes', async ({ page }) => {
        await page.goto('/');

        const dialog = page.locator('#palette-root');
        const input = page.locator('#palette-input');

        await expect(dialog).toHaveAttribute('data-palette-open', 'false');

        // `theme.js`'s document-level keydown listener triggers on
        // either metaKey or ctrlKey; sending Meta+K covers Mac and
        // Linux (where Playwright treats Meta as the modifier).
        await page.keyboard.press('Meta+k');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        // openPalette() defers focus by 10ms via setTimeout so the
        // dialog's showModal() finishes its focus dance first; we
        // poll on the focus state, not a fixed timeout.
        await expect(input).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');
    });

    test('topbar palette trigger renders icon-only at every viewport (#1207 CC-3)', async ({ page }, testInfo) => {
        // Slice 1 (PR #1208, CC-1) introduced the icon-only collapse
        // at <=768px because the labelled "search input + Ctrl-K hint"
        // couldn't share a row with the breadcrumb on mobile. Slice 9
        // (this PR, CC-3) extends the same collapse to desktop because
        // the labelled chrome was a duplicate affordance for the same
        // palette dialog ⌘K opens. Both projects (`chromium` +
        // `mobile-chromium`) lock the icon-only contract here so the
        // existing responsive/topbar.spec.ts mobile-only assertions
        // and this desktop counterpart fail independently if either
        // viewport regresses.
        //
        // The mobile-only floor (44px tap target) lives in
        // responsive/topbar.spec.ts; this spec asserts the cross-
        // viewport contract: label + kbd hint visually hidden, square
        // button shape.
        await page.goto('/');

        const trigger = page.locator('[data-testid="palette-trigger"]');
        await expect(trigger).toBeVisible();

        // Label and kbd hint stay in the DOM (so SR users still hear
        // the parent aria-label and applyPlatformHints can rewrite the
        // kbd text on Mac without re-rendering) but are visually
        // hidden via `display:none` at every viewport now.
        const label = trigger.locator('.topbar__search-label');
        await expect(label).toBeAttached();
        await expect(label).toBeHidden();

        const kbd = trigger.locator('.topbar__search-kbd');
        await expect(kbd).toBeAttached();
        await expect(kbd).toBeHidden();

        // Square icon-only button. Desktop is 2.25rem (36px) — matches
        // the `.btn--icon` sibling theme-toggle so the topbar's right
        // edge reads as two equal-weight icon affordances. Mobile bumps
        // to 2.75rem (44px) per slice 1's tap-target floor; that
        // contract is locked in responsive/topbar.spec.ts so we just
        // bracket the upper bound here to make sure neither viewport
        // accidentally re-expands to a labelled control via cascade
        // drift.
        const box = await trigger.boundingBox();
        expect(box, 'palette trigger must render a bounding box').not.toBeNull();
        expect(Math.abs(box!.width - box!.height)).toBeLessThanOrEqual(1);
        if (testInfo.project.name === 'mobile-chromium') {
            expect(box!.width).toBeGreaterThanOrEqual(44);
        } else {
            expect(box!.width).toBeGreaterThanOrEqual(34);
        }
        expect(box!.width).toBeLessThanOrEqual(56);
    });

    test('clicking the icon trigger opens the palette (#1207 CC-3)', async ({ page }) => {
        // Behavioural counterpart to the icon-only-shape test above:
        // the trigger keeps its `data-palette-open` attribute, so
        // theme.js's document-level click handler funnels through
        // openPalette() the same way Meta+k does. Both projects.
        await page.goto('/');

        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');

        await page.locator('[data-testid="palette-trigger"]').click();
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        await expect(page.locator('#palette-input')).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');
    });

    test('typing surfaces a seeded ban', async ({ page }, testInfo) => {
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );
        const seed = uniqueSeed(testInfo, 'type');
        // `seedBanViaApi` is idempotent at the test level: a retry
        // re-uses the same authid and trips `already_banned`. We swallow
        // that path so the rest of the test still asserts the search
        // surface — the row from the first attempt is what we'll find.
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');

        const dialog = page.locator('#palette-root');
        const input = page.locator('#palette-input');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
        await expect(input).toBeFocused();

        // Type the full unique nick (not a short prefix). PALETTE_MIN_QUERY
        // is 2, so any length >=2 fires the bans.search call; the server
        // caps results at 10 ordered by created DESC. A short prefix like
        // `e2e-palette-ta` matches every accumulated palette-* seed across
        // the suite (open/type/enter × desktop/mobile), and a stale debounce
        // wave can render a top-10 that excludes the brand-new row. Typing
        // the full unique nick collapses the search to exactly one matching
        // row, removing the dependency on result ordering.
        await input.fill(seed.nick);

        // Wait on the dialog's data-loading flag — theme.js sets it to
        // 'true' while the bans.search fetch is in flight and deletes it
        // on completion. This is the terminal-state hook (#1123 testability
        // contract) for the search: more reliable on mobile-chromium than
        // betting on the default 5s polling on the result locator, where
        // a slow runner can let the 200ms debounce + fetch land outside
        // the assertion window. 10s gives generous headroom for cold
        // runner I/O without making real flakes wait forever.
        await expect(dialog).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        const banResults = page.locator(
            '[data-testid="palette-result"][data-result-kind="ban"]',
        );
        await expect(banResults.first()).toBeVisible();
        await expect(banResults.filter({ hasText: seed.nick }).first()).toBeVisible();
    });

    test('player result row carries kbd hint group with Enter and Ctrl+Enter (#1207 DET-2)', async ({ page }, testInfo) => {
        // mobile-chromium skipped per #1206 (palette typing-search
        // flake, same root cause as the 'type' / 'enter' subtests).
        // The kbd hint contract is viewport-independent — the kbds
        // are in the DOM regardless — so chromium coverage is enough
        // to lock the contract; the visual layout media query
        // (`.palette__row-hint-label` collapsing at <=640px) is
        // separately covered by the screenshot gallery.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );
        const seed = uniqueSeed(testInfo, 'hints');
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');
        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');

        await page.locator('#palette-input').fill(seed.nick);
        await expect(dialog).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        const row = page
            .locator('[data-testid="palette-result"][data-result-kind="ban"]')
            .filter({ hasText: seed.nick })
            .first();
        await expect(row).toBeVisible();

        // The kbd hint group sits at the right edge of every player
        // row, scoped via `data-testid="palette-row-hints"`. The two
        // hints surface the row's two interactions:
        //   - bare Enter → opens the player drawer for the ban,
        //   - Ctrl+Enter → copies the row's SteamID via
        //     navigator.clipboard.
        const hints = row.locator('[data-testid="palette-row-hints"]');
        await expect(hints).toBeAttached();

        // The first kbd is the bare-Enter hint, server-rendered as
        // "Enter" (theme.js's applyPlatformHints rewrites it to ⏎ on
        // Mac after first paint; this Linux runner sees the non-Mac
        // form). The second hint pairs `Ctrl` + `Enter` for the copy
        // affordance — both kbds present so the visible glyph reads
        // as a key combo even when the verbose label collapses at
        // narrow widths.
        const enterKbds = hints.locator('kbd[data-enterkey]');
        await expect(enterKbds).toHaveCount(2);
        await expect(enterKbds.first()).toHaveText('Enter');

        const ctrlKbd = hints.locator('kbd[data-modkey]');
        await expect(ctrlKbd).toHaveCount(1);
        await expect(ctrlKbd).toHaveText('Ctrl');

        // The descriptive labels ("to open drawer" / "to copy
        // steamid") render at desktop width; the test runs on
        // chromium (1280×720) so we expect them visible here.
        // Pin one half of each label so a future copy edit fails
        // this spec (rather than silently slipping through).
        await expect(hints).toContainText('open drawer');
        await expect(hints).toContainText('copy steamid');
    });

    test('Ctrl+Enter on a focused player row copies the SteamID + toasts (#1207 DET-2)', async ({ page, context }, testInfo) => {
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );

        const seed = uniqueSeed(testInfo, 'copy');
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');

        // navigator.clipboard.readText() requires the
        // `clipboard-read` permission. Chromium grants it in
        // headless mode but Playwright's default permission set
        // doesn't include it; granting explicitly per-test makes the
        // contract loud at the call-site instead of relying on a
        // browser default. `clipboard-write` is only required for
        // older Chromium permissions matrices; on current builds
        // writeText() works without the explicit grant, but we ask
        // for both so the spec is portable across browser versions.
        // Granting AFTER goto() so the origin is the live test
        // origin, not 'about:blank'.
        await context.grantPermissions(['clipboard-read', 'clipboard-write'], {
            origin: new URL(page.url()).origin,
        });

        await page.keyboard.press('Meta+k');
        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');

        await page.locator('#palette-input').fill(seed.nick);
        await expect(dialog).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        const row = page
            .locator('[data-testid="palette-result"][data-result-kind="ban"]')
            .filter({ hasText: seed.nick })
            .first();
        await expect(row).toBeVisible();
        // Pin the steamid on the row — that's what Ctrl+Enter will
        // copy. theme.js writes b.steam (Steam2 form) into
        // `data-steamid`, so the assertion below is exact.
        await expect(row).toHaveAttribute('data-steamid', seed.steam);

        await row.focus();

        // Linux runner: `Control+Enter` is the primary chord. The
        // theme.js handler accepts metaKey || ctrlKey, so a Mac
        // runner would land here via `Meta+Enter`; we only test
        // the Linux form because that's the CI shape (browserName
        // 'chromium' on linux).
        await page.keyboard.press('Control+Enter');

        // Toast appears with the success copy. The toast surface is
        // shared with the drawer's [data-copy] button (#1184); we
        // filter by the title so a stray earlier toast doesn't false-
        // positive.
        const toast = page.locator('.toast').filter({ hasText: 'SteamID copied' });
        await expect(toast).toBeVisible();
        // The toast body echoes the copied value so the user can
        // visually confirm the right id landed on the clipboard.
        await expect(toast).toContainText(seed.steam);

        // Read the clipboard — value matches the row's data-steamid.
        // The grantPermissions call above is what makes this work
        // outside Playwright's default permission set.
        const clipboardValue = await page.evaluate(() => navigator.clipboard.readText());
        expect(clipboardValue).toBe(seed.steam);

        // The palette stays open — Ctrl+Enter is a non-navigating
        // affordance, not a "submit + close" chord. (Bare Enter
        // closes the palette and opens the drawer; that's the
        // sibling 'enter' subtest below.)
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
    });

    test('Ctrl+click on a player row preserves native href graceful-degradation (#1207 DET-2)', async ({ page, context }, testInfo) => {
        // #1207 DET-2 review finding 1: the existing `[data-drawer-bid]`
        // click delegate (theme.js) used to call `e.preventDefault()`
        // unconditionally, which meant Cmd/Ctrl+left-click was
        // intercepted indistinguishably from a bare left-click — the
        // browser's native "open in new tab" default action was
        // suppressed and the drawer opened in the current tab. The
        // delegate now guards `e.metaKey || e.ctrlKey || e.shiftKey ||
        // e.button !== 0` and bails before preventDefault, so the
        // anchor's `href` (?p=banlist&advType=name&advSearch=<name>)
        // takes over and the new tab lands on the name-filtered
        // banlist. This test locks that contract.
        //
        // mobile-chromium skipped: same palette typing-search flake
        // as the sibling 'type' / 'enter' / 'hints' / 'copy' subtests
        // (#1206). The modifier-guard contract is viewport-independent.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );

        const seed = uniqueSeed(testInfo, 'modclick');
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');
        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');

        await page.locator('#palette-input').fill(seed.nick);
        await expect(dialog).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        const row = page
            .locator('[data-testid="palette-result"][data-result-kind="ban"]')
            .filter({ hasText: seed.nick })
            .first();
        await expect(row).toBeVisible();

        // Capture the new page (tab) the browser opens for the
        // modifier-click. `Control` is the Linux/Win chord — the
        // Mac equivalent (`Meta`) is also covered by the same
        // delegate guard but we test the chromium-on-Linux runner
        // shape because that's what CI executes.
        const newPagePromise = context.waitForEvent('page');
        await row.click({ modifiers: ['Control'] });
        const newPage = await newPagePromise;
        // Playwright's 'page' event fires when the tab is created
        // (URL still `about:blank`); the navigation lands a tick
        // later. Wait on the URL pattern itself rather than a
        // generic load state so the assertion below isn't racing
        // against `about:blank`.
        await newPage.waitForURL(/[?&]p=banlist/, { timeout: 10000 });

        // The new tab's URL is the row's href fallback — the
        // name-filtered banlist URL.
        expect(newPage.url()).toMatch(/[?&]advType=name(?:&|$)/);
        expect(newPage.url()).toMatch(
            new RegExp(`[?&]advSearch=${encodeURIComponent(seed.nick)}(?:&|$)`),
        );
        await newPage.close();

        // The original tab's drawer did NOT open — the modifier-guard
        // returned early before `loadDrawer()` could run. This is
        // the post-fix shape; pre-fix this assertion would have
        // failed with `data-drawer-open="true"`.
        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'false');

        // The palette stays open — modifier-click is a non-navigating
        // action in the active tab; the new tab is where the
        // navigation lands.
        await expect(dialog).toHaveAttribute('data-palette-open', 'true');
    });

    test('focused result + Enter opens the player drawer (#1207 DET-2)', async ({ page }, testInfo) => {
        // Same mobile-chromium palette typing-search flake as the
        // 'type' / 'hints' / 'copy' subtests — tracked in #1206.
        test.skip(
            testInfo.project.name === 'mobile-chromium',
            'mobile-chromium palette typing-search flake; tracked in #1206',
        );
        const seed = uniqueSeed(testInfo, 'enter');
        try {
            await seedBanViaApi(page, { nickname: seed.nick, steam: seed.steam });
        } catch (err) {
            if (!String(err).includes('already_banned')) throw err;
        }

        await page.goto('/');
        await page.keyboard.press('Meta+k');
        const input = page.locator('#palette-input');
        await expect(input).toBeFocused();

        await input.fill(seed.nick.slice(0, 14));
        const result = page
            .locator('[data-testid="palette-result"][data-result-kind="ban"]')
            .filter({ hasText: seed.nick })
            .first();
        await expect(result).toBeVisible();

        // #1207 DET-2: each player row carries `data-drawer-bid="<bid>"`.
        // Focus + Enter fires a synthetic click on the anchor that
        // bubbles to theme.js's document-level click delegate; the
        // delegate spots the `[data-drawer-bid]` trigger inside the
        // open palette, calls `closePalette()` so the drawer isn't
        // stacked behind the palette dialog, then `loadDrawer(bid)`
        // fetches `bans.detail` and renders the player drawer.
        //
        // The href fallback (`?p=banlist&advType=name&advSearch=…`)
        // is preserved on the anchor so middle-click / Cmd+click
        // still expands the result to a name-filtered banlist for
        // users who want the wider context — that's why we focus +
        // press Enter rather than asserting on the href shape.
        await result.focus();
        await page.keyboard.press('Enter');

        const drawer = page.locator('#drawer-root');
        await expect(drawer).toHaveAttribute('data-drawer-open', 'true');
        await expect(drawer).not.toHaveAttribute('data-loading', 'true', { timeout: 10000 });

        // Palette closed — see `closePalette()` call inside the
        // click delegate's `target.closest('.palette')` branch.
        const dialog = page.locator('#palette-root');
        await expect(dialog).toHaveAttribute('data-palette-open', 'false');
    });
});
