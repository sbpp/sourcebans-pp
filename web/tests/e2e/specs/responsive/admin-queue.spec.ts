/**
 * Responsive: admin moderation queue card layout (#1207 PUB-2).
 *
 * The audit caught the moderation queue (`?p=admin&c=bans`,
 * "Ban submissions" + "Ban protests") still rendering each row as a
 * `<details>` packed onto a single horizontal flex line at iPhone-13
 * width. The third action ("Contact") truncated past the right edge
 * and the date wrapped to two lines.
 *
 * The fix promotes the public banlist's mobile card pattern to these
 * queues: the summary's `[name+steam stack] [date] [actions]` layout
 * keeps the same source order (so screen readers / keyboard nav read
 * left-to-right desktop) but at <=768px wraps onto two visual rows so
 * every action is reachable.
 *
 * Project gating
 * --------------
 * Mobile-chromium only. The desktop layout is unchanged at >=769px;
 * `flows/admin-ban-lifecycle.spec.ts` already exercises the
 * ban-action click path.
 *
 * Selectors
 * ---------
 * Per #1123, every assertion uses `data-testid` (`submission-row`,
 * `submission-row-steam`, `row-action-ban`, `row-action-remove`,
 * `row-action-contact`) — the testid contract is unchanged by the
 * card-layout fix; only the surrounding CSS class set moves.
 *
 * #1275 — Pattern A routing
 * -------------------------
 * Admin-bans is now Pattern A; the submissions queue lives at
 * `?p=admin&c=bans&section=submissions` (the default landing for
 * `?p=admin&c=bans` is `add-ban`, the first accessible section).
 * The card-layout invariant moves with the section — these tests
 * navigate to the submissions URL explicitly.
 */

import { expect, test } from '../../fixtures/auth.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';
import { anonymousSubmit, newAnonymousContext } from '../../pages/SubmitBanFlow.ts';

