<?php

// Issue #1509: durable ban/comm issuer attribution + soft-retire for admins.
//
// 1. `:prefix_admins.enabled` — soft-retire flag (1 = active, 0 = inactive).
//    Inactive admins keep their row so LEFT JOINs still resolve usernames,
//    but panel auth and SourceMod admin load refuse them.
// 2. `:prefix_bans.admin_name` / `:prefix_comms.admin_name` — denormalized
//    snapshot of the issuing admin's username at insert time (and re-snapshotted
//    on hard delete). Display prefers this column so hard-deleting an admin
//    no longer paints "deleted" on historical bans.
//
// Each ADD is guarded by a portable information_schema existence check
// (same shape as 801.php) so the migration is idempotent on MySQL and
// MariaDB. The backfill JOINs copy live `admins.user` into empty snapshots;
// already-orphaned rows (aid with no matching admin) stay empty and surface
// as "Unknown" in the UI.
//
// `$this` is supplied by Updater::update(), which loads this file inside
// the Updater instance scope; PHPStan can't see that, so `$this->dbs`
// reads below are suppressed inline.

/**
 * Add a column only when it isn't already present.
 *
 * @param callable(string, string, string): void $ensure
 */
$ensureColumn = static function (\Database $dbs, string $tableSuffix, string $column, string $alterSql): void {
    $dbs->query(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() '
        . 'AND TABLE_NAME = :table '
        . 'AND COLUMN_NAME = :column'
    );
    $dbs->bind(':table', $dbs->getPrefix() . '_' . $tableSuffix);
    $dbs->bind(':column', $column);
    $row = $dbs->single();

    if (is_array($row) && (int) ($row['c'] ?? 0) > 0) {
        return;
    }

    $dbs->query($alterSql);
    $dbs->execute();
};

// @phpstan-ignore variable.undefined
$ensureColumn($this->dbs, 'admins', 'enabled',
    'ALTER TABLE `:prefix_admins` ADD COLUMN `enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
// @phpstan-ignore variable.undefined
$ensureColumn($this->dbs, 'bans', 'admin_name',
    'ALTER TABLE `:prefix_bans` ADD COLUMN `admin_name` VARCHAR(64) NOT NULL DEFAULT \'\''
);
// @phpstan-ignore variable.undefined
$ensureColumn($this->dbs, 'comms', 'admin_name',
    'ALTER TABLE `:prefix_comms` ADD COLUMN `admin_name` VARCHAR(64) NOT NULL DEFAULT \'\''
);

// Backfill snapshots from live admin rows. Idempotent: only empty snapshots.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    'UPDATE `:prefix_bans` AS BA'
    . ' INNER JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid'
    . ' SET BA.admin_name = AD.user'
    . ' WHERE BA.admin_name = \'\''
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// @phpstan-ignore variable.undefined
$this->dbs->query(
    'UPDATE `:prefix_comms` AS CO'
    . ' INNER JOIN `:prefix_admins` AS AD ON CO.aid = AD.aid'
    . ' SET CO.admin_name = AD.user'
    . ' WHERE CO.admin_name = \'\''
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
