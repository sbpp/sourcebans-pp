/**
 * Announcement-strip end-to-end coverage (#announcements-feed).
 *
 * The dashboard renders an admin-only "Latest announcement" strip
 * sourced from the daily fetch into `SB_CACHE/announcements.json`
 * (see `Sbpp\Announce\AnnouncementFetcher`). This spec drives the
 * strip with a deterministic seeded cache rather than the real
 * https://sbpp.github.io/announcements.json round-trip — the
 * upstream is shipped via the docs deploy chain (covered separately
 * in CI), and the contract under test here is everything DOWNSTREAM
 * of the cache file: the strip mounts, the disclosure expands, the
 * body renders the IntroRenderer-rendered HTML, the external link
 * carries `rel="noopener noreferrer"`, and the surface is
 * a11y-clean.
 *
 * The cache file is shared with the live panel inside the dev
 * container (`SB_CACHE = <panel-root>/cache/`), so the per-spec
 * setup uses `seedAnnouncementsE2e` to write a known payload before
 * each test and `clearAnnouncementsCacheE2e` after to keep the
 * surface clean for sibling specs.
 */
import { test, expect } from '../../fixtures/auth.ts';
import {
    seedAnnouncementsE2e,
    clearAnnouncementsCacheE2e,
} from '../../fixtures/db.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';

// Every test in this file mutates the SAME `SB_CACHE/announcements.json`
// file (the dev container has one panel root, one cache dir). With the
// suite's default `fullyParallel: true`, sibling tests in this file run
// across multiple workers and race against each other's `beforeEach`
// seed + `afterEach` clear:
//
//   - Worker A's `renders the strip` test seeds the cache, navigates,
//     and expects the strip to be visible.
//   - Worker B's `strip is absent when the cache is cleared mid-test`
//     test seeds, clears, and expects no strip.
//
// If B's clear lands between A's seed and A's page load, A renders an
// empty strip and the test fails. File-scope `mode: 'serial'` pins all
// tests in this file to one worker so the cache file only ever has one
// owner at a time. Other specs continue to run in parallel — only this
// file's intra-file ordering is constrained. Same shape `_screenshots.spec.ts`
// uses for the per-route DB seed/restore loop.
test.describe.configure({ mode: 'serial' });

test.describe('dashboard announcement strip', () => {
    test.beforeEach(async () => {
        await seedAnnouncementsE2e([
            {
                id: 'e2e-rc1',
                title: 'v2.0.0 RC1 is now available',
                body_md:
                    'This is the **release candidate**. ' +
                    'Read the [release notes](https://example.com/notes).',
                url: 'https://example.com/release-notes',
                published_at: '2026-05-15T00:00:00Z',
            },
        ]);
    });

    test.afterEach(async () => {
        await clearAnnouncementsCacheE2e();
    });

    test('renders the strip with title, body, and external link', async ({ page }, testInfo) => {
        await page.goto('/index.php?p=home');

        // The strip's outer container is `[data-testid="dashboard-announcement"]`,
        // an `<aside>` with `aria-label="Latest announcement"`. Always
        // present in the DOM when the cache is populated and the user
        // is admin (the storage state minted in global-setup is the
        // seeded admin/admin row).
        const strip = page.locator('[data-testid="dashboard-announcement"]');
        await expect(strip).toBeVisible();

        // Title is rendered inside `<summary>` so it's visible at the
        // collapsed-by-default state. We assert by visible-text-FILTER
        // (the testid is the primary selector per AGENTS.md).
        await expect(strip).toContainText('v2.0.0 RC1 is now available');
        await expect(
            strip.locator('[data-testid="dashboard-announcement-date"]'),
        ).toHaveText('2026-05-15');

        // Body is hidden until the user clicks the summary. Click the
        // <summary> to expand the disclosure, then assert the
        // IntroRenderer-rendered HTML is present.
        const body = strip.locator('[data-testid="dashboard-announcement-body"]');
        // <summary> isn't a button per HTML semantics; click it
        // directly via the role-or-locator chain.
        await strip.locator('summary').click();
        await expect(body).toBeVisible();

        // Markdown was rendered to HTML by IntroRenderer (CommonMark).
        // The `**release candidate**` source becomes `<strong>` in the
        // body's inner HTML — pin the rendered shape.
        const bodyHtml = await body.innerHTML();
        expect(bodyHtml).toContain('<strong>release candidate</strong>');
        // The Markdown link `[release notes](https://example.com/notes)`
        // becomes a real <a>; that's the IntroRenderer integration
        // contract.
        await expect(
            body.getByRole('link', { name: 'release notes' }),
        ).toHaveAttribute('href', 'https://example.com/notes');

        // External "Read more" link must carry `noopener noreferrer`
        // — opening untrusted upstream URLs in a new tab without
        // these is the textbook reverse-tabnabbing surface the strip
        // is gated against.
        const externalLink = strip.locator('[data-testid="dashboard-announcement-link"]');
        await expect(externalLink).toBeVisible();
        await expect(externalLink).toHaveAttribute('href', 'https://example.com/release-notes');
        await expect(externalLink).toHaveAttribute('target', '_blank');
        await expect(externalLink).toHaveAttribute('rel', /\bnoopener\b/);
        await expect(externalLink).toHaveAttribute('rel', /\bnoreferrer\b/);

        // axe-core gate: no critical violations on the rendered surface.
        await expectNoCriticalA11y(page, testInfo);
    });

    test('strip is absent when the cache is cleared mid-test', async ({ page }) => {
        // Confirm the strip stops rendering once the cache is wiped.
        // This is the cold-install scenario — first dashboard render
        // before the shutdown hook lands the cache.
        await clearAnnouncementsCacheE2e();
        await page.goto('/index.php?p=home');
        await expect(
            page.locator('[data-testid="dashboard-announcement"]'),
        ).toHaveCount(0);
    });
});

test.describe('dashboard announcement strip (anonymous)', () => {
    // Storage state override: log out for this whole block. The
    // anonymous landing page is the canonical "untrusted visitor"
    // surface; the announcement is admin-only and must NEVER paint
    // for them.
    test.use({ storageState: { cookies: [], origins: [] } });

    test.beforeEach(async () => {
        await seedAnnouncementsE2e([
            {
                id: 'e2e-anon',
                title: 'Should not be visible to anonymous',
                published_at: '2026-05-15T00:00:00Z',
            },
        ]);
    });

    test.afterEach(async () => {
        await clearAnnouncementsCacheE2e();
    });

    test('strip is hidden from anonymous visitors', async ({ page }) => {
        await page.goto('/index.php?p=home');

        // The strip element must not exist in the DOM at all — not
        // hidden via CSS, not rendered with an empty body. The
        // server-side gate (`$userbank->is_admin()` in
        // `web/pages/page.home.php`) is load-bearing.
        await expect(
            page.locator('[data-testid="dashboard-announcement"]'),
        ).toHaveCount(0);
    });
});
