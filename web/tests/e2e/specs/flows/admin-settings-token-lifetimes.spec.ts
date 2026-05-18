/**
 * Flow: admin settings token-lifetime inputs (#1207 ADM-7).
 *
 * Asserts the regression the audit caught — the "Default / Remember
 * me / Steam login" inputs on the Authentication card used to render
 * as a 3-column grid where:
 *   - The input boxes were narrower than their labels at desktop, and
 *   - On mobile the help text ("Token lifetimes (in minutes)") stayed
 *     glued to the card header instead of attaching to each input.
 *
 * The fix in `web/themes/default/page_admin_settings_settings.tpl`
 * replaces the card-header chrome with a `<fieldset>` + `<legend>`,
 * stacks the three inputs vertically (one per row, label above input,
 * inline help text below the input), and ties each help paragraph to
 * its input via `aria-describedby`. The vertical stack is the same
 * shape on desktop and mobile, so the help-text-detached bug at
 * <=768px goes away too.
 *
 * Selector discipline (#1123 testability hooks)
 * ---------------------------------------------
 * Anchored on the locked hooks already in
 * `page_admin_settings_settings.tpl`:
 *   - `[data-testid="settings-token-lifetimes"]` — the fieldset.
 *   - `[data-testid="setting-row"][data-key="auth.maxlife*"]` — the
 *     three rows, addressable by their persisted setting key.
 *   - `[data-testid="setting-help-auth.maxlife*"]` — the help
 *     paragraphs, asserting they're rendered inline next to each
 *     input (NOT just on the card header).
 *   - `aria-describedby` — the input → help-paragraph relationship,
 *     asserting screen readers will announce the explanation as the
 *     input's description.
 *
 * The spec runs on both project profiles (chromium + mobile-chromium)
 * because the contract — vertical stack, inline help, no header-only
 * help — is identical at every viewport width (the regression was
 * specifically that the fix had to work BOTH on desktop AND mobile,
 * and the previous shape failed differently in each).
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';

test.describe('flow: admin settings token-lifetime inputs (#1207 ADM-7)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/index.php?p=admin&c=settings&section=settings');
        await page.waitForFunction(
            () => !document.querySelector('[data-loading="true"], [data-skeleton]:not([hidden])'),
        );
    });

    test('three token-lifetime inputs render vertically, each with its own help paragraph', async ({ page }) => {
        const fieldset = page.locator('[data-testid="settings-token-lifetimes"]');
        await expect(fieldset).toBeVisible();
        // The fieldset replaces the card-level h3 — verify the
        // `<legend>` is the form group's a11y label and the section
        // heading no longer lives on the card header. The legend's
        // text must include both the section title ("Authentication")
        // and the unit explanation ("minutes"); this is the contract
        // the audit's screenshot regressed against.
        const legend = fieldset.locator('legend');
        await expect(legend).toContainText(/Authentication/);
        await expect(legend).toContainText(/minutes/i);

        const keys = ['auth.maxlife', 'auth.maxlife.remember', 'auth.maxlife.steam'];
        for (const key of keys) {
            const row = fieldset.locator(`[data-testid="setting-row"][data-key="${key}"]`);
            await expect(row, `setting-row for ${key} must exist`).toBeVisible();

            const input = row.locator('input[type="number"]');
            const help  = row.locator(`[data-testid="setting-help-${key}"]`);
            await expect(input).toBeVisible();
            await expect(help, `inline help paragraph for ${key} must be visible next to its input`).toBeVisible();

            // a11y wiring: aria-describedby must point at the help
            // paragraph's id so SR users hear the explanation as the
            // input's description (not as orphaned prose elsewhere).
            const describedBy = await input.getAttribute('aria-describedby');
            const helpId      = await help.getAttribute('id');
            expect(describedBy, `${key} input must wire aria-describedby`).not.toBeNull();
            expect(helpId,      `${key} help must carry an id`).not.toBeNull();
            expect(describedBy).toBe(helpId);
        }
    });

    test('help paragraphs sit BELOW their inputs (vertical stack, not a horizontal row)', async ({ page }) => {
        // The pre-fix shape had the help text on the card header;
        // even when the inputs collapsed to one column on mobile, the
        // copy stayed at the top of the card. The fix puts each help
        // paragraph immediately under its input, so for every row the
        // help's top must be greater than the input's bottom (>=, not
        // ==, because layout subpixels can reshuffle on a fractional
        // device-pixel-ratio).
        const keys = ['auth.maxlife', 'auth.maxlife.remember', 'auth.maxlife.steam'];
        for (const key of keys) {
            const row = page.locator(`[data-testid="setting-row"][data-key="${key}"]`);
            const inputBox = await row.locator('input[type="number"]').boundingBox();
            const helpBox  = await row.locator(`[data-testid="setting-help-${key}"]`).boundingBox();
            expect(inputBox, `${key} input must have a layout box`).not.toBeNull();
            expect(helpBox,  `${key} help must have a layout box`).not.toBeNull();
            if (!inputBox || !helpBox) return;
            expect(
                helpBox.y,
                `${key} help paragraph must sit below its input (input bottom=${inputBox.y + inputBox.height}, help top=${helpBox.y})`,
            ).toBeGreaterThanOrEqual(inputBox.y + inputBox.height - 1);
        }
    });

    test('inputs stack vertically — three rows in a single column', async ({ page }) => {
        // The regression was specifically that the three inputs sat
        // in a horizontal row at desktop. After the fix the rows must
        // stack: input N+1's top is >= input N's bottom, AT EVERY
        // viewport (desktop AND mobile — the previous mobile-only
        // collapse was the OLD shape; the new shape is the same at
        // both sizes).
        const keys = ['auth.maxlife', 'auth.maxlife.remember', 'auth.maxlife.steam'];
        const tops: number[] = [];
        const bottoms: number[] = [];
        for (const key of keys) {
            const box = await page
                .locator(`[data-testid="setting-row"][data-key="${key}"]`)
                .boundingBox();
            expect(box, `${key} row must be measurable`).not.toBeNull();
            if (!box) return;
            tops.push(box.y);
            bottoms.push(box.y + box.height);
        }
        expect(
            tops[1],
            'auth.maxlife.remember row should sit below auth.maxlife row',
        ).toBeGreaterThanOrEqual(bottoms[0] - 1);
        expect(
            tops[2],
            'auth.maxlife.steam row should sit below auth.maxlife.remember row',
        ).toBeGreaterThanOrEqual(bottoms[1] - 1);
    });

    test('on desktop, inputs are wider than the rendered label TEXT', async ({ page, isMobile }) => {
        test.skip(
            isMobile,
            'Desktop-only invariant: at mobile widths the input clamp drops to 100% so the constraint is trivially true; the regression was specifically a desktop layout bug.',
        );

        // The audit's complaint was visual: the rendered input box
        // looked narrower than the text rendered above it. The
        // <label class="label"> is a `display: block` element that
        // stretches to its container, so its bounding box width is
        // the column width — not what the user reads. To reproduce
        // the user's visual contract we measure the actual TEXT
        // width by wrapping the label's text in a Range and reading
        // its bounding client rect; the input's visible width comes
        // from its bounding box (it's a leaf input, not a stretched
        // block).
        const keys = ['auth.maxlife', 'auth.maxlife.remember', 'auth.maxlife.steam'];
        for (const key of keys) {
            const row = page.locator(`[data-testid="setting-row"][data-key="${key}"]`);
            const labelTextWidth = await row.locator('label.label').evaluate((el) => {
                const range = document.createRange();
                range.selectNodeContents(el);
                return range.getBoundingClientRect().width;
            });
            const inputBox = await row.locator('input[type="number"]').boundingBox();
            expect(inputBox, `${key} input must be measurable`).not.toBeNull();
            if (!inputBox) return;
            expect(
                inputBox.width,
                `${key} input (width=${inputBox.width}px) must be at least as wide as the rendered label text (width=${labelTextWidth}px) — the audit caught the input visibly narrower than its label.`,
            ).toBeGreaterThanOrEqual(labelTextWidth - 1);
        }
    });

    test('axe: no critical a11y violations on the settings page', async ({ page }, testInfo) => {
        await expectNoCriticalA11y(page, testInfo, {
            include: ['#form-settings-main'],
        });
    });
});
