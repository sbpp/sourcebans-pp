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
    /** @var array<string, array{fn: callable, perm: int|string, requireAdmin: bool, public: bool}> */
    private static array $registry = [];

    private static bool $bootstrapped = false;

    /**
     * $perm is either an int bitmask (web flags from web.json) or a string
     * of sourcemod flag chars (m, z, ...). CUserManager::HasAccess() accepts
     * both forms; we forward whichever was registered.
     *
     * Auth model:
     *   public=true                            -> anyone (login form, public pages)
     *   public=false, perm=0, requireAdmin=0   -> any logged-in user (dispatcher-enforced)
     *   public=false, requireAdmin=true        -> any web admin (HasAccess(ALL_WEB))
     *   public=false, perm!=0                  -> caller must hold the flag bitmask / sm chars
     *
     * The "logged-in" baseline is enforced by the dispatcher itself, so a
     * registration that omits perm/requireAdmin still fails closed for
     * anonymous callers — handlers do not have to remember to call
     * is_logged_in() themselves.
     */
    public static function register(
        string $action,
        callable $fn,
        int|string $perm = 0,
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
            // Baseline: any non-public action requires a logged-in caller.
            // Without this, registering Api::register('foo', 'fn') with all
            // defaults silently exposes the handler to anonymous callers.
            if (!$userbank->is_logged_in()) {
                self::logHackingAttempt($action, 'not logged in');
                throw new ApiError('forbidden', 'No access', null, 403);
            }
            if ($entry['requireAdmin'] && !$userbank->is_admin()) {
                self::logHackingAttempt($action, 'not an admin');
                throw new ApiError('forbidden', 'No access', null, 403);
            }
            $hasPerm = $entry['perm'] !== 0 && $entry['perm'] !== '';
            if ($hasPerm && !$userbank->HasAccess($entry['perm'])) {
                self::logHackingAttempt($action, 'missing required permission');
                throw new ApiError('forbidden', 'No access', null, 403);
            }
        }

        $result = ($entry['fn'])($params);
        return is_array($result) ? $result : [];
    }

    /**
     * Centralised audit log for permission-denied API calls. The xajax
     * handlers used to emit a per-action "Hacking Attempt" warning; this
     * preserves that signal for SIEM consumers now that auth is enforced
     * in the dispatcher.
     */
    private static function logHackingAttempt(string $action, string $why): void
    {
        global $userbank;
        // Don't blow up the request if Log isn't initialised yet (CLI tests
        // that hit invoke() before bootstrap).
        if (!class_exists('Log', false)) {
            return;
        }
        $who = ($userbank instanceof CUserManager && $userbank->is_logged_in())
            ? (string)$userbank->GetProperty('user')
            : ($_SERVER['REMOTE_ADDR'] ?? 'anonymous');
        try {
            Log::add('w', 'Hacking Attempt', "$who tried to call $action ($why).");
        } catch (\Throwable $e) {
            // Logging must never block the auth response.
        }
    }

    /**
     * Top-level entry point. Reads JSON body, validates CSRF, runs the
     * handler, encodes the JSON envelope, exits.
     */
    public static function dispatch(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rawBody = file_get_contents('php://input') ?: '';
        // CSRF::fromRequest() reads $_POST/$_GET/X-CSRF-Token header, so it
        // works as a fallback when the body isn't JSON or doesn't carry
        // csrf_token. The body field still wins when present.
        $headerToken = CSRF::fromRequest() ?? '';

        [$status, $envelope] = self::handle($method, $rawBody, $headerToken);
        if ($status !== 200) {
            http_response_code($status);
        }
        echo self::encodeEnvelope($envelope);
        exit;
    }

    /**
     * JSON-encode the response envelope. Extracted from dispatch() so
     * tests can assert encoder behaviour in-process without spawning a
     * subprocess (dispatch() exits).
     *
     * JSON_INVALID_UTF8_SUBSTITUTE: server-query responses sometimes
     * surface legacy-encoded player/host names (Latin-1, CP1252) that
     * PHP treats as UTF-8. Without the substitute flag json_encode
     * returns FALSE on the first bad byte, the client sees an empty
     * body, and the per-server admin tile goes dark (#971). Replacing
     * invalid sequences with U+FFFD keeps the response well-formed
     * and the rest of the payload readable.
     *
     * @param array<string, mixed> $envelope
     */
    public static function encodeEnvelope(array $envelope): string
    {
        return (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Pure dispatcher: accepts the raw HTTP-shaped inputs and returns
     * `[status_code, envelope_array]`. Extracted so tests can exercise
     * method/JSON/CSRF gates without spawning a subprocess.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public static function handle(string $method, string $rawBody, string $headerToken): array
    {
        if (strtoupper($method) !== 'POST') {
            return [405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'POST required']]];
        }

        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return [400, ['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'Invalid JSON body']]];
        }

        $action = is_string($body['action'] ?? null) ? $body['action'] : '';
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        if ($action === '') {
            return [400, ['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'Missing action']]];
        }

        // CSRF protection. The token may also arrive in the JSON body for
        // tools that can't set headers (xhr fallback).
        $token = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : $headerToken;
        if (!CSRF::validate($token)) {
            return [403, ['ok' => false, 'error' => ['code' => 'csrf', 'message' => 'CSRF token validation failed']]];
        }

        try {
            $result = self::invoke($action, $params);
            if (isset($result['__redirect']) && is_string($result['__redirect'])) {
                return [200, ['ok' => false, 'redirect' => $result['__redirect']]];
            }
            return [200, ['ok' => true, 'data' => (object)$result]];
        } catch (ApiError $e) {
            $err = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            if ($e->field !== null) {
                $err['field'] = $e->field;
            }
            return [$e->httpStatus, ['ok' => false, 'error' => $err]];
        } catch (\Throwable $e) {
            $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : 'An unexpected error occurred. See server logs for details.';
            error_log('[api] uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [500, ['ok' => false, 'error' => ['code' => 'server_error', 'message' => $msg]]];
        }
    }
}
