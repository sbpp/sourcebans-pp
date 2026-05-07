/**
 * Schema + settings parity helpers for the upgrade harness (#1269).
 *
 * The contract: a v1.x DB walked through every applicable migration
 * MUST converge to the same shape as a fresh v2.x install. The
 * harness diffs both sides and snapshots any drift under
 * `__snapshots__/<version>/schema.diff` so:
 *
 *   - the spec stays green when there's no drift (snapshot empty),
 *   - the spec fails loudly when a migration regresses parity,
 *   - the snapshot itself is a checked-in record of *known* drift
 *     while individual fixes ship in their own follow-up PRs (see
 *     the PR body for the open list).
 *
 * Everything here is pure-string + JSON; no Playwright APIs. Easy to
 * call from a Node CLI as well if a future slice wants to dump
 * cross-version drift outside Playwright.
 */

import type { SchemaDump } from './upgradeDb';

/**
 * Produce a stable, line-by-line text view of a normalized schema
 * dump. We reuse JSON-with-sorted-keys because the dump is already
 * sorted (table order, column order, index order) by the PHP helper;
 * the JSON serialization is just the output format. Each top-level
 * element is on its own line so a `diff` reads cleanly.
 */
export function formatSchemaForDiff(schema: SchemaDump): string {
    return JSON.stringify(schema, null, 2) + '\n';
}

/**
 * Generate a unified-diff-style view of two schema dumps, suitable
 * for comparing against a checked-in snapshot.
 *
 * Strategy: walk both dumps' table lists, descend into columns +
 * indexes, and emit "+", "-", "~" lines for adds/drops/diffs. The
 * output is human-readable and stable across runs; not byte-equal to
 * `diff -u` (no hunk headers) but cleaner for snapshot review.
 *
 * Returns an empty string when the two dumps are identical.
 */
export function diffSchemas(upgraded: SchemaDump, fresh: SchemaDump): string {
    const lines: string[] = [];
    const allTables = new Set<string>([...Object.keys(upgraded), ...Object.keys(fresh)]);
    const sortedTables = [...allTables].sort();

    for (const table of sortedTables) {
        const u = upgraded[table];
        const f = fresh[table];

        if (!u && f) {
            lines.push(`+ table ${table} (only in fresh install)`);
            continue;
        }
        if (u && !f) {
            lines.push(`- table ${table} (only in post-upgrade DB)`);
            continue;
        }
        if (!u || !f) continue;

        const tableDiff = diffTable(table, u, f);
        if (tableDiff.length > 0) {
            lines.push(...tableDiff);
        }
    }

    return lines.length === 0 ? '' : lines.join('\n') + '\n';
}

function diffTable(
    name: string,
    upgraded: SchemaDump[string],
    fresh: SchemaDump[string],
): string[] {
    const lines: string[] = [];

    if (upgraded.engine !== fresh.engine) {
        lines.push(`~ ${name}: engine ${upgraded.engine} → ${fresh.engine}`);
    }
    if (upgraded.collation !== fresh.collation) {
        lines.push(`~ ${name}: collation ${upgraded.collation} → ${fresh.collation}`);
    }

    const colByName = (cols: SchemaDump[string]['columns']): Map<string, Record<string, unknown>> =>
        new Map(cols.map((c) => [String(c.COLUMN_NAME), c]));
    const uCols = colByName(upgraded.columns);
    const fCols = colByName(fresh.columns);
    const allCols = new Set<string>([...uCols.keys(), ...fCols.keys()]);
    for (const colName of [...allCols].sort()) {
        const uc = uCols.get(colName);
        const fc = fCols.get(colName);
        if (!uc && fc) {
            lines.push(`+ ${name}.${colName} (only in fresh install)`);
            continue;
        }
        if (uc && !fc) {
            lines.push(`- ${name}.${colName} (only in post-upgrade DB)`);
            continue;
        }
        if (!uc || !fc) continue;

        const colDiff = diffColumn(name, colName, uc, fc);
        if (colDiff.length > 0) lines.push(...colDiff);
    }

    const idxByName = (
        idxs: SchemaDump[string]['indexes'],
    ): Map<string, SchemaDump[string]['indexes'][number]> => new Map(idxs.map((i) => [i.name, i]));
    const uIdx = idxByName(upgraded.indexes);
    const fIdx = idxByName(fresh.indexes);
    const allIdx = new Set<string>([...uIdx.keys(), ...fIdx.keys()]);
    for (const idxName of [...allIdx].sort()) {
        const ui = uIdx.get(idxName);
        const fi = fIdx.get(idxName);
        if (!ui && fi) {
            lines.push(
                `+ ${name} index ${idxName} on (${fi.columns.join(', ')}) (only in fresh install)`,
            );
            continue;
        }
        if (ui && !fi) {
            lines.push(
                `- ${name} index ${idxName} on (${ui.columns.join(', ')}) (only in post-upgrade DB)`,
            );
            continue;
        }
        if (!ui || !fi) continue;
        if (ui.unique !== fi.unique) {
            lines.push(`~ ${name} index ${idxName}: unique ${ui.unique} → ${fi.unique}`);
        }
        if (ui.columns.join(',') !== fi.columns.join(',')) {
            lines.push(
                `~ ${name} index ${idxName}: columns (${ui.columns.join(', ')}) → (${fi.columns.join(', ')})`,
            );
        }
    }

    return lines;
}

function diffColumn(
    table: string,
    colName: string,
    upgraded: Record<string, unknown>,
    fresh: Record<string, unknown>,
): string[] {
    const lines: string[] = [];
    // We compare every key the dumper emits except COLUMN_NAME (already
    // matched) and ORDINAL_POSITION (column order shifts when an
    // upgrade adds new columns at the end of the table — that's fine
    // for our parity contract).
    const keys = new Set<string>([...Object.keys(upgraded), ...Object.keys(fresh)]);
    keys.delete('COLUMN_NAME');
    keys.delete('ORDINAL_POSITION');
    for (const key of [...keys].sort()) {
        const u = upgraded[key];
        const f = fresh[key];
        if (u !== f && JSON.stringify(u) !== JSON.stringify(f)) {
            lines.push(
                `~ ${table}.${colName}: ${key} ${JSON.stringify(u)} → ${JSON.stringify(f)}`,
            );
        }
    }
    return lines;
}

/**
 * Settings parity: every key in `fresh` MUST exist in `upgraded`.
 *
 * Values are NOT compared — `config.version`, generated random
 * settings, install timestamps and the like legitimately differ.
 * The contract is: an admin upgrading a 1.x panel never finds
 * themselves missing a setting key the 2.x panel expects.
 *
 * Returns the list of missing keys (empty when parity holds).
 */
export function missingSettingsKeys(
    upgraded: Record<string, string>,
    fresh: Record<string, string>,
): string[] {
    const missing: string[] = [];
    for (const key of Object.keys(fresh).sort()) {
        if (!(key in upgraded)) missing.push(key);
    }
    return missing;
}
