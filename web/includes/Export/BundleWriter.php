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
     * Threshold (bytes) above which entity / manifest bodies spill
     * from the in-memory `php://temp` buffer onto disk. Below this,
     * the stream stays in PHP's heap — the typical settings /
     * groups / mods bundle entity is a few KiB and never touches
     * the filesystem; the spill is the safety net for the long
     * tail (large `:prefix_log` audit-log streams on long-lived
     * installs, banlog history, large banlists with thousands of
     * appeals).
     *
     * 8 MiB matches the OOM ceiling the runtime hardening in
     * `web/export.php` sets via `@ini_set('memory_limit', '256M')`
     * minus the headroom needed for PDO row buffers + Smarty +
     * Composer autoload — pushing the spill threshold higher would
     * eat into that headroom and risk OOM on shared hosting where
     * 128 MiB is the realistic floor.
     */
    private const SPILL_THRESHOLD_BYTES = 8 * 1024 * 1024;

    /**
     * Cumulative byte count tracking the bundle's progress against
     * {@see Manifest::MAX_BUNDLE_BYTES}.
     *
     * The accuracy of this counter depends on which output sink the
     * caller wired the {@see ZipStream} to. See
     * {@see currentCompressedSize} for the per-mode details:
     *
     *   - **`s3` mode** — the writer holds a non-null
     *     {@see outputHandle} pointing at the build-to-disk
     *     tempfile; after each entry the writer `fstat`s the
     *     handle and gets the EXACT compressed-byte count. The
     *     cap check is precise.
     *   - **`zip` mode** — the writer's output sink is
     *     `php://output` (unseekable, unstattable), so the writer
     *     falls back to a conservative uncompressed-byte estimate.
     *     This OVER-counts (DEFLATE typically compresses JSONL
     *     3-8x) but the over-count direction is the safe one — a
     *     premature `CAP_EXCEEDED` surfaces a clear error message
     *     pre-overflow, whereas an under-count would let the
     *     bundle exceed ZIP 2.0's 4 GiB ceiling and corrupt
     *     silently. The conservative estimate stays in place for
     *     `zip` mode because there is no exact counter the
     *     unseekable sink can offer.
     */
    private int $bytesWritten = 0;

    /**
     * Optional file resource the {@see ZipStream} is writing to.
     * Non-null in `s3` mode (build-to-disk path); null in `zip`
     * mode (output is `php://output`). When non-null, post-entry
     * `fstat`s give the exact compressed-byte count and the cap
     * check is precise; when null, the writer falls back to the
     * uncompressed estimate documented on {@see bytesWritten}.
     *
     * @var resource|null
     */
    private $outputHandle;

    /**
     * @param resource|null $outputHandle  Pass the same file
     *   resource the caller handed to {@see ZipStream} when
     *   building the s3-mode build-to-disk tempfile. Pass `null`
     *   for the zip-mode `php://output` path (the writer falls
     *   back to a conservative uncompressed-byte cap estimate per
     *   the {@see bytesWritten} contract).
     */
    public function __construct(
        private readonly ZipStream $zip,
        private readonly Manifest $manifest,
        private readonly EntityExporter $entities,
        private readonly string $demosDir,
        private readonly bool $flushAfterEntries,
        $outputHandle = null,
    ) {
        $this->outputHandle = $outputHandle;
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

        // Finalize the ZIP — emits the central directory. ZipStream's
        // return value tracks the bytes IT pushed to the output
        // sink (independent of any compression layer the sink might
        // apply). For s3 mode we re-snap from the on-disk tempfile
        // post-finish so the reported count includes the central
        // directory bytes and matches what the S3 PUT will see. For
        // zip mode the ZipStream-reported count is authoritative
        // since we have no on-disk file to stat.
        $reported = (int) $this->zip->finish();
        $exact    = $this->currentCompressedSize();
        $this->bytesWritten = $exact ?? $reported;
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
     * Manifest goes in first by contract. The body is bounded
     * (per-entity row counts + the demo manifest entries — a few
     * KiB to a few hundred KiB on a busy install), so addFile
     * with the in-memory body is the right shape; spilling to a
     * tempfile here would only add filesystem overhead with no
     * memory benefit.
     */
    private function writeManifest(): void
    {
        $body = $this->manifest->toJson();
        $this->zip->addFile(
            fileName:          'manifest.json',
            data:              $body,
            compressionMethod: CompressionMethod::DEFLATE,
        );
        $this->advanceCounter(strlen($body), 'manifest.json');
        $this->maybeFlush();
    }

    /**
     * Stream one entity's JSONL output through a `php://temp`
     * spill stream so each row flows from the SELECT iterator into
     * the spill stream and only the bounded
     * {@see SPILL_THRESHOLD_BYTES} working set ever lives in PHP's
     * heap. Larger entities (audit log on long-lived installs,
     * banlog history, banlists with thousands of rows) spill to a
     * tempfile that the OS cleans up automatically when the
     * resource handle is closed at the end of the call.
     *
     * The pre-#H1 shape concatenated the entire entity into a
     * single PHP string inside an `addFileFromCallback`, which
     * defeated the streaming contract documented on the writer
     * AND risked OOM on installs with multi-hundred-MB audit
     * logs.
     *
     * @param callable(): iterable<string> $factory
     */
    private function writeEntity(string $name, callable $factory): void
    {
        $spill = fopen('php://temp/maxmemory:' . self::SPILL_THRESHOLD_BYTES, 'w+b');
        if ($spill === false) {
            // SECURITY-REVIEW: fopen of php://temp should never
            // fail on a healthy install — the only realistic
            // cause is a pathological tmpfs / open_basedir
            // configuration. Surface as a structured DISK_WRITE
            // failure rather than silently dropping the entity.
            throw new ExportError(
                ExportError::DISK_WRITE_FAILED,
                sprintf(
                    'fopen(php://temp/maxmemory:%d) failed while preparing entity %s.',
                    self::SPILL_THRESHOLD_BYTES,
                    $name,
                ),
            );
        }
        try {
            $uncompressed = 0;
            foreach ($factory() as $line) {
                $written = fwrite($spill, $line);
                if ($written === false || $written !== strlen($line)) {
                    throw new ExportError(
                        ExportError::DISK_WRITE_FAILED,
                        sprintf(
                            'Short write to php://temp spill while staging entity %s.',
                            $name,
                        ),
                    );
                }
                $uncompressed += $written;
            }
            rewind($spill);
            $this->zip->addFileFromStream(
                fileName:          'entities/' . $name . '.jsonl',
                stream:            $spill,
                compressionMethod: CompressionMethod::DEFLATE,
            );
            // Counter advance comes AFTER addFileFromStream
            // returns — at that point the zip has finished
            // writing the entry to the underlying output handle.
            // For s3 mode the post-write fstat sees the exact
            // compressed size; for zip mode the fallback uses the
            // uncompressed total (conservative — see the
            // bytesWritten docblock).
            $this->advanceCounter($uncompressed, 'entities/' . $name . '.jsonl');
        } finally {
            fclose($spill);
        }
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
        // STORE-mode demos: compressed size ≈ uncompressed size
        // (the ZIP entry adds ~46 bytes of local header overhead,
        // negligible against multi-MB demo payloads). Track the
        // file's on-disk size as the increment.
        $this->advanceCounter($demo['size_bytes'], 'demos/' . $demo['name']);
        $this->maybeFlush();
    }

    /**
     * Advance the running cap-check counter by `$uncompressedDelta`
     * — the uncompressed-byte size of the entry just handed off to
     * {@see ZipStream}. When the writer has access to the output
     * handle (s3 mode), the counter snaps to the handle's `fstat`
     * size INSTEAD, giving the exact compressed-byte total. When
     * the output handle isn't seekable (zip mode), the counter
     * adds the uncompressed delta as the conservative fallback
     * documented on {@see bytesWritten}.
     */
    private function advanceCounter(int $uncompressedDelta, string $lastEntry): void
    {
        $exact = $this->currentCompressedSize();
        if ($exact !== null) {
            $this->bytesWritten = $exact;
        } else {
            $this->bytesWritten += $uncompressedDelta;
        }
        $this->checkCap($lastEntry);
    }

    /**
     * Return the EXACT compressed-byte count when the writer's
     * output handle is seekable (s3 mode's build-to-disk
     * tempfile), or `null` when it isn't (zip mode's
     * `php://output`). The exact count includes ZipStream's
     * per-entry local-file-header bytes the in-flight central
     * directory tracker can't see, so it's the authoritative
     * value for cap-check purposes.
     */
    private function currentCompressedSize(): ?int
    {
        if ($this->outputHandle === null) {
            return null;
        }
        if (!is_resource($this->outputHandle)) {
            return null;
        }
        $stat = @fstat($this->outputHandle);
        if ($stat === false) {
            return null;
        }
        return (int) $stat['size'];
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
