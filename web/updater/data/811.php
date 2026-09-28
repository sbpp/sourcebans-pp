<?php

// Issue #1578: `:prefix_bans` ships `KEY type_authid (type, authid)` and
// `KEY type_ip (type, ip)` in `struc.sql`, and `702.php` adds them for
// installs that upgraded through that range. Installs that reached a
// later `config.version` by another path (old installer seeds, manual
// schema imports, forks) never got them, so `PruneBans()`'s submission
// lookup had no composite index to probe and 2.2.1's `FORCE INDEX`
// hints fataled every banlist / servers / dashboard render.
//
// Add each index only when no index of that name exists. The
// `information_schema` probe keeps this portable (MySQL has no
// `ADD INDEX IF NOT EXISTS`) and makes re-runs a no-op. Fresh installs
// already carry both indexes from `struc.sql`, so they converge.
//
// `$this` is supplied by Updater::update() which loads this file inside
// the Updater instance scope; PHPStan can't see that, so each
// `$this->dbs` call is suppressed in the same way as sibling migrations.

// @phpstan-ignore variable.undefined
$bansTable = $this->dbs->getPrefix() . '_bans';

$indexes = [
    'type_authid' => '(`type`, `authid`)',
    'type_ip'     => '(`type`, `ip`)',
];

foreach ($indexes as $name => $columns) {
    // @phpstan-ignore variable.undefined
    $this->dbs->query(
        'SELECT COUNT(*) AS n FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA = DATABASE()'
        . ' AND TABLE_NAME = :table'
        . ' AND INDEX_NAME = :index'
    );
    // @phpstan-ignore variable.undefined
    $this->dbs->bind(':table', $bansTable);
    // @phpstan-ignore variable.undefined
    $this->dbs->bind(':index', $name);
    // @phpstan-ignore variable.undefined
    $row = $this->dbs->single();

    if ((int) ($row['n'] ?? 0) > 0) {
        continue;
    }

    // @phpstan-ignore variable.undefined
    $this->dbs->query("ALTER TABLE `:prefix_bans` ADD INDEX `$name` $columns");
    // @phpstan-ignore variable.undefined
    $this->dbs->execute();
}

return true;
