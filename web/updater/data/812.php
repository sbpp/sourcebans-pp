<?php

// Issue #1578: ensure the composite `type_authid` / `type_ip` indexes exist
// on `:prefix_bans`. `702.php` adds them, but some long-lived installs carry a
// `config.version` past 702 without the indexes, so 702 never runs for them.
// `PruneBans()` relies on these indexes on every banlist / home / servers render.
//
// Each ADD is guarded by a portable information_schema probe (no
// MariaDB-only `ADD INDEX IF NOT EXISTS`), so re-running is a no-op and the
// migration works on MySQL and MariaDB alike. Fresh installs get the indexes
// from install/includes/sql/struc.sql and never run the updater.
//
// `$this` is supplied by Updater::update(); PHPStan can't see that, so each
// `$this->dbs` read is suppressed like every sibling migration.

$ensureBansIndex = static function (\Database $dbs, string $index, string $alterSql): void {
    $dbs->query(
        'SELECT COUNT(*) AS c FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE() '
        . 'AND TABLE_NAME = :table '
        . 'AND INDEX_NAME = :index'
    );
    $dbs->bind(':table', $dbs->getPrefix() . '_bans');
    $dbs->bind(':index', $index);
    $row = $dbs->single();

    if (is_array($row) && (int) ($row['c'] ?? 0) > 0) {
        return;
    }

    $dbs->query($alterSql);
    $dbs->execute();
};

// @phpstan-ignore variable.undefined
$ensureBansIndex($this->dbs, 'type_authid', 'ALTER TABLE `:prefix_bans` ADD INDEX `type_authid` (`type`, `authid`)');
// @phpstan-ignore variable.undefined
$ensureBansIndex($this->dbs, 'type_ip', 'ALTER TABLE `:prefix_bans` ADD INDEX `type_ip` (`type`, `ip`)');

return true;
