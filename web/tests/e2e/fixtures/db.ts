/**
 * Bridge between Playwright (TypeScript) and the PHPUnit Fixture (PHP)
 * that owns the `sourcebans_e2e` database.
 *
 * The actual install/reset logic lives in
 * `web/tests/e2e/scripts/reset-e2e-db.php` which reuses
 * `Sbpp\Tests\Fixture` — same renderer, same struc.sql + data.sql, same
 * seeded admin row (admin/admin) — but pointed at a dedicated
 * `sourcebans_e2e` schema so PHPUnit's `sourcebans_test` and the dev
 * `sourcebans` DB stay untouched.
 *
 * Two execution modes are supported:
 *
 * 1. Host-side (`E2E_IN_CONTAINER` unset/empty): we shell out via
 *    `docker compose exec -T web php …`. Used when the spec runs from
 *    the host (e.g. `npx playwright test` invoked manually with the
 *    panel reachable on a published port).
 * 2. In-container (`E2E_IN_CONTAINER=1`): we invoke `php` directly
 *    because we're already inside the web container. `./sbpp.sh e2e`
 *    flips this on so the suite doesn't need a Docker socket inside
 *    the container.
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileP = promisify(execFile);

const SCRIPT_INSIDE_CONTAINER = '/var/www/html/web/tests/e2e/scripts/reset-e2e-db.php';
const SEED_COMMS_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/seed-comms-e2e.php';

/**
 * Run the PHP shim that drives `Sbpp\Tests\Fixture` against
 * `sourcebans_e2e`. `args` is forwarded as-is.
 */
async function runReset(args: string[] = []): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SCRIPT_INSIDE_CONTAINER, ...args]
        : ['compose', 'exec', '-T', 'web', 'php', SCRIPT_INSIDE_CONTAINER, ...args];

    try {
        await execFileP(cmd, cmdArgs, {
            // Generous buffer: the install path can emit a few KB of
            // PDO warnings on a freshly-created DB. Reset prints far
            // less but the cap stays the same.
            maxBuffer: 8 * 1024 * 1024,
            // The host-side path uses `docker compose` which resolves
            // both docker-compose.yml and any worktree-local
            // override (per AGENTS.md "Parallel stacks") from cwd.
            cwd: inContainer ? undefined : process.cwd(),
        });
    } catch (err) {
        const e = err as NodeJS.ErrnoException & { stdout?: string; stderr?: string };
        const stdout = e.stdout ?? '';
        const stderr = e.stderr ?? '';
        throw new Error(
            `reset-e2e-db.php (${args.join(' ') || 'install'}) failed: ${e.message}\n` +
                `stdout:\n${stdout}\nstderr:\n${stderr}`,
        );
    }
}

/**
 * Drop + recreate `sourcebans_e2e` from the install/sql templates and
 * seed the default admin row. Called once from `global-setup.ts`.
 */
export async function resetE2eDb(): Promise<void> {
    await runReset([]);
}

/**
 * Truncate every table in `sourcebans_e2e` and re-seed the rows
 * `data.sql` provides + the admin. Cheaper than a full install and
 * preferred between specs (see Fixture::reset()).
 */
export async function truncateE2eDb(): Promise<void> {
    await runReset(['--truncate']);
}

/**
 * Per-row shape consumed by `seedCommsRawE2e`. `type=silence` maps to
 * `:prefix_comms.type=3` — the SourceComms-fork combined-block label
 * the chip filter (#1274) recognises but `Actions.CommsAdd` doesn't
 * directly emit. See `web/tests/e2e/scripts/seed-comms-e2e.php` for
 * the full `state→length/ends/RemoveType` mapping.
 */
export interface CommsSeedRow {
    steam: string;
    nickname: string;
    type: 'mute' | 'gag' | 'silence';
    state: 'active' | 'unmuted' | 'expired' | 'permanent';
    reason?: string;
}

/**
 * Seed comm-block rows directly via the SQL layer (bypasses
 * `Actions.CommsAdd`). Used by `comms-filter-chips.spec.ts` because
 * the chip filter has to handle `type=3` (silence) rows that the
 * normal API path never emits, plus mixed states (`unmuted`,
 * `expired`, `permanent`) that would otherwise require driving
 * separate API actions per row.
 *
 * Caller responsibility: invoke after `truncateE2eDb()` so the seeded
 * rows are the only ones the spec sees.
 */
export async function seedCommsRawE2e(rows: CommsSeedRow[]): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SEED_COMMS_INSIDE_CONTAINER]
        : ['compose', 'exec', '-T', 'web', 'php', SEED_COMMS_INSIDE_CONTAINER];

    const child = execFile(cmd, cmdArgs, {
        // Generous buffer matches runReset; the seeder prints a single
        // confirmation line in the happy path but a noisy PHP stack
        // trace on failure.
        maxBuffer: 8 * 1024 * 1024,
        cwd: inContainer ? undefined : process.cwd(),
    });

    let stdout = '';
    let stderr = '';
    child.stdout?.on('data', (chunk: Buffer) => { stdout += chunk.toString('utf8'); });
    child.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString('utf8'); });

    child.stdin?.write(JSON.stringify(rows));
    child.stdin?.end();

    await new Promise<void>((resolve, reject) => {
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(
                `seed-comms-e2e.php exited ${code}\n`
                + `stdout:\n${stdout}\nstderr:\n${stderr}`,
            ));
        });
    });
}
