/**
 * Login spec — drives the form directly.
 *
 * Every other spec inherits the storage state minted in
 * `fixtures/global-setup.ts` (logged in as the seeded admin), but the
 * login flow itself has to exercise the form. We opt out of the
 * storage state per-describe via `test.use({ storageState: …empty… })`;
 * Playwright honours that on top of the project default.
 *
 * Selectors follow #1123's testability hooks (data-testid + ARIA).
 * No CSS class chains, no visible-text primary selectors, no
 * `setTimeout` waits — see `_base.ts` for the rule.
 */

import { expect, test } from '../../fixtures/auth.ts';

test.describe('login', () => {
    // Per-describe override: log out for this whole block. Without
    // this, the storage-state cookie minted by global-setup would
    // already carry a logged-in admin and the form would never be
    // visited.
    test.use({ storageState: { cookies: [], origins: [] } });

    // No per-spec truncateE2eDb() in Slice 0: globalSetup runs
    // Fixture::install() once per `playwright test` invocation, and
    // the login flow doesn't mutate state in a way that affects its
    // own re-run. Future slices that need per-spec resets must
    // coordinate across the parallel projects (chromium +
    // mobile-chromium share the DB) — `truncateE2eDb()` from
    // `fixtures/db.ts` is the helper they'll use, with a worker
    // serialisation mechanism to avoid races. See the foundation
    // PR (#1124) for the rationale.

    test('valid credentials redirect to the dashboard', async ({ page }) => {
        await page.goto('/index.php?p=login');

        await page.locator('[data-testid="login-username"]').fill('admin');
        await page.locator('[data-testid="login-password"]').fill('admin');
        await page.locator('[data-testid="login-submit"]').click();

        // Success terminal state: the redirect envelope from
        // api_auth_login lands the user on `?` (the home dashboard);
        // the navbar's account link is only rendered when CUserManager
        // sees a logged-in user — that's the deterministic
        // server-side signal. We assert `toBeAttached()` (in the DOM)
        // rather than `toBeVisible()` because the sidebar collapses
        // off-screen at mobile-chromium's iPhone-13 viewport, so the
        // element exists but isn't rendered until the hamburger
        // toggles `data-mobile-open="true"`.
        await expect(page.locator('[data-testid="nav-account"]')).toBeAttached();
        await expect(page.locator('[data-testid="nav-login"]')).toHaveCount(0);
        await expect(page).toHaveURL(/\/(?:\?|index\.php\??)?$/);
    });

    test('invalid credentials surface the failure toast', async ({ page }) => {
        await page.goto('/index.php?p=login');

        await page.locator('[data-testid="login-username"]').fill('admin');
        await page.locator('[data-testid="login-password"]').fill('wrong-password');
        await page.locator('[data-testid="login-submit"]').click();

        // Failure path: api_auth_login returns a redirect to
        // `?p=login&m=failed`; api.js follows it; the inline JS in
        // page_login.tpl reads `?m=…` and calls
        // `window.SBPP.showToast({ kind: 'error', title: 'Login failed', … })`.
        // The toast div is `.toast` with `data-kind="error"` (the
        // attribute is set by theme.js's showToast). We use `data-kind`
        // as the primary attribute selector — never visible text alone —
        // and disambiguate the multi-toast case via the title text.
        const toast = page.locator('.toast[data-kind="error"]').filter({ hasText: 'Login failed' });
        await expect(toast).toBeVisible();
        await expect(page).toHaveURL(/[?&]p=login(?:&|$)/);
        await expect(page).toHaveURL(/[?&]m=failed(?:&|$)/);
    });
});
