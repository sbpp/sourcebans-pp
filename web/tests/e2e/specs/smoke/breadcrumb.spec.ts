/**
 * Issue #1207 AUTH-3: login + lostpassword breadcrumb is single-segment.
 *
 * Pre-fix the chrome breadcrumb on every page rendered as
 * `Home > $title` — including the login and lost-password pages.
 * That's misleading for those two surfaces: the visitor is logged
 * out by definition (the page handlers redirect any authenticated
 * caller to `index.php`), so the "Home" prefix is a link to the
 * public dashboard they didn't arrive to visit.
 *
 * Fix: `core/title.php` consults `Sbpp\View\LoginView::breadcrumb()` /
 * `Sbpp\View\LostPasswordView::breadcrumb()` for `$_GET['p'] === 'login'`
 * / `'lostpassword'` respectively, both of which return a
 * single-segment breadcrumb (`[{title: 'Sign in', url: ...}]` /
 * `[{title: 'Reset password', url: ...}]`). Every other route keeps
 * the default 2-segment shape.
 *
 * Selectors follow #1123's testability hooks:
 *   - the breadcrumb container is `nav[aria-label="Breadcrumb"]` (set
 *     in `core/title.tpl`);
 *   - the active segment carries `aria-current="page"` so screen
 *     readers and tests can point at it without parsing visible text.
 *
 * Both `chromium` and `mobile-chromium` run the same assertions —
 * the chrome contract is the same on both viewports (the topbar's
 * mobile collapse from CC-1 hides the search label, not the
 * breadcrumb).
 */

import { test, expect } from '../../fixtures/auth.ts';

test.describe('#1207 AUTH-3: login + lostpassword breadcrumb', () => {
    // Per-describe override: log out for this whole block. The
    // project-default storageState carries the seeded admin's
    // session cookie, which would (per the AUTH-1 redirect guard
    // shipped in slice 3) bounce the browser back to `index.php`
    // before the breadcrumb ever rendered. This block exercises
    // the logged-out path that real users hit on these surfaces.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login renders a single-segment "Sign in" breadcrumb', async ({ page }) => {
        await page.goto('/index.php?p=login');

        // The form's email/username/password trio is the
        // deterministic terminal mark for the logged-out path —
        // if the redirect guard had bounced us, we'd be on the
        // dashboard with no `login-username` input in the DOM.
        await expect(page.locator('[data-testid="login-username"]')).toBeVisible();

        const breadcrumb = page.locator('nav[aria-label="Breadcrumb"]');
        await expect(breadcrumb).toBeVisible();

        // Single-segment: exactly one `<a>` inside the breadcrumb.
        // The pre-fix shape rendered two anchors ("Home" + the page
        // title), so this count guards against accidentally falling
        // back to the default 2-segment branch in `core/title.php`.
        const links = breadcrumb.locator('a');
        await expect(links).toHaveCount(1);

        // The active segment is the last one (aria-current="page"),
        // which on a single-segment breadcrumb is also the only one.
        // We pin BOTH the visible text ("Sign in", per the issue's
        // suggested copy) and the href so a future copy/href edit
        // either lands together or fails loudly here. The "Login"
        // string the legacy breadcrumb rendered must NOT come back.
        const active = breadcrumb.locator('a[aria-current="page"]');
        await expect(active).toHaveCount(1);
        await expect(active).toHaveText('Sign in');
        await expect(active).toHaveAttribute('href', 'index.php?p=login');
        await expect(breadcrumb).not.toContainText(/^Home/);
    });

    test('lostpassword renders a single-segment "Reset password" breadcrumb', async ({ page }) => {
        await page.goto('/index.php?p=lostpassword');

        // Same deterministic mark as the login spec above — the
        // form's email input is server-rendered if and only if the
        // logged-out branch took.
        await expect(page.locator('[data-testid="lostpw-email"]')).toBeVisible();

        const breadcrumb = page.locator('nav[aria-label="Breadcrumb"]');
        await expect(breadcrumb).toBeVisible();

        const links = breadcrumb.locator('a');
        await expect(links).toHaveCount(1);

        const active = breadcrumb.locator('a[aria-current="page"]');
        await expect(active).toHaveCount(1);
        await expect(active).toHaveText('Reset password');
        await expect(active).toHaveAttribute('href', 'index.php?p=lostpassword');
        // "Lost your password" was the pre-fix breadcrumb title (from
        // page-builder's route() return value). The new breadcrumb
        // shape replaces it with the canonical "Reset password" copy
        // — guard the regression both ways.
        await expect(breadcrumb).not.toContainText('Lost your password');
        await expect(breadcrumb).not.toContainText(/^Home/);
    });
});

test.describe('#1207 AUTH-3: regression — other routes keep the 2-segment breadcrumb', () => {
    // Inherits the project-default authenticated session from
    // `fixtures/global-setup.ts` — every other route renders for
    // the seeded admin without redirecting. We pin one public route
    // (banlist) so the fix can't accidentally widen the
    // single-segment branch to ALL pages.
    test('banlist still renders Home > Ban List', async ({ page }) => {
        await page.goto('/index.php?p=banlist');

        const breadcrumb = page.locator('nav[aria-label="Breadcrumb"]');
        await expect(breadcrumb).toBeVisible();

        // Pre-fix shape: 2 anchors. The active one is the last
        // (aria-current="page") and reads "Ban List"; the first is
        // "Home" linking back to the dashboard. The new dispatch in
        // `core/title.php` falls through to this default branch for
        // every `$_GET['p']` other than login / lostpassword.
        const links = breadcrumb.locator('a');
        await expect(links).toHaveCount(2);

        await expect(breadcrumb.locator('a').first()).toHaveText('Home');
        await expect(breadcrumb.locator('a[aria-current="page"]')).toHaveText('Ban List');
    });
});
