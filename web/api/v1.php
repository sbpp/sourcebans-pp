<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

// REST API v1 for external clients. PAT auth. No CSRF. Cookie JWT is ignored.
ini_set('display_errors', '0');

set_exception_handler(function (\Throwable $e): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    error_log('[rest] uncaught: ' . $e);
    $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        : 'An unexpected error occurred. See server logs for details.';
    echo json_encode(['error' => ['code' => 'server_error', 'message' => $msg]]);
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
    error_log(sprintf('[rest] fatal: %s in %s:%d', $err['message'], $err['file'], $err['line']));
    $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? sprintf('%s @ %s:%d', $err['message'], $err['file'], $err['line'])
        : 'A fatal error occurred. See server logs for details.';
    echo json_encode(['error' => ['code' => 'fatal', 'message' => $msg]]);
});

if (!defined('SBPP_REST')) {
    define('SBPP_REST', true);
}

include_once dirname(__DIR__) . '/init.php';
require_once INCLUDES_PATH . '/system-functions.php';

\Sbpp\Rest\FrontController::dispatch()->send();
