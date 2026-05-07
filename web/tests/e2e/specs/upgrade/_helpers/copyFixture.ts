/**
 * Stash + restore the panel's `config.php` between upgrade-spec lifetimes (#1269).
 *
 * The fixture-staging copy (`fixtures/upgrade/<v>.sql.gz` + matching
 * `config.<v>.php` → `/tmp/sbpp-upgrade-*` inside the web container)
 * is the wrapper's responsibility (`./sbpp.sh upgrade-e2e` does this
 * via `docker compose cp` before spawning Playwright). The spec only
 * needs to *verify* the staging happened — it never copies host-side
 * paths inside the container, which would couple the spec to the host
 * filesystem layout.
 *
 * What lives here is the spec-side stash/restore for the live
 * `web/config.php`, the only file the spec actually mutates on the
 * panel itself. The wrapper layers ITS OWN stash
 * (`config.php.upgrade-e2e-stash`) on top so an aborted process
 * (SIGINT, panic) still recovers without leaking the upgrade-DB
 * pointer into a developer's interactive session.
 */

import { execFile } from 'node:child_process';
import { copyFile } from 'node:fs/promises';
import { promisify } from 'node:util';

const execFileP = promisify(execFile);

/**
 * Copy the panel's live `config.php` to a stash path inside the
 * container. The spec calls this in `beforeAll` so an `afterAll` hook
 * can put it back even if a mid-spec `expect` failure aborts the run.
 *
 * Idempotent — overwrites the stash on every call. If the live config
 * is already pointing at a previous upgrade DB (e.g. a prior aborted
 * run), the caller is expected to have invoked the wrapper's restore
 * step before calling this one. The wrapper's `trap` also handles the
 * worst-case "the spec process crashed without `afterAll`" path.
 */
export async function stashPanelConfig(configPath: string, stashPath: string): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    if (inContainer) {
        await copyFile(configPath, stashPath);
        return;
    }

    // Host-side: stash via the container so file ownership stays the same
    // as the live config (avoids the "stash owned by host UID, restore
    // can't write" failure mode).
    const args = ['compose', 'exec', '-T', 'web', 'cp', configPath, stashPath];
    try {
        await execFileP('docker', args, {
            maxBuffer: 8 * 1024 * 1024,
            cwd: process.cwd(),
        });
    } catch (err) {
        const e = err as NodeJS.ErrnoException & { stdout?: string; stderr?: string };
        throw new Error(
            `docker compose exec cp ${configPath} ${stashPath} failed: ${e.message}\n` +
                `stdout:\n${e.stdout ?? ''}\nstderr:\n${e.stderr ?? ''}`,
        );
    }
}
