import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public dashboard (`?p=home`, served at the bare `/` path via the
 * page-builder fallback in `web/includes/page-builder.php`).
 *
 * Page-mount marker: `[data-testid="dashboard-header"]` — the header
 * `<header>` wrapping the H1 + intro copy in `page_dashboard.tpl`.
 * It's always rendered for both authenticated and anonymous users
 * (the View carries `dashboard_title` / `dashboard_text` regardless of
 * session) and sits in the main content area, so it's visible on both
 * the desktop chromium and mobile-chromium iPhone-13 viewports.
 *
 * Why this anchor and not e.g. `dashboard-stat-total-bans` (also a
 * stable testid in page_dashboard.tpl): the stat card grid's contents
 * depend on whether anything has been seeded; `dashboard-header` is
 * a structural element that exists on every render, including the
 * empty-DB smoke run. The `[data-testid]` is the primary selector per
 * AGENTS.md "Playwright E2E specifics" — never CSS class chains, never
 * visible text as the primary anchor.
 */
export class DashboardPage extends BasePage {
    /**
     * Navigate to the dashboard. Overload of {@link BasePage.goto}
     * that doesn't take a path — the dashboard is the bare `/`
     * route (page.home.php is the fallback handler).
     */
    async goto(): Promise<void> {
        await super.goto('/');
    }

    /** Page-mount marker locator. */
    header(): Locator {
        return this.page.locator('[data-testid="dashboard-header"]');
    }
}
