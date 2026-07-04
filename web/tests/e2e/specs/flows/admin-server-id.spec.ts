/**
 * Flow: the admin Server Management list surfaces each server's numeric
 * Server ID on its card (#1504).
 *
 * Background
 * ----------
 * The SourceMod plugin's `sourcebans.cfg` carries a `ServerID` field
 * whose inline comment reads "Check in the admin panel -> servers to
 * find the ID of this server", and the setup docs tell operators to
 * note the ID after adding a server. But the v2.0 card-grid rewrite of
 * `page_admin_servers_list.tpl` never printed the sid anywhere the
 * operator could read it (only inside RCON/Edit/Admins hrefs and the
 * no-icon fallback glyph), so operators had no way to find the value
 * the plugin needs. The reporter (issue #1504) hit exactly this wall.
 *
 * This spec seeds a server through the JSON API and asserts the tile
 * renders a labelled, copyable "Server ID" row showing the raw sid.
 * The clipboard round-trip itself is already exercised by the shared
 * [data-copy] delegate spec (`flows/ui/copy-buttons.spec.ts`), so this
 * spec locks the render contract only.
 *
 * It also runs the critical-axe gate on the POPULATED card. The
 * sibling smoke spec (`smoke/admin/servers.spec.ts`) deliberately
 * never seeds a row, so its axe pass only covers the empty state —
 * the new icon-only copy button + Server ID row only exist once a
 * tile renders, and this is the only spec that puts axe on them.
 *
 * Selectors are `data-testid` per #1123. Single-project (chromium)
 * because it drives `truncateE2eDb()` — same DB-isolation rationale as
 * `server-refresh-debounce.spec.ts` (see that file's comment for the
 * truncate-vs-Apache race against the shared `sourcebans_e2e` DB).
 */

import { expect, test } from '../../fixtures/auth.ts';
import { expectNoCriticalA11y } from '../../fixtures/axe.ts';
import { truncateE2eDb } from '../../fixtures/db.ts';
import { AdminServersPage } from '../../pages/admin/AdminServers.ts';

test.describe('flow: admin servers — Server ID is discoverable (#1504)', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'Browser-shape-agnostic server-rendered markup; skip the second project to avoid the truncate-vs-Apache race against sourcebans_e2e (see server-refresh-debounce.spec.ts).',
        );
    });

    test('the server card shows the numeric Server ID with a copy button', async ({ page }, testInfo) => {
        await truncateE2eDb();

        // Seed a server via the JSON API. RFC 5737 documentation IP so
        // no real Source server can ever answer the A2S probe — this
        // spec only cares about the server-rendered Server ID, not the
        // live hydration cells.
        await page.goto('/index.php?p=admin&c=servers&section=add');
        const addEnvelope = await page.evaluate(async () => {
            const w = window as unknown as {
                sb: { api: { call: (a: string, p: Record<string, unknown>) => Promise<{ ok: boolean }> } };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.ServersAdd, {
                ip: '203.0.113.7',
                port: '27015',
                rcon: '',
                rcon2: '',
                mod: 1,
                enabled: true,
                group: '0',
            });
        });
        expect(addEnvelope, 'servers.add envelope should round-trip ok').toMatchObject({ ok: true });

        const p = new AdminServersPage(page);
        await p.goto();
        await expect(p.pageMounted).toBeVisible();

        // Resolve the seeded sid from the DOM without relying on the
        // API envelope shape (that is not the contract under test):
        // there is exactly one tile after the truncate + single seed.
        const seededTile = page.locator('[data-testid="server-tile"][data-id]').first();
        await expect(seededTile).toBeVisible();
        const sidAttr = await seededTile.getAttribute('data-id');
        expect(sidAttr, 'seeded tile must carry a data-id sid').toBeTruthy();
        const sid = Number(sidAttr);

        // The labelled Server ID row is visible and prints the sid.
        const idRow = p.serverIdRow(sid);
        await expect(idRow).toBeVisible();
        await expect(idRow.locator('[data-testid="server-id-value"]')).toHaveText(String(sid));

        // The copy button carries the sid as its clipboard payload so
        // the shared [data-copy] delegate can write it verbatim.
        const copyBtn = idRow.locator('[data-testid="server-id-copy"]');
        await expect(copyBtn).toBeVisible();
        await expect(copyBtn).toHaveAttribute('data-copy', String(sid));

        // The literal label anchors discoverability — the whole point
        // of the fix is that "Server ID" is written on the card.
        await expect(p.tile(sid)).toContainText('Server ID');

        // No critical a11y regressions on the populated card (the
        // icon-only copy button carries aria-label + title; the smoke
        // spec's axe pass only sees the empty state).
        await expectNoCriticalA11y(page, testInfo);
    });
});
