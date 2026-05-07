/**
 * Upgrade harness — 1.7.0 → 2.0 happy-path spec (#1269).
 *
 * Drives a full upgrade against `fixtures/upgrade/1.7.0.sql.gz` end-to-end
 * and asserts on:
 *
 *   1. /upgrade.php appends `SB_SECRET_KEY` to a config that lacks it
 *      (and is a no-op when the key is already defined).
 *   2. /updater/index.php walks 705 → 801 → 802 → 803 → 804 → 805 in one
 *      pass, lands on the "Updated successfully" terminal marker.
 *   3. A second /updater/index.php pass is a verified no-op (idempotency).
 *   4. The post-upgrade schema matches a fresh `struc.sql + data.sql`
 *      install — captured as a snapshot so any new drift fails the spec.
 *   5. Every key in fresh `data.sql`'s `:prefix_settings` exists post-
 *      upgrade. Values are deliberately not compared (`config.version`
 *      and per-install random keys legitimately differ).
 *   6. Smoke flow: `admin / admin` logs in against the upgraded DB.
 *
 * Lifecycle (this spec opts out of the suite's global `storageState`
 * because we drive the login flow against the upgraded panel ourselves):
 *
 *   beforeAll — copy fixture into the container, stash live config.php,
 *     drop+create the upgrade DB, load the gz fixture, render config.php
 *     to point at it (with SB_SECRET_KEY removed so /upgrade.php exercises
 *     its append branch), and install a fresh reference DB for the parity
 *     comparisons.
 *   tests   — drive the upgrade flow + assertions. Each `test.step` is
 *     one logical assertion so the Playwright report shows progress.
 *   afterAll — restore config.php from the stash. The drop of the
 *     throwaway upgrade DB is left to the next `beforeAll` so post-mortem
 *     inspection (Adminer at `:9169`, MariaDB on `:13169`) is possible
 *     when a run goes red.
 *
 * The 1.x panel chrome doesn't carry `data-testid` hooks (those landed at
 * #1123); but we never actually drive the 1.x UI. The only HTML this spec
 * scrapes is the v2.0 `/updater/index.php` body — which DOES carry
 * `data-testid` hooks (`updater-progress`, `updater-cleanup`,
 * `updater-return`). Login goes through the 2.0 chrome
 * (`data-testid="login-username"` etc.). String anchors that are NOT
 * testid-based are documented inline.
 *
 * Tagged `@upgrade` so `./sbpp.sh upgrade-e2e` (and ad-hoc
 * `./sbpp.sh e2e --grep @upgrade`) can target it without dragging the
 * whole suite.
 */

