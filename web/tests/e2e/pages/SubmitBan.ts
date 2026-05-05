import { type Locator } from '@playwright/test';

import { BasePage } from './_base.ts';

/**
 * Public "Submit a ban / Report a player" form (`?p=submit` →
 * `page.submit.php` → `page_submitban.tpl`, the B6 redesign).
 *
 * Page-mount marker: `[data-testid="submitban-submit"]` — the
 * primary submit button at the bottom of the form card. The button
 * is unconditionally rendered (no perm gate; the form itself is
 * gated upstream by `config.enablesubmit`, and a disabled flag would
 * 403 the route, not hide the button), and stays visible on both
 * desktop and mobile.
 *
 * Per the Slice 1 brief: smoke specs assert the submit button is
 * visible only — they do NOT submit the form. Submission flows
 * (anonymous + admin moderation round-trip) are Slice 3
 * (`flow-public-submission`).
 */
export class SubmitBanPage extends BasePage {
    /**
     * Navigate to the public submit form. Overload of
     * {@link BasePage.goto} pinned to the canonical query string.
     */
    async goto(): Promise<void> {
        await super.goto('/index.php?p=submit');
    }

    /** Page-mount marker locator. */
    submitButton(): Locator {
        return this.page.locator('[data-testid="submitban-submit"]');
    }
}
