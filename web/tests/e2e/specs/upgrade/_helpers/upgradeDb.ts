/**
 * Upgrade-spec DB lifecycle bridge (#1269).
 *
 * Thin TypeScript wrapper over `_helpers/scripts/upgrade-db.php`. Lives
 * separately from `web/tests/e2e/fixtures/db.ts` because the upgrade
 * harness has a *different* DB lifecycle from the rest of the e2e
 * suite:
 *
 *   - Regular e2e specs share `sourcebans_e2e` (truncate + reseed
 *     between specs).
 *   - The upgrade spec creates its own throwaway `sourcebans_upgrade_*`
 *     schemas (drop + recreate per test), drives the panel against
 *     them, and never touches `sourcebans_e2e`.
 *
 * Mode parity: both `db.ts` and this module support host-side
 * (`docker compose exec`) and in-container (`php` directly) calls,
 * gated on `E2E_IN_CONTAINER`. `./sbpp.sh upgrade-e2e` flips the env
 * var on (we're already inside the web container by the time
 * Playwright spawns), so the production path is the in-container
 * branch; the host-side branch keeps `npx playwright test --grep
 * @upgrade` workable from a host shell for ad-hoc local debugging.
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileP = promisify(execFile);

const SCRIPT_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/specs/upgrade/_helpers/scripts/upgrade-db.php';

/** Run the PHP helper with the given subcommand + args. */
async function runPhp(args: string[]): Promise<string> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SCRIPT_INSIDE_CONTAINER, ...args]
        : ['compose', 'exec', '-T', 'web', 'php', SCRIPT_INSIDE_CONTAINER, ...args];

    try {
        const { stdout } = await execFileP(cmd, cmdArgs, {
            // Schema dumps are several KB. 8 MB is the same cap
            // `db.ts` uses; it's plenty.
            maxBuffer: 8 * 1024 * 1024,
            cwd: inContainer ? undefined : process.cwd(),
        });
        return stdout;
    } catch (err) {
        const e = err as NodeJS.ErrnoException & { stdout?: string; stderr?: string };
        const stdout = e.stdout ?? '';
        const stderr = e.stderr ?? '';
        throw new Error(
            `upgrade-db.php (${args.join(' ')}) failed: ${e.message}\n` +
                `stdout:\n${stdout}\nstderr:\n${stderr}`,
        );
    }
}

/**
 * Drop + re-create `<db>` and load the gunzipped 1.x fixture from
 * `<fixturePath>` into it. `fixturePath` is read inside the container,
 * so callers must arrange for the fixture file to be reachable from
 * `/var/www/html/web/...` or `/tmp/` on the container side (the
 * `./sbpp.sh upgrade-e2e` wrapper `docker compose cp`s it to
 * `/tmp/sbpp-upgrade-<version>.sql.gz`).
 */
export async function resetUpgradeDb(db: string, fixturePath: string): Promise<void> {
    await runPhp(['reset-upgrade-db', db, fixturePath]);
}

/**
 * Drop + re-create `<db>` and install a fresh `struc.sql + data.sql`
 * pair into it. Used as the reference shape the post-upgrade schema
 * is compared against in the parity assertions.
 */
export async function installFreshReference(db: string): Promise<void> {
    await runPhp(['install-fresh', db]);
}

/**
 * JSON shape returned by `dump-schema`. Kept loose on purpose — the
 * harness only compares the value of the JSON serialization byte-for
 * -byte, not individual columns/indexes. If a future slice adds
 * field-level assertions, tighten this shape then.
 */
export interface SchemaDump {
    [tableName: string]: {
        engine: string | null;
        collation: string | null;
        columns: Array<Record<string, unknown>>;
        indexes: Array<{ name: string; unique: boolean; type: string; columns: string[] }>;
    };
}

/** Dump the schema of `<db>` as a normalized JSON object. */
export async function dumpSchema(db: string): Promise<SchemaDump> {
    const stdout = await runPhp(['dump-schema', db]);
    return JSON.parse(stdout) as SchemaDump;
}

/** Dump `:prefix_settings` as a setting -> value map. */
export async function dumpSettings(db: string): Promise<Record<string, string>> {
    const stdout = await runPhp(['dump-settings', db]);
    return JSON.parse(stdout) as Record<string, string>;
}

/**
 * Rewrite the panel's config.php to point at `<db>` and drop any
 * `SB_SECRET_KEY` line so /upgrade.php hits the append-the-key
 * branch on the next request. Idempotent.
 */
export async function renderPanelConfig(configPath: string, db: string): Promise<void> {
    await runPhp(['render-config', configPath, db]);
}

/**
 * Restore the panel's config.php from a previously stashed copy.
 * Idempotent — no-op if `stashPath` doesn't exist.
 */
export async function restorePanelConfig(configPath: string, stashPath: string): Promise<void> {
    await runPhp(['restore-config', configPath, stashPath]);
}
