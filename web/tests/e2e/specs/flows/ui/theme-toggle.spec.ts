/**
 * Theme toggle (#1124, Slice 6).
 *
 * Acceptance criteria from #1124:
 *   "toggle cycles light → dark → system, persists across navigation,
 *    persists across reload, <html data-theme> reflects the choice."
 *
 * Divergence from the issue's literal text: the chrome contract that
 * actually shipped in #1123 mirrors resolved theme to the `dark` CSS
 * class on `<html>` (see `web/themes/default/js/theme.js`'s
 * `applyTheme()`), NOT to a `data-theme` attribute. The localStorage
 * key is `sbpp-theme`, not `theme`. This spec asserts against the
 * shipped chrome — see Slice 0's `_screenshots.spec.ts` header for
 * the same note.
 *
 * Post-#1185-followup the chrome ALSO mirrors the *preference* (the
 * verbatim localStorage value) to `<html data-theme-pref="...">`. The
 * theme toggle's tri-state icon (sun / moon / monitor) gates on this
 * attribute so "system" mode is visually distinguishable from
 * whichever of light/dark the OS resolves to. The class-vs-attribute
 * split is deliberate: `class="dark"` is the *resolved* theme (what's
 * actually painted), `data-theme-pref` is the *choice*.
 *
 * The toggle button carries `[data-theme-toggle]` (also `data-testid="theme-toggle"`).
 * Its click cycle, per `theme.js`:
 *   light → dark → system → light → …
 *
 * "System" resolves to `prefers-color-scheme: dark`. We pin the
 * media query via `page.emulateMedia({ colorScheme: 'light' })` so
 * "system" is deterministically light during the test, otherwise the
 * intermediate state would depend on the host's OS preference.
 */

import { expect, test } from '../../../fixtures/auth.ts';

const THEME_KEY = 'sbpp-theme';

/**
 * Expected aria-label strings per preference (#1185 follow-up). Mirror
 * of `THEME_LABELS` in `web/themes/default/js/theme.js` — kept verbatim
 * here so the test IS the chrome's contract anchor for AT users.
 * A typo / rename / refactor on either side fails this spec immediately;
 * sighted users see the icon swap so visual regressions are caught
 * elsewhere, but screen-reader users are the population least likely to
 * file a bug, hence the explicit lock here.
 */
const THEME_LABELS: Readonly<Record<'light' | 'dark' | 'system', string>> = {
    light: 'Color theme: light. Click to switch to dark.',
    dark: 'Color theme: dark. Click to switch to system (auto).',
    system: 'Color theme: system (auto). Click to switch to light.',
};

/** Wait for the toggle's aria-label to match the expected preference's label. */
async function expectAriaLabel(
    page: import('@playwright/test').Page,
    expected: 'light' | 'dark' | 'system',
): Promise<void> {
    await expect(page.getByTestId('theme-toggle').first()).toHaveAttribute(
        'aria-label',
        THEME_LABELS[expected],
    );
}

/** Wait for the resolved-theme attribute on `<html>` to match the expected mode. */
async function expectResolvedTheme(
    page: import('@playwright/test').Page,
    expected: 'light' | 'dark',
): Promise<void> {
    await expect
        .poll(async () =>
            page.evaluate(() => document.documentElement.classList.contains('dark')),
        )
        .toBe(expected === 'dark');
}

/**
 * Wait for the *preference* attribute on `<html>` to match. Different
 * from `expectResolvedTheme` — the resolved-theme class reflects what's
 * painted; `data-theme-pref` reflects the user's choice (light / dark /
 * system) verbatim. The theme toggle's tri-state icon CSS reads this.
 */
async function expectThemePref(
    page: import('@playwright/test').Page,
    expected: 'light' | 'dark' | 'system',
): Promise<void> {
    await expect
        .poll(async () =>
            page.evaluate(() => document.documentElement.getAttribute('data-theme-pref')),
        )
        .toBe(expected);
}

