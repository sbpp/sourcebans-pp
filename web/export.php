<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/*
 * Top-level streaming entry point for the data export feature.
 *
 * Lives at panel-root because the bundle's wire format is binary
 * (`Content-Type: application/zip`) and doesn't fit the JSON API
 * dispatcher's contract — the JSON dispatcher always emits a
 * `Content-Type: application/json` envelope, and there's no clean
 * extension point for a streaming binary handler. Same shape as
 * `web/exportbans.php` (which has lived at panel-root since the v1.x
 * line for the same reason) and `web/getdemo.php`.
 *
 * Lifecycle (mirrors the plan's "Request flow" diagram):
 *
 *   1. POST-only. GET / HEAD / etc. return HTTP 405 with a plain
 *      text body. The form posts; a direct GET is either a hostile
 *      probe or operator typing the URL into the address bar.
 *   2. `init.php` bootstraps `$userbank`, `CSRF`, `$GLOBALS['PDO']`,
 *      `SB_VERSION`, `SB_DEMOS`, `SB_CACHE`. Per the panel-wide
 *      convention this comes BEFORE anything else.
 *   3. CSRF validation via `CSRF::rejectIfInvalid()`. The form
 *      template emits `{csrf_field}`; the helper checks the form
 *      field AND the `X-CSRF-Token` header. Rejects via toast+redirect
 *      on failure.
 *   4. Owner-only permission check via `HasAccess(WebPermission::Owner)`.
 *      Non-owner attempts log a warning row to the audit log and
 *      return HTTP 403 with a plain-text body — there's no chrome
 *      to render at this layer of the stack.
 *   5. Shared-host hardening: `@set_time_limit(0)`,
 *      `@ini_set('memory_limit', '256M')`, `ignore_user_abort(true)`.
 *      Wrapped with `@` so a `disable_functions = set_time_limit,ini_set`
 *      host doesn't inject warning text into the response body.
 *   6. Output buffer drain: walk every `ob_get_level()` and end them.
 *      Apache + the SAPI may have stacked buffers; we want bytes to
 *      flow directly from the writer's `flush()` calls to the wire.
 *   7. `X-Accel-Buffering: no` + Apache's `no-gzip` so reverse
 *      proxies and Apache don't re-buffer our streamed bytes.
 *   8. Mode dispatch: `zip` streams to `php://output`; `s3` builds
 *      to a tempfile under `SB_CACHE/exports/` and PUTs the result
 *      to the operator's presigned URL.
 *   9. Every reachable terminal branch emits an audit-log entry —
 *      successes via `LogType::Message`, failures via `LogType::Error`
 *      with the `ExportError::code()` value pinned in the body so
 *      the operator can correlate the toast they saw with the audit
 *      row.
 *
 * Cap semantics (per-mode): the underlying archive is Zip64 in both
 * modes (no structural size ceiling), so the cap is mode-conditional.
 * `zip` mode (direct browser download) is uncapped — the writer is
 * constructed with `capBytes: null` and the running cap check
 * no-ops. `s3` mode (presigned PUT) is capped at
 * {@see Manifest::MAX_S3_PUT_BYTES} minus
 * {@see Manifest::SAFETY_MARGIN_BYTES} because S3 single-PUT is
 * structurally limited to 5 GiB across every S3-API-compatible
 * provider (above that the operator has to switch to multipart
 * upload, a fundamentally different flow). The s3 arm checks the
 * pre-flight `exceeds_cap` flag BEFORE staging anything to disk so
 * a too-big bundle bails up front; the writer's running check is
 * defence-in-depth for JSONL byte-estimate undershoot.
 *
 * Error handling: this entry point deliberately catches ONLY
 * `ExportError` — anything else (a real DB outage, a memory
 * exhaustion, a regression in the writer) propagates to the
 * dispatcher's generic 500 so the stack trace lands in the audit
 * log via the project's error handler. Catching `Throwable` blanket
 * would mask real bugs behind a generic "export failed" toast.
 */

use Sbpp\Export\BundleWriter;
use Sbpp\Export\EntityExporter;
use Sbpp\Export\ExportError;
use Sbpp\Export\Manifest;
use Sbpp\Export\ManifestBuilder;
use Sbpp\Export\S3PresignedUploader;
use Sbpp\Security\CSRF;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

require_once __DIR__ . '/init.php';

// ---------------------------------------------------------------------
// 1. Method gate
// ---------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo "POST required.\n";
    exit;
}

// ---------------------------------------------------------------------
// 3. CSRF (load-bearing — this is a state-changing surface that
//    emits PII; a stale tab MUST NOT be able to trigger an export).
//    `rejectIfInvalid` 403s with a plain-text body on failure.
// ---------------------------------------------------------------------
CSRF::rejectIfInvalid();

