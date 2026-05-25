/**
 * Flow spec — issue #1444 (notification boxes disappear too quickly).
 *
 * The user-reported regression had two layers:
 *
 *   1. The default auto-dismiss window (4000ms in v2 RC chrome) was
 *      below the read-time threshold for a corner-of-screen
 *      notification — users perceived the toast as "flashed and
 *      disappeared".
 *   2. The screenshot use case (explicitly called out in the issue:
 *      "I was expecting it…and couldn't get a screenshot of it") needs
 *      an arbitrary-length hold, which a one-shot timer bump can't
 *      provide — even 10s would be tight if the user has to find the
 *      screenshot key first.
 *
 * The fix has two halves:
 *   1. `SHOWTOAST_DEFAULT_DURATION` 4000ms → 6000ms — see the
 *      `toast-persistent-duration.spec.ts` routine-toast arm for that
 *      regression guard.
 *   2. Pause-on-hover + pause-on-keyboard-focus on the auto-dismiss
 *      timer — this spec. The contract: while the cursor is over the
 *      painted toast OR keyboard focus is inside the toast (e.g. the
 *      X button is tab-focused), the dismiss timer is paused. When
 *      the user leaves the toast (mouseleave / focusout) AND no other
 *      input modality is still active, the timer resumes from where
 *      it was paused, NOT from the original full duration.
 *
 * # What's NOT covered here
 *
 * - The default-duration timer math (already covered by the routine-
 *   toast arm in `toast-persistent-duration.spec.ts`).
 * - The persistent-toast `duration_ms: 0` semantic (covered by the
 *   persistent arm in `toast-persistent-duration.spec.ts`).
 *
 * This spec covers ONLY the pause/resume timer behaviour, end-to-end,
 * to catch a regression that drops one of the four `addEventListener`
 * calls in `showToast()` (`mouseenter` / `mouseleave` / `focusin` /
 * `focusout`), breaks the `pauseIfActive()` / `resumeIfIdle()`
 * closure math, or collapses the independent `hovered` / `focused`
 * state booleans into a single signal (review M-1).
 *
 * # Timer semantics + lockstep with SHOWTOAST_DEFAULT_DURATION
 *
 * Every `waitForTimeout` here is derived at RUNTIME from
 * `window.SBPP.SHOWTOAST_DEFAULT_DURATION` (#1444 review M-2). The
 * lockstep between this spec and the chrome's default duration is
 * machine-enforced rather than prose-documented: bump the constant
 * and the spec's hover windows widen automatically. Hardcoded
 * literals like `7500` derived from 6000 + 1500 would silently
 * pass for the wrong reason if a future PR bumped the constant
 * (the spec's "still visible" assertion would fire BEFORE the new
 * unpaused timer would have, so pause-on-hover could be entirely
 * commented out without the test failing).
 *
 * Two timing constants relative to the chrome's default:
 *
 *   - `HOVER_MARGIN_MS` (1500ms): how long past the chrome default
 *     to keep hovering before asserting "still visible". An
 *     unpaused timer would have fired at T=defaultMs (relative
 *     to paint); we wait defaultMs + 1500 to leave enough slack
 *     that a slow CI runner's setTimeout jitter can't close the
 *     window prematurely. Margin sized to comfortably exceed
 *     macrotask-queue delay (~100ms) + Playwright frame jitter
 *     (~50-200ms) + any browser-process scheduling slack.
 *
 *   - `RESUME_MARGIN_MS` (500ms): how long past `defaultMs` to
 *     wait after unhovering before asserting "gone". The
 *     resumed timer restarts with `remainingMs` ~= defaultMs
 *     minus the ~100ms between paint and hover-start, so
 *     defaultMs + 500 leaves a ~600ms net margin for jitter.
 *
 * # Auth + DB scope
 *
 * Default storage state (admin/admin) so the home page renders. No DB
 * mutations (we drive `window.SBPP.showToast(...)` directly).
 * `truncateE2eDb` is NOT called — single-DB / `workers: 1` per
 * AGENTS.md.
 */

import { expect, test } from '../../fixtures/auth.ts';
import type { Page } from '@playwright/test';

