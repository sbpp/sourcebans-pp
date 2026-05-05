import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the SourceBans++ E2E suite (#1124).
 *
 * The harness runs against the live dev stack (`./sbpp.sh up`); see
 * AGENTS.md "Playwright E2E specifics" for the contract this config
 * honours (storage-state auth minted in `fixtures/global-setup.ts`,
 * DB isolation against `sourcebans_e2e`, axe `critical` threshold,
 * `prefers-reduced-motion: reduce` set globally).
 *
 * `E2E_BASE_URL` defaults to the host-published port (`:8080`) so
 * `npx playwright test` from the host hits the same panel a developer
 * is browsing. `./sbpp.sh e2e` overrides this to `http://localhost`
 * because the suite then runs *inside* the web container where Apache
 * is reachable on port 80.
 */
export default defineConfig({
    testDir: './specs',
    fullyParallel: true,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: [['html', { open: 'never' }], ['list']],
    globalSetup: './fixtures/global-setup.ts',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        colorScheme: 'light',
        // `reducedMotion: 'reduce'` is honoured automatically by
        // Playwright via the device descriptors, but surfacing it
        // explicitly here documents the contract from #1123: any
        // animation longer than ~100ms is skippable, so tests never
        // wait out a transition just to assert state changed.
        contextOptions: { reducedMotion: 'reduce' },
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'playwright/.auth/admin.json',
            },
        },
        {
            name: 'mobile-chromium',
            use: {
                ...devices['iPhone 13'],
                // `devices['iPhone 13']` defaults `defaultBrowserType: 'webkit'`;
                // we want chrome-on-mobile-form-factor, not Safari, so we
                // can keep a single-browser install footprint.
                defaultBrowserType: 'chromium',
                browserName: 'chromium',
                storageState: 'playwright/.auth/admin.json',
            },
        },
    ],
});
