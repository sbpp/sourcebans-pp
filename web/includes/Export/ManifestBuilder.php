<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

use LogType;
use Sbpp\Db\Database;
use Sbpp\Log;

/**
 * Pre-flight pass + manifest minter for the data export bundle.
 *
 * Responsibilities:
 *
 *   - One `SELECT COUNT(*)` per entity. The list of entities is the
 *     same set {@see EntityExporter} streams, kept in lockstep so a
 *     new entity addition surfaces missing-row-count regressions
 *     loudly (the manifest's `row_counts` keys are asserted equal to
 *     the JSONL entry count by the integration tests).
 *   - Enumerate every demo file under `SB_DEMOS` that's referenced
 *     by a `:prefix_demos.filename` row. `filesize` each one to
 *     produce `demo_total_bytes`. Demos whose row exists but whose
 *     file is missing on disk are dropped from the manifest's
 *     `demo_files` list AND `Log::add(LogType::Warning, ...)`'d so
 *     the operator notices the bookkeeping drift.
 *   - Mint a fresh UUIDv4 bundle ID and current `created_at` so each
 *     pre-flight call yields a fresh manifest. The page handler's
 *     UI doesn't care (it only reads the count + cap-status fields),
 *     but the writer's output stamps the freshly-minted ID into
 *     `manifest.json`'s `bundle_id` field AND the bundle's filename.
 *   - Compute the JSONL byte estimate (`row_count × 1024 byte
 *     heuristic`) + the demo bytes + the manifest overhead and
 *     compare against {@see Manifest::MAX_BUNDLE_BYTES} minus
 *     {@see Manifest::SAFETY_MARGIN_BYTES}.
 *
 * The 1024-byte-per-row heuristic is deliberately generous: a typical
 * `:prefix_bans` row is well under 512 bytes (timestamps + ints + a
 * short reason string), but a `:prefix_log` row with a long message
 * column can stretch toward 2 KiB. The cushion built into the
 * heuristic + the {@see Manifest::SAFETY_MARGIN_BYTES} subtraction
 * leaves headroom for the bytes the heuristic doesn't see (the JSONL
 * structural overhead `{"field":...}\n`, the manifest itself,
 * the central directory the ZIP appends at the tail). The bundle
 * writer re-checks the running compressed-byte total per-entry and
 * aborts the same way if pre-flight underestimated.
 */
final class ManifestBuilder
{
    /**
     * Bytes-per-row estimate for the JSONL entity files. Used by
     * `build()` to predict the bundle size BEFORE we stream anything;
     * the writer enforces the same cap at runtime, so an undershoot
     * here just trades one upstream-friendly error envelope for a
     * mid-stream abort. See class docblock for the heuristic
     * rationale.
     */
    private const BYTES_PER_ROW_ESTIMATE = 1024;

    /**
     * Manifest line-overhead estimate: the encoded JSON manifest
     * itself + its central-directory entry. Generous (10 KiB),
     * because the actual encoded body is ~1-2 KiB.
     */
    private const MANIFEST_BYTES_ESTIMATE = 10 * 1024;

    /**
     * The full deterministic-order list of entities the export
     * subsystem covers. Mirrors `EntityExporter::entityStreams()`'s
     * key set — keep both in lockstep. The order is alphabetical so
     * the bundle's central directory is stable across reruns; the
     * tests' first-entry contract ("manifest.json comes first") rides
     * on the BundleWriter explicitly writing the manifest before
     * iterating this list, NOT on alphabetical ordering placing it
     * first.
     *
     * @var list<string>
     */
    private const ENTITIES = [
        'admins',
        'admins_servers_groups',
        'banlog',
        'bans',
        'comments',
        'comms',
        'groups',
        'log',
        'mods',
        'notes',
        'overrides',
        'protests',
        'servers',
        'servers_groups',
        'settings',
        'srvgroups',
        'srvgroups_overrides',
        'submissions',
    ];

    public function __construct(
        private readonly Database $dbs,
        private readonly string $demosDir,
        private readonly string $panelVersion,
    ) {
    }

