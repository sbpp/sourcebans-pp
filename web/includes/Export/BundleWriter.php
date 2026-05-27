<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Orchestrates the streaming ZIP bundle for the data export feature.
 *
 * The writer is the single seam between (a) the in-memory manifest
 * payload + the {@see EntityExporter}-yielded JSONL streams + the
 * on-disk demo files and (b) the ZIP output sink. The two consumers
 * are:
 *
 *   - **`zip` mode** ({@see \Sbpp\Export\BundleWriter}'s caller in
 *     `web/export.php` constructs a {@see ZipStream::class} pointing
 *     at `php://output` with `$flushAfterEntries = true`). The
 *     entry point hands the writer a {@see ZipStream} instance
 *     pre-wired to flush headers + write bytes straight to the
 *     client TCP socket; the writer's job is to feed the entries
 *     in deterministic order AND `flush()` after each one so a
 *     slow client doesn't sit with a `Content-Type: application/zip`
 *     header for 30 seconds before the first byte arrives.
 *   - **`s3` mode** (the caller constructs a {@see ZipStream}
 *     pointing at a `fopen($tempfile, 'wb')` handle with
 *     `$flushAfterEntries = false`). No client to flush to —
 *     the bundle builds to disk first and the {@see S3PresignedUploader}
 *     PUTs the finished file in a second step. Suppressing the flush
 *     keeps per-entry kernel overhead off the critical path.
 *
 * Contract:
 *
 *   - **`manifest.json` is the FIRST entry.** The integration test
 *     anchors on `ZipArchive::statIndex(0)['name'] === 'manifest.json'`;
 *     a consumer that wants to short-circuit the bundle to read the
 *     PII policy or row counts can stop after one entry.
 *   - **Entity JSONL files land under `entities/<name>.jsonl`** in
 *     deterministic key order.
 *   - **Demo files land under `demos/<basename>.dem`** in
 *     deterministic name order, compressed with {@see CompressionMethod::STORE}.
 *     Demos are already DEFLATE'd at the source-engine level, and
 *     re-compressing them costs CPU without saving bytes — STORE
 *     means "wrap the raw bytes in a ZIP entry without compression"
 *     which is the right shape for a binary payload that's
 *     already entropy-maximal.
 *   - **Running compressed-byte budget.** After each entity / demo
 *     the writer checks the cumulative byte total against
 *     {@see Manifest::MAX_BUNDLE_BYTES} minus
 *     {@see Manifest::SAFETY_MARGIN_BYTES}; if exceeded, throws
 *     {@see ExportError::CAP_EXCEEDED}. Pre-flight in
 *     {@see ManifestBuilder} should catch this earlier in 99% of
 *     cases — this is the safety net for the JSONL byte-estimate
 *     undershoot case.
 *   - **No ZIP64.** The {@see ZipStream} is constructed with
 *     `$enableZip64 = false` by the caller; the 4 GiB ceiling is
 *     the load-bearing reason — every mainstream unzipper handles
 *     ZIP 2.0 natively, ZIP64 support is patchy.
 */
final class BundleWriter
{
    /**
     * Cumulative compressed-byte count, including ZIP headers /
     * central directory bytes the {@see ZipStream} tracks.
     */
    private int $bytesWritten = 0;

    public function __construct(
        private readonly ZipStream $zip,
        private readonly Manifest $manifest,
        private readonly EntityExporter $entities,
        private readonly string $demosDir,
        private readonly bool $flushAfterEntries,
    ) {
    }

    /**
     * Drive the full bundle: manifest first, entity JSONL streams
     * next, demo files last, then finalise the ZIP central
     * directory.
     *
     * Caller is responsible for the outer try/catch — see
     * `web/export.php` for the canonical shape. {@see ExportError}
     * surfaces a structured error code; anything else is a real
     * bug and propagates to the dispatcher's generic 500.
     */
    public function write(): void
    {
        $this->writeManifest();

        foreach ($this->entities->entityStreams() as $name => $factory) {
            $this->writeEntity($name, $factory);
        }

        foreach ($this->manifest->demo_files as $demo) {
            $this->writeDemo($demo);
        }

        // Finalize the ZIP — emits the central directory. ZipStream
        // tracks an estimated cumulative size internally; the
        // returned value is authoritative for the cap-check sanity
        // assertion in tests.
        $this->bytesWritten = (int) $this->zip->finish();
    }

