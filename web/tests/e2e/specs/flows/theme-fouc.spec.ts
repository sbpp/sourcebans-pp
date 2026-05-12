/**
 * Anti-FOUC regression guard (#1367).
 *
 * The reported symptom: "When navigating between pages while using
 * dark mode, the page briefly renders in light mode for a split
 * second before switching back to dark, causing an uncomfortable
 * flash" + a sister "content elements disappear and reappear" on
 * every page navigation.
 *
 * Root cause
 * ----------
 * Pre-fix `web/themes/default/core/header.tpl` rendered a bare
 * `<html lang="en">` and `theme.js` (loaded from the document tail
 * via `core/footer.tpl`) was the only thing that flipped the
 * `class="dark"` toggle. With the script at the tail, the browser
 * paints the entire body in light mode (the `:root` tokens default
 * to light) BEFORE theme.js runs `applyTheme(currentTheme())` —
 * the user perceives that as a white flash + content flicker on
 * every page navigation. The existing color-scheme.spec.ts even
 * acknowledged this in its preamble: *"theme.js applies <html
 * class='dark'> after the first paint (the script loads from the
 * document tail, not <head>) and the resolved color-scheme value
 * follows."*
 *
 * The fix
 * -------
 * `core/header.tpl` now ships a tiny inline blocking script in
 * `<head>` (above `<link rel="stylesheet">`) that reads the same
 * `localStorage['sbpp-theme']` key theme.js owns and adds
 * `class="dark"` to `<html>` synchronously, before the body parses.
 * The first paint lands in the user's persisted theme — no flash.
 * theme.js is unchanged; its boot-time `applyTheme(currentTheme())`
 * is now a no-op when the class is already correct (toggle(true)
 * on a set class is a no-op), and it stays the load-bearing path
 * for the click handler + the matchMedia listener.
 *
 * What this spec proves
 * ---------------------
 * The straightforward "open a dark page and assert <html class='dark'>"
 * can't distinguish between the bootloader and theme.js — by the time
 * Playwright reads the DOM, theme.js has long since run too. The
 * regression-catch we want is specifically: *"the dark class is set
 * BEFORE theme.js executes"*. We `page.route('**\/theme.js')` the
 * network request and stall the response; while theme.js is in flight,
 * the parser has paused at the `<script src="theme.js">` tag at the
 * tail of `<body>`, but the inline bootloader in `<head>` has already
 * run. We then read `<html>` and assert the `dark` class is present.
 *
 * Pre-fix this assertion would fail (only theme.js sets the class, and
 * theme.js is stalled) — exactly the regression we want to catch.
 * Post-fix the class is set by the inline bootloader well before
 * the parser reaches theme.js, so the assertion passes.
 *
 * Three tests cover the three branches of the bootloader's resolution
 * logic (mirrors theme.js's `applyTheme(currentTheme())`):
 *
 *   1. mode = 'dark'             → bootloader adds class
 *   2. mode = 'light'            → bootloader does NOT add class
 *   3. mode = 'system' + OS-dark → bootloader resolves to dark, adds class
 *
 * No DB reset, no login state — the bootloader is pure HTML/JS in
 * the chrome and doesn't depend on the page body.
 *
 * Why we don't use page.addInitScript + MutationObserver
 * -------------------------------------------------------
 * The "natural" Playwright shape — install a MutationObserver via
 * `addInitScript` and record `document.readyState` when the `dark`
 * class first appears — runs into a known timing trap:
 * `addInitScript` runs in the freshly-created document context BEFORE
 * the parser has consumed the implicit `<html>` start tag, so
 * `document.documentElement` is `null` at attachment time. The
 * MutationObserver can't attach to a null target, and a deferred
 * "wait for documentElement" chain is racy enough to miss the actual
 * mutation in some chromium builds. The network-stall approach above
 * is direct: it proves the contract the user actually cares about
 * (the class is set before theme.js runs) without depending on the
 * parser's microtask scheduling.
 */

import { expect, test, chromium } from '@playwright/test';

const TARGET_ROUTE = '/index.php?p=banlist';
const THEME_JS_PATTERN = '**/theme.js';

/**
 * Stall the theme.js network request until released. Returns a
 * promise that resolves when theme.js is intercepted (i.e., the
 * parser has reached the `<script src="theme.js">` tag in `<body>`'s
 * tail), plus a release function to call when the assertions finish.
 *
 * Other API requests pass through normally — only theme.js is
 * stalled. This keeps the test scoped to "what happens BEFORE
 * theme.js runs".
 */
function setupThemeJsStall(page: import('@playwright/test').Page): {
    waitForStall: Promise<void>;
    release: () => void;
} {
    let resolveRelease: (() => void) | null = null;
    let resolveStall: (() => void) | null = null;
    const waitForStall = new Promise<void>((resolve) => {
        resolveStall = resolve;
    });
    const releasePromise = new Promise<void>((resolve) => {
        resolveRelease = resolve;
    });

    void page.route(THEME_JS_PATTERN, async (route) => {
        if (resolveStall) {
            resolveStall();
            resolveStall = null; // first hit only
        }
        await releasePromise;
        await route.continue();
    });

    return {
        waitForStall,
        release: () => {
            if (resolveRelease) resolveRelease();
        },
    };
}