import { execFile } from 'node:child_process';
import { access, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { promisify } from 'node:util';

import { test, expect } from '../../fixtures/auth.ts';

import { stashPanelConfig } from './_helpers/copyFixture.ts';
import {
    diffSchemas,
    formatSchemaForDiff,
    missingSettingsKeys,
} from './_helpers/parity.ts';
import {
    assertUpdaterIsIdempotent,
    runUpdaterEndpoint,
    runUpgradeEndpoint,
} from './_helpers/upgradeFlow.ts';
import {
    dumpSchema,
    dumpSettings,
    installFreshReference,
    renderPanelConfig,
    resetUpgradeDb,
    restorePanelConfig,
} from './_helpers/upgradeDb.ts';

const execFileP = promisify(execFile);

// Throwaway DB names for THIS spec — `sourcebans_upgrade_*` is the
// only namespace `upgrade-db.php` is willing to operate on (refusal
// guard mirrors the dev synthesizer's). Two DBs: one for the upgrade
// walk, one as the fresh-install reference for parity diffs.
const UPGRADE_DB = 'sourcebans_upgrade_170';
const FRESH_DB = 'sourcebans_upgrade_170_fresh';

// In-container paths the wrapper stages the fixtures at. The
// `./sbpp.sh upgrade-e2e` wrapper `docker compose cp`s the .sql.gz +
// matching config.php into these locations BEFORE Playwright spawns.
// Host-side ad-hoc runs need the same staging via:
//
//   docker compose cp fixtures/upgrade/1.7.0.sql.gz       web:/tmp/sbpp-upgrade-1.7.0.sql.gz
//   docker compose cp fixtures/upgrade/config.1.7.0.php   web:/tmp/sbpp-upgrade-config-1.7.0.php
//
// (documented in `web/tests/e2e/specs/upgrade/README.md`). The spec
// asserts the files exist before consuming them; missing files surface
// a single readable error rather than a downstream schema-load PDO
// exception.
const CONTAINER_FIXTURE_GZ = '/tmp/sbpp-upgrade-1.7.0.sql.gz';
const CONTAINER_FIXTURE_CONFIG = '/tmp/sbpp-upgrade-config-1.7.0.php';
const CONTAINER_PANEL_CONFIG = '/var/www/html/web/config.php';
const CONTAINER_PANEL_CONFIG_STASH = '/var/www/html/web/config.php.upgrade-stash';

// Snapshot artefacts. The `schema.diff` file is checked into git as
// the locked baseline; new drift fails the spec when the file's
// contents change. See `__snapshots__/1.7.0/README.md` for the
// known-drift policy.
const SNAPSHOT_SCHEMA_DIFF = resolve(__dirname, '__snapshots__/1.7.0/schema.diff');

test.describe('@upgrade 1.7.0 → 2.0 upgrade harness', () => {
    // Opt out of the suite's global storage state. The login spec is the
    // only other place this happens; we do it for the same reason: this
    // spec exercises auth against a panel pointed at a non-default DB,
    // and the global storage state was minted against the dev DB by
    // `fixtures/global-setup.ts`.
    test.use({ storageState: { cookies: [], origins: [] } });

    // Run this describe's tests serially. The setup/teardown is heavy
    // enough that the spec writer treats it as a single transaction;
    // serial mode also means a future second-test addition (currently
    // we ship one) doesn't have to re-run the upgrade.
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(async ({ request }) => {
        // 1. Verify the wrapper / dev staged the fixture + config in /tmp/.
        //    The `./sbpp.sh upgrade-e2e` wrapper `docker compose cp`s
        //    them into place before Playwright spawns; ad-hoc host-side
        //    runs need the same staging (see README). When a casual
        //    `./sbpp.sh e2e --grep <broad>` accidentally pulls the
        //    spec in WITHOUT having staged anything, we'd rather skip
        //    than fail — the wrapper is the only invocation path
        //    where running the harness is meaningful. The detailed
        //    "what to do" message lives in `assertContainerFileExists`
        //    for the explicit-failure case (caller meant to run it).
        const staged = await containerFileExists(CONTAINER_FIXTURE_GZ);
        const stagedConfig = await containerFileExists(CONTAINER_FIXTURE_CONFIG);
        test.skip(
            !staged || !stagedConfig,
            'upgrade fixtures not staged in /tmp/sbpp-upgrade-* — run via `./sbpp.sh upgrade-e2e`',
        );
        await assertContainerFileExists(CONTAINER_FIXTURE_GZ);
        await assertContainerFileExists(CONTAINER_FIXTURE_CONFIG);

        // 2. Stash the live config.php so afterAll can restore it. The
        //    e2e wrapper already stashes config.php.upgrade-e2e-stash
        //    on entry; that stash is what an outer "restore on failure"
        //    cleanup relies on. We layer our own stash inside it.
        await stashPanelConfig(CONTAINER_PANEL_CONFIG, CONTAINER_PANEL_CONFIG_STASH);

        // 3. Stage the upgrade DB: drop + recreate, load the 1.7.0 gz
        //    dump. After this step DB version is 704.
        await resetUpgradeDb(UPGRADE_DB, CONTAINER_FIXTURE_GZ);

        // 4. Stage a fresh reference DB on the side. We'll diff the
        //    post-upgrade schema against this one for parity. Doing the
        //    install ahead of time keeps the schema dump deterministic
        //    even if a future migration rewrites struc.sql semantics.
        await installFreshReference(FRESH_DB);

        // 5. Render config.php so the panel's request handler points at
        //    the upgrade DB and SB_SECRET_KEY is absent — that's the
        //    pre-upgrade.php state of an operator running their first
        //    1.x install. The panel's webserver re-reads config.php on
        //    every request (mod_php), so the next HTTP request after
        //    this call sees the new DB_NAME.
        await renderPanelConfigFromTemplate(CONTAINER_FIXTURE_CONFIG, CONTAINER_PANEL_CONFIG);
        await renderPanelConfig(CONTAINER_PANEL_CONFIG, UPGRADE_DB);

        // 6. Sanity probe: the panel must successfully boot against the
        //    upgrade DB. We hit `/index.php?p=login` (read-only) rather
        //    than /upgrade.php — the latter mutates config.php (it's
        //    what the spec body exercises in step 1) and any pre-test
        //    probe against it would silently consume the
        //    "key-was-absent → key-now-present" transition the test
        //    is supposed to assert on.
        const pre = await request.get('/index.php?p=login');
        expect(pre.status(), 'panel must boot against the upgrade DB').toBeLessThan(400);
    });

    test.afterAll(async () => {
        await restorePanelConfig(CONTAINER_PANEL_CONFIG, CONTAINER_PANEL_CONFIG_STASH);
    });

    test('full upgrade walk + parity + idempotency + login smoke', async ({ page, request }) => {
        await test.step('1. /upgrade.php appends SB_SECRET_KEY (absent → present)', async () => {
            const body = await runUpgradeEndpoint(request);
            // The append branch's static body. Documented in
            // web/upgrade.php (the script `echo`s exactly this string
            // when file_put_contents() succeeds).
            expect(
                body,
                '/upgrade.php should report a successful append on first hit',
            ).toContain('config.php</kbd> updated correctly');

            // Re-hit: the constant is now defined; the script must
            // short-circuit. We assert on the second-call body so a
            // future regression that always re-appends gets caught.
            const idempotent = await runUpgradeEndpoint(request);
            expect(
                idempotent,
                '/upgrade.php second run must short-circuit on defined SB_SECRET_KEY',
            ).toContain('Upgrade not needed.');
        });

        let firstUpdaterRun: Awaited<ReturnType<typeof runUpdaterEndpoint>>;
        await test.step('2. /updater/index.php walks 704 → 805', async () => {
            firstUpdaterRun = await runUpdaterEndpoint(request);

            expect(
                firstUpdaterRun.detectedFromVersion,
                'updater preamble must report the 1.7.0 starting version',
            ).toBe(704);

            // 1.7.0's config.version is 704; 705 is the first migration
            // applicable to it. After 705 the chain runs straight to 805.
            // Locking the exact list keeps a future store.json shuffle
            // (e.g. an inserted 706) from silently widening the surface.
            expect(
                firstUpdaterRun.migrationsApplied,
                '1.7.0 → 2.0 walk should run 705, 801, 802, 803, 804, 805',
            ).toEqual([705, 801, 802, 803, 804, 805]);

            expect(
                firstUpdaterRun.landedOnActiveMarker,
                'first updater run must land on the "Updated successfully" marker',
            ).toBe(true);

            // Sanity: the v2.0 updater chrome is the post-#1123 testable
            // shape. The body must carry the documented testid hooks so
            // a follow-up screenshot/E2E gallery can anchor on them.
            expect(firstUpdaterRun.body).toContain('data-testid="updater-progress"');
            expect(firstUpdaterRun.body).toContain('data-testid="updater-return"');
        });

        await test.step('3. Re-running the updater is a no-op (idempotency)', async () => {
            const second = await assertUpdaterIsIdempotent(request);
            expect(
                second.detectedFromVersion,
                'idempotent re-run must report the post-walk version (805)',
            ).toBe(805);
        });

        await test.step('4. Schema parity: post-upgrade matches a fresh struc+data install', async () => {
            const upgradedSchema = await dumpSchema(UPGRADE_DB);
            const freshSchema = await dumpSchema(FRESH_DB);

            const actualDiff = diffSchemas(upgradedSchema, freshSchema);

            // Snapshot contract: the checked-in `schema.diff` file is
            // the LOCKED baseline. Empty file = full parity (target
            // state); non-empty file = known drift, documented in the
            // PR's deferred-followups list. Any drift NOT in the
            // snapshot is a regression and the spec fails — that's
            // exactly the gate the issue calls for.
            //
            // We snapshot the actual diff against the file (not via
            // toMatchSnapshot which is project-aware and fragile across
            // chromium / mobile-chromium). If the snapshot doesn't
            // exist (first-run scaffolding), we write it; otherwise we
            // assert byte-equality.
            const expectedDiff = await readSnapshotIfExists(SNAPSHOT_SCHEMA_DIFF);
            if (expectedDiff === null) {
                await mkdir(dirname(SNAPSHOT_SCHEMA_DIFF), { recursive: true });
                await writeFile(SNAPSHOT_SCHEMA_DIFF, actualDiff, 'utf8');
                // First-run scaffolding: emit a debug line so the run
                // log makes the auto-write obvious. Subsequent runs
                // hit the equality branch.
                // eslint-disable-next-line no-console
                console.warn(
                    `[upgrade-1.7.0] snapshot ${SNAPSHOT_SCHEMA_DIFF} did not exist; ` +
                        `wrote current diff (${actualDiff.length} bytes). ` +
                        `Commit it as the locked baseline.`,
                );
            } else {
                expect(
                    actualDiff,
                    `schema parity drifted from the locked snapshot at __snapshots__/1.7.0/schema.diff. ` +
                        `Either fix the migration that caused the drift, or update the snapshot ` +
                        `(after filing a follow-up issue). Full upgrade dump:\n` +
                        formatSchemaForDiff(upgradedSchema),
                ).toBe(expectedDiff);
            }
        });

        await test.step('5. Settings parity: every fresh data.sql key exists post-upgrade', async () => {
            const upgradedSettings = await dumpSettings(UPGRADE_DB);
            const freshSettings = await dumpSettings(FRESH_DB);

            const missing = missingSettingsKeys(upgradedSettings, freshSettings);
            expect(
                missing,
                'every key the v2.0 fresh install ships in :prefix_settings must exist on an upgraded DB',
            ).toEqual([]);
        });

        await test.step('6. Smoke flow: admin/admin logs in against the upgraded DB', async () => {
            // We're inside the same test, but `request` is API-only.
            // Drive the form through the real browser to exercise the
            // login flow end-to-end (including JWT cookie minting +
            // session bootstrap).
            await page.goto('/index.php?p=login');
            await page.locator('[data-testid="login-username"]').fill('admin');
            await page.locator('[data-testid="login-password"]').fill('admin');
            await page.locator('[data-testid="login-submit"]').click();

            // Same terminal-state assertion as the regular login spec
            // (`smoke/login.spec.ts`): the topbar account link is only
            // rendered when CUserManager sees a logged-in user.
            await expect(page.locator('[data-testid="nav-account"]')).toBeAttached({
                timeout: 30_000,
            });
        });
    });
});

// ---------------------------------------------------------------------------
// Local helpers (pulling the fixture's pre-rendered config.php into the live
// panel slot before render-config rewrites DB_NAME + strips SB_SECRET_KEY).
// Lives here rather than in `_helpers/` because it's spec-shape glue, not a
// reusable primitive.

async function renderPanelConfigFromTemplate(
    sourceInContainer: string,
    targetInContainer: string,
): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'cp' : 'docker';
    const cmdArgs = inContainer
        ? [sourceInContainer, targetInContainer]
        : ['compose', 'exec', '-T', 'web', 'cp', sourceInContainer, targetInContainer];

    try {
        await execFileP(cmd, cmdArgs, { maxBuffer: 8 * 1024 * 1024, cwd: process.cwd() });
    } catch (err) {
        const e = err as NodeJS.ErrnoException & { stdout?: string; stderr?: string };
        throw new Error(
            `cp ${sourceInContainer} ${targetInContainer} failed: ${e.message}\n` +
                `stdout:\n${e.stdout ?? ''}\nstderr:\n${e.stderr ?? ''}`,
        );
    }
}

