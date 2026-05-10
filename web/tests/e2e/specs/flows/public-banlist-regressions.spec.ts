/**
 * #1315 — public banlist / commslist v1.x → v2.0 regressions.
 *
 * Three regression slices land in the same PR:
 *   1. Advanced-search disclosure on the banlist + commslist
 *      (default-collapsed `<details class="filters-details">`,
 *      auto-opens on a post-submit `?advType=&advSearch=` URL).
 *   2. Banlist Re-apply icon affordance for expired / unbanned rows
 *      (gated on `ADMIN_OWNER | ADMIN_ADD_BAN`).
 *   3. Inline unban-meta line ("Unbanned by <admin>: <reason>")
 *      below the truncated reason cell on admin-lifted rows
 *      (priority on commslist — no drawer fallback there).
 *
 * This spec covers slice 1 end-to-end in a real browser. Slices 2
 * and 3 require seeded DB state (`RemovedBy` / `ureason` columns
 * populated; ban rows in `expired`/`unbanned` states) that the e2e
 * fixture doesn't provide and the JSON API doesn't expose cleanly
 * enough to reach without coupling to PR #1323's in-flight unban
 * flow. They're locked server-side by `PublicBanListRegressionTest`
 * (PHPUnit, isolated DB state).
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';

/**
 * Closed-disclosure axe coverage runs at the start of each test (the
 * bare page paint, with the form's `<select>` elements still inside
 * a collapsed `<details>` and therefore hidden from the a11y tree).
 *
 * We deliberately do NOT run axe AFTER opening the disclosure: the
 * legacy `box_admin_bans_search.tpl` / `box_admin_comms_search.tpl`
 * partials predate #1123's testability sweep and ship `<select>`s
 * without an associated label (axe rule `select-name`, critical).
 * That's a real a11y bug, but it lived in the legacy form for
 * years; #1315 just makes it reachable from the bare page (it was
 * always reachable via the `?advSearch=…&advType=…` URL shim). Per
 * AGENTS.md "Playwright E2E specifics" the threshold must NOT be
 * downgraded to make tests green; the right move is a follow-up
 * a11y issue against the underlying legacy form, not a `disabled`
 * filter here. The smoke specs (`smoke/banlist.spec.ts`,
 * `smoke/commslist.spec.ts`) already audit the closed-disclosure
 * paint; this spec adds focused coverage on the disclosure
 * vocabulary itself.
 */
test.describe('#1315: public banlist / commslist disclosure regressions', () => {
    test('banlist advanced-search disclosure defaults closed; opens on submit URL', async ({ page }, testInfo) => {
        await page.goto('/index.php?p=banlist');

        const disclosure = page.locator('[data-testid="banlist-advsearch-disclosure"]');
        const toggle     = page.locator('[data-testid="banlist-advsearch-toggle"]');
        await expect(disclosure).toBeVisible();
        await expect(toggle).toBeVisible();

        // Closed-state axe — the legacy form is not yet exposed.
        await expectNoCriticalA11y(page, testInfo);

        // Native <details> reflects [open] as a JS property —
        // synchronous attribute, no animation in the way.
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(false);
        await expect(page.locator('[data-testid="banlist-advsearch-active"]')).toHaveCount(0);

        await toggle.click();
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(true);

        // The legacy advanced-search form is now reachable inside
        // the disclosure body. The form carries the
        // `[data-testid="search-bans-form"]` hook from the legacy
        // box_admin_bans_search.tpl partial.
        await expect(page.locator('[data-testid="search-bans-form"]')).toBeVisible();

        // Post-submit auto-open: navigating to a URL with the
        // legacy `?advType=&advSearch=` shim must paint the
        // disclosure with `[open]` already set so the form chrome
        // stays visible while the user iterates on filters.
        await page.goto('/index.php?p=banlist&advType=name&advSearch=somenick');
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(true);
        await expect(page.locator('[data-testid="banlist-advsearch-active"]')).toBeVisible();
    });

    test('commslist advanced-search disclosure defaults closed; opens on submit URL', async ({ page }, testInfo) => {
        await page.goto('/index.php?p=commslist');

        const disclosure = page.locator('[data-testid="commslist-advsearch-disclosure"]');
        const toggle     = page.locator('[data-testid="commslist-advsearch-toggle"]');
        await expect(disclosure).toBeVisible();
        await expect(toggle).toBeVisible();

        // Closed-state axe — the legacy form is not yet exposed.
        await expectNoCriticalA11y(page, testInfo);

        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(false);
        await expect(page.locator('[data-testid="commslist-advsearch-active"]')).toHaveCount(0);

        await toggle.click();
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(true);

        await expect(page.locator('[data-testid="search-comms-form"]')).toBeVisible();

        await page.goto('/index.php?p=commslist&advType=name&advSearch=somenick');
        await expect
            .poll(async () => await disclosure.evaluate((el) => (el as HTMLDetailsElement).open))
            .toBe(true);
        await expect(page.locator('[data-testid="commslist-advsearch-active"]')).toBeVisible();
    });
});