    /**
     * Run the pre-flight pass and mint a complete {@see Manifest}.
     *
     * The page handler also calls this — it reads the cap-status
     * + counts fields off the returned DTO and discards the rest.
     * That's fine: minting a throwaway UUID per page render costs
     * one `random_bytes(16)` call and the resulting Manifest never
     * leaves the request scope.
     */
    public function build(): Manifest
    {
        $rowCounts   = $this->collectRowCounts();
        $demoFiles   = $this->collectDemoFiles();
        $demoBytes   = array_sum(array_column($demoFiles, 'size_bytes'));
        $jsonlBytes  = array_sum($rowCounts) * self::BYTES_PER_ROW_ESTIMATE;
        $estimated   = $demoBytes + $jsonlBytes + self::MANIFEST_BYTES_ESTIMATE;
        $capBytes    = Manifest::MAX_BUNDLE_BYTES - Manifest::SAFETY_MARGIN_BYTES;
        $exceedsCap  = $estimated > $capBytes;

        return new Manifest(
            bundle_id:              self::mintBundleId(),
            created_at:             time(),
            panel_version:          $this->panelVersion,
            row_counts:             $rowCounts,
            demo_files:             $demoFiles,
            demo_total_bytes:       (int) $demoBytes,
            estimated_bundle_bytes: $estimated,
            exceeds_cap:            $exceedsCap,
            cap_bytes:              $capBytes,
            pii_policy:             self::piiPolicy(),
        );
    }

    /**
     * Build the manifest AND throw {@see ExportError::CAP_EXCEEDED}
     * if the estimate trips the cap. The writer-side entry point
     * calls this so a too-big bundle bails BEFORE any bytes hit the
     * wire; the page handler calls {@see build} directly because the
     * UI surfaces the cap-status as a disabled button instead of an
     * exception.
     */
    public function buildOrThrow(): Manifest
    {
        $manifest = $this->build();
        if ($manifest->exceeds_cap) {
            throw new ExportError(
                ExportError::CAP_EXCEEDED,
                sprintf(
                    'Bundle estimate %d bytes exceeds the %d-byte cap (4 GiB minus the %d-byte safety margin). '
                    . 'Clear stale demos and rerun.',
                    $manifest->estimated_bundle_bytes,
                    $manifest->cap_bytes,
                    Manifest::SAFETY_MARGIN_BYTES,
                ),
            );
        }
        return $manifest;
    }

    /**
     * One `SELECT COUNT(*)` per entity. Composite-PK tables
     * (`admins_servers_groups`, `servers_groups`, `banlog`) and
     * non-`id`-keyed tables count the same way — the cardinality is
     * what we care about, not the key shape.
     *
     * Settings is the exception: {@see EntityExporter::settings()}
     * filters out {@see EntityExporter::FORBIDDEN_SETTING_KEYS} at
     * the SQL WHERE level. The manifest's row count MUST match the
     * actual JSONL line count, so the same filter rides this
     * pre-flight count. Drift between the two surfaces is a
     * wire-contract break — pinned by ExportBundleWriterTest's
     * "row_counts agree with JSONL line counts" assertion.
     *
     * @return array<string, int>
     */
    private function collectRowCounts(): array
    {
        $out = [];
        foreach (self::ENTITIES as $entity) {
            if ($entity === 'settings') {
                $out[$entity] = $this->countFilteredSettings();
                continue;
            }
            $table = '`:prefix_' . $entity . '`';
            $this->dbs->query("SELECT COUNT(*) AS n FROM $table");
            $row = $this->dbs->single();
            $out[$entity] = (int) ($row['n'] ?? 0);
        }
        return $out;
    }

