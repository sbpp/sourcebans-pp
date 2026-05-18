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
const SEED_COMMENTS_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/seed-comments-e2e.php';
const SEED_ANNOUNCEMENTS_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/seed-announcements-e2e.php';
const SEED_LOSTPASSWORD_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/seed-lostpassword-e2e.php';
const SET_SETTING_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/set-setting-e2e.php';
const ORPHAN_BAN_AID_INSIDE_CONTAINER =
    '/var/www/html/web/tests/e2e/scripts/orphan-ban-aid-e2e.php';

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

/**
 * Per-row shape consumed by `seedCommentsRawE2e`. `type='B'` attaches
 * the comment to a ban row (the bid is the bans table primary key);
 * `type='C'` attaches to a comm-block row (the bid here is the
 * comms table cid — the column was reused from v1.x without a rename).
 *
 * Used by the banlist-comments-visibility spec because there is no
 * JSON action for adding admin-authored per-row comments — the
 * production write path is the legacy `?p=banlist&comment=N` POST
 * handler. Driving an HTML POST through Playwright would couple the
 * spec to the comment-edit chrome and CSRF handshake, which is
 * unrelated to what we're verifying (the disclosure renders, the
 * drawer paints the same data).
 */
export interface CommentSeedRow {
    type: 'B' | 'C';
    bid: number;
    text: string;
}

/**
 * Seed `:prefix_comments` rows directly via the SQL layer. Caller
 * responsibility: the parent ban / comm row must already exist
 * (`seedBanViaApi` / `seedCommsRawE2e`); the e2e DB must already be
 * truncated.
 */
export async function seedCommentsRawE2e(rows: CommentSeedRow[]): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SEED_COMMENTS_INSIDE_CONTAINER]
        : ['compose', 'exec', '-T', 'web', 'php', SEED_COMMENTS_INSIDE_CONTAINER];

    const child = execFile(cmd, cmdArgs, {
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
                `seed-comments-e2e.php exited ${code}\n`
                + `stdout:\n${stdout}\nstderr:\n${stderr}`,
            ));
        });
    });
}

/**
 * Per-row shape consumed by `seedAnnouncementsE2e`. Mirrors the
 * `docs/public/announcements.json` schema that
 * `Sbpp\Announce\AnnouncementFetcher::parseEntries` validates.
 */
export interface AnnouncementSeedRow {
    id: string;
    title: string;
    body_md?: string;
    url?: string;
    published_at?: string;
    expires_at?: string;
}

/**
 * Write an announcements cache file directly to `SB_CACHE` so the
 * dashboard renders the strip without doing the upstream HTTPS
 * fetch. Call `clearAnnouncementsCacheE2e()` in the spec's `afterAll`
 * (or `afterEach`) to undo. The cache file is shared between specs
 * (single SB_CACHE inside the dev container), so a spec that seeds
 * an announcement must clean up afterwards.
 */
export async function seedAnnouncementsE2e(rows: AnnouncementSeedRow[]): Promise<void> {
    await runAnnouncementsHelper(JSON.stringify(rows), []);
}

/**
 * Drop the announcements cache file. Idempotent.
 */
export async function clearAnnouncementsCacheE2e(): Promise<void> {
    await runAnnouncementsHelper(null, ['--clear']);
}

/**
 * Pre-seeded state for the lostpassword happy-path spec (#1403):
 *   - `email` is the seeded admin's address.
 *   - `token` is the `:prefix_admins.validate` value the spec must
 *     pass in `?validation=<token>` so the success branch fires.
 */
export interface LostpasswordSeed {
    email: string;
    token: string;
}

/**
 * Seed the SMTP config (pointing at the dev stack's mailpit) and
 * write a known `:prefix_admins.validate` token for the seeded
 * admin row. Returns `{email, token}` so the spec can drive the
 * `?p=lostpassword&email=…&validation=…` URL deterministically.
 *
 * Mirrors `seedAnnouncementsE2e` / `seedCommsRawE2e`: shells out to
 * `web/tests/e2e/scripts/seed-lostpassword-e2e.php` which is the
 * single source of truth for which settings the happy-path requires
 * (kept out of the TS side so the schema-shape of `:prefix_settings`
 * stays Python-style invisible to the spec author).
 */
export async function seedLostpasswordE2e(): Promise<LostpasswordSeed> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SEED_LOSTPASSWORD_INSIDE_CONTAINER]
        : ['compose', 'exec', '-T', 'web', 'php', SEED_LOSTPASSWORD_INSIDE_CONTAINER];

    const { stdout, stderr } = await execFileP(cmd, cmdArgs, {
        maxBuffer: 1 * 1024 * 1024,
        cwd: inContainer ? undefined : process.cwd(),
    });
    const trimmed = stdout.trim();
    if (trimmed === '') {
        throw new Error(`seed-lostpassword-e2e.php: empty stdout\nstderr:\n${stderr}`);
    }
    try {
        const parsed = JSON.parse(trimmed) as LostpasswordSeed;
        if (typeof parsed.email !== 'string' || typeof parsed.token !== 'string') {
            throw new Error('missing email/token keys');
        }
        return parsed;
    } catch (err) {
        const msg = err instanceof Error ? err.message : String(err);
        throw new Error(
            `seed-lostpassword-e2e.php: malformed stdout (${msg})\nstdout:\n${trimmed}\nstderr:\n${stderr}`,
        );
    }
}

