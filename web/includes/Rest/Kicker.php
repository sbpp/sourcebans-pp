<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Api\ApiError;

/**
 * Server-side RCON kick fan-out for REST `POST /bans` with `kick: true`.
 * The panel chrome uses a kickit iframe; bots cannot. Failures per
 * server are collected. The ban row is already committed.
 */
final class Kicker
{
    /**
     * @return array{attempted: int, results: list<array{sid: int, status: string, code?: string}>}
     */
    public static function fanOut(string $check, int $type): array
    {
        $loaded = Api::invoke('kickit.load_servers', []);
        $servers = $loaded['servers'] ?? [];
        if (!is_array($servers)) {
            return ['attempted' => 0, 'results' => []];
        }

        $results = [];
        $attempted = 0;
        foreach ($servers as $server) {
            if (!is_array($server)) {
                continue;
            }
            $sid = (int) ($server['sid'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (empty($server['has_rcon'])) {
                $results[] = ['sid' => $sid, 'status' => 'no_rcon'];
                continue;
            }
            $attempted++;
            try {
                $kick = Api::invoke('kickit.kick_player', [
                    'check' => $check,
                    'sid' => $sid,
                    'num' => (int) ($server['num'] ?? 0),
                    'type' => $type,
                    'mode' => 'ban',
                ]);
                $results[] = [
                    'sid' => $sid,
                    'status' => (string) ($kick['status'] ?? 'unknown'),
                ];
            } catch (ApiError $e) {
                $results[] = [
                    'sid' => $sid,
                    'status' => 'error',
                    'code' => $e->errorCode,
                ];
            }
        }

        return ['attempted' => $attempted, 'results' => $results];
    }
}
