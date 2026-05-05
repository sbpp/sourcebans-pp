import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public communications-block list (`?p=commslist` →
 * `page.commslist.php` → `page_comms.tpl`, the B4 mirror of B2's
 * marquee redesign).
 *
 * Page-mount marker: `[data-testid="comms-header"]` — the
 * `<header>` element wrapping the H1 + summary copy. It's always
 * rendered (the body has no `?comment=` branch like banlist), sits
 * outside the responsive table/cards swap, and stays visible on
 * both desktop and mobile-chromium viewports.
 *
 * The `[data-testid="comms-table"]` is intentionally not the anchor
 * here: theme.css hides the desktop `<table>` below 769px and lets
 * `.ban-cards` take over, so the testid'd table node is in the DOM
 * but not visible at iPhone-13 — a `toBeVisible` smoke check would
 * flake on the mobile project.
 */
export class CommsListPage extends BasePage {
    /**
     * Navigate to the public comms list. Overload of
     * {@link BasePage.goto} pinned to the canonical query string.
     */
    async goto(): Promise<void> {
        await super.goto('/index.php?p=commslist');
    }

    /** Page-mount marker locator. */
    header(): Locator {
        return this.page.locator('[data-testid="comms-header"]');
    }
}
