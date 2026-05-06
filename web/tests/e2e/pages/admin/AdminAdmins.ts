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
 *
 * #1207 ADM-3 / ADM-4 hooks
 * -------------------------
 * The page-level ToC and the rebuilt single-submit advanced search
 * box expose stable testids; pin them on the page object so flow
 * specs don't have to rebuild the selector tree.
 */
export class AdminAdminsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=admins';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="admin-count"]');
    }

    /** ADM-3 — outer page wrapper that hosts the sticky ToC sidebar. */
    get shell(): Locator {
        return this.page.locator('[data-testid="admin-admins-shell"]');
    }

    /** ADM-3 — the page-level ToC (anchor sidebar / accordion). */
    get toc(): Locator {
        return this.page.locator('[data-testid="admin-admins-toc"]');
    }

    /** ADM-3 — single ToC link by section key. */
    tocLink(section: 'search' | 'admins' | 'add-admin' | 'overrides' | 'add-override'): Locator {
        return this.page.locator(`[data-testid="admin-admins-toc-link-${section}"]`);
    }

    /** ADM-3 — anchored section by key (matches the link slugs above). */
    section(section: 'search' | 'admins' | 'add-admin' | 'overrides' | 'add-override'): Locator {
        return this.page.locator(`[data-testid="admin-admins-section-${section}"]`);
    }

    /** ADM-4 — the (single) advanced-search form. */
    get searchForm(): Locator {
        return this.page.locator('[data-testid="search-admins-form"]');
    }

    /** ADM-4 — the (single) submit button at the bottom of the form. */
    get searchSubmit(): Locator {
        return this.page.locator('[data-testid="search-admins-submit"]');
    }

    /** ADM-4 — the "Clear filters" reset link. */
    get searchReset(): Locator {
        return this.page.locator('[data-testid="search-admins-reset"]');
    }

    /** ADM-4 — pre-filled inputs by name. */
    searchInput(field: 'name' | 'steamid' | 'admemail' | 'webgroup' | 'srvadmgroup' | 'srvgroup' | 'admwebflag' | 'admsrvflag' | 'server'): Locator {
        return this.page.locator(`[data-testid="search-admins-${field}"]`);
    }

    /** Per-row admin entries in the table — count via `.count()`. */
    get adminRows(): Locator {
        return this.page.locator('[data-testid="admin-row"]');
    }

    /** "(N)" headcount span next to the Admins H1. */
    get adminCount(): Locator {
        return this.page.locator('[data-testid="admin-count"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }
}