/**
 * Read the chrome's default toast duration from
 * `window.SBPP.SHOWTOAST_DEFAULT_DURATION` (the runtime-exposed
 * constant — #1444 review M-2). Asserts a reasonable value so a
 * regression that drops the exposure (`undefined`) or sets it to
 * something nonsensical fails LOUDLY rather than producing
 * mystery-meat wait timings.
 */
async function readDefaultDuration(page: Page): Promise<number> {
    const ms = await page.evaluate(() => {
        const sbpp = (window as unknown as { SBPP?: { SHOWTOAST_DEFAULT_DURATION?: unknown } }).SBPP;
        return sbpp ? sbpp.SHOWTOAST_DEFAULT_DURATION : undefined;
    });
    expect(
        typeof ms,
        'window.SBPP.SHOWTOAST_DEFAULT_DURATION must be exposed as a number — chrome JS did not boot or'
        + ' the SBPP namespace assignment in theme.js is broken (#1444 review M-2)',
    ).toBe('number');
    const ok = typeof ms === 'number' && ms >= 1000 && ms <= 60000;
    expect(
        ok,
        `SHOWTOAST_DEFAULT_DURATION must be a sane positive duration (1000-60000ms); got ${String(ms)}`,
    ).toBe(true);
    return ms as number;
}

const HOVER_MARGIN_MS = 1500;
const RESUME_MARGIN_MS = 500;

