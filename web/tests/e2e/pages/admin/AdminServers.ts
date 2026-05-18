import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Admin > Servers list (`?p=admin&c=servers&section=list`).
 *
 * Pair: `web/pages/admin.servers.php` -> Sbpp\View\AdminServersListView
 * + `page_admin_servers_list.tpl`. Pattern A `?section=` route per
 * #1239 / #1259 / #1275; the list is the default section so a bare
 * `?p=admin&c=servers` URL also lands here.
 *
 * Page-mount marker: `[data-testid="server-list-section"]` — the
 * outer `<section>` wrapper, always rendered when the user holds
 * `ADMIN_LIST_SERVERS` (the seeded admin holds OWNER, so it's
 * unconditional in the e2e environment). It sits above the
 * `{if $server_count == 0}` empty-state branch and the populated
 * `[data-testid="server-grid"]`, so it stays a deterministic
 * anchor regardless of how many servers exist.
 *
 * Why not `[data-testid="server-grid"]`: that one only renders when
 * `$server_count > 0`. The e2e DB is reset between specs and the
 * default seed has zero servers, so the grid wouldn't exist on a
 * fresh run.
 *
 * Why not `[data-testid="server-tile"]`: same reason — only present
 * when at least one server is configured. Specs that need to
 * exercise the hydration helper must seed a row first via the JSON
 * API (`servers.add`).
 */
export class AdminServersPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=servers&section=list';

    /** Page-mount marker locator. */
    get pageMounted(): Locator {
        return this.page.locator('[data-testid="server-list-section"]');
    }

    /**
     * The shared hydration helper script. Specs that lock the
     * "the script must be wired in" contract assert this <script src=>
     * is in the DOM (#1313). The locator counts hidden DOM nodes too
     * because <script> tags are non-rendering by default.
     */
    hydrationScript(): Locator {
        return this.page.locator('script[src$="server-tile-hydrate.js"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
