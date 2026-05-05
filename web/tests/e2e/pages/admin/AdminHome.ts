import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin landing page (`?p=admin`, no `c=`).
 *
 * Pair: `web/pages/page.admin.php` -> `web/themes/default/page_admin.tpl`.
 *
 * The seeded admin holds `ADMIN_OWNER`, so every `$can_<area>` boolean
 * the View precomputes resolves to true and every `admin-card-*` tile
 * renders. We pin on `admin-card-bans` because the Bans tile is the
 * area an admin most consistently has access to (anyone with even a
 * single ADMIN_*BAN flag sees it), so the same selector keeps working
 * if a future slice runs this spec under a non-OWNER fixture.
 */
export class AdminHomePage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="admin-card-bans"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
