import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public ban list (`?p=banlist` → `page.banlist.php` →
 * `page_bans.tpl` — the marquee redesign from #1123 B2).
 *
 * Page-mount marker: `[data-testid="bans-search"]` — the search
 * input inside the sticky filter bar (`#banlist-filters`). It's
 * always rendered on the listing branch (the `?comment=N` flow is
 * the only branch that swaps the body, and smoke never hits it),
 * sits inside `#banlist-root`, and stays visible on both desktop and
 * mobile-chromium viewports because the form is `position: sticky`,
 * not display-toggled by the responsive cutover.
 *
 * Why not `[data-testid="ban-row"]` (also stable in page_bans.tpl):
 * row testids only exist when the result set is non-empty, and the
 * smoke run starts against the freshly-truncated `sourcebans_e2e`
 * DB (admin-only seed; zero bans). The `data-testid="banlist-table"`
 * the issue suggested isn't on the desktop `<table>` yet (comms-table
 * has its sibling testid; banlist's table doesn't), and even if we
 * added it, theme.css hides the desktop table below 769px so it
 * wouldn't `toBeVisible` on mobile-chromium.
 */
export class BanListPage extends BasePage {
    /**
     * Navigate to the public ban list. Overload of
     * {@link BasePage.goto} pinned to the canonical query string.
     */
    async goto(): Promise<void> {
        await super.goto('/index.php?p=banlist');
    }

    /** Page-mount marker locator. */
    searchInput(): Locator {
        return this.page.locator('[data-testid="bans-search"]');
    }
}
