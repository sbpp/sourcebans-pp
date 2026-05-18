/**
 * Regression guard (#1362) for ESSENTIAL-MOTION animations under
 * `prefers-reduced-motion: reduce`. Two surfaces are covered:
 *
 *   1. The busy-button spinner (`.btn[data-loading="true"]::after`).
 *      Probed by sampling `getComputedStyle(::after).transform`
 *      across frame boundaries — a rotating spinner produces a series
 *      of distinct matrix(…) values; a frozen one stays at `none`
 *      or identity.
 *   2. The `.skel` skeleton shimmer (used by the drawer's
 *      `renderDrawerLoading()` and the lazy-pane
 *      `renderPaneSkeleton()`). Probed by sampling
 *      `getComputedStyle(.skel).backgroundPositionX` — a sliding
 *      shimmer produces a series of distinct positions; a frozen one
 *      stays pinned at the rule-default.
 *
 * What this catches that the existing flow specs do not
 * ---------------------------------------------------------------------
 * The sibling `action-loading-indicator.spec.ts` proves the
 * busy-attribute triple (data-loading + aria-busy + disabled) lands on
 * the submit button while `Actions.CommsUnblock` is in flight.
 * `drawer-loading-indicator.spec.ts` proves the `.skel` block paints
 * a `linear-gradient` background (i.e. the rule's specificity wins
 * and the gradient is present, not blank — the `class="skeleton"`
 * typo regression catch).
 *
 * Both are necessary but neither catches MOTION. Pre-#1362 the
 * three busy attributes flipped correctly AND the gradient painted
 * correctly… but the animations that *make those affordances
 * actually read as "loading"* were frozen by the global
 *
 *     @media (prefers-reduced-motion: reduce) {
 *       *, *::before, *::after {
 *         animation-duration: 0.001ms !important;
 *         animation-iteration-count: 1 !important;
 *         …
 *       }
 *     }
 *
 * block in `theme.css`. The Playwright suite runs with
 * `contextOptions: { reducedMotion: 'reduce' }` set globally
 * (playwright.config.ts line 48), so neither sibling spec ever saw
 * the frozen-animation regression. Real users hit it on Windows 11
 * with "Show animation effects" toggled off, on macOS with "Reduce
 * motion" on, and on any OS-level high-contrast / power-saver
 * profile that resolves to `prefers-reduced-motion: reduce` — the
 * spinner reads as a frozen donut, the skeleton reads as a
 * permanent gray placeholder, and the affordance silently fails.
 *
 * Why we test the COMPUTED VALUE OVER TIME, not the animation properties
 * ---------------------------------------------------------------------
 * Asserting `animationDuration === '0.6s'` (or `'1.4s'` for shimmer)
 * would catch the specific CSS-rule regression from #1362 but not,
 * e.g., a future `animation-play-state: paused` sneaking in via a
 * parent rule, a `transform: none !important` / `background-position:
 * 0 !important` override, or a runtime `animation.pause()` JS call.
 * Sampling the rendered VALUE at multiple frame boundaries asserts
 * the only thing that actually matters to the user: the affordance
 * is in motion, frame to frame. If two consecutive samples are
 * bit-identical, the animation has stopped — regardless of why.
 *
 * Why we bypass the project-level reducedMotion setting
 * ---------------------------------------------------------------------
 * `playwright.config.ts` pins `reducedMotion: 'reduce'` so the wider
 * suite doesn't wait out 250ms slide-in animations on every spec
 * (AGENTS.md "Playwright E2E specifics"). That same setting is
 * exactly the configuration we want to test against here, AND we
 * also want the `'no-preference'` (default OS) case as a control.
 * We `chromium.launch()` fresh browsers with explicit `reducedMotion`
 * settings instead of inheriting the project default. This is the
 * one spec in the suite that needs to control reduced-motion
 * per-test; everything else is right to share the project setting.
 *
 * No login state, no DB reset
 * ---------------------------------------------------------------------
 * Both surfaces are pure CSS — only the `data-loading="true"`
 * attribute on a `.btn` (or the `.skel` class on a `<div>`) matters.
 * We inject the probe element on the panel's unauthenticated landing
 * page (which still serves the panel's `theme.css`) so we don't burn
 * cycles on `truncateE2eDb()` or driving the login form. The
 * fresh-browser approach also means we can't use `storageState`,
 * which keeps the test self-contained.
 */

import { expect, test, chromium } from '@playwright/test';

/**
 * Generic helper: in a fresh browser context with the given
 * `reducedMotion` setting, inject a probe element, then sample a
 * computed-style value at multiple frame boundaries. Returns the
 * static animation properties (for the rule-presence sanity check)
 * plus the array of samples.
 */
async function sampleAnimation(args: {
    reducedMotion: 'reduce' | 'no-preference';
    baseURL: string;
    /** Builds the probe element + returns the initial computed-style snapshot. */
    setup: () => { animationName: string; animationDuration: string };
    /** Reads ONE sample from the probe element. Called repeatedly. */
    sample: () => string;
    /** Milliseconds between successive samples. */
    intervalMs: number;
}): Promise<{ animationName: string; animationDuration: string; samples: string[] }> {
    const { reducedMotion, baseURL, setup, sample, intervalMs } = args;
    const browser = await chromium.launch();
    try {
        const ctx = await browser.newContext({ reducedMotion, baseURL });
        const page = await ctx.newPage();
        await page.goto('/');
        // The setup + sample callbacks run in the browser via
        // `page.evaluate`; passing functions across the wire serialises
        // them as source text — the closure can't capture host-side
        // state, but for these pure DOM helpers that's fine.
        const initial = await page.evaluate(setup);
        const samples: string[] = [];
        for (let i = 0; i < 6; i++) {
            const v = await page.evaluate(sample);
            samples.push(String(v));
            await page.waitForTimeout(intervalMs);
        }
        return { ...initial, samples };
    } finally {
        await browser.close();
    }
}

