/**
 * Drive the v1.x → v2.x upgrade flow over HTTP and parse the runner's output (#1269).
 *
 * Two endpoints, two distinct surfaces:
 *
 *   1. `/upgrade.php` — appends `define('SB_SECRET_KEY', '<random>')` to
 *      `config.php` if the constant isn't defined, then exits. Side-effect-
 *      free on a panel that already has the key.
 *   2. `/updater/index.php` — walks `web/updater/store.json` and applies
 *      every migration newer than `:prefix_settings.config.version` in
 *      numeric order. Two terminal states:
 *
 *      a. "Updated successfully. Please delete the /updater folder."
 *         (some migrations were applied this run).
 *      b. "Installation up-to-date." (nothing to apply — fresh install,
 *         or the second run of an idempotent updater).
 *
 *      Both surface in the same `<li>` list. The harness distinguishes the
 *      two by whether any "Running Update: <bold>N</bold>" lines were
 *      emitted: zero means a no-op, ≥1 means active migration.
 *
 * The 1.x panel chrome doesn't carry `data-testid` hooks (those landed
 * with #1123's testability rewrite), but we never actually drive the 1.x
 * UI — the harness operates entirely against the v2.x updater chrome
 * served from the worktree, which DOES carry hooks
 * (`data-testid="updater-progress"`, `updater-cleanup`, `updater-return`).
 * String anchors below are documented inline; if they ever drift, the
 * assertion fails loudly here rather than silently passing through.
 */

import type { APIRequestContext } from '@playwright/test';
import { expect } from '@playwright/test';

/** Markers the updater emits on the two terminal landings. */
const ACTIVE_MIGRATION_MARKER = 'Updated successfully. Please delete the /updater folder.';
const NO_OP_MARKER = 'Installation up-to-date.';

/** Result of one updater pass — useful for asserting on which migrations ran. */
export interface UpdaterRunResult {
    /** Raw HTML body of the updater response. Useful for snapshot/diagnostics. */
    body: string;
    /**
     * Numeric ids of every migration that ran this pass, in order. Empty
     * on a no-op run. Parsed from `<li>Running Update: <b>NNN</b></li>`
     * lines.
     */
    migrationsApplied: number[];
    /** Detected DB version reported by `Checking current database version...`. */
    detectedFromVersion: number | null;
    /** True when the body lands on the active-migration terminal marker. */
    landedOnActiveMarker: boolean;
    /** True when the body lands on the no-op terminal marker. */
    landedOnNoOpMarker: boolean;
}

/**
 * Hit `/upgrade.php` once. Two acceptable post-conditions:
 *
 *   a. `config.php` had no `SB_SECRET_KEY` line; the script appended one
 *      and rendered "config.php updated correctly".
 *   b. The constant was already defined; the script printed "Upgrade not
 *      needed." and exited.
 *
 * Either body is a success; we only fail on a PHP error or an HTTP error.
 */
export async function runUpgradeEndpoint(req: APIRequestContext): Promise<string> {
    const res = await req.get('/upgrade.php');
    expect(res.status(), 'GET /upgrade.php should return 2xx').toBeLessThan(400);

    const body = await res.text();

    if (body.includes('Fatal error') || body.includes('Parse error')) {
        throw new Error(`/upgrade.php returned a PHP error:\n${body}`);
    }

    return body;
}

/**
 * Hit `/updater/index.php` once and parse the runner's output. Asserts
 * the response is HTTP 2xx and lands on one of the two terminal markers
 * — see {@link UpdaterRunResult} for the call sites that use the
 * returned shape.
 *
 * This DOES NOT assert that any migrations actually ran; that's the
 * caller's job (the first run expects ≥1, the idempotent re-run expects
 * exactly 0). Splitting "drive once + parse" from "assert what ran"
 * keeps the helper composable for future fixtures (1.8.4 et al.).
 */
export async function runUpdaterEndpoint(req: APIRequestContext): Promise<UpdaterRunResult> {
    const res = await req.get('/updater/index.php');
    expect(res.status(), 'GET /updater/index.php should return 2xx').toBeLessThan(400);

    const body = await res.text();

    if (body.includes('Fatal error') || body.includes('Parse error')) {
        throw new Error(`/updater/index.php returned a PHP error:\n${body}`);
    }

    const landedOnActiveMarker = body.includes(ACTIVE_MIGRATION_MARKER);
    const landedOnNoOpMarker = body.includes(NO_OP_MARKER);

    expect(
        landedOnActiveMarker || landedOnNoOpMarker,
        'updater body should land on either "Updated successfully" or "Installation up-to-date"',
    ).toBe(true);

    const migrationsApplied = parseMigrationsApplied(body);
    const detectedFromVersion = parseDetectedFromVersion(body);

    return { body, migrationsApplied, detectedFromVersion, landedOnActiveMarker, landedOnNoOpMarker };
}

/**
 * Run the updater a second time and assert it's a no-op: the body lands
 * on the "Installation up-to-date" marker AND no migrations were
 * applied. This is the dominant operator-failure mode the harness is
 * built to catch — a non-idempotent migration silently re-applies and
 * breaks every subsequent upgrade. Migration 801 is a known offender
 * (filed as a follow-up, see PR body).
 */
export async function assertUpdaterIsIdempotent(req: APIRequestContext): Promise<UpdaterRunResult> {
    const result = await runUpdaterEndpoint(req);
    expect(
        result.landedOnNoOpMarker,
        'second updater run must land on the "Installation up-to-date" marker',
    ).toBe(true);
    expect(
        result.migrationsApplied,
        'second updater run must apply zero migrations (idempotent re-run)',
    ).toEqual([]);
    return result;
}

/**
 * Parse `<li>Running Update: <b>NNN</b></li>` lines into a list of
 * migration numbers. The runner emits one such line per applied
 * migration; the count tells us whether any work happened this pass.
 */
function parseMigrationsApplied(body: string): number[] {
    const re = /Running Update:\s*<b>\s*(\d+)\s*<\/b>/g;
    const out: number[] = [];
    let m: RegExpExecArray | null;
    while ((m = re.exec(body)) !== null) {
        out.push(Number(m[1]));
    }
    return out;
}

/**
 * Parse `Checking current database version... <b> NNN</b>` from the
 * runner's preamble. Returns null when the marker is missing (e.g. a
 * fatal error before the runner emits anything).
 */
function parseDetectedFromVersion(body: string): number | null {
    const m = /Checking current database version\.\.\.\s*<b>\s*(\d+)\s*<\/b>/.exec(body);
    return m ? Number(m[1]) : null;
}
