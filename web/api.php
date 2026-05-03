<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

// Never let a PHP warning/notice/deprecation print to stdout: it would
// corrupt the JSON envelope that the client tries to parse. Errors still
// reach the server log via error_log() in Api::dispatch().
ini_set('display_errors', '0');

// Last-resort safety net: if init.php dies, a handler triggers a fatal,
// or anything else escapes Api::dispatch()'s try/catch, still return a
// well-formed JSON envelope so the client never has to guess.
set_exception_handler(function (\Throwable $e): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    error_log('[api] uncaught: ' . $e);
    $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        : 'An unexpected error occurred. See server logs for details.';
    echo json_encode(['ok' => false, 'error' => ['code' => 'server_error', 'message' => $msg]]);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array($err['type'], $fatal, true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    error_log(sprintf('[api] fatal: %s in %s:%d', $err['message'], $err['file'], $err['line']));
    $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? sprintf('%s @ %s:%d', $err['message'], $err['file'], $err['line'])
        : 'A fatal error occurred. See server logs for details.';
    echo json_encode(['ok' => false, 'error' => ['code' => 'fatal', 'message' => $msg]]);
});

include_once __DIR__ . '/init.php';
require_once INCLUDES_PATH . '/system-functions.php';
require_once INCLUDES_PATH . '/Api.php';

Api::bootstrap();
Api::dispatch();
