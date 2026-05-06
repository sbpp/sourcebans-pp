/**
 * Flow: myaccount "Your permissions" categorisation (#1207 ADM-9).
 *
 * Asserts the regression the audit caught — the "Your permissions"
 * card on `/index.php?p=myaccount` used to render every web flag as a
 * single 30-item bullet list next to a "None" column on the SourceMod
 * side. With every permission sharing the same visual weight, "Add
 * Bans" was indistinguishable from "Edit Groups" at a glance.
 *
 * The fix in `web/themes/default/page_youraccount.tpl` +
 * `Sbpp\View\YourAccountView` + `Sbpp\View\PermissionCatalog`
 * publishes a structured `web_permissions_grouped` and renders one
 * `<section data-testid="account-perm-cat-<key>">` per category
 * (Bans, Servers, Admins, Groups, Mods, Settings, Owner). At
 * desktop widths the categories lay out in a 2-column grid (3
 * columns at >=1280px); on mobile they collapse to a single column
 * stack.
 *
 * Selector discipline (#1123 testability hooks)
 * ---------------------------------------------
 * Anchors:
 *   - `[data-testid="account-permissions"]` — the outer card.
 *   - `[data-testid="account-permissions-web"]` /
 *     `[data-testid="account-permissions-server"]` — the two sides.
 *   - `[data-testid="account-perm-cat-<key>"]` — each category
 *     section, addressable by the catalog's stable keys (`bans`,
 *     `servers`, `admins`, `groups`, `mods`, `settings`, `owner`,
 *     `server`).
 *   - `[data-perm-cat="<key>"]` — paired with the testid for CSS /
 *     non-test consumers.
 * NEVER asserts on visible label text as the *primary* selector;
 * `hasText` filters are used only to disambiguate (per AGENTS.md
 * "Playwright E2E specifics").
 *
 * The seeded admin/admin holds `ADMIN_OWNER` only (see
 * `Fixture::seedAdmin()` — `extraflags = 16777216`). Owner-bypass in
 * the catalog lights up every web category with at least one
 * permission, so the spec asserts every `account-perm-cat-<web>` row
 * is present and non-empty. The seeded admin has no SourceMod
 * `srv_flags`, so the SourceMod side renders the empty state with
 * the existing `account-permissions-server-empty` testid.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';

const WEB_CATEGORY_KEYS = [
    'bans',
    'servers',
    'admins',
    'groups',
    'mods',
    'settings',
    'owner',
] as const;

test.describe('flow: myaccount "Your permissions" categorisation (#1207 ADM-9)', () => {
    test.beforeEach(async ({ page }) => {
        // Route is `?p=account` (see `page-builder.php`'s switch:
        // it maps `account` → `page.youraccount.php`); the page
        // itself is informally called "myaccount" because the nav
        // entry reads "My account", but the URL parameter is the
        // legacy `account` slug. Linking with the wrong slug 404s
        // silently — the spec used `?p=myaccount` in an earlier
        // draft and the resulting "no perm cat" failures were a
        // routing tail, not a regression in the template.
        await page.goto('/index.php?p=account');
        await page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]:not([hidden])'),
        );
    });

    test('owner sees every web category populated with at least one permission', async ({ page }) => {
        const card = page.locator('[data-testid="account-permissions"]');
        await expect(card).toBeVisible();

        const webSide = card.locator('[data-testid="account-permissions-web"]');
        await expect(webSide).toBeVisible();

        // Empty-state shouldn't render for an owner — fail fast if it
        // does, otherwise the per-category assertions below would
        // pass vacuously (no categories to check).
        await expect(webSide.locator('[data-testid="account-permissions-web-empty"]')).toHaveCount(0);

        for (const key of WEB_CATEGORY_KEYS) {
            const cat = webSide.locator(`[data-testid="account-perm-cat-${key}"]`);
            await expect(cat, `web category "${key}" must render for an ADMIN_OWNER user`).toBeVisible();
            await expect(cat).toHaveAttribute('data-perm-cat', key);

            // Each category must have at least one rendered permission
            // (`<li>` inside the `.permissions-group__list`); the
            // catalog filters out empty categories so a visible
            // category with zero items would be a regression in
            // PermissionCatalog::groupedDisplayFromMask.
            const items = cat.locator('ul.permissions-group__list > li');
            await expect(
                items.first(),
                `web category "${key}" must have at least one permission listed`,
            ).toBeVisible();
            const count = await items.count();
            expect(count, `web category "${key}" must list >=1 permission, got ${count}`).toBeGreaterThanOrEqual(1);
        }
    });

    test('seeded admin SourceMod side renders the empty state (no srv_flags on the fixture)', async ({ page }) => {
        const serverSide = page.locator('[data-testid="account-permissions-server"]');
        await expect(serverSide).toBeVisible();

        // The fixture admin holds ADMIN_OWNER on the web side but has
        // no SourceMod `srv_flags`, so `SmFlagsToSb` returns false and
        // the template emits the empty-state branch. The testid is
        // the contract from the previous template shape and stays
        // stable across the redesign.
        await expect(serverSide.locator('[data-testid="account-permissions-server-empty"]')).toBeVisible();
    });

    test('desktop: web categories lay out in a multi-column grid', async ({ page, isMobile, viewport }) => {
        test.skip(
            isMobile,
            'Multi-column layout is a desktop-only contract; the mobile path is covered by the dedicated mobile test below.',
        );

        // Skip in the unlikely event a future configuration runs the
        // chromium project at a viewport <1024px (where the CSS
        // collapses to a single column). Doing the skip here keeps
        // the assertion's invariant — "we ARE at desktop" — explicit.
        if (viewport && viewport.width < 1024) {
            test.skip(true, `viewport.width=${viewport.width} < 1024 — multi-column layout only kicks in at >=1024px.`);
        }

        // Read every category's bounding box; if the columns reflowed
        // to a single stack, every category's `x` would be roughly
        // the same. With multi-column layout, at least two categories
        // should sit at *different* x coordinates on the same row.
        const xs: number[] = [];
        for (const key of WEB_CATEGORY_KEYS) {
            const box = await page
                .locator(`[data-testid="account-perm-cat-${key}"]`)
                .boundingBox();
            expect(box, `${key} must be measurable`).not.toBeNull();
            if (!box) return;
            xs.push(box.x);
        }
        const distinctXs = new Set(xs.map((x) => Math.round(x)));
        expect(
            distinctXs.size,
            `at desktop the web categories must occupy >=2 distinct x-positions (got ${distinctXs.size}; xs=${xs.join(',')})`,
        ).toBeGreaterThanOrEqual(2);
    });

    test('mobile: web categories collapse to a single column', async ({ page, isMobile }) => {
        test.skip(
            !isMobile,
            'Single-column collapse is a mobile contract; desktop is covered by the multi-column test above.',
        );

        // On mobile, every category sits at roughly the same x
        // coordinate (one stack). Allow a 1px slop for subpixel
        // rounding from the device-pixel-ratio.
        const xs: number[] = [];
        for (const key of WEB_CATEGORY_KEYS) {
            const box = await page
                .locator(`[data-testid="account-perm-cat-${key}"]`)
                .boundingBox();
            expect(box, `${key} must be measurable`).not.toBeNull();
            if (!box) return;
            xs.push(box.x);
        }
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        expect(
            maxX - minX,
            `at mobile every category must share the same x (within 1px); got minX=${minX} maxX=${maxX}`,
        ).toBeLessThanOrEqual(1);
    });

    test('axe: no critical a11y violations on the permissions card', async ({ page }, testInfo) => {
        await expectNoCriticalA11y(page, testInfo, {
            include: ['[data-testid="account-permissions"]'],
        });
    });
});
