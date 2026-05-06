/**
 * Issue #1207 Slice 3 (routing + truthiness fixes).
 *
 * Covers the user-observable invariants for ADM-1, ADM-2, AUTH-1, CC-5,
 * and CC-6 — i.e. the things the audit screenshots showed and that the
 * fix was supposed to land:
 *
 *   - **ADM-1** — `?p=admin&c=overrides` (the pre-fix "Overrides" card
 *     href) and any other unrecognised `c=…` returns the 404 page slot
 *     with HTTP 404, instead of silently rendering the admin landing.
 *   - **ADM-1** (cont.) — clicking the admin-home Overrides card lands
 *     on `?p=admin&c=admins#overrides` and the overrides editor anchor
 *     is in the DOM.
 *   - **ADM-2** — the audit-log card description no longer contains
 *     the misleading "(coming soon)" copy.
 *   - **AUTH-1** — the lostpassword page rendered to a logged-out
 *     visitor shows the public chrome (Login link visible, no admin
 *     section).
 *   - **CC-5** — the footer carries `data-version="dev"` in dev (no
 *     tarball, no git binary), so telemetry / E2E specs can pin the
 *     dev sentinel without parsing the user-visible string.
 *   - **CC-6** — the footer attribution link points at the GitHub
 *     repo, not the marketing site.
 *
 * Selectors follow #1123's testability hooks (`data-testid`,
 * `data-version`, ARIA roles) — never CSS class chains, never visible
 * text as the *primary* selector. The `hasText` filters here are
 * disambiguators, not primary selectors.
 */

import { test, expect } from '../../fixtures/auth.ts';

