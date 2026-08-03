import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Settings (`?p=admin&c=settings`).
 *
 * Pair: `web/pages/admin.settings.php` -> Sbpp\View\AdminSettingsView
 * + `page_admin_settings_settings.tpl` (default `?section=settings`).
 * Section chrome lives in the main sidebar accordion (#1490). The
 * mount marker is content-area (`settings-save`) so smoke specs stay
 * valid on mobile where `#sidebar` is off-canvas until the hamburger
 * opens it.
 */
export class AdminSettingsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=settings';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="settings-save"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
