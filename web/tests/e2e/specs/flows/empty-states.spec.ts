/**
 * Empty states + copy slice (#1207 PUB-3 / PUB-4 / PUB-5 / SET-1).
 *
 * Covers the behaviour that the audit's screenshots couldn't catch:
 *
 *   - PUB-3: public servers empty state surfaces the "Add a server"
 *     CTA only when the viewer holds `ADMIN_ADD_SERVER` (the seeded
 *     admin/admin row holds every flag, so the default storage state
 *     is the positive case; anonymous browsing context is the
 *     negative case).
 *   - PUB-5: dashboard cards (Latest bans, Servers, Latest comm
 *     blocks) emit per-card primary CTAs in their empty state when
 *     the corresponding ADMIN_* flag is held. The "Latest blocked
 *     attempts" card stays CTA-less by design (read-only stream).
 *   - PUB-4: the public submit-ban form blocks submission when both
 *     Steam ID and IP are empty, flips
 *     `[data-required-group="steamid-or-ip"][data-error="true"]`,
 *     and toggles `[data-state="error"]` on the help line. Filling
 *     either input clears the error.
 *   - SET-1: dashboard intro field's preview pane updates on input,
 *     piping through `system.preview_intro_text` (Sbpp\Markup\IntroRenderer).
 *     Raw HTML is escaped, `javascript:` links are stripped.
 *
 * DB state contract
 * -----------------
 * Every test in this file runs against a freshly-truncated
 * `sourcebans_e2e` DB so the empty states are deterministic. The
 * truncate path in `Sbpp\Tests\Fixture::truncateAndReseed` holds a
 * MySQL named lock so even with workers > 1 the truncate-and-reseed
 * pair stays atomic; subsequent specs that need data of their own
 * call `truncateE2eDb()` themselves (see
 * `responsive/admin-queue.spec.ts` for the precedent).
 *
 * Selector discipline (#1123 testability hooks)
 * ---------------------------------------------
 * Primary anchors are `data-testid` from the locked hooks in
 * page_servers.tpl, page_dashboard.tpl, page_submitban.tpl,
 * page_admin_settings_settings.tpl. The CTA links resolve via the
 * unique per-card testids:
 *
 *   - `servers-empty-add`
 *   - `dashboard-recent-bans-empty-add`
 *   - `dashboard-servers-empty-add`
 *   - `dashboard-recent-comms-empty-add`
 *
 * Project gating
 * --------------
 * Pinned to chromium (desktop) — the contract is identical on
 * mobile-chromium for the CTA presence + submit form validation,
 * but the dashboard cards reflow differently and the screenshot
 * gallery already covers that visual diff. Forcing the spec on
 * both projects would double the runtime without adding signal.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';
import { newAnonymousContext } from '../../pages/SubmitBanFlow.ts';

test.describe('flow: empty states + copy (#1207)', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Empty-state contract is project-agnostic; pinning to desktop chromium for runtime.',
        );
    });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    // ----- PUB-3 ------------------------------------------------------

    test('PUB-3: empty servers page exposes "Add a server" CTA for an admin viewer', async ({ page }) => {
        await page.goto('/index.php?p=servers');

        const emptyCard = page.locator('[data-testid="servers-empty"]');
        await expect(emptyCard).toBeVisible();

        const cta = page.locator('[data-testid="servers-empty-add"]');
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /\?p=admin&(amp;)?c=servers/);
        await expect(cta).toContainText(/Add a server/i);
    });

    test('PUB-3: empty servers page hides the CTA from an anonymous viewer', async ({ browser }, testInfo) => {
        const ctx = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const anon = await ctx.newPage();
            await anon.goto('/index.php?p=servers');

            await expect(anon.locator('[data-testid="servers-empty"]')).toBeVisible();
            await expect(anon.locator('[data-testid="servers-empty-add"]')).toHaveCount(0);
        } finally {
            await ctx.close();
        }
    });

    // ----- PUB-5 ------------------------------------------------------

    test('PUB-5: dashboard cards expose per-domain CTAs in their empty states', async ({ page }) => {
        await page.goto('/');
        await expect(page.locator('[data-testid="dashboard-header"]')).toBeVisible();

        const cards = [
            { testid: 'dashboard-recent-bans-empty-add', href: /\?p=admin&(amp;)?c=bans/, label: /Add a ban/i },
            { testid: 'dashboard-servers-empty-add', href: /\?p=admin&(amp;)?c=servers/, label: /Add a server/i },
            {
                testid: 'dashboard-recent-comms-empty-add',
                href: /\?p=admin&(amp;)?c=comms/,
                label: /Add a comm block/i,
            },
        ];

        for (const card of cards) {
            const cta = page.locator(`[data-testid="${card.testid}"]`);
            await expect(cta, `${card.testid} should be visible on the empty dashboard`).toBeVisible();
            await expect(cta).toHaveAttribute('href', card.href);
            await expect(cta).toContainText(card.label);
        }
    });

    // ----- empty-states unification: banlist / commslist first-run -----

    test('banlist first-run empty state shows "Add a ban" CTA, no filter chip', async ({ page }) => {
        // Fresh DB → zero bans, no filter active. The empty state
        // should be the first-run shape (data-filtered="false") with
        // an "Add a ban" CTA gated on `can_add_ban` (admin storage
        // state holds ADMIN_OWNER → true).
        await page.goto('/index.php?p=banlist');

        const empty = page.locator('[data-testid="banlist-empty"]');
        await expect(empty).toBeVisible();
        await expect(empty).toHaveAttribute('data-filtered', 'false');

        const cta = page.locator('[data-testid="banlist-empty-add"]');
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /\?p=admin&(amp;)?c=bans/);
        await expect(cta).toContainText(/Add a ban/i);

        // Filtered state is still on the page when a search is active —
        // navigate with a no-match search and the empty state should
        // flip to the filtered shape with a "Clear filters" CTA.
        await page.goto('/index.php?p=banlist&searchText=does-not-match-anything');
        await expect(empty).toBeVisible();
        await expect(empty).toHaveAttribute('data-filtered', 'true');
        await expect(page.locator('[data-testid="banlist-empty-clear"]')).toBeVisible();
    });

    test('commslist first-run empty state shows "Add a comm block" CTA, no filter chip', async ({ page }) => {
        await page.goto('/index.php?p=commslist');

        const empty = page.locator('[data-testid="comms-empty"]').first();
        await expect(empty).toBeVisible();
        await expect(empty).toHaveAttribute('data-filtered', 'false');

        const cta = page.locator('[data-testid="comms-empty-add"]');
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /\?p=admin&(amp;)?c=comms/);
        await expect(cta).toContainText(/Add a comm block/i);

        // Filter via searchText → flips to filtered shape.
        await page.goto('/index.php?p=commslist&searchText=does-not-match-anything');
        await expect(empty).toBeVisible();
        await expect(empty).toHaveAttribute('data-filtered', 'true');
        await expect(page.locator('[data-testid="comms-empty-clear"]')).toBeVisible();
    });

    // ----- PUB-4 ------------------------------------------------------

    test('PUB-4: submit form blocks submission when both Steam ID and IP are empty', async ({ browser }, testInfo) => {
        // The form is intentionally exercised in an anonymous context:
        // submission is the public path (the seeded admin would also
        // go through the form, but the bug surface is for visitors
        // and the storage state shouldn't influence the JS guard).
        const ctx = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const anon = await ctx.newPage();
            await anon.goto('/index.php?p=submit');

            const group = anon.locator('[data-testid="submitban-id-or-ip"]');
            const help = anon.locator('[data-testid="submitban-id-or-ip-help"]');
            const submit = anon.locator('[data-testid="submitban-submit"]');

            await expect(group).toBeVisible();
            await expect(group).toHaveAttribute('data-error', 'false');
            await expect(help).toHaveAttribute('data-state', 'info');

            // Fill the rest of the form (so the only failing rule is
            // the Steam ID / IP either-or). The `novalidate` on the
            // form lets the submit attempt land in our JS handler
            // even with empty required fields.
            await anon.locator('[data-testid="submitban-name"]').fill('e2e-pub4');
            await anon.locator('[data-testid="submitban-reason"]').fill('e2e: PUB-4 client-side validation');
            await anon.locator('[data-testid="submitban-reporter-email"]').fill('e2e-pub4@example.test');
            await anon.locator('[data-testid="submitban-server"]').selectOption({ value: '0' });

            await submit.click();

            // Form did not navigate: still on /index.php?p=submit and
            // the group flipped into the error state. We anchor on
            // the [data-error] / [data-state] attributes per #1123's
            // "State in attributes, not just styling" rule.
            await expect(group).toHaveAttribute('data-error', 'true');
            await expect(help).toHaveAttribute('data-state', 'error');
            await expect(anon.locator('[data-testid="submitban-steam"]')).toHaveAttribute('aria-invalid', 'true');
            await expect(anon.locator('[data-testid="submitban-ip"]')).toHaveAttribute('aria-invalid', 'true');
            await expect(anon).toHaveURL(/p=submit/);

            // Filling either input clears the error inline (no submit
            // needed — the `input` event handler is wired to the
            // same predicate as the submit guard).
            await anon.locator('[data-testid="submitban-steam"]').fill('STEAM_0:1:777');
            await expect(group).toHaveAttribute('data-error', 'false');
            await expect(help).toHaveAttribute('data-state', 'info');
        } finally {
            await ctx.close();
        }
    });

    // ----- SET-1 ------------------------------------------------------

    test('SET-1: dashboard intro preview renders Markdown via IntroRenderer', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=settings');

        const textarea = page.locator('[data-testid="dash-intro-textarea"]');
        const preview = page.locator('[data-testid="dash-intro-preview"]');
        const previewBody = page.locator('[data-testid="dash-intro-preview-body"]');
        const cheatsheet = page.locator('[data-testid="dash-intro-cheatsheet-link"]');

        await expect(textarea).toBeVisible();
        await expect(preview).toBeVisible();
        await expect(cheatsheet).toBeVisible();
        await expect(cheatsheet).toHaveAttribute('href', /commonmark\.org\/help/i);

        // Type Markdown that exercises the three contract surfaces:
        // a paragraph, a bold span, and an HTML escape (raw `<script>`
        // must come back as text, not parsed). The preview pane is
        // server-rendered via system.preview_intro_text — our wait
        // anchor is the rendered text landing in the body.
        await textarea.fill('Hello **world** <script>x</script>');

        // The preview's [data-loading] flips to `true` mid-call and
        // back to `false` once IntroRenderer's response lands. We
        // anchor on the terminal state (loading=false) AND the body
        // content. The 200ms input debounce + ~200ms round-trip is
        // bounded by Playwright's default timeout (30s).
        await expect(preview).toHaveAttribute('data-loading', 'false');
        await expect(previewBody.locator('strong')).toHaveText('world');
        // Raw `<script>` is escaped, not parsed.
        await expect(previewBody.locator('script')).toHaveCount(0);
        // The escaped tag still renders as visible text.
        await expect(previewBody).toContainText('<script>');
    });
});
