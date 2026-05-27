<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

use RuntimeException;
use Throwable;

/**
 * Typed exception class for the export subsystem.
 *
 * Every error the pre-flight, bundle writer, or S3 uploader surfaces
 * lands here so the entry point ({@see \web/export.php}) can branch on
 * `$e->code` and emit the right operator-facing toast. Anything OTHER
 * than `ExportError` that escapes is a real bug and deliberately not
 * caught — let it propagate to the dispatcher's generic 500 so the
 * stack trace lands in the audit log and the regression is visible.
 *
 * The `$code` strings are documented in
 * `docs/src/content/docs/configuring/data-export.mdx` — pinning them
 * as class constants here keeps the wire-facing identifiers single-
 * source and lets call sites read `ExportError::CAP_EXCEEDED` instead
 * of stringly-typed `'cap_exceeded'` literals.
 */
final class ExportError extends RuntimeException
{
    /**
     * The pre-flight or running-byte gate tripped: the bundle would
     * exceed the ZIP 2.0 4 GiB ceiling (minus the safety margin) if
     * we kept going. The operator's mitigation is to clear stale
     * demos / unrelated rows before retrying.
     */
    public const CAP_EXCEEDED = 'cap_exceeded';

    /**
     * The S3 (or compatible) PUT returned a non-2xx HTTP status. The
     * exception message carries the response body truncated to 2 KiB
     * for diagnostics; the audit log entry surfaces the same.
     */
    public const S3_PUT_FAILED = 's3_put_failed';

    /**
     * The presigned URL the operator pasted didn't start with
     * `https://`. We refuse `http://` outright — the full panel
     * dataset is in flight, including admin emails and IPs.
     */
    public const PRESIGN_INVALID_SCHEME = 'presign_invalid_scheme';

    /**
     * The presigned URL didn't parse via `parse_url` (no host, no
     * path, or returned `false`). Catches operator typos before the
     * cURL request fires.
     */
    public const PRESIGN_INVALID_URL = 'presign_invalid_url';

    /**
     * Writing to the on-disk bundle staging area
     * (`SB_CACHE/exports/<bundle_id>.zip`) failed — typically a
     * missing / unwritable cache dir on a misconfigured install.
     */
    public const DISK_WRITE_FAILED = 'disk_write_failed';

    /**
     * Mid-write `fwrite()` returned a short count or `false`. The
     * disk filled up before the bundle finished serialising.
     */
    public const DISK_FULL = 'disk_full';

    /**
     * Wire-facing error code, one of the class constants above.
     *
     * Named `errorCode` (not `code`) because the parent
     * `\Exception::$code` is `int`-typed at the language level and
     * native PHP doesn't allow narrowing a readwrite `int` property
     * to a readonly `string`. The accessor below mirrors the
     * "fluent enum-like check" affordance call sites would otherwise
     * reach for via `$e->code`.
     */
    public readonly string $errorCode;

    public function __construct(
        string $code,
        string $message,
        ?Throwable $previous = null,
    ) {
        $this->errorCode = $code;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Wire-facing error code accessor.
     *
     * Provided so call sites read as
     * `match ($e->code()) { ExportError::CAP_EXCEEDED => ... }`
     * without having to learn the underlying property name.
     */
    public function code(): string
    {
        return $this->errorCode;
    }
}
