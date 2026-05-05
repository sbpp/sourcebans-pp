/**
 * Smoke spec for the public submit-ban form
 * (`/index.php?p=submit`).
 *
 * Asserts:
 *
 *   1. Page mounts — `[data-testid="submitban-submit"]` is
 *      visible. Per Slice 1 brief, smoke does NOT submit the form;
 *      submission round-trips (anonymous submit → admin moderation
 *      → public banlist) are Slice 3.
 *   2. No JS errors land in the console.
 *   3. Zero critical-impact axe violations.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { SubmitBanPage } from '../../pages/SubmitBan.ts';

test.describe('smoke /submit', () => {
    test('mounts without console errors and 0 critical a11y violations', async ({ page }, testInfo) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        const submit = new SubmitBanPage(page);
        await submit.goto();
        await expect(submit.submitButton()).toBeVisible();

        await expectNoCriticalA11y(page, testInfo);

        expect(
            consoleErrors,
            `console errors:\n${consoleErrors.join('\n')}`,
        ).toEqual([]);
    });
});
