import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public servers list (`?p=servers` → `page.servers.php` →
 * `page_servers.tpl`, the B5 redesign).
 *
 * Page-mount marker: `[data-testid="servers-summary"]` — the
 * server-count copy (`{$server_list|@count} configured`) inside the
 * page header. It's always rendered, sits outside the
 * `{if $server_list|@count == 0}` empty-state guard, and stays
 * visible on both desktop and mobile viewports.
 *
 * Why not `[data-testid="servers-list"]`: that one only renders
 * when `$server_list|@count > 0`. Smoke runs against an empty seed
 * by default (servers are an opt-in dev seed; the e2e DB only seeds
 * the admin row), so the list element wouldn't exist.
 *
 * Why not `[data-testid="server-tile"]`: same reason — only present
 * when at least one server is configured.
 */
export class ServerListPage extends BasePage {
    /**
     * Navigate to the public servers list. Overload of
     * {@link BasePage.goto} pinned to the canonical query string.
     */
    async goto(): Promise<void> {
        await super.goto('/index.php?p=servers');
    }

    /** Page-mount marker locator. */
    summary(): Locator {
        return this.page.locator('[data-testid="servers-summary"]');
    }
}
