/**
 * Flow spec — issue #1480: `template.logo` resolution and the
 * `<img>` brand-mark on the sidebar (logged-in chrome) + the
 * sign-in card (logged-out chrome) actually loads instead of
 * shipping a broken `<img>` icon.
 *
 * Pre-#1480 the chrome read `Config::get('template.logo')`
 * directly and concatenated it into `<img src="{$theme_url}/{$logo}">`,
 * which silently surfaced three reachable broken-image shapes:
 *
 *   1. The v1.x default `logos/sb-large.png` (which the v2.0
 *      default theme never shipped — the migration converted
 *      seeded panels forward but installations that missed the
 *      migration kept the stale literal in `:prefix_settings`).
 *   2. Empty / missing `template.logo` row (the column has no
 *      enforced default; a manually-cleaned `:prefix_settings`
 *      would surface as `themes/<theme>/`).
 *   3. Custom paths to deleted files (operator typed
 *      `logos/foo.png`, then deleted `foo.png` from the theme
 *      tree — the row stays in `:prefix_settings` but the
 *      `<img>` is broken).
 *
 * `BrandLogoTest` + `BrandLogoChromeWiringTest` (PHPUnit) cover
 * the resolver + the wiring contracts in isolation. This E2E
 * spec is the end-to-end gate: launch a real browser, navigate
 * to the chrome, and assert the rendered `<img>` actually
 * loaded (via `naturalWidth > 0`) — the canonical browser-side
 * test for "this image is not broken". A regression that silently
 * reintroduces the pre-#1480 raw-concat shape would surface here
 * as `naturalWidth === 0` (the browser couldn't load the image)
 * even if the PHPUnit suite passed.
 *
 * Two scenarios:
 *
 *   - **Default value** (admin storage state — logged in): the
 *     navbar's `<img data-testid="brand-mark">` renders the
 *     resolved default (`images/favicon.svg`). Sanity-checks that
 *     the dev seed didn't accidentally introduce a broken default.
 *   - **Broken value falls back gracefully** (admin storage state):
 *     seed `template.logo = logos/sb-large.png` (the v1.x default
 *     literal — never shipped in the v2.0 default theme), reload,
 *     assert the chrome's `<img>` still loads (because the resolver
 *     intercepted the broken value and fell back to the shield).
 *     This is the marquee #1480 regression — pre-fix the chrome
 *     would silently ship a broken `<img>`.
 *
 * The login-page render path (logged-out, `?p=login`) is exercised
 * by the same `data-testid="brand-mark"` selector — `page_login.tpl`
 * carries the testid on its sidebar-brand-mark `<img>` so the same
 * spec walks both surfaces.
 *
 * Cleanup: the broken-value test reverts `template.logo` back to the
 * default in `afterAll`. The e2e DB is shared between specs (per
 * AGENTS.md "Playwright E2E specifics" — `Fixture::truncateAndReseed`
 * does NOT reset `sb_settings`); leaving a stale broken value would
 * silently affect every spec that runs after this one.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { setSettingE2e } from '../../fixtures/db.ts';

const DEFAULT_LOGO = 'images/favicon.svg';
const V1_DEFAULT_LOGO = 'logos/sb-large.png';

test.describe('flow: template.logo brand-mark resolution (#1480)', () => {
    test.afterAll(async () => {
        // Revert to the default after the broken-value test runs,
        // mirroring the `groupban-dispatcher.spec.ts` afterAll
        // pattern — the e2e DB is shared between specs and
        // Fixture::truncateAndReseed() does NOT reset sb_settings.
        await setSettingE2e('template.logo', DEFAULT_LOGO);
    });

    test('sidebar brand mark loads from the default value (admin chrome)', async ({ page }) => {
        // Default value is already in the seed. Hit the dashboard
        // (the admin-chrome surface that renders core/navbar.tpl).
        await page.goto('/index.php');

        // `data-testid="brand-mark"` lives on the `<img>` in
        // `core/navbar.tpl`. Anchoring on the testid (not the
        // `.sidebar__brand-mark` CSS class) keeps the assertion
        // compliant with AGENTS.md's "selectors must use testability
        // hooks; never CSS class chains as the primary selector".
        // The element is attached + visible in the desktop chrome;
        // in mobile-chromium it's attached but the sidebar is
        // collapsed off-screen until the hamburger toggles it
        // (mirror `smoke/login.spec.ts`'s sidebar-element handling).
        const brandMark = page.locator('[data-testid="brand-mark"]').first();
        await expect(brandMark).toBeAttached();

        // The load-bearing assertion: assert the browser actually
        // succeeded in loading the image. `naturalWidth > 0` is the
        // standard browser-side test for "image loaded successfully"
        // — the property is 0 for an `<img>` whose `src` failed
        // (404 / wrong path / etc) regardless of whether the
        // element itself is rendered. Pre-fix a broken
        // `template.logo` value would ship `<img src="themes/default/<broken>">`
        // and `naturalWidth` would stay 0.
        const naturalWidth = await brandMark.evaluate(
            (el) => (el as HTMLImageElement).naturalWidth,
        );
        expect(naturalWidth, 'navbar brand-mark <img> failed to load (naturalWidth=0)').toBeGreaterThan(0);
    });

    test('sidebar brand mark loads from the resolved fallback when template.logo is broken (#1480)', async ({ page }) => {
        // Seed the v1.x default literal — the canonical broken-value
        // shape this PR addresses. Pre-fix the chrome would emit
        // `<img src="themes/default/logos/sb-large.png">` and the
        // browser would 404 the image; post-fix `Sbpp\View\BrandLogo`
        // intercepts the broken value (the path doesn't exist in the
        // v2.0 default theme tree) and substitutes the shield
        // fallback so the chrome ships a valid `<img>`.
        await setSettingE2e('template.logo', V1_DEFAULT_LOGO);

        // Hard reload to bypass any in-process Config cache that
        // pre-dates the setSettingE2e call (the cache rebuilds on
        // every PHP request, but the browser cache could keep an
        // old DOM around).
        await page.goto('/index.php', { waitUntil: 'networkidle' });

        const brandMark = page.locator('[data-testid="brand-mark"]').first();
        await expect(brandMark).toBeAttached();

        // The src attribute will still show the raw configured value
        // (the navbar template renders `{$theme_url}/{$logo}` and
        // `$logo` comes from `BrandLogo::resolve()`). After the
        // resolver intercepts the broken value, `$logo` is
        // `images/favicon.svg`, NOT `logos/sb-large.png`. Assert
        // the `src` attribute matches the fallback's tail.
        const src = await brandMark.getAttribute('src');
        expect(src, 'brand-mark <img> src must be the resolved fallback, not the broken raw value').toMatch(/images\/favicon\.svg$/);

        // The load-bearing assertion: even with a broken
        // `template.logo` in the DB, the rendered image MUST load
        // successfully because the resolver substituted the shield.
        // A regression that bypasses the resolver (reverts
        // `core/header.php` to the raw `Config::get` shape) would
        // fail this with `naturalWidth=0`.
        const naturalWidth = await brandMark.evaluate(
            (el) => (el as HTMLImageElement).naturalWidth,
        );
        expect(naturalWidth, 'fallback brand-mark <img> failed to load (naturalWidth=0)').toBeGreaterThan(0);
    });
});

test.describe('flow: template.logo brand-mark on the sign-in card (#1480)', () => {
    // Per-describe override: log out for this block. The login page
    // redirects authenticated visitors to /index.php before rendering
    // the sign-in card; without the empty storage state the
    // `data-testid="brand-mark"` selector on `page_login.tpl` is
    // unreachable. Mirrors `smoke/login.spec.ts`'s `test.use({
    // storageState: { cookies: [], origins: [] } })` shape.
    test.use({ storageState: { cookies: [], origins: [] } });

    test.afterAll(async () => {
        // Same cleanup contract as the admin-chrome describe — revert
        // to the default after broken-value tests run.
        await setSettingE2e('template.logo', DEFAULT_LOGO);
    });

    test('sign-in card brand mark loads from the default value', async ({ page }) => {
        await page.goto('/index.php?p=login');

        // `page_login.tpl` carries the same `data-testid="brand-mark"`
        // on the sign-in card's `<img>`. The selector walks both
        // surfaces uniformly.
        const brandMark = page.locator('[data-testid="brand-mark"]').first();
        await expect(brandMark).toBeAttached();

        const naturalWidth = await brandMark.evaluate(
            (el) => (el as HTMLImageElement).naturalWidth,
        );
        expect(naturalWidth, 'login-card brand-mark <img> failed to load (naturalWidth=0)').toBeGreaterThan(0);
    });

    test('sign-in card brand mark loads from the resolved fallback when template.logo is broken (#1480)', async ({ page }) => {
        // Same broken-value scenario as the admin chrome — different
        // render path (`page.login.php` → `LoginView::$brand_logo_url`
        // → `BrandLogo::resolveUrl()`), same regression class.
        await setSettingE2e('template.logo', V1_DEFAULT_LOGO);

        await page.goto('/index.php?p=login', { waitUntil: 'networkidle' });

        const brandMark = page.locator('[data-testid="brand-mark"]').first();
        await expect(brandMark).toBeAttached();

        // The login page assigns the FULL theme-relative URL to
        // `$brand_logo_url` (so `BrandLogo::resolveUrl()` returns
        // `themes/<theme>/<resolved>`). The src attribute should
        // therefore end with `images/favicon.svg`, NOT the broken
        // raw value.
        const src = await brandMark.getAttribute('src');
        expect(src, 'sign-in card brand-mark <img> src must be the resolved fallback URL').toMatch(/themes\/[^/]+\/images\/favicon\.svg$/);

        const naturalWidth = await brandMark.evaluate(
            (el) => (el as HTMLImageElement).naturalWidth,
        );
        expect(naturalWidth, 'fallback login-card brand-mark <img> failed to load (naturalWidth=0)').toBeGreaterThan(0);
    });
});