/** Read the persisted preference (returns null when localStorage is unset). */
async function readPersistedTheme(page: import('@playwright/test').Page): Promise<string | null> {
    return page.evaluate((key) => {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    }, THEME_KEY);
}

/**
 * Trigger the theme toggle's click handler.
 *
 * theme.js wires the toggle via a delegated `document.addEventListener('click', …)`
 * + `target.closest('[data-theme-toggle]')`. We dispatch a click directly on
 * the button (via `Element.click()`) instead of `locator.click()` because the
 * shipped 2026 chrome's mobile topbar (`core/title.tpl` + `theme.css#topbar`)
 * sets `min-width: 16rem` on the palette trigger and no horizontal scroll,
 * so on a 390px iPhone-13 viewport the palette button overflows the topbar
 * far enough for its subtree to intercept pointer events targeted at the
 * theme toggle. That layout-overflow is a separate UX concern (the topbar
 * needs `overflow-x: auto` or `flex-wrap: wrap`) — not the JS contract this
 * spec is about. The DOM `click()` call fires a real `click` Event that
 * bubbles to theme.js's document-level listener exactly the same way a
 * tap would on a non-overflowing topbar, so the contract under test is
 * unchanged.
 */
async function clickThemeToggle(page: import('@playwright/test').Page): Promise<void> {
    await page.locator('[data-theme-toggle]').first().evaluate((el) => {
        (el as HTMLElement).click();
    });
}

