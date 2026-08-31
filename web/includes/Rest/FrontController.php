<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;
use Sbpp\Auth\UserManager;
use Throwable;

/**
 * REST `/api/v1` dispatcher. PAT auth only. No CSRF. Cookie JWT is ignored.
 */
final class FrontController
{
    /**
     * In-process dispatch for tests and `api/v1.php`.
     */
    public static function dispatch(?string $rawBody = null): Response
    {
        Api::bootstrap();

        $cors = self::corsHeaders();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS') {
            return Envelope::empty(204, $cors);
        }

        $identity = PatAuthenticator::bindUserbank();
        $rlKey = $identity !== null
            ? 'tok:' . $identity['token_id']
            : 'ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rl = RateLimiter::consume($rlKey);
        $rlHeaders = array_merge($cors, [
            'X-RateLimit-Limit' => (string) $rl['limit'],
            'X-RateLimit-Remaining' => (string) $rl['remaining'],
        ]);
        if (!$rl['ok']) {
            return Envelope::error(
                'rate_limited',
                'Too many requests.',
                429,
                null,
                array_merge($rlHeaders, ['Retry-After' => (string) $rl['retry_after']]),
            );
        }

        try {
            $path = self::requestPath();
            $body = self::decodeBody($method, $rawBody);
            $query = $_GET;
            $router = new Router(Routes::all());
            $matched = $router->match($method, $path);

            if (isset($matched['error'])) {
                $allow = implode(', ', $matched['allow']);
                $headers = $rlHeaders;
                if ($matched['error'] === 405 && $allow !== '') {
                    $headers['Allow'] = $allow;
                }
                $code = $matched['error'] === 405 ? 'method_not_allowed' : 'not_found';
                $message = $matched['error'] === 405 ? 'Method not allowed.' : 'Not found.';
                return Envelope::error($code, $message, $matched['error'], null, $headers);
            }

            /** @var array{route: array{method: string, path: string, auth: bool, perm: int|string, handler: callable}, params: array<string, string>} $matched */
            $route = $matched['route'];
            $params = $matched['params'];

            if ($identity === null && self::presentedWellFormedPat()) {
                return Envelope::error('unauthorized', 'A valid API token is required.', 401, null, $rlHeaders);
            }

            if ($route['auth']) {
                /** @var UserManager $userbank */
                $userbank = $GLOBALS['userbank'];
                if (!$userbank->is_logged_in()) {
                    return Envelope::error('unauthorized', 'A valid API token is required.', 401, null, $rlHeaders);
                }
                if ($route['perm'] !== 0 && $route['perm'] !== '' && !$userbank->HasAccess($route['perm'])) {
                    return Envelope::error('forbidden', 'No access', 403, null, $rlHeaders);
                }
            }

            $response = ($route['handler'])($params, $body, $query);
            if (!$response instanceof Response) {
                return Envelope::error('server_error', 'Handler returned an invalid response.', 500, null, $rlHeaders);
            }
            return new Response(
                $response->status,
                $response->payload,
                array_merge($rlHeaders, $response->headers),
                $response->rawBody,
                $response->contentType,
            );
        } catch (ApiError $e) {
            $mapped = Envelope::fromApiError($e);
            return new Response(
                $mapped->status,
                $mapped->payload,
                array_merge($rlHeaders, $mapped->headers),
            );
        } catch (Throwable $e) {
            error_log('[rest] uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $msg = (defined('DEBUG_MODE') && DEBUG_MODE)
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : 'An unexpected error occurred. See server logs for details.';
            return Envelope::error('server_error', $msg, 500, null, $rlHeaders);
        }
    }

    /**
     * A well-formed PAT that did not bind is 401 on every route, including
     * public GET. Missing or junk Authorization stays anonymous.
     */
    private static function presentedWellFormedPat(): bool
    {
        $header = PatAuthenticator::authorizationHeader();
        if (preg_match('/^Bearer\s+(\S+)/i', trim($header), $m) !== 1) {
            return false;
        }
        return PatAuthenticator::isWellFormedSecret($m[1]);
    }

    public static function requestPath(): string
    {
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if (is_string($pathInfo) && $pathInfo !== '') {
            return Router::normalize($pathInfo);
        }

        $uri = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (preg_match('#/api/v1(?:\.php)?(/.*)?$#', $uri, $m) === 1) {
            return Router::normalize($m[1] ?? '/');
        }
        return '/';
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeBody(string $method, ?string $rawBody): array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }
        $raw = $rawBody ?? (file_get_contents('php://input') ?: '');
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiError('bad_request', 'Invalid JSON body.', null, 400);
        }
        if (!is_array($decoded)) {
            throw new ApiError('bad_request', 'JSON body must be an object.', null, 400);
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private static function corsHeaders(): array
    {
        if (!defined('SB_REST_CORS_ORIGINS') || SB_REST_CORS_ORIGINS === '') {
            return [];
        }
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return [];
        }
        $allowed = array_map('trim', explode(',', (string) SB_REST_CORS_ORIGINS));
        if (!in_array($origin, $allowed, true)) {
            return [];
        }
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Access-Control-Allow-Methods' => 'GET, PUT, PATCH, POST, DELETE, OPTIONS',
            'Vary' => 'Origin',
        ];
    }
}
