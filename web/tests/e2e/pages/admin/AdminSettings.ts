import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Settings (`?p=admin&c=settings`).
 *
 * Pair: `web/pages/admin.settings.php` -> Sbpp\View\AdminSettingsView
 * + `page_admin_settings_settings.tpl` (default `?section=settings`).
 * #1259 unified the sidebar chrome on `core/admin_sidebar.tpl` so the
 * Settings page now exposes its sub-nav under the same testid shape
 * every Pattern A admin route uses (`admin-tab-<slug>`); the sidebar
 * itself sits outside the `$can_web_settings` gate, so this selector
 * remains valid even on the access-denied fallback.
 */
export class AdminSettingsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=settings';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="admin-tab-settings"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