test.describe('theme toggle', () => {
    // Pin "system" to resolve to light so subtest 1 has a deterministic
    // anchor when the toggle lands on `'system'`. Setting it on the
    // describe is fine because all three subtests share the chromium
    // / mobile-chromium contexts; reducedMotion is already on globally
    // via `playwright.config.ts`.
    test.use({ colorScheme: 'light' });

    test('cycles light → dark → system → light', async ({ page }) => {
        // Pin starting state to 'light' BEFORE the page boots
        // theme.js — we navigate once to establish the origin so
        // localStorage.setItem is allowed, write the key, then
        // reload so the boot path's `applyTheme(currentTheme())`
        // picks it up on first paint.
        await page.goto('/');
        await page.evaluate(
            ({ key }) => {
                try {
                    localStorage.setItem(key, 'light');
                } catch {
                    /* localStorage unavailable; the assertion below catches it */
                }
            },
            { key: THEME_KEY },
        );
        await page.reload();
        await expectResolvedTheme(page, 'light');
        await expectThemePref(page, 'light');
        await expectAriaLabel(page, 'light');
        expect(await readPersistedTheme(page)).toBe('light');

        // light → dark
        await clickThemeToggle(page);
        await expectResolvedTheme(page, 'dark');
        await expectThemePref(page, 'dark');
        await expectAriaLabel(page, 'dark');
        expect(await readPersistedTheme(page)).toBe('dark');

        // dark → system (resolves to light because we emulated it).
        // `data-theme-pref` reflects the *choice* ('system') — distinct
        // from the resolved-theme class which reflects what's painted.
        await clickThemeToggle(page);
        await expectResolvedTheme(page, 'light');
        await expectThemePref(page, 'system');
        await expectAriaLabel(page, 'system');
        expect(await readPersistedTheme(page)).toBe('system');

        // system → light
        await clickThemeToggle(page);
        await expectResolvedTheme(page, 'light');
        await expectThemePref(page, 'light');
        await expectAriaLabel(page, 'light');
        expect(await readPersistedTheme(page)).toBe('light');
    });

    test('persists across navigation', async ({ page }) => {
        await page.goto('/');
        await page.evaluate(
            ({ key }) => {
                try {
                    localStorage.setItem(key, 'dark');
                } catch {
                    /* see above */
                }
            },
            { key: THEME_KEY },
        );
        await page.reload();
        await expectResolvedTheme(page, 'dark');

        // Cross-page navigation reuses the same origin's localStorage,
        // and theme.js boots `applyTheme(currentTheme())` on every
        // first paint, so the new page should land in dark mode
        // without any user interaction.
        await page.goto('/index.php?p=banlist');
        await expectResolvedTheme(page, 'dark');
        expect(await readPersistedTheme(page)).toBe('dark');
    });

    test('icon reflects preference, not resolved theme (#1185 follow-up)', async ({ page }) => {
        // The visual half of the tri-state contract: the toggle button
        // renders three placeholders (sun / moon / monitor) and CSS
        // shows exactly one based on `<html data-theme-pref>`. Pre-fix
        // the CSS gated on `<html class="dark">` instead, so "system"
        // was indistinguishable from whichever of light/dark the OS
        // resolved to — operators had no way to tell at a glance which
        // mode the toggle would jump to next.
        //
        // We assert on `getComputedStyle(...).display` rather than
        // `toBeVisible()` because Lucide replaces the `<i>` placeholders
        // with `<svg>` elements at boot, and the class names
        // (`theme-toggle__sun` / `__moon` / `__system`) carry through to
        // the SVG — but `toBeVisible()` would also pass for an SVG that
        // happens to be 0×0 pixels. `display: inline-block` is the
        // load-bearing CSS contract the bug was about.
        await page.goto('/');
        // Return the SET of icon slots whose computed `display` is not
        // `none`. The contract is "exactly one visible at any time", so
        // every caller asserts the set has size 1 with the expected
        // member — NOT "the first non-`none` slot in some priority
        // order" (which would silently pass when a CSS regression
        // leaves multiple icons visible at once, e.g. dropping the
        // default `.theme-toggle__moon { display: none; }` rule that
        // makes moon also paint in light mode). Quality-vs-quantity:
        // one stricter probe per arm catches more regression shapes
        // than three loose probes.
        const visibleIcons = async (): Promise<string[]> =>
            page.evaluate(() => {
                const slots = ['sun', 'moon', 'system'];
                return slots.filter((name) => {
                    const el = document.querySelector(`.theme-toggle__${name}`);
                    return el !== null && getComputedStyle(el).display !== 'none';
                });
            });

        // Pin to 'light' first.
        await page.evaluate(
            ({ key }) => {
                try {
                    localStorage.setItem(key, 'light');
                } catch {
                    /* see above */
                }
            },
            { key: THEME_KEY },
        );
        await page.reload();
        await expectThemePref(page, 'light');
        expect(await visibleIcons()).toEqual(['sun']);

        // light → dark: moon shows.
        await clickThemeToggle(page);
        await expectThemePref(page, 'dark');
        expect(await visibleIcons()).toEqual(['moon']);

        // dark → system: monitor shows (NOT sun — even though the
        // resolved theme is light because of the colorScheme emulation
        // pinned on the describe).
        await clickThemeToggle(page);
        await expectThemePref(page, 'system');
        expect(await visibleIcons()).toEqual(['system']);

        // system → light: back to sun.
        await clickThemeToggle(page);
        await expectThemePref(page, 'light');
        expect(await visibleIcons()).toEqual(['sun']);
    });

    test('persists across reload', async ({ page }) => {
        // Drive the toggle (rather than localStorage.setItem) so
        // this spec also covers the toggle → persisted-key path,
        // not just the manually-pinned-key one.
        await page.goto('/');
        await page.evaluate(
            ({ key }) => {
                try {
                    localStorage.setItem(key, 'light');
                } catch {
                    /* see above */
                }
            },
            { key: THEME_KEY },
        );
        await page.reload();
        await expectResolvedTheme(page, 'light');

        // light → dark via the actual button (delegated click; see
        // clickThemeToggle for why DOM .click() instead of locator.click()).
        await clickThemeToggle(page);
        await expectResolvedTheme(page, 'dark');
        expect(await readPersistedTheme(page)).toBe('dark');

        await page.reload();
        await expectResolvedTheme(page, 'dark');
        expect(await readPersistedTheme(page)).toBe('dark');
    });
});
