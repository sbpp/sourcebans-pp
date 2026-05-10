/**
 * #1303 — admin/admins advanced-search collapsible disclosure.
 *
 * Pre-#1303 the advanced-search card always rendered fully expanded
 * above the admin list, pushing the actual list well below the fold.
 * #1303 wraps the form in a `<details class="card filters-details">`
 * disclosure that:
 *
 *   1. **Defaults to collapsed** — bare `?p=admin&c=admins` paints
 *      `<details>` (no `[open]`) so the unfiltered list is the first
 *      thing the user sees.
 *   2. **Auto-expands** when any filter slot is populated — post-submit
 *      paints `<details open>` so the form chrome (and the
 *      Clear-filters affordance) remain visible while the user iterates.
 *   3. **Surfaces an "N active" badge** on the `<summary>` so users can
 *      see at a glance how narrow the current filter set is even while
 *      the disclosure is collapsed.
 *
 * Selector contract: every assertion anchors on the `data-testid`
 * hooks (`search-admins-disclosure`, `search-admins-toggle`,
 * `search-admins-active-count`) — never CSS class chains, never
 * visible text as the primary selector. The `<details>` toggle is a
 * native HTML control so we use `evaluate(el => el.hasAttribute(...))`
 * to read the open state (Playwright doesn't ship a "details open"
 * helper); the assertion is single-instant, no `setTimeout` needed.
 *
 * Server-side AND-semantics + the count's per-slot definition are
 * covered in the PHPUnit `AdminAdminsSearchTest` (see
 * `testDisclosureCountMatchesPopulatedFilterSlots` and friends). This
 * spec only locks the BROWSER chrome — that the disclosure exists,
 * defaults closed, opens on click, and re-paints opened on a deep
 * link with active filters.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { AdminAdminsPage } from '../../pages/admin/AdminAdmins.ts';

test.describe('flow: admin/admins advanced-search disclosure (#1303)', () => {
    test('defaults to collapsed on a bare visit', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Disclosure chrome is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();

        // The disclosure is in the DOM and visible (the toggle paints
        // even when closed). The form body is rendered (browser keeps
        // it in the DOM even when `<details>` is closed) but visually
        // hidden by the native disclosure mechanism.
        await expect(p.searchDisclosure).toBeVisible();
        await expect(p.searchToggle).toBeVisible();

        const isOpen = await p.searchDisclosure.evaluate((el) => (el as HTMLDetailsElement).open);
        expect(isOpen).toBe(false);

        // No filter populated → no "N active" badge.
        await expect(p.searchActiveCount).toHaveCount(0);

        // The form body still renders inside the closed `<details>`
        // (the browser keeps it parsed); the submit input is just
        // hidden by the native disclosure. The Submit element is
        // therefore in the DOM but `toBeVisible` would (correctly)
        // report false because it's inside a closed `<details>`.
        await expect(p.searchSubmit).toHaveCount(1);
    });

    test('opens on toggle click', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Disclosure chrome is project-agnostic; pinning to desktop for runtime.');

        const p = new AdminAdminsPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();

        // Click the summary — native `<details>` opens. The `[open]`
        // attribute flip is synchronous so we can read it back
        // without waiting on any animation-driven sentinel.
        await p.searchToggle.click();
        const isOpen = await p.searchDisclosure.evaluate((el) => (el as HTMLDetailsElement).open);
        expect(isOpen).toBe(true);

        // Form fields become reachable post-toggle (the submit was
        // hidden inside the closed disclosure on first paint).
        await expect(p.searchSubmit).toBeVisible();
        await expect(p.searchInput('name')).toBeVisible();
    });

    test('auto-expands when navigated to a URL with active filters', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Disclosure chrome is project-agnostic; pinning to desktop for runtime.');

        // Deep-link with `?name=admin` — the page handler computes
        // `has_active_filters=true` and the template emits
        // `<details open>`. Mirrors the post-submit paint shape.
        await page.goto('/index.php?p=admin&c=admins&section=admins&name=admin');
        const p = new AdminAdminsPage(page);
        await expect(p.pageMounted).toBeVisible();

        const isOpen = await p.searchDisclosure.evaluate((el) => (el as HTMLDetailsElement).open);
        expect(isOpen).toBe(true);

        // The "N active" badge surfaces with the right copy.
        await expect(p.searchActiveCount).toBeVisible();
        await expect(p.searchActiveCount).toContainText('1 active');

        // Form fields are visible because the disclosure is open.
        await expect(p.searchInput('name')).toHaveValue('admin');
        await expect(p.searchSubmit).toBeVisible();
    });

    test('auto-expand survives multi-filter URLs and the badge tracks the populated count', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Count badge contract is project-agnostic; pinning to desktop for runtime.');

        // Three populated value slots: `name`, `steamid`, `webgroup`.
        // `name_match` / `steam_match` are refinements on `name` /
        // `steamid` and must NOT lift the count.
        await page.goto('/index.php?p=admin&c=admins&section=admins&name=admin&name_match=0&steamid=STEAM_0:0:0&steam_match=1&webgroup=1');
        const p = new AdminAdminsPage(page);
        await expect(p.pageMounted).toBeVisible();

        const isOpen = await p.searchDisclosure.evaluate((el) => (el as HTMLDetailsElement).open);
        expect(isOpen).toBe(true);

        await expect(p.searchActiveCount).toBeVisible();
        await expect(p.searchActiveCount).toContainText('3 active');

        // The disclosure root carries the same count as a data
        // attribute so a future fix that drifts the badge from the
        // computed count fails this assertion immediately.
        await expect(p.searchDisclosure).toHaveAttribute('data-active-filter-count', '3');
    });
});
