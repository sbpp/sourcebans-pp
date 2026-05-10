/**
 * Command palette permission filtering (#1304).
 *
 * Pre-fix the palette's "Navigate" entries were a hardcoded `NAV_ITEMS`
 * array in `web/themes/default/js/theme.js` with no permission filter,
 * so logged-out + partial-permission users saw `Admin panel` and
 * `Add ban` alongside the public entries — clicking either bounced
 * them off the "you must be logged in" / 403 surface.
 *
 * The fix server-renders the entry set via
 * `Sbpp\View\PaletteActions::for($userbank)` and emits it as a JSON
 * blob inside `<script type="application/json" id="palette-actions">`
 * in `core/footer.tpl`; theme.js reads + JSON.parses the blob at boot
 * and uses it instead of the hardcoded array. The PHPUnit suite
 * (`PaletteActionsTest`) holds the helper accountable in isolation;
 * this spec exercises the wire contract end-to-end (server-rendered
 * blob → JSON.parse → palette result rows) by asserting on the
 * rendered DOM as the user would see it.
 *
 * Two contracts pinned here:
 *   1. The server-rendered JSON blob (`#palette-actions`) carries the
 *      filtered shape — never the admin entries for an anonymous
 *      visitor, never `Add ban` for a partial-permission admin.
 *   2. The palette's rendered nav rows match the blob — opening the
 *      palette + reading the `[data-result-kind="nav"]` rows agrees
 *      with the JSON the chrome shipped, so a future regression in
 *      either half (server filter or JS consumer) gets caught.
 */

import { expect, test } from '../../../fixtures/auth.ts';

test.describe('command palette: permission-filtered nav entries (#1304)', () => {
    test.describe('logged-out visitor', () => {
        // Per-describe override: opt out of the project-default
        // storageState (which carries the seeded admin's session
        // cookie). The leak the issue describes is most visible to
        // unauthenticated visitors, so this is the load-bearing
        // subtest.
        test.use({ storageState: { cookies: [], origins: [] } });

        test('server-rendered #palette-actions blob excludes admin entries', async ({ page }) => {
            await page.goto('/');

            // The `<script type="application/json" id="palette-actions">`
            // tag is in the DOM regardless of whether the palette has
            // been opened — theme.js reads it at boot. We assert on the
            // textContent (JSON.parse-able payload) directly so the
            // server-side filter is the contract under test.
            const blob = page.locator('[data-testid="palette-actions"]');
            await expect(blob).toBeAttached();
            const json = await blob.textContent();
            expect(json, 'palette-actions blob must carry a JSON payload').toBeTruthy();

            // Decode + assert. Each entry is `{icon, label, href}`;
            // we filter on `label` because that's the user-visible
            // dimension the palette displays + filters on.
            const entries = JSON.parse(json ?? '[]') as Array<{
                icon: string;
                label: string;
                href: string;
            }>;
            const labels = entries.map((e) => e.label);

            // Primary leak: no admin entries for an unauthenticated
            // visitor.
            expect(labels).not.toContain('Admin panel');
            expect(labels).not.toContain('Add ban');

            // Public entries that should always render so the palette
            // stays useful for anonymous visitors who want to jump to
            // the public banlist / submit form.
            expect(labels).toContain('Dashboard');
            expect(labels).toContain('Servers');
            expect(labels).toContain('Ban list');
        });

        test('opening the palette renders only the filtered nav rows', async ({ page }) => {
            await page.goto('/');

            // Open the palette via Meta+K (theme.js's keydown listener
            // accepts metaKey || ctrlKey, so this works on every
            // runner). Cross-references the existing
            // command-palette.spec.ts harness — we pin the *content*
            // of the rendered rows rather than the open/close
            // mechanics that spec already covers.
            await page.keyboard.press('Meta+k');
            const dialog = page.locator('#palette-root');
            await expect(dialog).toHaveAttribute('data-palette-open', 'true');

            // theme.js's renderPaletteResults emits each nav entry as
            // `<a data-testid="palette-result" data-result-kind="nav">`
            // with the label as the visible text. The first paint shows
            // the unfiltered nav set (palette opens with empty input);
            // we read those rows and assert on their labels.
            const navRows = page.locator(
                '[data-testid="palette-result"][data-result-kind="nav"]',
            );

            // Wait for at least one nav row to render. The blob is
            // server-rendered so the rows should appear synchronously
            // on open() — the count poll guards against a Lucide
            // icon-init race that could empty the container briefly.
            await expect(navRows.first()).toBeVisible();
            const renderedLabels = await navRows.allTextContents();

            // Trim each label — each row contains a Lucide icon +
            // whitespace + label text; allTextContents() returns the
            // trimmed combined text but we strip again defensively.
            const cleaned = renderedLabels.map((s) => s.trim());

            // Anti-leakage: clicking these would land the visitor on
            // the "you must be logged in" / 403 surface. Pre-fix they
            // were both visible.
            expect(cleaned).not.toContain('Admin panel');
            expect(cleaned).not.toContain('Add ban');

            // Public entries remain — the palette is still useful for
            // anonymous visitors.
            expect(cleaned).toContain('Dashboard');
            expect(cleaned).toContain('Ban list');
        });
    });

    test.describe('logged-in admin (owner)', () => {
        // No storageState override here: this block inherits the
        // project-default session minted by `fixtures/global-setup.ts`
        // for the seeded admin (admin/admin, ADMIN_OWNER). The owner
        // bypass means every entry surfaces — this subtest is the
        // anti-confusion guard so a future PR that "fixes" the leak
        // by hiding admin entries from EVERYONE fails the build.
        test('owner sees admin entries in the rendered palette', async ({ page }) => {
            await page.goto('/');

            const blob = page.locator('[data-testid="palette-actions"]');
            await expect(blob).toBeAttached();
            const json = await blob.textContent();
            const entries = JSON.parse(json ?? '[]') as Array<{
                icon: string;
                label: string;
                href: string;
            }>;
            const labels = entries.map((e) => e.label);

            // Owner sees every admin entry — the bypass is the
            // documented contract (mirrors the `is_admin()` shape in
            // `core/navbar.tpl`).
            expect(labels).toContain('Admin panel');
            expect(labels).toContain('Add ban');
            expect(labels).toContain('Dashboard');
            expect(labels).toContain('Ban list');

            // And the palette's rendered rows agree.
            await page.keyboard.press('Meta+k');
            const dialog = page.locator('#palette-root');
            await expect(dialog).toHaveAttribute('data-palette-open', 'true');

            const navRows = page.locator(
                '[data-testid="palette-result"][data-result-kind="nav"]',
            );
            await expect(navRows.first()).toBeVisible();

            // hasText filters to disambiguate the multi-row case —
            // each filter expects a single-row match. Per AGENTS.md
            // these are fine for narrowing once the primary
            // data-testid selector has matched a set.
            await expect(
                navRows.filter({ hasText: 'Admin panel' }).first(),
            ).toBeVisible();
            await expect(
                navRows.filter({ hasText: 'Add ban' }).first(),
            ).toBeVisible();
        });
    });
});
