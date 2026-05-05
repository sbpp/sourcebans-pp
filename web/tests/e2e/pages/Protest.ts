import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public ban-appeal form (`?p=protest` → `page.protest.php` →
 * `page_protestban.tpl`, the B7 redesign).
 *
 * Note on the route key: the Slice 1 brief listed this as
 * `?p=appeal`, but `web/includes/page-builder.php` only handles
 * `?p=protest` — `?p=appeal` falls through the switch and renders
 * the home dashboard via the fallback. The navbar template emits
 * `data-testid="nav-{$nav.endpoint}"` and the live page serves
 * `data-testid="nav-protest"`, confirming the canonical key is
 * `protest`. This is flagged in the PR description.
 *
 * Page-mount marker: `[data-testid="protest-submit"]` — the
 * primary submit button. Always rendered (the form is gated by
 * `config.enableprotest`, which 200s with a "page disabled" notice
 * if off; the button is part of the body when the form renders),
 * and visible on both desktop and mobile viewports.
 *
 * Per the Slice 1 brief: smoke asserts the submit button is
 * visible only — submission is out of scope for this slice.
 */
export class ProtestPage extends BasePage {
    /**
     * Navigate to the public protest/appeal form. Overload of
     * {@link BasePage.goto} pinned to the canonical query string.
     */
    async goto(): Promise<void> {
        await super.goto('/index.php?p=protest');
    }

    /** Page-mount marker locator. */
    submitButton(): Locator {
        return this.page.locator('[data-testid="protest-submit"]');
    }
}