test.describe('#1207 routing + truthiness fixes', () => {
    test.describe('ADM-1: unknown c=… returns 404', () => {
        test('?p=admin&c=overrides renders the 404 page with HTTP 404', async ({ page }) => {
            const response = await page.goto('/index.php?p=admin&c=overrides');

            // The route() function calls http_response_code(404) BEFORE
            // emitting any body, so the response status is the
            // deterministic signal monitoring tools see — pin it.
            expect(response?.status(), 'unknown ?c= must surface as HTTP 404').toBe(404);

            // The 404 page slot renders inside the chrome (sidebar +
            // topbar + footer all stay), so the user can navigate
            // away. The `data-testid="page-404"` outer wrapper is the
            // stable terminal mark; the home CTA below is part of the
            // same component.
            await expect(page.locator('[data-testid="page-404"]')).toBeVisible();
            await expect(page.locator('[data-testid="page-404-home"]')).toBeVisible();
        });

        test('typo categories (e.g. ?c=bnas) also 404, not silently fall through', async ({ page }) => {
            const response = await page.goto('/index.php?p=admin&c=bnas');
            expect(response?.status()).toBe(404);
            await expect(page.locator('[data-testid="page-404"]')).toBeVisible();
        });

        test('the bare admin landing (?p=admin) still renders', async ({ page }) => {
            // Regression guard: the 404 branch must not swallow the
            // intentional "no c=" route. The Bans card is the most
            // permissively-gated tile (any ADMIN_*BAN flag lights it
            // up) so it lands for the seeded ADMIN_OWNER user too.
            const response = await page.goto('/index.php?p=admin');
            expect(response?.status()).toBe(200);
            await expect(page.locator('[data-testid="admin-card-bans"]')).toBeVisible();
        });
    });

    test.describe('ADM-1: Overrides card links to the editor', () => {
        test('admin home Overrides card href anchors to ?c=admins#overrides', async ({ page }) => {
            await page.goto('/index.php?p=admin');

            const card = page.locator('[data-testid="admin-card-overrides"]');
            await expect(card).toBeVisible();
            await expect(card).toHaveAttribute('href', /index\.php\?p=admin&(?:amp;)?c=admins#overrides$/);
        });

        test('clicking the Overrides card lands on the admins page with the overrides editor in the DOM', async ({ page }) => {
            await page.goto('/index.php?p=admin');
            await page.locator('[data-testid="admin-card-overrides"]').click();

            // The editor lives at the bottom of admin.admins.php's
            // c=admins route (admin.overrides.php is `require`d there).
            // The hash anchors to `#overrides` — the wrapper div in
            // page_admin_overrides.tpl. We assert the URL contains
            // both the c=admins query and the #overrides fragment,
            // and that the editor anchor is in the DOM.
            await expect(page).toHaveURL(/\?p=admin&(?:amp;)?c=admins#overrides$/);
            // The "Overrides" wrapper div is the editor anchor; the
            // `overrides-form` testid is the form inside it (added
            // for the CSRF discipline note in page_admin_overrides.tpl).
            await expect(page.locator('#overrides')).toBeAttached();
            await expect(page.locator('[data-testid="overrides-form"]')).toBeVisible();
        });
    });

    test.describe('ADM-2: audit-log card copy', () => {
        test('Audit log card description no longer contains "(coming soon)"', async ({ page }) => {
            await page.goto('/index.php?p=admin');

            // Pin the description text negatively (and a positive
            // anchor on the card itself) so a future copy edit
            // doesn't accidentally reintroduce the legacy parenthetical.
            const card = page.locator('[data-testid="admin-card-audit"]');
            await expect(card).toBeVisible();
            await expect(card).not.toContainText('coming soon');
            await expect(card).toContainText(/admin actions/i);
        });
    });

    test.describe('AUTH-1: lostpassword renders public chrome to logged-out visitors', () => {
        // Per-describe override: log out for this whole block. The
        // fixture's project-default storageState carries the seeded
        // admin's session cookie, which would (post-fix) bounce the
        // browser to /home before the form ever rendered. This block
        // exists specifically to exercise the logged-out path.
        test.use({ storageState: { cookies: [], origins: [] } });

        test('logged-out visitor sees public chrome (no admin section, login link visible)', async ({ page }) => {
            await page.goto('/index.php?p=lostpassword');

            // The form itself is the deterministic terminal mark for
            // the logged-out path — the page handler's redirect guard
            // would have sent us elsewhere if we were authenticated.
            await expect(page.locator('[data-testid="lostpw-email"]')).toBeVisible();

            // Public chrome contract: the bottom-left cluster shows a
            // Login link (data-testid="nav-login"), NOT the
            // account/Logout pair (`nav-account` + `nav-logout`).
            //
            // We assert `toBeAttached()` rather than `toBeVisible()`
            // because the sidebar collapses off-screen at the
            // mobile-chromium project's iPhone-13 viewport — the
            // element exists in the DOM but isn't rendered until the
            // hamburger toggles `data-mobile-open="true"`. Same
            // pattern as `specs/smoke/login.spec.ts`'s `nav-account`
            // assertion.
            await expect(page.locator('[data-testid="nav-login"]')).toBeAttached();
            await expect(page.locator('[data-testid="nav-account"]')).toHaveCount(0);
            await expect(page.locator('[data-testid="nav-logout"]')).toHaveCount(0);

            // No admin sub-section: the entire `{if $isAdmin}…{/if}`
            // block in core/navbar.tpl is gated off, so no
            // `nav-admin-*` link should exist anywhere in the DOM.
            await expect(page.locator('[data-testid^="nav-admin-"]')).toHaveCount(0);
            // And `nav-admin` (the top-level "Admin Panel" link) is
            // built from the `permission` flag in navbar.php — gated
            // on `$userbank->is_admin()`, so it's excluded.
            await expect(page.locator('[data-testid="nav-admin"]')).toHaveCount(0);
        });

        test('logged-out visitor lands on the form, not a redirect', async ({ page }) => {
            // Belt-and-braces: the URL must remain on lostpassword.
            // The post-fix guard only redirects logged-in visitors.
            await page.goto('/index.php?p=lostpassword');
            await expect(page).toHaveURL(/[?&]p=lostpassword(?:&|#|$)/);
        });
    });

    test.describe('AUTH-1: logged-in visitors get bounced off lostpassword', () => {
        // No storageState override here: this block inherits the
        // project-default authenticated session minted by
        // `fixtures/global-setup.ts` for the seeded admin. That's the
        // exact case the audit screenshot caught — a logged-in admin
        // landing on `?p=lostpassword` saw the admin sidebar leak
        // around the form. The page handler's guard mirrors
        // page.login.php: emit `<script>window.location.href = 'index.php'</script>`
        // and `exit;`, so the user-observable result is the browser
        // ends up on the dashboard, not on the form.
        test('logged-in admin visiting lostpassword does NOT see the form', async ({ page }) => {
            // The JS redirect fires on `DOMContentLoaded`-ish timing,
            // but Playwright's default `goto` waits for `load`, by
            // which point `window.location.href = …` has already run
            // and the navigation chain has settled on the dashboard.
            await page.goto('/index.php?p=lostpassword');

            // Terminal assertion: the URL must NOT be lostpassword
            // anymore. Same shape as `specs/smoke/login.spec.ts`'s
            // logged-in redirect assertion.
            await expect(page).not.toHaveURL(/[?&]p=lostpassword(?:&|#|$)/);

            // And the form body must NOT be in the DOM — even if a
            // future refactor changes the redirect destination, the
            // user must never reach a state where they can fill in
            // the lost-password email field while authenticated.
            await expect(page.locator('[data-testid="lostpw-email"]')).toHaveCount(0);
        });
    });

    test.describe('CC-5 + CC-6: footer credibility', () => {
        test('footer carries data-version="dev" in the dev stack', async ({ page }) => {
            await page.goto('/');

            const footer = page.locator('footer.sbpp-footer');
            await expect(footer).toBeAttached();

            // The dev docker image doesn't ship `git`, doesn't bind-mount
            // `.git`, and doesn't carry a release tarball's
            // `configs/version.json`. So `Sbpp\Version::resolve()` falls
            // through to the third-tier sentinel `'dev'`. The
            // `data-version` attribute mirrors SB_VERSION verbatim,
            // so the dev stack always lands `dev` here.
            //
            // Release-tarball installs would render
            // `data-version="2.1.0"` (or whatever version.json
            // declares), which the regex in this assertion still
            // accepts via the alternation — so a future "test against
            // a release-tarball image" wouldn't have to fork the spec.
            await expect(footer).toHaveAttribute('data-version', /^(?:dev|\d+\.\d+\.\d+\S*)$/);

            // Also pin the user-visible string for the dev sentinel
            // case: pre-#1207 the footer rendered the literal "N/A",
            // which read like a runtime error. The new sentinel is
            // self-describing.
            const versionAttr = await footer.getAttribute('data-version');
            if (versionAttr === 'dev') {
                await expect(footer).toContainText('dev');
                await expect(footer).not.toContainText('N/A');
            }
        });

        test('footer attribution link points at the GitHub repo', async ({ page }) => {
            await page.goto('/');

            // Locate the link by its containing footer + the
            // attribute we ship as the canonical contract — never by
            // visible text alone.
            const link = page.locator('footer.sbpp-footer a[href*="github.com/sbpp/sourcebans-pp"]');
            await expect(link).toBeVisible();
            await expect(link).toHaveAttribute('href', 'https://github.com/sbpp/sourcebans-pp');
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', /noopener/);

            // Anti-regression: the legacy marketing-site URL must
            // not appear anywhere in the footer. If a future PR
            // adds a second attribution row, this catches the wrong
            // one slipping back in.
            await expect(page.locator('footer.sbpp-footer a[href*="sbpp.github.io"]')).toHaveCount(0);
        });
    });
});
