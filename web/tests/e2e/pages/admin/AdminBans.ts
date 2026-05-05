import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Bans (`?p=admin&c=bans`).
 *
 * Pair: `web/pages/admin.bans.php` renders `core/admin_tabs.tpl`
 * followed by every accessible tab content (Add a ban, Ban protests,
 * Ban submissions, …). Each tab block is a separate Renderer::render
 * call against its own View; see admin.bans.php for the full list.
 *
 * The "Add a ban" tab is the first one in the AdminTabs constructor
 * and is gated on `ADMIN_OWNER | ADMIN_ADD_BAN`. The seeded admin
 * holds OWNER so the form's `addban-section` testid is always
 * present — that's the page-mounted signal.
 */
export class AdminBansPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=bans';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="addban-section"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
