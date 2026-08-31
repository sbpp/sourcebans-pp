<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\ApiError;

/**
 * REST envelope builder. HTTP status is load-bearing (unlike the panel RPC).
 *
 * Success: `{ "data": …, "meta": { … } }`
 * Error:   `{ "error": { "code", "message", "field"? } }`
 */
final class Envelope
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     * @param array<string, string> $headers
     */
    public static function ok(array $data, array $meta = [], int $status = 200, array $headers = []): Response
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return new Response($status, $payload, $headers);
    }

    /** @param array<string, string> $headers */
    public static function empty(int $status = 204, array $headers = []): Response
    {
        return new Response($status, [], $headers);
    }

    /** @param array<string, string> $headers */
    public static function error(
        string $code,
        string $message,
        int $status,
        ?string $field = null,
        array $headers = [],
    ): Response {
        $err = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $err['field'] = $field;
        }
        return new Response($status, ['error' => $err], $headers);
    }

    public static function fromApiError(ApiError $e): Response
    {
        $status = $e->httpStatus !== 200 ? $e->httpStatus : self::statusForCode($e->errorCode);
        return self::error($e->errorCode, $e->getMessage(), $status, $e->field);
    }

    public static function yaml(string $body, int $status = 200): Response
    {
        return new Response(
            $status,
            [],
            [],
            $body,
            'application/yaml; charset=utf-8',
        );
    }

    private static function statusForCode(string $code): int
    {
        return match ($code) {
            'not_found' => 404,
            'forbidden' => 403,
            'validation', 'bad_request', 'bad_password', 'bad_email' => 400,
            'cannot_delete_owner',
            'cannot_deactivate_owner',
            'already_inactive',
            'already_active',
            'already_banned',
            'already_blocked',
            'not_active',
            'immune',
            'duplicate',
            'mod_exists',
            'conflict' => 409,
            'delete_failed' => 500,
            default => 400,
        };
    }
}