// ---------------------------------------------------------------------
// 4. Owner-only permission gate. The navbar + palette filter hides
//    the entry from non-owners (UX), but the load-bearing security
//    check is right here at the entry point. A direct curl post by
//    a partial-permission admin hits THIS gate, not the navbar.
// ---------------------------------------------------------------------
if (!$userbank->HasAccess(WebPermission::Owner)) {
    Log::add(
        LogType::Warning,
        'Data Export',
        sprintf(
            'Non-owner aid=%d attempted to POST /export.php — blocked at permission gate.',
            $userbank->GetAid(),
        ),
    );
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    exit;
}

// ---------------------------------------------------------------------
// 5-7. Shared-host hardening + response buffer drain.
// ---------------------------------------------------------------------
@set_time_limit(0);
@ini_set('memory_limit', '256M');
ignore_user_abort(true);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Accel-Buffering: no');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

// ---------------------------------------------------------------------
// 8. Mode dispatch.
// ---------------------------------------------------------------------
$mode = isset($_POST['mode']) ? (string) $_POST['mode'] : '';
if (!in_array($mode, ['zip', 's3'], true)) {
    // L1: log the attempt so an operator triaging "the form posted
    // but I got an error toast" can correlate the rejected mode
    // value with the painted toast. The mode value is operator-
    // typed indirectly (the form template hardcodes `zip` / `s3`),
    // so anything else is either a stale tab or a curl-driven
    // probe; either way the audit log is the source of truth.
    Log::add(
        LogType::Warning,
        'Data Export',
        sprintf(
            'Rejected POST: aid=%d invalid mode=%s',
            $userbank->GetAid(),
            substr($mode, 0, 32),
        ),
    );
    sbpp_export_redirect_failure('mode_invalid', $mode);
}

// Build the manifest pre-flight. The cap is s3-mode-specific (S3
// single-PUT is structurally limited to 5 GiB; ZIP direct download
// is uncapped under Zip64), so cap enforcement moves into the s3
// arm below — the manifest carries the `exceeds_cap` flag for the
// arm to read and short-circuit on.
$manifest = (new ManifestBuilder(
    dbs:          $GLOBALS['PDO'],
    demosDir:     SB_DEMOS,
    panelVersion: SB_VERSION,
))->build();

$entities = new EntityExporter(
    dbs:      $GLOBALS['PDO'],
    demosDir: SB_DEMOS,
);

if ($mode === 'zip') {
    sbpp_export_run_zip_mode($manifest, $entities, $userbank->GetAid());
} else {
    sbpp_export_run_s3_mode($manifest, $entities, $userbank->GetAid());
}

// ---------------------------------------------------------------------
// Helpers below this line — locals to the entry point so they can't
// be reached from another scope.
// ---------------------------------------------------------------------

/**
 * Stream the bundle straight to the client's TCP socket as
 * `Content-Type: application/zip`. The writer's `flush()` after each
 * entry keeps the browser's download progress bar moving.
 *
 * On success: the response naturally ends when `ZipStream::finish()`
 * emits the central directory and the script exits. The audit-log
 * row lands AFTER the bytes are gone — that's deliberate; logging
 * before would race the client-abort path.
 *
 * On `ExportError` mid-stream (the running-byte cap trip): we can't
 * redirect — the response headers are already on the wire as
 * `application/zip` — so we log the error, push whatever bytes have
 * already been written downstream (the consumer will see a
 * truncated ZIP), and exit. The browser surfaces the truncation
 * as a "download failed" message; the audit log carries the why.
 */
function sbpp_export_run_zip_mode(Manifest $manifest, EntityExporter $entities, int $aid): never
{
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="sbpp-export-' . $manifest->bundle_id . '.zip"');
    // Defeat caching at every reasonable hop — the bundle is a one-shot
    // dynamic response and any caching would leak across operators.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        // Falls through to the generic 500 (the dispatcher's
        // Throwable handler) — `fopen('php://output')` failing is
        // a real bug, not an operator-actionable error.
        throw new RuntimeException('Failed to open php://output for writing.');
    }

    // Zip64 (ZipStream v3.x default) — direct ZIP download is
    // uncapped by design; the BundleWriter receives no `capBytes`
    // so its running cap check no-ops.
    $zip = new ZipStream(
        outputStream:             $output,
        sendHttpHeaders:          false,
        defaultCompressionMethod: CompressionMethod::DEFLATE,
    );

    $writer = new BundleWriter(
        zip:                $zip,
        manifest:           $manifest,
        entities:           $entities,
        demosDir:           SB_DEMOS,
        flushAfterEntries:  true,
        // capBytes: null (default) — zip mode is uncapped.
    );

    try {
        $writer->write();
        Log::add(
            LogType::Message,
            'Data Export',
            sprintf(
                'ZIP mode bundle delivered: aid=%d bundle_id=%s estimated=%d bytes=%d',
                $aid,
                $manifest->bundle_id,
                $manifest->estimated_bundle_bytes,
                $writer->bytesWritten(),
            ),
        );
    } catch (ExportError $e) {
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'ZIP mode failed mid-stream: aid=%d bundle_id=%s code=%s — %s',
                $aid,
                $manifest->bundle_id,
                $e->code(),
                $e->getMessage(),
            ),
        );
    }
    exit;
}

