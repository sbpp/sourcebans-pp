<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\Api;
use Sbpp\Auth\UserManager;
use Sbpp\Config;
use WebPermission;

/**
 * Fires `sm_rehash` on the given server ids when admin rehashing is enabled.
 */
final class Rehasher
{
    /**
     * @param list<int> $sids
     * @return array{attempted: bool, sids: list<int>, results: list<array{sid: int, success: bool}>}
     */
    public static function run(array $sids): array
    {
        $sids = array_values(array_unique(array_map('intval', $sids)));
        $userbank = $GLOBALS['userbank'] ?? null;
        $rehashMask = WebPermission::mask(
            WebPermission::Owner,
            WebPermission::EditAdmins,
            WebPermission::EditGroups,
            WebPermission::AddAdmins,
        );
        if (
            $sids === []
            || !Config::getBool('config.enableadminrehashing')
            || !$userbank instanceof UserManager
            || !$userbank->HasAccess($rehashMask)
        ) {
            return ['attempted' => false, 'sids' => $sids, 'results' => []];
        }

        $csv = implode(',', array_map(static fn (int $sid): string => (string) $sid, $sids));
        $out = Api::invoke('system.rehash_admins', ['servers' => $csv]);
        /** @var list<array{sid: int, success: bool}> $results */
        $results = is_array($out['results'] ?? null) ? $out['results'] : [];

        return [
            'attempted' => true,
            'sids' => $sids,
            'results' => $results,
        ];
    }

    /**
     * @return list<int>
     */
    public static function allEnabledSids(): array
    {
        $rows = $GLOBALS['PDO']->query(
            'SELECT sid FROM `:prefix_servers` WHERE enabled = 1'
        )->resultset();
        $sids = [];
        foreach ($rows as $row) {
            $sids[] = (int) $row['sid'];
        }
        return $sids;
    }
}
