<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

namespace Sbpp\Api;

use RuntimeException;

/**
 * Throwable raised by API handlers to short-circuit dispatch with a
 * structured error envelope. The dispatcher serialises any ApiError as
 * {"ok": false, "error": {"code": "...", "message": "..."}} and the
 * client renders it via sb.message.error().
 *
 * Optional $field carries a form-field id so the client can scope the
 * error to a specific input (matching legacy xajax addAssign("field.msg")).
 */
final class ApiError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $field = null,
        public readonly int $httpStatus = 200,
    ) {
        parent::__construct($message);
    }
}

// Issue #1290 phase B: legacy global-name shim. The api/handlers/*.php
// files still throw `new ApiError(...)`; this alias keeps them working
// until the call-site sweep PR.
class_alias(\Sbpp\Api\ApiError::class, 'ApiError');
