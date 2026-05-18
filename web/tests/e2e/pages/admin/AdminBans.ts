import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Bans (`?p=admin&c=bans`).
 *
 * Pair: `web/pages/admin.bans.php` is Pattern A after #1275 — each
 * pane (Add a ban, Ban protests, Ban submissions, Import bans, Group
 * ban) is its own `?section=…` URL with its own server render. The
 * bare `?p=admin&c=bans` URL defaults to `add-ban` (the first
 * accessible section), so this page object's `goto()` lands on the
 * Add-ban form unconditionally for the seeded admin.
 *
 * The "Add a ban" tab is gated on `ADMIN_OWNER | ADMIN_ADD_BAN`. The
 * seeded admin holds OWNER so the form's `addban-section` testid is
 * always present — that's the page-mounted signal.
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