/**
 * Assert at least 4 of 6 consecutive samples are distinct values. A
 * truly-frozen animation returns the same string for ALL 6 samples;
 * a robust animation produces 5-6 distinct values. The 4-of-6
 * threshold tolerates rare cases where Chrome's compositor lands
 * two reads in the same animation tick (which can happen under
 * GPU contention) without ever masking the actual frozen-animation
 * regression.
 */
function assertSamplesChange(samples: string[], label: string): void {
    const distinct = new Set(samples);
    expect(
        distinct.size,
        `${label}: computed style should change across samples but ` +
        `only ${distinct.size}/${samples.length} were distinct — ` +
        `samples: ${JSON.stringify(samples)}`,
    ).toBeGreaterThanOrEqual(4);
}

// ============================================================
// Surface 1: busy-button spinner rotation
// ============================================================

test.describe('flow: action-button spinner rotates regardless of reduced-motion', () => {
    for (const reducedMotion of ['reduce', 'no-preference'] as const) {
        test(`rotates with reducedMotion=${reducedMotion}`, async ({ baseURL }) => {
            const { animationName, samples } = await sampleAnimation({
                reducedMotion,
                baseURL: baseURL ?? 'http://localhost:8080',
                setup: () => {
                    const btn = document.createElement('button');
                    btn.id = 'spinner-rotation-probe';
                    btn.className = 'btn btn--primary';
                    btn.textContent = 'probe';
                    btn.style.position = 'fixed';
                    btn.style.left = '-9999px';
                    btn.style.top = '0';
                    document.body.appendChild(btn);
                    btn.setAttribute('data-loading', 'true');
                    const s = window.getComputedStyle(btn, '::after');
                    return { animationName: s.animationName, animationDuration: s.animationDuration };
                },
                sample: () => {
                    const btn = document.querySelector('#spinner-rotation-probe');
                    if (!btn) return 'no-btn';
                    return window.getComputedStyle(btn, '::after').transform;
                },
                // 80ms per sample × 6 = 480ms span. Spinner duration is
                // 0.6s, so we cover ~80% of one rotation — plenty of
                // distinct angles even with linear timing.
                intervalMs: 80,
            });
            // Sanity check: the animation rule itself is in scope. If
            // someone renames `sbpp-btn-spin`, this fires before the
            // rotation assertion so the failure pinpoints the rename
            // rather than the downstream "no rotation" symptom.
            expect(animationName).toContain('sbpp-btn-spin');
            assertSamplesChange(samples, `spinner reducedMotion=${reducedMotion}`);
            // The frozen-spinner regression returns "none" for every
            // sample (the keyframe never advances past 0%, so the
            // element's computed transform stays at its rule-default).
            // Explicitly assert we don't ONLY see the static identity
            // / none values across all samples — guards against an
            // animation that produces 6 different-but-still-identity
            // values (unlikely but cheap to catch).
            expect(
                samples.some((t) => t !== 'none' && t !== 'matrix(1, 0, 0, 1, 0, 0)'),
                `spinner reducedMotion=${reducedMotion}: stuck at identity — ${JSON.stringify(samples)}`,
            ).toBe(true);
        });
    }
});

// ============================================================
// Surface 2: .skel skeleton shimmer
// ============================================================

test.describe('flow: .skel shimmer animates regardless of reduced-motion', () => {
    for (const reducedMotion of ['reduce', 'no-preference'] as const) {
        test(`slides with reducedMotion=${reducedMotion}`, async ({ baseURL }) => {
            const { animationName, samples } = await sampleAnimation({
                reducedMotion,
                baseURL: baseURL ?? 'http://localhost:8080',
                setup: () => {
                    const el = document.createElement('div');
                    el.id = 'skel-shimmer-probe';
                    el.className = 'skel';
                    el.style.position = 'fixed';
                    el.style.left = '0';
                    el.style.top = '0';
                    // 200px wide gives the shimmer enough horizontal
                    // travel to produce visibly distinct positions
                    // across the sample window.
                    el.style.width = '200px';
                    el.style.height = '20px';
                    document.body.appendChild(el);
                    const s = window.getComputedStyle(el);
                    return { animationName: s.animationName, animationDuration: s.animationDuration };
                },
                sample: () => {
                    const el = document.querySelector('#skel-shimmer-probe');
                    if (!el) return 'no-el';
                    // `backgroundPositionX` is what the @keyframes
                    // rule animates. Chrome reports it as a calc()
                    // string while the animation interpolates
                    // (`calc(20.2% - 119.1px)`), which is fine —
                    // distinct strings = distinct positions.
                    return window.getComputedStyle(el).backgroundPositionX;
                },
                // Shimmer duration is 1.4s, so 150ms × 6 = 900ms span
                // covers ~64% of one cycle — enough distinct positions
                // even on a busy GPU.
                intervalMs: 150,
            });
            expect(animationName).toContain('shimmer');
            assertSamplesChange(samples, `shimmer reducedMotion=${reducedMotion}`);
        });
    }
});
