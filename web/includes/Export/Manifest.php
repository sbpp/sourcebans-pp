<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

/**
 * Readonly DTO carrying every field that lands in `manifest.json` at
 * the head of an export bundle. {@see ManifestBuilder} mints the
 * instance; {@see BundleWriter} consumes it.
 *
 * The class is also the source of truth for the bundle-cap math:
 * {@see MAX_S3_PUT_BYTES} is the S3 single-PUT object-size ceiling
 * (5 GiB) and {@see SAFETY_MARGIN_BYTES} is the cushion against the
 * central directory + JSONL line endings + our own row-size heuristic
 * undershoot. The cap is **mode-conditional**: it ONLY applies to
 * `s3` mode (presigned PUT). `zip` mode (direct browser download)
 * is uncapped — Zip64 is enabled in {@see BundleWriter}, so the
 * archive itself has no structural size ceiling. Pre-flight on the
 * s3 path subtracts the margin BEFORE comparing the estimate; the
 * bundle writer re-checks the running compressed-byte total on each
 * entity / demo when constructed with a non-null `$capBytes` (s3
 * mode) and no-ops when constructed with `null` (zip mode).
 *
 * The `$exceeds_cap` / `$cap_bytes` fields on this DTO carry the
 * **S3 PUT cap** — they're load-bearing for the S3 form's UX gate
 * (the S3 submit button is server-rendered as `disabled` when
 * `exceeds_cap` is true) and informational on the manifest of a
 * direct-ZIP-download bundle (the ZIP download is uncapped).
 *
 * The PII policy block is part of the operator contract — the bundle
 * is intentionally honest about every category it carries (admin
 * emails, IPs, Steam IDs, unban reasons) so a recipient can route
 * the bundle through the appropriate handling controls. Reach for an
 * `includes_*` field of `false` ONLY when the panel genuinely
 * doesn't store that category; lying about a category that IS
 * present is the worst-case outcome.
 *
 * `format_version` is the binding identifier for the wire format
 * (entity column layout, manifest shape, the `null`-for-absent
 * contract, the Steam64-as-decimal-string contract, the unix-seconds
 * contract). Bump it whenever any of those change in a way that
 * would break a downstream consumer; add a paired downstream
 * migration note in the operator-facing docs at the same time.
 */
final class Manifest
{
    /**
     * Wire-format identifier. Bump on any breaking change to the
     * entity column layout, manifest shape, or per-field contracts.
     */
    public const FORMAT_VERSION = 1;

    /**
     * S3 single-PUT object-size ceiling (5 GiB). Every S3-API
     * compatible provider (AWS S3, Cloudflare R2, MinIO, Backblaze
     * B2, Wasabi) enforces this hard limit on a presigned PUT;
     * above it the operator has to switch to multipart upload, a
     * fundamentally different flow than presigned single-PUT. The
     * cap is structural, not arbitrary.
     *
     * Direct ZIP download is **uncapped** — Zip64 is enabled in
     * {@see BundleWriter}, so the archive itself has no structural
     * size ceiling, and the operator's browser can stream a
     * multi-tens-of-GB bundle if they choose to.
     */
    public const MAX_S3_PUT_BYTES = 5 * 1024 * 1024 * 1024;

    /**
     * Cushion against the central directory + per-entry overhead +
     * our own JSONL row-size heuristic undershoot. Subtract from
     * {@see MAX_S3_PUT_BYTES} before the s3-mode pre-flight compare
     * AND the running-byte gate inside the writer when it's
     * constructed with a non-null `$capBytes`.
     */
    public const SAFETY_MARGIN_BYTES = 64 * 1024 * 1024;

    /**
     * The effective S3-mode cap: {@see MAX_S3_PUT_BYTES} minus
     * {@see SAFETY_MARGIN_BYTES}. Single source for both the
     * pre-flight `exceeds_cap` decision in {@see ManifestBuilder}
     * AND the in-flight running-byte gate in {@see BundleWriter}
     * when it's constructed s3-mode-shaped.
     */
    public static function s3PutCapBytes(): int
    {
        return self::MAX_S3_PUT_BYTES - self::SAFETY_MARGIN_BYTES;
    }

    /**
     * Pre-flight cap predicate: does the estimated bundle size
     * exceed the S3 PUT cap (minus safety margin)? Pure function —
     * extracted so unit tests can pin the boundary behaviour
     * without spinning up a 5 GiB DB fixture. ManifestBuilder
     * calls this against its row-count + demo-bytes estimate; the
     * result is preserved on every manifest regardless of mode
     * (informational on a direct-ZIP-download bundle, load-bearing
     * UX gate on the S3 form).
     */
    public static function computeExceedsCap(int $estimatedBundleBytes): bool
    {
        return $estimatedBundleBytes > self::s3PutCapBytes();
    }

    /**
     * @param array<string, int>                                  $row_counts     Entity name → row count.
     * @param list<array{name: string, size_bytes: int}>          $demo_files     Demo files we'd ship (in deterministic name order).
     * @param array<string, bool|string>                          $pii_policy     See `pii_policy` shape below.
     * @param bool                                                $exceeds_cap    True iff `$estimated_bundle_bytes > $cap_bytes`. Load-bearing for the S3 form's UX gate (S3 submit is server-rendered `disabled` when true). Informational on a direct-ZIP-download bundle's manifest — ZIP download is uncapped.
     * @param int                                                 $cap_bytes      The S3 PUT cap (see {@see s3PutCapBytes()}). Applies only to s3 mode; ZIP direct download has no cap.
     */
    public function __construct(
        public readonly string $bundle_id,
        public readonly int $created_at,
        public readonly string $panel_version,
        public readonly array $row_counts,
        public readonly array $demo_files,
        public readonly int $demo_total_bytes,
        public readonly int $estimated_bundle_bytes,
        public readonly bool $exceeds_cap,
        public readonly int $cap_bytes,
        public readonly array $pii_policy,
    ) {
    }

    /**
     * JSON-encode the manifest as the on-wire `manifest.json` body.
     *
     * Uses the same `JSON_INVALID_UTF8_SUBSTITUTE` flag every other
     * entity-emission path uses, so a panel-version string with
     * malformed bytes (theoretical — `panel_version` comes from
     * `SB_VERSION` which is a clean ASCII string today) substitutes
     * to U+FFFD instead of throwing. The `JSON_UNESCAPED_*` flags
     * keep the on-disk payload human-readable when the operator
     * opens it in a text editor.
     */
    public function toJson(): string
    {
        $payload = [
            'format_version'         => self::FORMAT_VERSION,
            'bundle_id'              => $this->bundle_id,
            'created_at'             => $this->created_at,
            'panel_version'          => $this->panel_version,
            'row_counts'             => $this->row_counts,
            'demo_files'             => $this->demo_files,
            'demo_total_bytes'       => $this->demo_total_bytes,
            'estimated_bundle_bytes' => $this->estimated_bundle_bytes,
            'exceeds_cap'            => $this->exceeds_cap,
            'cap_bytes'              => $this->cap_bytes,
            'pii_policy'             => $this->pii_policy,
        ];

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRETTY_PRINT,
        );
    }
}
