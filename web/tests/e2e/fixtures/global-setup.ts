import { chromium, type FullConfig } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

import { resetE2eDb } from './db.ts';

/**
 * Global setup runs ONCE per `playwright test` invocation, before any
 * project (chromium / mobile-chromium) starts.
 *
 *   1. Reset `sourcebans_e2e` from struc.sql + data.sql (drop + recreate).
 *      This is the expensive path; spec-level resets use truncate via
 *      `truncateE2eDb()`.
 *   2. Mint `playwright/.auth/admin.json` by driving the login form
 *      against the seeded admin/admin user. Every spec then loads that
 *      storage state and starts already-logged-in.
 *
 * The login spec opts back out of storageState (see `specs/smoke/login.spec.ts`)
 * so it exercises the form itself.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    await resetE2eDb();

    // Pull the baseURL from the first project's `use` block. The two
    // projects share the same baseURL via the top-level `use`, so
    // `projects[0].use.baseURL` is the canonical source.
    const baseURL = config.projects[0]?.use.baseURL ?? process.env.E2E_BASE_URL ?? 'http://localhost:8080';

    const storageStatePath = resolve(__dirname, '..', 'playwright', '.auth', 'admin.json');
    await mkdir(dirname(storageStatePath), { recursive: true });

    const browser = await chromium.launch();
    try {
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();

        await page.goto('/index.php?p=login');
        await page.locator('[data-testid="login-username"]').fill('admin');
        await page.locator('[data-testid="login-password"]').fill('admin');
        await page.locator('[data-testid="login-submit"]').click();

        // The login API returns a redirect envelope; api.js follows it
        // client-side and lands on the dashboard. The seeded admin is
        // a logged-in user, so the navbar's account link becomes
        // visible — that's our deterministic terminal state.
        await page.locator('[data-testid="nav-account"]').waitFor({ state: 'visible', timeout: 30_000 });

        await context.storageState({ path: storageStatePath });
    } finally {
        await browser.close();
    }
}
