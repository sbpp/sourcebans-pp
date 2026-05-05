/**
 * Smoke spec for the public protest/appeal form
 * (`/index.php?p=protest`).
 *
 * Asserts:
 *
 *   1. Page mounts — `[data-testid="protest-submit"]` is visible.
 *      Per Slice 1 brief, smoke does NOT submit the form.
 *   2. No JS errors land in the console.
 *   3. Zero critical-impact axe violations.
 *
 * Note on the route key: the Slice 1 brief listed this as
 * `?p=appeal`, but `web/includes/page-builder.php` only handles
 * `?p=protest` — `?p=appeal` falls through to the home dashboard.
 * The navbar's `data-testid="nav-protest"` confirms `protest` is
 * the canonical key. See `pages/Protest.ts` for the same note.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { ProtestPage } from '../../pages/Protest.ts';

test.describe('smoke /protest', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const protest = new ProtestPage(page);
        await protest.goto();
        await expect(protest.submitButton()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
