/**
 * Uploaded demos must only be reachable through `getdemo.php`.
 *
 * The anonymous submission form stores evidence under `web/demos/`
 * without an extension. This spec drives each layer through HTTP:
 *
 *   1. Stored names are 32 random hex chars.
 *   2. Apache denies `/demos/` (dev + prod confs, `demos/.htaccess`
 *      on tarball installs).
 *   3. Every response carries `X-Content-Type-Options: nosniff`.
 *
 * `getdemo.php` (octet-stream + attachment) must keep working.
 */

import { readdir, readFile, rm } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test } from '../../fixtures/auth.ts';
import {
    adminSubmissionRow,
    anonymousSubmit,
    newAnonymousContext,
    type SubmissionFixture,
} from '../../pages/SubmitBanFlow.ts';

const DEMOS_DIR = resolve(process.cwd(), '../../demos');
const STORED_NAME = /^[0-9a-f]{32}$/;

async function storedDemoNames(): Promise<Set<string>> {
    return new Set((await readdir(DEMOS_DIR)).filter((name) => STORED_NAME.test(name)));
}

test.describe('flow: uploaded demos are not directly reachable', () => {
    test('panel responses send nosniff and the demos directory is forbidden', async ({ browser }, testInfo) => {
        const anon = await newAnonymousContext(browser, testInfo.project.use);
        try {
            const home = await anon.request.get('/index.php');
            expect(home.status()).toBe(200);
            expect(home.headers()['x-content-type-options']).toBe('nosniff');

            for (const path of ['/demos/', '/demos/.htaccess', '/demos/.gitkeep']) {
                const res = await anon.request.get(path);
                expect(res.status(), path).toBe(403);
            }
        } finally {
            await anon.close();
        }
    });

    test('submission demo gets a random name and is served only via getdemo.php', async ({ page, browser, isMobile }, testInfo) => {
        test.skip(
            Boolean(isMobile),
            'writes submissions keyed on a SteamID; the desktop project covers the contract',
        );

        const seed = `${Date.now()}${testInfo.workerIndex}`;
        const steam = `STEAM_0:1:${seed}`;
        const base: Omit<SubmissionFixture, 'demo'> = {
            steam,
            playerName: `e2e-demo-${seed}`,
            reason: `e2e: demo direct access ${seed}`,
            reporterName: 'e2e-reporter',
            email: 'e2e-reporter@example.test',
        };
        const payloads = [
            `e2e-demo-payload-${seed}-a`,
            `e2e-demo-payload-${seed}-b`,
        ];

        const before = await storedDemoNames();
        // Other specs write their own 32-hex fixtures here; only claim ours.
        const ownUploads = async (): Promise<string[]> => {
            const fresh = [...(await storedDemoNames())].filter((name) => !before.has(name));
            const owned = await Promise.all(fresh.map(async (name) => {
                const body = await readFile(resolve(DEMOS_DIR, name), 'utf8').catch(() => '');
                return body.includes(seed) ? name : null;
            }));
            return owned.filter((name): name is string => name !== null);
        };
        const anon = await newAnonymousContext(browser, testInfo.project.use);

        try {
            const anonPage = await anon.newPage();
            for (const contents of payloads) {
                await anonymousSubmit(anonPage, { ...base, demo: { name: 'evidence.dem', contents } });
            }

            const created = await ownUploads();
            expect(created).toHaveLength(payloads.length);
            expect(new Set(created).size).toBe(payloads.length);

            for (const name of created) {
                const direct = await anon.request.get(`/demos/${name}`);
                expect(direct.status(), `/demos/${name}`).toBe(403);
                expect(await direct.text()).not.toContain(seed);
            }

            await page.goto('/index.php?p=admin&c=bans&section=submissions');
            const rows = adminSubmissionRow(page, steam);
            await expect(rows).toHaveCount(payloads.length);
            const hrefs = await rows
                .locator('a[href^="getdemo.php"]')
                .evaluateAll((links) => links.map((a) => a.getAttribute('href') ?? ''));
            expect(hrefs).toHaveLength(payloads.length);

            const served: string[] = [];
            for (const href of hrefs) {
                const res = await page.request.get(`/${href}`);
                expect(res.status(), href).toBe(200);
                const headers = res.headers();
                expect(headers['content-type']).toBe('application/octet-stream');
                expect(headers['content-disposition']).toMatch(/^attachment;/);
                expect(headers['x-content-type-options']).toBe('nosniff');
                served.push(await res.text());
            }
            expect(served.sort()).toEqual([...payloads].sort());
        } finally {
            await anon.close();
            const leftovers = await ownUploads();
            await Promise.all(leftovers.map((name) => rm(resolve(DEMOS_DIR, name), { force: true })));
        }
    });
});