async function readSnapshotIfExists(path: string): Promise<string | null> {
    try {
        return await readFile(path, 'utf8');
    } catch (err) {
        const e = err as NodeJS.ErrnoException;
        if (e.code === 'ENOENT') return null;
        throw err;
    }
}

async function assertContainerFileExists(path: string): Promise<void> {
    if (await containerFileExists(path)) return;
    throw new Error(
        `upgrade harness expected ${path} to be staged inside the web container, ` +
            `but it doesn't exist. Run \`./sbpp.sh upgrade-e2e\` (which stages ` +
            `fixtures/upgrade/<version>.sql.gz + config.<version>.php into /tmp/ ` +
            `before Playwright spawns), or stage manually:\n` +
            `  docker compose cp fixtures/upgrade/1.7.0.sql.gz     web:/tmp/sbpp-upgrade-1.7.0.sql.gz\n` +
            `  docker compose cp fixtures/upgrade/config.1.7.0.php web:/tmp/sbpp-upgrade-config-1.7.0.php`,
    );
}

/**
 * Check whether a path exists inside the web container. Returns
 * boolean rather than throwing so callers can choose between
 * `test.skip` (the spec was pulled in incidentally without staging)
 * and an explicit error (the wrapper claimed to stage and didn't).
 *
 * Mode parity with the rest of the harness: `node:fs` when running
 * in-container under `./sbpp.sh upgrade-e2e`, `docker compose exec
 * test -f` when running host-side.
 */
async function containerFileExists(path: string): Promise<boolean> {
    if (process.env.E2E_IN_CONTAINER === '1') {
        try {
            await access(path);
            return true;
        } catch {
            return false;
        }
    }
    try {
        await execFileP('docker', ['compose', 'exec', '-T', 'web', 'test', '-f', path], {
            maxBuffer: 1024 * 1024,
            cwd: process.cwd(),
        });
        return true;
    } catch {
        return false;
    }
}
