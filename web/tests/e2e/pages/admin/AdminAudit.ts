import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Audit log (`?p=admin&c=audit`).
 *
 * Pair: `web/pages/admin.audit.php` -> Sbpp\View\AuditLogView +
 * `page_admin_audit.tpl`. The page is gated on `ADMIN_OWNER` (the
 * router enforces `CheckAdminAccess(ADMIN_OWNER)` for `c=audit`); the
 * seeded admin holds OWNER, so the search + filter chips render
 * unconditionally. We pin on `audit-search` (the search input) which
 * exists regardless of how many log rows the queue currently holds.
 */
export class AdminAuditPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=audit';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="audit-search"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