/**
 * Build the bundle to a tempfile under `SB_CACHE/exports/`, then
 * PUT it to the operator's presigned URL. On success → 302 redirect
 * back to the admin page with a `?result=success` arm; on
 * `ExportError` → 302 with `?result=error&code=...`. The shutdown
 * function guarantees the tempfile is cleaned up even if a mid-build
 * fatal escapes.
 */
function sbpp_export_run_s3_mode(Manifest $manifest, EntityExporter $entities, int $aid): never
{
    // ----- S3 single-PUT cap pre-flight ------------------------
    // S3 single-PUT is structurally limited to 5 GiB across every
    // S3-API-compatible provider (AWS S3, Cloudflare R2, MinIO,
    // Backblaze B2, Wasabi). Above that requires multipart upload,
    // a fundamentally different flow than presigned single-PUT.
    // Short-circuit BEFORE staging anything to disk so a too-big
    // bundle bails up front; the writer's running cap check is
    // defence-in-depth for JSONL byte-estimate undershoot.
    // The operator's escape hatch is the direct ZIP download form
    // (uncapped under Zip64) or pruning data.
    if ($manifest->exceeds_cap) {
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'S3 mode pre-flight failed: aid=%d bundle_id=%s code=%s — estimated %d bytes exceeds the %d-byte S3 PUT cap.',
                $aid,
                $manifest->bundle_id,
                ExportError::CAP_EXCEEDED,
                $manifest->estimated_bundle_bytes,
                $manifest->cap_bytes,
            ),
        );
        sbpp_export_redirect_failure(
            ExportError::CAP_EXCEEDED,
            sprintf(
                'Bundle estimate %d bytes exceeds the S3 PUT cap of %d bytes (5 GiB minus a %d-byte safety margin). '
                . 'Use direct ZIP download (uncapped) or prune data and retry.',
                $manifest->estimated_bundle_bytes,
                $manifest->cap_bytes,
                Manifest::SAFETY_MARGIN_BYTES,
            ),
        );
    }

    // ----- presigned URL validation (server-side; the form template's
    //       `pattern="^https://...$"` is the UX-first gate) -------
    $presignUrl = isset($_POST['presign_url']) ? trim((string) $_POST['presign_url']) : '';
    if ($presignUrl === '' || strlen($presignUrl) > 2048) {
        // L1: log the malformed-URL rejection so audit-trail
        // visibility matches the painted toast. Do NOT log the
        // raw URL (operator-typed; could carry sensitive presign
        // signature parameters even on the failure branch) —
        // log a length signature instead. The audit log is for
        // operator triage, not credential capture.
        Log::add(
            LogType::Warning,
            'Data Export',
            sprintf(
                'Rejected POST: aid=%d bundle_id=%s S3 presign URL length=%d (must be 1-2048).',
                $aid,
                $manifest->bundle_id,
                strlen($presignUrl),
            ),
        );
        sbpp_export_redirect_failure(
            ExportError::PRESIGN_INVALID_URL,
            'Presigned URL must be 1-2048 characters.',
        );
    }

    // ----- staging file under SB_CACHE/exports/ -----------------
    $stagingDir = SB_CACHE . 'exports' . DIRECTORY_SEPARATOR;
    if (!is_dir($stagingDir) && !@mkdir($stagingDir, 0755, true) && !is_dir($stagingDir)) {
        // L2: include bundle_id so the audit row can be correlated
        // with the manifest-build event upstream of this branch.
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'S3 mode failed: aid=%d bundle_id=%s — cache dir not writable: %s',
                $aid,
                $manifest->bundle_id,
                $stagingDir,
            ),
        );
        sbpp_export_redirect_failure(
            ExportError::DISK_WRITE_FAILED,
            'Bundle staging directory is not writable.',
        );
    }

    $tmpFile = $stagingDir . $manifest->bundle_id . '.zip';

    // Register the cleanup BEFORE the build so a mid-build fatal
    // still wipes the staging file. The shutdown function fires
    // after `exit`/`die`/the natural script end, regardless of
    // whether an uncaught Throwable bubbled up.
    register_shutdown_function(static function () use ($tmpFile): void {
        if (is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    });

    $output = @fopen($tmpFile, 'wb');
    if ($output === false) {
        // L2: include bundle_id for cross-reference with the
        // manifest-build event upstream + the cleanup register
        // call below.
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'S3 mode failed: aid=%d bundle_id=%s — could not open staging file: %s',
                $aid,
                $manifest->bundle_id,
                $tmpFile,
            ),
        );
        sbpp_export_redirect_failure(
            ExportError::DISK_WRITE_FAILED,
            'Failed to open bundle staging file for write.',
        );
    }

    // Zip64 (ZipStream v3.x default). The cap below isn't a
    // Zip64 limitation — it's the S3 single-PUT object-size
    // ceiling, enforced by the BundleWriter's running cap check.
    $zip = new ZipStream(
        outputStream:             $output,
        sendHttpHeaders:          false,
        defaultCompressionMethod: CompressionMethod::DEFLATE,
    );

    $writer = new BundleWriter(
        zip:                $zip,
        manifest:           $manifest,
        entities:           $entities,
        demosDir:           SB_DEMOS,
        flushAfterEntries:  false,
        // M1: hand the writer the staging-file handle so its
        // running cap counter snaps to the on-disk `fstat` size
        // after each entry — exact compressed-byte tracking
        // instead of the conservative uncompressed-byte estimate
        // the zip-mode path is stuck with. Documented on
        // BundleWriter::bytesWritten + BundleWriter::currentCompressedSize.
        outputHandle:       $output,
        // S3 PUT cap: the writer enforces the 5 GiB single-PUT
        // ceiling (minus the JSONL-estimate safety margin)
        // mid-stream. Pre-flight above caught the obvious
        // overshoot; this is defence-in-depth for the case
        // where the row-byte estimate undershot reality.
        capBytes:           Manifest::s3PutCapBytes(),
    );

    try {
        $writer->write();
    } catch (ExportError $e) {
        @fclose($output);
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'S3 mode build failed: aid=%d bundle_id=%s code=%s — %s',
                $aid,
                $manifest->bundle_id,
                $e->code(),
                $e->getMessage(),
            ),
        );
        sbpp_export_redirect_failure($e->code(), $e->getMessage());
    }

    // ZipStream::finish closes the underlying handle on most builds,
    // but we re-call fclose defensively in case a future ZipStream
    // change changes that behaviour. The double-close is a no-op
    // when the handle is already gone.
    if (is_resource($output)) {
        @fclose($output);
    }

    // ----- upload -----------------------------------------------
    try {
        (new S3PresignedUploader())->upload($presignUrl, $tmpFile);
    } catch (ExportError $e) {
        Log::add(
            LogType::Error,
            'Data Export',
            sprintf(
                'S3 mode upload failed: aid=%d bundle_id=%s code=%s — %s',
                $aid,
                $manifest->bundle_id,
                $e->code(),
                $e->getMessage(),
            ),
        );
        sbpp_export_redirect_failure($e->code(), $e->getMessage());
    }

    // Success: the shutdown function will clean up the tempfile;
    // we also explicitly unlink here so the file is gone the
    // instant the upload acknowledges, not whenever PHP gets
    // around to shutdown handlers.
    if (is_file($tmpFile)) {
        @unlink($tmpFile);
    }

    Log::add(
        LogType::Message,
        'Data Export',
        sprintf(
            'S3 mode upload OK: aid=%d bundle_id=%s estimated=%d bytes=%d',
            $aid,
            $manifest->bundle_id,
            $manifest->estimated_bundle_bytes,
            $writer->bytesWritten(),
        ),
    );

    sbpp_export_redirect_success($manifest->bundle_id);
}

/**
 * 302 back to the admin export page with a `result=success` arm.
 * The page handler reads the query, drops a success toast via
 * {@see \Sbpp\View\Toast::emit}, and renders the form.
 */
function sbpp_export_redirect_success(string $bundleId): never
{
    header('Location: ?p=admin&c=export&result=success&bid=' . rawurlencode($bundleId));
    exit;
}

/**
 * 302 back to the admin export page with a `result=error&code=...`
 * arm. The page handler maps the code back to an operator-readable
 * message and surfaces it via a persistent error toast (so the
 * operator can't miss it — the destructive operation FAILED and
 * cleanup may be needed).
 *
 * `$context` is logged but NOT propagated to the redirect URL — we
 * trust the toast's operator-facing message to be self-explanatory,
 * and the audit log already carries the full diagnostic body.
 */
function sbpp_export_redirect_failure(string $code, string $context = ''): never
{
    header('Location: ?p=admin&c=export&result=error&code=' . rawurlencode($code));
    exit;
}
