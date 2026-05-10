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
 * #1275 — Pattern A `?section=…` routing
 * --------------------------------------
 * Pre-#1275 the page rode the page-level ToC (#1207 ADM-3); each
 * "section" was an anchor target in a single long-scroll DOM. #1275
 * collapsed the page onto Pattern A: each section is its own URL
 * (`?section=admins`, `?section=add-admin`, `?section=overrides`),
 * so `tocLink()` / `section()` now both pivot on the standard
 * Pattern A `data-testid="admin-tab-<slug>"` hook. The
 * `data-testid="admin-admins-section-<slug>"` wrappers stay on the
 * rendered section body so cross-section assertions (e.g. "the
 * search form is inside the admins section") still work.
 */
export class AdminAdminsPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=admin&c=admins';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="admin-count"]');
    }

    /** Pattern A — outer page wrapper (now the admin sidebar shell). */
    get shell(): Locator {
        return this.page.locator('[data-testid="admin-sidebar-shell"]');
    }

    /** Pattern A — the vertical sidebar (replaces the page-level ToC). */
    get toc(): Locator {
        return this.page.locator('[data-testid="admin-sidebar"]');
    }

    /** Pattern A — single sidebar link by section slug. */
    tocLink(section: 'admins' | 'add-admin' | 'overrides'): Locator {
        return this.page.locator(`[data-testid="admin-tab-${section}"]`);
    }

    /**
     * The rendered section body. Each section is its own URL after
     * #1275, so on most landings only one section's body is in the
     * DOM — the locator returns whichever section's body is rendered
     * (search lives inside `admins`).
     */
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

    /** #1303 — the `<details>` disclosure wrapping the search form. */
    get searchDisclosure(): Locator {
        return this.page.locator('[data-testid="search-admins-disclosure"]');
    }

    /** #1303 — the `<summary>` toggle that opens / closes the disclosure. */
    get searchToggle(): Locator {
        return this.page.locator('[data-testid="search-admins-toggle"]');
    }

    /** #1303 — the "N active" badge (only present when count > 0). */
    get searchActiveCount(): Locator {
        return this.page.locator('[data-testid="search-admins-active-count"]');
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