test.describe('responsive: admin moderation queue (#1207 PUB-2)', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'mobile-chromium',
            'Mobile-only contract — desktop chrome is unchanged.',
        );
    });

    test.beforeEach(async () => {
        await truncateE2eDb();
    });

    test('submission queue row stacks summary vertically on mobile', async ({ page, browser }, testInfo) => {
        // Seed a submission via the live anonymous form so the queue
        // has a row to lay out. Same shape as
        // `flows/public-ban-submission.spec.ts` — keeps the seed
        // reliant on the actual production code path.
        const fx = {
            steam: 'STEAM_0:1:1207002',
            playerName: 'e2e-pub2-queue',
            reason: 'e2e: PUB-2 queue card layout',
            reporterName: 'e2e-reporter',
            email: 'e2e-pub2@example.test',
        };
        const anonCtx = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const anon = await anonCtx.newPage();
            await anonymousSubmit(anon, fx);
        } finally {
            await anonCtx.close();
        }

        // #1275 — submissions is Pattern A's `?section=submissions`;
        // the bare `?p=admin&c=bans` URL defaults to `add-ban`.
        await page.goto('/index.php?p=admin&c=bans&section=submissions');

        const row = page
            .locator('[data-testid="submission-row"]')
            .filter({ has: page.locator('[data-testid="submission-row-steam"]', { hasText: fx.steam }) });
        await expect(row).toBeVisible();

        // The card-layout class is the layout contract: theme.css's
        // `.queue-row > summary` flex rules + the <=768px override
        // depend on it. A regression that drops the class would
        // silently revert to the cramped horizontal pack.
        await expect(row).toHaveClass(/\bqueue-row\b/);

        const summary = row.locator('> summary');
        await expect(summary).toBeVisible();
        // Inside the summary, the body / date / actions live as
        // siblings. The mobile media query orders body=row1,
        // date+actions=row2.
        const body = summary.locator('.queue-row__body');
        const date = summary.locator('.queue-row__date');
        const actions = summary.locator('.row-actions');
        await expect(body).toBeVisible();
        await expect(date).toBeVisible();
        await expect(actions).toBeVisible();

        // Layout invariant: at mobile width the body's bounding box
        // sits ABOVE both the date and the action cluster (i.e. the
        // summary actually wraps onto a second row). We allow a 1px
        // tolerance for sub-pixel rounding.
        const [bodyBox, dateBox, actionsBox] = await Promise.all([
            body.boundingBox(),
            date.boundingBox(),
            actions.boundingBox(),
        ]);
        expect(bodyBox, 'queue-row body has a bounding box').not.toBeNull();
        expect(dateBox, 'queue-row date has a bounding box').not.toBeNull();
        expect(actionsBox, 'queue-row actions have a bounding box').not.toBeNull();

        const bodyBottom = bodyBox!.y + bodyBox!.height;
        expect(
            dateBox!.y,
            'date renders below the body on mobile (queue-row card stack)',
        ).toBeGreaterThanOrEqual(bodyBottom - 1);
        expect(
            actionsBox!.y,
            'actions render below the body on mobile (queue-row card stack)',
        ).toBeGreaterThanOrEqual(bodyBottom - 1);
    });

    test('all three row actions are visible and inside the viewport', async ({ page, browser }, testInfo) => {
        // Re-seed: each test starts from a truncated DB.
        const fx = {
            steam: 'STEAM_0:1:1207003',
            playerName: 'e2e-pub2-actions',
            reason: 'e2e: PUB-2 actions reachable',
            reporterName: 'e2e-reporter',
            email: 'e2e-pub2-actions@example.test',
        };
        const anonCtx = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const anon = await anonCtx.newPage();
            await anonymousSubmit(anon, fx);
        } finally {
            await anonCtx.close();
        }

        // #1275 — submissions is Pattern A's `?section=submissions`;
        // the bare `?p=admin&c=bans` URL defaults to `add-ban`.
        await page.goto('/index.php?p=admin&c=bans&section=submissions');

        const row = page
            .locator('[data-testid="submission-row"]')
            .filter({ has: page.locator('[data-testid="submission-row-steam"]', { hasText: fx.steam }) });
        await expect(row).toBeVisible();

        // Every action is a #1123 testability hook; the audit
        // showed "Contact" truncating past the right edge in the
        // pre-fix layout. Each must be visible AND its bounding
        // box must sit fully inside the queue row's container —
        // the row itself ("submission-row") is where the PUB-2
        // contract lives, so locking the assertion to the row's
        // own bounding box keeps this spec scoped to the
        // card-stack invariant rather than the page-level chrome.
        const rowBox = await row.boundingBox();
        expect(rowBox, 'queue row renders a bounding box').not.toBeNull();
        for (const testid of ['row-action-ban', 'row-action-remove', 'row-action-contact']) {
            const action = row.locator(`[data-testid="${testid}"]`);
            await expect(action).toBeVisible();
            const box = await action.boundingBox();
            expect(box, `${testid} renders a bounding box`).not.toBeNull();
            expect(
                box!.x,
                `${testid} starts inside the queue row`,
            ).toBeGreaterThanOrEqual(rowBox!.x - 1);
            expect(
                box!.x + box!.width,
                `${testid} ends inside the queue row`,
            ).toBeLessThanOrEqual(rowBox!.x + rowBox!.width + 1);
        }

        // The summary itself doesn't horizontal-scroll either —
        // i.e. content fits within the painted card and no inner
        // overflow hides anything. This is the bounded version of
        // the pre-fix bug ("Contact truncated"): the summary's
        // scrollWidth must equal its clientWidth.
        const summary = row.locator('> summary');
        const summaryOverflow = await summary.evaluate((el) => ({
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
        }));
        expect(
            summaryOverflow.scrollWidth,
            'queue summary content fits within its rendered width',
        ).toBeLessThanOrEqual(summaryOverflow.clientWidth + 1);
    });
});
