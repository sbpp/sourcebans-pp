<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

/**
 * Throwable raised by API handlers to short-circuit dispatch with a
 * structured error envelope. The dispatcher serialises any ApiError as
 * {"ok": false, "error": {"code": "...", "message": "..."}} and the
 * client renders it via sb.message.error().
 *
 * Optional $field carries a form-field id so the client can scope the
 * error to a specific input (matching legacy xajax addAssign("field.msg")).
 */
class ApiError extends \RuntimeException
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