/**
 * Set a single `:prefix_settings` row in the e2e DB. Useful for
 * feature toggles that ship disabled in `data.sql` but need to be
 * on for a spec (e.g. `config.enablegroupbanning` for the
 * group-ban dispatcher regression in #1402). Mirrors the
 * `REPLACE INTO sb_settings` shape that `BansTest.php` uses for the
 * same reason. Caller is responsible for reverting in afterEach;
 * the e2e DB is shared between specs.
 */
export async function setSettingE2e(setting: string, value: string): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SET_SETTING_INSIDE_CONTAINER]
        : ['compose', 'exec', '-T', 'web', 'php', SET_SETTING_INSIDE_CONTAINER];

    const child = execFile(cmd, cmdArgs, {
        maxBuffer: 8 * 1024 * 1024,
        cwd: inContainer ? undefined : process.cwd(),
    });

    let stdout = '';
    let stderr = '';
    child.stdout?.on('data', (chunk: Buffer) => { stdout += chunk.toString('utf8'); });
    child.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString('utf8'); });

    child.stdin?.write(JSON.stringify({ setting, value }));
    child.stdin?.end();

    await new Promise<void>((resolve, reject) => {
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(
                `set-setting-e2e.php exited ${code}\n`
                + `stdout:\n${stdout}\nstderr:\n${stderr}`,
            ));
        });
    });
}

/**
 * Orphan a `:prefix_bans` row by UPDATE-ing its `aid` to a value that
 * doesn't exist in `:prefix_admins`. Triggers the `page.banlist.php`
 * capital-NOT branch (`'Player NOT Unbanned'`, L159 post-#1409) which
 * fires when the admin INNER JOIN at L72 returns empty even though the
 * bans row itself still exists — the documented "destructive action
 * FAILED" branch the #1409 follow-up converts to a persistent toast
 * (`duration_ms: 0`).
 *
 * Caller responsibility: pass a `bid` returned from `seedBanViaApi` so
 * the underlying bans row has the right shape for the L83 SELECT to
 * resolve; `new_aid` defaults to 99999 (well outside any realistic
 * admin-id sequence — matches the `NONEXISTENT_BID` convention in
 * `banlist-getfallback-toast.spec.ts`). The shim refuses to overwrite
 * with an aid that DOES exist in `:prefix_admins` so a future change
 * to the seed admin layout doesn't silently degrade the scenario.
 *
 * Used by `toast-persistent-duration.spec.ts`. Mirrors the shape of
 * `setSettingE2e`'s shell-out + stdin-JSON pattern.
 */
export async function orphanBanAidE2e(bid: number, newAid = 99999): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [ORPHAN_BAN_AID_INSIDE_CONTAINER]
        : ['compose', 'exec', '-T', 'web', 'php', ORPHAN_BAN_AID_INSIDE_CONTAINER];

    const child = execFile(cmd, cmdArgs, {
        maxBuffer: 8 * 1024 * 1024,
        cwd: inContainer ? undefined : process.cwd(),
    });

    let stdout = '';
    let stderr = '';
    child.stdout?.on('data', (chunk: Buffer) => { stdout += chunk.toString('utf8'); });
    child.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString('utf8'); });

    child.stdin?.write(JSON.stringify({ bid, new_aid: newAid }));
    child.stdin?.end();

    await new Promise<void>((resolve, reject) => {
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(
                `orphan-ban-aid-e2e.php exited ${code}\n`
                + `stdout:\n${stdout}\nstderr:\n${stderr}`,
            ));
        });
    });
}

async function runAnnouncementsHelper(
    stdin: string | null,
    extraArgs: string[],
): Promise<void> {
    const inContainer = process.env.E2E_IN_CONTAINER === '1';
    const cmd = inContainer ? 'php' : 'docker';
    const cmdArgs = inContainer
        ? [SEED_ANNOUNCEMENTS_INSIDE_CONTAINER, ...extraArgs]
        : ['compose', 'exec', '-T', 'web', 'php', SEED_ANNOUNCEMENTS_INSIDE_CONTAINER, ...extraArgs];

    const child = execFile(cmd, cmdArgs, {
        maxBuffer: 8 * 1024 * 1024,
        cwd: inContainer ? undefined : process.cwd(),
    });

    let stdout = '';
    let stderr = '';
    child.stdout?.on('data', (chunk: Buffer) => { stdout += chunk.toString('utf8'); });
    child.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString('utf8'); });

    if (stdin !== null) {
        child.stdin?.write(stdin);
    }
    child.stdin?.end();

    await new Promise<void>((resolve, reject) => {
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(
                `seed-announcements-e2e.php exited ${code}\n`
                + `stdout:\n${stdout}\nstderr:\n${stderr}`,
            ));
        });
    });
}
