import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Admins (`?p=admin&c=admins`).
 *
 * Pair: `web/pages/admin.admins.php` -> Sbpp\View\AdminAdminsListView
 * + `page_admin_admins_list.tpl`. The list view is gated on
 * `$can_list_admins`; the seeded admin holds OWNER so the `<span
 * data-testid="admin-count">` lands unconditionally — that's the
 * stable terminal mark we wait on.
 */
export class AdminAdminsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=admins';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="admin-count"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