test.describe('flow: anti-FOUC bootloader (#1367)', () => {
    test('mode=dark → html.dark is set BEFORE theme.js executes (bootloader contract)', async ({
        page,
    }) => {
        // Establish the origin + persist 'dark' BEFORE installing the
        // network stall. This first navigation lands in light mode (no
        // localStorage entry → 'system' default → matches the
        // project-level `colorScheme: 'light'` from playwright.config.ts).
        await page.goto('/');
        await page.evaluate(() => localStorage.setItem('sbpp-theme', 'dark'));

        const { waitForStall, release } = setupThemeJsStall(page);

        // Fire the navigation in the background. We can't `await` it
        // because the parser will block at `<script src="theme.js">` in
        // `<body>`'s tail (theme.js is stalled), and `page.goto`'s
        // default `waitUntil: 'load'` would hang indefinitely. Catching
        // any rejection guards against a teardown race in the rare case
        // the browser closes before we resolve the stall.
        const navigation = page
            .goto(TARGET_ROUTE)
            .catch((err: Error) => {
                // page.route can leak through navigation aborts; the
                // catch keeps the test from failing on tear-down noise.
                if (!/(closed|aborted)/i.test(err.message)) throw err;
            });

        // Wait until the parser reaches the theme.js tag and our route
        // handler intercepts it. At this exact moment, the inline
        // bootloader in `<head>` has long since run (it's higher up in
        // the document), but theme.js itself has not — its execution
        // depends on the response body, which we're holding.
        await waitForStall;

        // The contract: the inline bootloader has already added `dark`
        // to <html> by the time the parser even reached theme.js. If
        // this fails, the bootloader is missing or broken — theme.js
        // (the only other path that adds the class) hasn't executed
        // yet, so there's nothing else to set it.
        const isDark = await page.evaluate(() =>
            document.documentElement.classList.contains('dark'),
        );
        expect(
            isDark,
            'pre-fix theme.js was the only path that set html.dark; with theme.js ' +
                'stalled, this read `false` and the body painted in light mode for the ' +
                "duration of the in-flight window — exactly the white flash the user " +
                'reported. Post-fix the inline bootloader in <head> sets the class long ' +
                'before the parser reaches theme.js, so this reads `true` regardless of ' +
                "theme.js's load timing.",
        ).toBe(true);

        // Release theme.js so the page finishes loading cleanly and
        // any subsequent test reuses the same page context safely.
        release();
        await navigation;

        // Sanity check: the class is still on <html> after the
        // navigation completes — guards against a future regression
        // where the bootloader adds the class but theme.js immediately
        // removes it.
        await expect(page.locator('html')).toHaveClass(/dark/);
    });

    test('mode=light → html.dark is NOT set BEFORE theme.js executes (bootloader respects light)', async ({
        page,
    }) => {
        await page.goto('/');
        await page.evaluate(() => localStorage.setItem('sbpp-theme', 'light'));

        const { waitForStall, release } = setupThemeJsStall(page);
        const navigation = page
            .goto(TARGET_ROUTE)
            .catch((err: Error) => {
                if (!/(closed|aborted)/i.test(err.message)) throw err;
            });

        await waitForStall;

        const isDark = await page.evaluate(() =>
            document.documentElement.classList.contains('dark'),
        );
        expect(
            isDark,
            'when the user has pinned light, the bootloader must not add html.dark — ' +
                'doing so would resolve the wrong theme on first paint and produce the ' +
                "inverse FOUC (briefly dark, then light). theme.js's boot-time " +
                "applyTheme('light') would then remove the class on the second paint, " +
                'producing the same flicker the bootloader is supposed to prevent.',
        ).toBe(false);

        release();
        await navigation;

        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });
});

test.describe('flow: anti-FOUC bootloader — system mode resolution (#1367)', () => {
    test('mode=system + OS-dark → bootloader resolves to dark BEFORE theme.js executes', async () => {
        // The project-level config pins `colorScheme: 'light'`, and
        // `test.use({ colorScheme: 'dark' })` only flips the context
        // option for the whole describe — but we want to scope the
        // colorScheme override tightly to this one test. A fresh
        // `chromium.launch()` is the canonical way to do that
        // (loading-animations.spec.ts uses the same pattern). Skip
        // mobile-chromium since the matchMedia resolution is
        // viewport-independent — the second project run would burn
        // cycles without revealing different findings.
        const browser = await chromium.launch();
        try {
            const ctx = await browser.newContext({
                colorScheme: 'dark',
                baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
            });
            const page = await ctx.newPage();

            await page.goto('/');
            await page.evaluate(() => localStorage.setItem('sbpp-theme', 'system'));

            const { waitForStall, release } = setupThemeJsStall(page);
            const navigation = page
                .goto(TARGET_ROUTE)
                .catch((err: Error) => {
                    if (!/(closed|aborted)/i.test(err.message)) throw err;
                });

            await waitForStall;

            const isDark = await page.evaluate(() =>
                document.documentElement.classList.contains('dark'),
            );
            expect(
                isDark,
                'system mode + OS-dark must resolve to dark on first paint — the bootloader ' +
                    'reads `matchMedia("(prefers-color-scheme: dark)").matches` against the ' +
                    "context's emulated colorScheme and adds html.dark before the parser " +
                    'reaches theme.js. If this fails, the bootloader is missing the matchMedia ' +
                    'branch or the context override is being ignored.',
            ).toBe(true);

            release();
            await navigation;

            await expect(page.locator('html')).toHaveClass(/dark/);
        } finally {
            await browser.close();
        }
    });
});