test.describe('flow: pause-on-hover / pause-on-focus (#1444 part 2)', () => {
    test('Hovering a routine toast pauses the auto-dismiss timer; un-hovering resumes it', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=home');

        const defaultMs = await readDefaultDuration(page);

        // Pre-check: no leftover toasts on the home page.
        await expect(
            page.locator('[data-testid="toast"]'),
            'precondition: home dashboard should have no pending toasts before the manual `showToast` call',
        ).toHaveCount(0);

        // Fire a routine toast (no `durationMs` → falls through to
        // SHOWTOAST_DEFAULT_DURATION).
        await page.evaluate(() => {
            const sbpp = (window as unknown as { SBPP?: { showToast?: (opts: unknown) => void } }).SBPP;
            if (!sbpp || !sbpp.showToast) {
                throw new Error('window.SBPP.showToast is not exposed — chrome JS did not boot');
            }
            sbpp.showToast({
                kind: 'info',
                title: '1444 hover-pause probe',
                body: 'Hovering this toast should pause the auto-dismiss timer.',
            });
        });

        const toast = page
            .locator('[data-testid="toast"]')
            .filter({ hasText: '1444 hover-pause probe' });
        await expect(toast).toBeVisible({ timeout: 1500 });

        // Hover the toast immediately after paint — `hover()`
        // dispatches `mouseenter` which the chrome's
        // `addEventListener('mouseenter', onMouseEnter)` picks up.
        // The timer should be cancelled.
        await toast.hover();

        // Wait LONGER than SHOWTOAST_DEFAULT_DURATION. An unpaused
        // timer would have fired around T=defaultMs (relative to
        // paint, NOT hover-start), so by T=defaultMs + HOVER_MARGIN_MS
        // the toast would be gone if pause-on-hover regressed. We're
        // still hovering at that point.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + HOVER_MARGIN_MS);

        // The toast MUST still be visible. Catches a regression that
        // drops the `mouseenter` listener, breaks the `pauseIfActive()`
        // closure (e.g. forgets to `clearTimeout(timerId)`), or
        // races with a `setTimeout(..., 0)` shape.
        await expect(
            toast,
            'toast must remain visible while hovered, regardless of SHOWTOAST_DEFAULT_DURATION'
            + ' — pause-on-hover regression: mouseenter handler is missing, timerId not cleared,'
            + ' or pauseIfActive()/resumeIfIdle() closure is broken',
        ).toBeVisible();

        // Move the cursor off the toast. Playwright's `mouse.move()`
        // dispatches `mousemove` events, and the move to a coord
        // outside the toast's bounding box triggers a `mouseleave`
        // on the toast — which the chrome's
        // `addEventListener('mouseleave', onMouseLeave)` picks up.
        // The timer resumes from where it was paused.
        //
        // The `(0, 0)` corner is outside the toast (toast lives at
        // top-right of the viewport with `position: fixed`); a
        // `body.click()` would ALSO leave the toast but would
        // accidentally click whatever's at (0, 0).
        await page.mouse.move(0, 0);

        // Wait long enough for the resumed timer to fire. The toast
        // was hovered at T=~100ms (so remainingMs ≈ defaultMs - 100
        // after unhover). defaultMs + RESUME_MARGIN_MS is comfortably
        // past that — leaves ~600ms net margin for jitter.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + RESUME_MARGIN_MS);

        // The toast MUST be gone now. Catches a regression that
        // drops the `mouseleave` listener (timer never restarts;
        // toast lives forever) OR breaks the `resumeIfIdle()`
        // closure (e.g. forgets to `setTimeout(..., remainingMs)`).
        await expect(
            toast,
            'toast must auto-dismiss after the resume timer fires post-unhover'
            + ' — pause-on-hover regression: mouseleave handler is missing, resumeIfIdle() closure is broken,'
            + ' or remainingMs math is off',
        ).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Focusing a toast (keyboard navigation) pauses the auto-dismiss timer', async ({ page }) => {
        // Sister contract: keyboard / screen-reader users should get
        // the same "let me read this without scrambling" affordance
        // as mouse users get via hover. The chrome wires both
        // `mouseenter`/`mouseleave` AND `focusin`/`focusout` to the
        // pause/resume helpers; this test probes the focus arm.
        //
        // Why `focusin` / `focusout` and not `focus` / `blur`:
        // `focus`/`blur` don't bubble, so a tab landing on the
        // inner X button wouldn't trigger them on the outer toast
        // element. `focusin`/`focusout` DO bubble — the canonical
        // shape for "the toast has focus, including via descendants".
        // A regression that swapped back to non-bubbling events
        // would silently break this test.
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=home');

        const defaultMs = await readDefaultDuration(page);

        await expect(
            page.locator('[data-testid="toast"]'),
        ).toHaveCount(0);

        await page.evaluate(() => {
            const sbpp = (window as unknown as { SBPP?: { showToast?: (opts: unknown) => void } }).SBPP;
            if (!sbpp || !sbpp.showToast) {
                throw new Error('window.SBPP.showToast is not exposed — chrome JS did not boot');
            }
            sbpp.showToast({
                kind: 'info',
                title: '1444 focus-pause probe',
                body: 'Tabbing into this toast should pause the auto-dismiss timer.',
            });
        });

        const toast = page
            .locator('[data-testid="toast"]')
            .filter({ hasText: '1444 focus-pause probe' });
        await expect(toast).toBeVisible({ timeout: 1500 });

        // Focus the X button programmatically. We could also drive
        // Tab from a focused element to walk to the X, but that
        // depends on the DOM tab order which is fragile across
        // chrome additions. Direct `.focus()` on the X button
        // produces the same focusin event the keyboard path
        // would.
        await toast.locator('[data-toast-close]').focus();

        // Wait past the default. An unpaused timer would have fired
        // around T=defaultMs; we're still focused at that point + the
        // safety margin.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + HOVER_MARGIN_MS);

        await expect(
            toast,
            'toast must remain visible while keyboard-focused'
            + ' — focus-pause regression: focusin/focusout listeners are missing or non-bubbling focus/blur substituted',
        ).toBeVisible();

        // Blur the X button by moving focus to `document.body`. The
        // `.blur()` call on the focused element fires `focusout` on
        // the toast which the chrome picks up via the bubbling
        // listener. Using `evaluate` here rather than reaching for a
        // chrome anchor outside the toast — the toast's own X
        // button is the only guaranteed focusable element on every
        // page, so blurring it directly is the simplest and most
        // robust shape.
        await page.evaluate(() => {
            const closeBtn = document.querySelector(
                '[data-testid="toast"] [data-toast-close]',
            ) as HTMLElement | null;
            if (closeBtn && typeof closeBtn.blur === 'function') closeBtn.blur();
        });

        // Wait for the resumed timer to fire.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + RESUME_MARGIN_MS);

        await expect(
            toast,
            'toast must auto-dismiss after the resume timer fires post-blur'
            + ' — focus-pause regression: focusout listener is missing or resumeIfIdle() is broken',
        ).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });

    test('Multi-modal (hover + focus): unhovering while still focused must NOT resume the timer', async ({ page }) => {
        // Review M-1 regression guard. Pre-fix `showToast` collapsed
        // hover and focus into a single "is the timer paused?" signal,
        // so leaving ONE modality while still actively engaged via
        // the OTHER would call `resume()` and the timer would fire
        // out from under a user who's clearly still on the toast.
        //
        // The trace pre-fix:
        //   T=0      paint              → timer scheduled with full defaultMs
        //   T=100    focus the X        → pause() runs, remainingMs ≈ defaultMs - 100
        //   T=200    hover the toast    → mouseenter fires pause(); inner guard
        //                                  (timerId === null) → no-op
        //   T=300    unhover            → mouseleave fires resume(); guard checks
        //                                  ONLY timerId (was null), so reschedules
        //                                  a fresh setTimeout for remainingMs even
        //                                  though X is still focused
        //   T=300 + remainingMs ≈ defaultMs + 200
        //                              → el.remove(), X-button focus lost
        //                                  silently, toast disappears.
        //
        // Post-fix `hovered` and `focused` are independent state;
        // `resumeIfIdle()` reschedules only when BOTH are false.
        // Multi-input users (mouse + keyboard, AT user + mouse,
        // anyone using two input devices at once) get the
        // "stay-visible until I leave entirely" semantic the
        // single-modality cases already had.
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.goto('/index.php?p=home');

        const defaultMs = await readDefaultDuration(page);

        await expect(
            page.locator('[data-testid="toast"]'),
        ).toHaveCount(0);

        await page.evaluate(() => {
            const sbpp = (window as unknown as { SBPP?: { showToast?: (opts: unknown) => void } }).SBPP;
            if (!sbpp || !sbpp.showToast) {
                throw new Error('window.SBPP.showToast is not exposed — chrome JS did not boot');
            }
            sbpp.showToast({
                kind: 'info',
                title: '1444 hover-plus-focus probe',
                body: 'Hover AND focus this toast, then leave hover only — timer must stay paused.',
            });
        });

        const toast = page
            .locator('[data-testid="toast"]')
            .filter({ hasText: '1444 hover-plus-focus probe' });
        await expect(toast).toBeVisible({ timeout: 1500 });

        // Engage BOTH input modalities. Focus the X button (keyboard
        // path) then hover the toast (mouse path). Order doesn't
        // matter functionally — the post-fix code records both
        // booleans independently.
        await toast.locator('[data-toast-close]').focus();
        await toast.hover();

        // Now leave the MOUSE modality only — keep keyboard focus on
        // the X button. Pre-fix `resume()` would have rescheduled
        // the timer here; post-fix `resumeIfIdle()` returns early
        // because `focused === true`.
        await page.mouse.move(0, 0);

        // Wait past the default. With the pre-fix bug the toast
        // would dismiss around T=defaultMs + 200ms; with the fix
        // the X button is still focused so the timer stays paused
        // indefinitely.
        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + HOVER_MARGIN_MS);

        await expect(
            toast,
            'toast must remain visible while X-button is still focused, even after mouse leaves'
            + ' — M-1 regression: hover and focus collapsed into a single state; resume() runs when'
            + ' only ONE modality is still active. Independent `hovered` / `focused` booleans are the contract',
        ).toBeVisible();

        // Now blur the X button. With both modalities idle the
        // resumed timer fires within ~defaultMs.
        await page.evaluate(() => {
            const closeBtn = document.querySelector(
                '[data-testid="toast"] [data-toast-close]',
            ) as HTMLElement | null;
            if (closeBtn && typeof closeBtn.blur === 'function') closeBtn.blur();
        });

        // eslint-disable-next-line playwright/no-wait-for-timeout
        await page.waitForTimeout(defaultMs + RESUME_MARGIN_MS);

        await expect(
            toast,
            'toast must auto-dismiss after BOTH modalities go idle',
        ).toHaveCount(0);

        expect(
            consoleErrors,
            `unexpected console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
