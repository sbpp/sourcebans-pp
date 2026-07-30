<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

namespace Sbpp\Auth;

use Sbpp\Db\Database;

/**
 * Schema probes for `:prefix_admins` columns that landed after a panel
 * version is already live. Auth + updater boot through {@see UserManager}
 * before migration 811 can add `enabled`; probing once (and treating a
 * missing column as "everyone is active") lets `/updater/` run instead
 * of fatalling on `Unknown column 'adm.enabled'`.
 */
final class AdminsSchema
{
    private static ?bool $hasEnabledColumn = null;

    public static function hasEnabledColumn(Database $dbs): bool
    {
        if (self::$hasEnabledColumn !== null) {
            return self::$hasEnabledColumn;
        }

        $dbs->query(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table '
            . 'AND COLUMN_NAME = \'enabled\''
        );
        $dbs->bind(':table', $dbs->getPrefix() . '_admins');
        $row = $dbs->single();
        self::$hasEnabledColumn = is_array($row) && (int) ($row['c'] ?? 0) > 0;

        return self::$hasEnabledColumn;
    }

    /** Reset the probe cache (tests / same-request post-migration). */
    public static function clearCache(): void
    {
        self::$hasEnabledColumn = null;
    }
}
