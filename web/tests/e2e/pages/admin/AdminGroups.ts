import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Groups (`?p=admin&c=groups`).
 *
 * Pair: `web/pages/admin.groups.php` -> Sbpp\View\AdminGroupsListView
 * + `page_admin_groups_list.tpl`. The list template lays out three
 * sections: web admin groups (master-detail), server admin groups,
 * and server groups. The first section's wrapper exposes
 * `data-testid="web-groups-section"` whenever `$permission_listgroups`
 * is true — that's the mount signal.
 */
export class AdminGroupsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=groups';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="web-groups-section"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
