import AxeBuilder from '@axe-core/playwright';
import type { Page, TestInfo } from '@playwright/test';
import { expect } from '@playwright/test';

/**
 * Run an axe-core scan and assert zero `critical`-impact violations.
 *
 * The threshold is **critical** by contract (#1124). Slices 1–8 must
 * use this helper as-is; do NOT downgrade the threshold to make tests
 * green — file follow-ups against the underlying #1123 testability
 * patterns instead.
 *
 * The full report (every violation, every impact level) is attached
 * to the failing test via `testInfo.attach('axe', …)` so debugging
 * doesn't require re-running locally — the trace + report download
 * from the workflow artifact is enough.
 */
export async function expectNoCriticalA11y(
    page: Page,
    testInfo: TestInfo,
    opts: { include?: string[]; exclude?: string[] } = {},
): Promise<void> {
    let builder = new AxeBuilder({ page });
    if (opts.include) {
        for (const sel of opts.include) builder = builder.include(sel);
    }
    if (opts.exclude) {
        for (const sel of opts.exclude) builder = builder.exclude(sel);
    }

    const results = await builder.analyze();

    await testInfo.attach('axe', {
        body: JSON.stringify(results, null, 2),
        contentType: 'application/json',
    });

    const critical = results.violations.filter((v) => v.impact === 'critical');
    if (critical.length > 0) {
        const ruleSummary = critical
            .map((v) => `  - ${v.id} (${v.nodes.length} node${v.nodes.length === 1 ? '' : 's'}): ${v.help}`)
            .join('\n');
        expect(
            critical,
            `axe found ${critical.length} critical a11y violation${critical.length === 1 ? '' : 's'}:\n${ruleSummary}\n` +
                `Full report attached to the test as "axe".`,
        ).toEqual([]);
    }
}
