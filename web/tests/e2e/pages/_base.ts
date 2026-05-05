import type { Page } from '@playwright/test';

/**
 * Base page object used by every concrete page model under
 * `web/tests/e2e/pages/`.
 *
 * `waitForReady()` waits on the *terminal* attributes the new theme
 * exposes per #1123's "Testability hooks" contract — never on a
 * `setTimeout` or transition. Specifically:
 *
 *   - `[data-loading="true"]` is absent (drawer / palette / inline
 *     fetch surfaces flip this back to `false` once the network call
 *     settles).
 *   - `[data-skeleton]` elements are removed (skeleton placeholders
 *     are torn down when content lands, not visually swapped).
 *
 * If a future slice adds a page that exposes additional terminal
 * attributes (e.g. a chart's "rendered" sentinel), extend the
 * `waitForFunction` predicate here so the contract stays in one place.
 */
export class BasePage {
    constructor(protected readonly page: Page) {}

    async goto(path: string): Promise<void> {
        await this.page.goto(path);
        await this.waitForReady();
    }

    async waitForReady(): Promise<void> {
        await this.page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]'),
        );
    }
}
