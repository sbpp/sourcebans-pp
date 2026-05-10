/**
 * Flow: public banlist surfaces the IP column to admins (#1302).
 *
 * The v2.0 redesign of `web/themes/default/page_bans.tpl` dropped
 * the per-row IP column entirely — even an `ADMIN_OWNER` user lost
 * the at-a-glance IP that v1.x rendered (gated on `is_admin()`).
 *
 * The page handler (`web/pages/page.banlist.php`) was already
 * computing `$hideplayerips = Config::getBool('banlist.hideplayerips')
 * && !$userbank->is_admin()` correctly, and `BanListView` already
 * carried the per-row `ban_ip_raw` field. Only the template was
 * missing — `BanListIpColumnTest` covers the template-level
 * regression in both directions (visible + suppressed). This spec
 * closes the integration gap end-to-end: the seeded `admin/admin`
 * user (storage state from `fixtures/global-setup.ts` —
 * `is_admin()` = true via `ALL_WEB`) seeds an IP-bearing ban
 * through `bans.add` and asserts the IP renders in the live
 * desktop `<table>` AND the mobile `.ban-cards` block.
 *
 * Project gating
 * --------------
 * Runs on BOTH desktop-chromium and mobile-chromium so we cover
 * the desktop `<table>` testid (`ban-ip`) and the mobile card
 * mirror (`ban-ip-mobile`). The describe block branches on
 * `isMobile` to assert the right surface per project — keeps the
 * regression locked at every viewport.
 */

import type { Page } from '@playwright/test';
import { expect, test } from '../../fixtures/auth.ts';

const SEED_STEAM = 'STEAM_0:1:1302001';
const SEED_NICK = 'e2e-ip-col';
const SEED_IP = '203.0.113.42';

/**
 * Seed a Steam ban WITH an IP via `bans.add` and tolerate the
 * `already_banned` collision (the panel runs against the dev
 * `sourcebans` DB, which persists across `playwright test`
 * invocations — see `responsive/banlist.spec.ts`'s `seedBan`
 * for the per-DB rationale).
 *
 * The shared `seedBanViaApi` fixture hardcodes `ip: ''`, so
 * we drive `bans.add` directly with the IP set. The handler
 * persists `:prefix_bans.ip = '203.0.113.42'` which the page
 * handler later reads into the per-row `ban_ip_raw` field.
 */
async function seedBanWithIp(page: Page): Promise<void> {
    await page.goto('/');

    const env = await page.evaluate(
        async (args) => {
            const w = window as unknown as {
                sb: {
                    api: {
                        call: (
                            action: string,
                            params: Record<string, unknown>,
                        ) => Promise<{
                            ok: boolean;
                            data?: { bid?: number };
                            error?: { code: string; message: string };
                        }>;
                    };
                };
                Actions: Record<string, string>;
            };
            return await w.sb.api.call(w.Actions.BansAdd, {
                nickname: args.nickname,
                steam: args.steam,
                type: 0,
                ip: args.ip,
                length: 0,
                reason: 'e2e: banlist IP column seed (#1302)',
            });
        },
        { nickname: SEED_NICK, steam: SEED_STEAM, ip: SEED_IP },
    );

    if (env && env.ok === false && env.error?.code !== 'already_banned') {
        throw new Error(`bans.add seed failed: ${JSON.stringify(env)}`);
    }
}

test.describe('flow: banlist IP column visible to admin (#1302)', () => {
    test('IP column renders for the admin caller on the public banlist', async ({
        page,
        isMobile,
    }) => {
        await seedBanWithIp(page);
        await page.goto('/index.php?p=banlist');

        if (isMobile) {
            // Mobile chrome is `.ban-cards`, not `<table>` (theme.css
            // L266: `.table { display: none }` at <=768px). The IP
            // line lives directly under the SteamID line on each
            // card, gated on `{if !$hideplayerips && $ban.ban_ip_raw
            // != ''}`.
            const cards = page.locator('#banlist-root .ban-cards');
            await expect(cards).toBeVisible();

            const ipLine = page
                .locator('[data-testid="ban-ip-mobile"]')
                .filter({ hasText: SEED_IP });
            await expect(ipLine).toBeVisible();
        } else {
            // Desktop column header: visible "IP" copy + `.col-ip`
            // hook. Anchor on both so a future refactor that reuses
            // the class for an unrelated column still trips the
            // assertion.
            const table = page.locator('#banlist-root .table');
            await expect(table).toBeVisible();
            const header = table.locator('th.col-ip');
            await expect(header).toBeVisible();
            await expect(header).toHaveText(/^IP$/);

            // Per-row IP cell — testid contract for any future
            // filter / styling work.
            const seededRow = table
                .locator('[data-testid="ban-row"]')
                .filter({ hasText: SEED_STEAM });
            await expect(seededRow).toBeVisible();
            const ipCell = seededRow.locator('[data-testid="ban-ip"]');
            await expect(ipCell).toBeVisible();
            await expect(ipCell).toContainText(SEED_IP);
        }
    });
});
