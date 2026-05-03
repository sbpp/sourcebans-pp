<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

require_once __DIR__ . '/ApiError.php';

/**
 * JSON action dispatcher. Replaces the xajax callback pipeline.
 *
 * Wire format:
 *   Request:  POST /api.php { "action": "topic.verb", "params": {...} }
 *             with X-CSRF-Token header (or csrf_token field).
 *   Response: 200 application/json
 *             { "ok": true, "data": ... }                       on success
 *             { "ok": false, "error": { "code", "message", "field"? } } on handled error
 *             { "ok": false, "redirect": "..." }                on auth/redirect
 *
 * Handlers are pure functions: array $params -> array (the data envelope).
 * Throw ApiError to surface a structured client-side message.
 * Return ['__redirect' => '...'] (or call Api::redirect()) to navigate.
 */
class Api
{
    /** @var array<string, array{fn: callable, perm: int, requireAdmin: bool, public: bool}> */
    private static array $registry = [];

    private static bool $bootstrapped = false;

    public static function register(
        string $action,
        callable $fn,
        int $perm = 0,
        bool $requireAdmin = false,
        bool $public = false
    ): void {
        self::$registry[$action] = [
            'fn'           => $fn,
            'perm'         => $perm,
            'requireAdmin' => $requireAdmin,
            'public'       => $public,
        ];
    }

    /** Exposed for tests: clear and re-register all handlers. */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        require_once __DIR__ . '/../api/handlers/_register.php';
        self::$bootstrapped = true;
    }

    /** Sentinel a handler can return to issue a client-side redirect. */
    public static function redirect(string $url): array
    {
        return ['__redirect' => $url];
    }

    /** Look up handler metadata; null when the action is unknown. */
    public static function lookup(string $action): ?array
    {
        return self::$registry[$action] ?? null;
    }

    /**
     * Invoke a registered handler in-process (used by the test harness so
     * it can bypass the HTTP boundary). Throws ApiError on any failure.
     *
     * @return array Raw handler return value (envelope is built by dispatch()).
     */
    public static function invoke(string $action, array $params): array
    {
        self::bootstrap();
        global $userbank;

        $entry = self::$registry[$action] ?? null;
        if ($entry === null) {
            throw new ApiError('unknown_action', "Unknown action: $action");
        }

        if (!$entry['public']) {
            if ($entry['requireAdmin'] && !$userbank->is_admin()) {
                throw new ApiError('forbidden', 'No access', null, 403);
            }
            if ($entry['perm'] !== 0 && !$userbank->HasAccess($entry['perm'])) {
                throw new ApiError('forbidden', 'No access', null, 403);
            }
        }

        $result = ($entry['fn'])($params);
        return is_array($result) ? $result : [];
    }

    /**
     * Top-level entry point. Reads JSON body, validates CSRF, runs the
     * handler, encodes the JSON envelope, exits.
     */
    public static function dispatch(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'POST required']]);
            exit;
        }

        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'Invalid JSON body']]);
            exit;
        }

        $action = is_string($body['action'] ?? null) ? $body['action'] : '';
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        if ($action === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'Missing action']]);
            exit;
        }

        // CSRF protection. The token may also arrive in the JSON body for
        // tools that can't set headers (xhr fallback).
        $token = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : CSRF::fromRequest();
        if (!CSRF::validate($token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => ['code' => 'csrf', 'message' => 'CSRF token validation failed']]);
            exit;
        }

        try {
            $result = self::invoke($action, $params);
            if (isset($result['__redirect']) && is_string($result['__redirect'])) {
                echo json_encode(['ok' => false, 'redirect' => $result['__redirect']]);
                exit;
            }
            echo json_encode(['ok' => true, 'data' => (object)$result], JSON_UNESCAPED_SLASHES);
            exit;
        } catch (ApiError $e) {
            if ($e->httpStatus !== 200) {
                http_response_code($e->httpStatus);
            }
            $err = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            if ($e->field !== null) {
                $err['field'] = $e->field;
            }
            echo json_encode(['ok' => false, 'error' => $err]);
            exit;
        } catch (\Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $msg = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            } else {
                $msg = 'An unexpected error occurred. See server logs for details.';
            }
            error_log('[api] uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => ['code' => 'server_error', 'message' => $msg]]);
            exit;
        }
    }
}