    /**
     * Settings-specific count: applies the same
     * {@see EntityExporter::FORBIDDEN_SETTING_KEYS} filter the
     * entity exporter uses so the manifest's claim agrees with the
     * actual JSONL line count.
     */
    private function countFilteredSettings(): int
    {
        $forbidden    = EntityExporter::FORBIDDEN_SETTING_KEYS;
        $placeholders = implode(',', array_fill(0, count($forbidden), '?'));
        $this->dbs->query(
            "SELECT COUNT(*) AS n
             FROM `:prefix_settings`
             WHERE `setting` NOT IN ($placeholders)"
        );
        $i = 1;
        foreach ($forbidden as $key) {
            $this->dbs->bind($i++, $key);
        }
        $row = $this->dbs->single();
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Enumerate every `:prefix_demos.filename` referenced row, stat
     * the file on disk, drop the rows whose file is missing, and
     * return the surviving list in deterministic name order. Missing
     * files surface a single `Log::add(LogType::Warning)` per
     * pre-flight pass so an operator notices the drift.
     *
     * @return list<array{name: string, size_bytes: int}>
     */
    private function collectDemoFiles(): array
    {
        $this->dbs->query('SELECT DISTINCT `filename` FROM `:prefix_demos`');
        $rows = $this->dbs->resultset();

        $out      = [];
        $missing  = [];
        foreach ($rows as $row) {
            $filename = (string) ($row['filename'] ?? '');
            if ($filename === '') {
                continue;
            }
            // Basename the value before we touch the disk. The column
            // is operator-controlled (it's whatever the SourceMod
            // plugin stamped); a hostile / typo'd row like `../etc`
            // shouldn't be able to walk us out of the demos dir.
            $safe = basename($filename);
            if ($safe === '' || $safe === '.' || $safe === '..') {
                continue;
            }
            $path = $this->demosDir . DIRECTORY_SEPARATOR . $safe;
            if (!is_file($path)) {
                $missing[] = $safe;
                continue;
            }
            $size = @filesize($path);
            if ($size === false) {
                $missing[] = $safe;
                continue;
            }
            $out[] = [
                'name'       => $safe,
                'size_bytes' => (int) $size,
            ];
        }

        // Deterministic order so the manifest hash (if a downstream
        // ever needs one) and the bundle's central directory stay
        // stable across reruns.
        usort($out, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        if ($missing !== []) {
            // De-duplicate so a hot row with multiple ban references
            // doesn't spam the audit log.
            $unique = array_values(array_unique($missing));
            // Cap the body string at 512 chars so a pathological
            // case (thousands of missing demos) doesn't write a
            // 100 KiB audit log row.
            $bodyList = implode(', ', array_slice($unique, 0, 12));
            if (count($unique) > 12) {
                $bodyList .= sprintf(' (+%d more)', count($unique) - 12);
            }
            Log::add(
                LogType::Warning,
                'Data Export pre-flight',
                sprintf(
                    'Skipping %d demo file%s referenced by :prefix_demos but missing on disk: %s',
                    count($unique),
                    count($unique) === 1 ? '' : 's',
                    $bodyList,
                ),
            );
        }

        return $out;
    }

    /**
     * The PII contract block lands verbatim in `manifest.json`'s
     * `pii_policy` field. Every flag here is anchored against an
     * actual category present in `web/install/includes/sql/struc.sql`:
     *
     *   - `includes_admin_emails`   `:prefix_admins.email` is shipped.
     *   - `includes_ip_addresses`   `:prefix_bans.ip` + `:prefix_bans.adminIp` + similar columns are shipped.
     *   - `includes_chat_messages`  The panel doesn't store chat messages
     *                               anywhere in the schema — kept as `false`
     *                               so a downstream consumer isn't misled.
     *   - `includes_steam_ids`      Every `authid` column is shipped, mapped
     *                               to decimal-string Steam64.
     *   - `includes_unban_reasons`  `:prefix_bans.ureason` + `:prefix_comms.ureason`
     *                               are shipped verbatim.
     *   - `password_hashes`         Always `"never"` — `EntityExporter` enforces
     *                               the column drop in code; this field is the
     *                               manifest-side declaration of the contract.
     *
     * @return array<string, bool|string>
     */
    private static function piiPolicy(): array
    {
        return [
            'includes_admin_emails'  => true,
            'includes_ip_addresses'  => true,
            'includes_chat_messages' => false,
            'includes_steam_ids'     => true,
            'includes_unban_reasons' => true,
            'password_hashes'        => 'never',
        ];
    }

    /**
     * RFC 4122 UUIDv4 mint. 16 random bytes, version + variant
     * nibbles patched per the spec. Returns the canonical 8-4-4-4-12
     * hex form with the version `4` nibble at index 12 and the
     * variant `8/9/a/b` nibble at index 16.
     */
    private static function mintBundleId(): string
    {
        $bytes = random_bytes(16);
        // Set version 4 (0b0100_xxxx) on byte 6
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant (RFC 4122 — 0b10_xxxxxx) on byte 8
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