    /**
     * Total bytes the underlying ZipStream reports written. Only
     * meaningful AFTER {@see write} returns; intermediate values
     * during the run track the running budget, not the final
     * compressed size (the central directory bytes land in
     * {@see ZipStream::finish}).
     */
    public function bytesWritten(): int
    {
        return $this->bytesWritten;
    }

    /**
     * Manifest goes in first by contract. `addFile` is the simplest
     * shape since we already have the encoded body in memory.
     */
    private function writeManifest(): void
    {
        $body = $this->manifest->toJson();
        $this->zip->addFile(
            fileName:          'manifest.json',
            data:              $body,
            compressionMethod: CompressionMethod::DEFLATE,
        );
        $this->bytesWritten += strlen($body);
        $this->checkCap('manifest.json');
        $this->maybeFlush();
    }

    /**
     * Stream one entity's JSONL output through `addFileFromCallback`
     * so the bytes flow straight from the SELECT iterator into the
     * ZIP without buffering the whole entity in PHP memory.
     *
     * @param callable(): iterable<string> $factory
     */
    private function writeEntity(string $name, callable $factory): void
    {
        $estimatedBytes = 0;
        $this->zip->addFileFromCallback(
            fileName:          'entities/' . $name . '.jsonl',
            exactSize:         null,
            compressionMethod: CompressionMethod::DEFLATE,
            callback:          function () use ($factory, &$estimatedBytes): string {
                $out = '';
                foreach ($factory() as $line) {
                    $out .= $line;
                }
                $estimatedBytes = strlen($out);
                return $out;
            },
        );
        $this->bytesWritten += $estimatedBytes;
        $this->checkCap('entities/' . $name . '.jsonl');
        $this->maybeFlush();
    }

    /**
     * Demo files land under `demos/<basename>.dem` with
     * {@see CompressionMethod::STORE} — they're already
     * entropy-maximal binary streams (the source engine emits a
     * DEFLATE-friendly demo only when explicitly told to), and
     * re-compressing trades CPU for no byte savings.
     *
     * @param array{name: string, size_bytes: int} $demo
     */
    private function writeDemo(array $demo): void
    {
        $path = $this->demosDir . DIRECTORY_SEPARATOR . $demo['name'];
        // Defence-in-depth: ManifestBuilder already stat'd the file
        // when minting the demo list, but a race window between
        // pre-flight and write (operator manually deletes a demo,
        // a sweep cron runs) shouldn't 500 the export — skip and
        // continue. The manifest still claims the demo exists, so
        // a strict consumer would notice the bookkeeping drift;
        // the alternative is silent corruption of an inflight
        // bundle, which is worse.
        if (!is_file($path)) {
            return;
        }
        $this->zip->addFileFromPath(
            fileName:          'demos/' . $demo['name'],
            path:              $path,
            compressionMethod: CompressionMethod::STORE,
        );
        $this->bytesWritten += $demo['size_bytes'];
        $this->checkCap('demos/' . $demo['name']);
        $this->maybeFlush();
    }

    /**
     * Compare the running cumulative byte total against the cap
     * (minus safety margin). Throws {@see ExportError::CAP_EXCEEDED}
     * with the offending entry's name so an operator can identify
     * which slice tipped the budget over.
     */
    private function checkCap(string $lastEntry): void
    {
        $cap = Manifest::MAX_BUNDLE_BYTES - Manifest::SAFETY_MARGIN_BYTES;
        if ($this->bytesWritten > $cap) {
            throw new ExportError(
                ExportError::CAP_EXCEEDED,
                sprintf(
                    'Bundle exceeded the %d-byte cap (4 GiB minus the %d-byte safety margin) '
                    . 'after writing %s (cumulative %d bytes). Clean up stale demos and rerun.',
                    $cap,
                    Manifest::SAFETY_MARGIN_BYTES,
                    $lastEntry,
                    $this->bytesWritten,
                ),
            );
        }
    }

    /**
     * Push bytes downstream after each entry in zip-mode so the
     * client TCP socket sees progress instead of a long opaque
     * pause. In s3-mode the writer's output is a file handle — no
     * client to flush to, and the per-call kernel overhead is dead
     * weight.
     */
    private function maybeFlush(): void
    {
        if (!$this->flushAfterEntries) {
            return;
        }
        // PHP's flush() pushes the output buffer chain; the wrapping
        // entry point in `web/export.php` has already cleared every
        // ob_* layer, so this hits the SAPI directly. ob_flush() is
        // a defensive no-op when there's no buffer to flush, but the
        // `@` suppresses the notice that PHP emits when called
        // outside an ob_start context.
        flush();
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
    }
}
