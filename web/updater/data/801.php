<?php

// Issue #1498 (regression of #1472's fix in #1473): add the admin lockout
// columns idempotently on BOTH MySQL and MariaDB.
//
// #1473 swapped the original bare `ADD` for `ADD IF NOT EXISTS` so panels
// that already carry the columns (v1.8.x installs, or a re-run after a
// partial pass) stop fataling with SQLSTATE[42S21] "Duplicate column name".
// But `ALTER TABLE ... ADD [COLUMN] IF NOT EXISTS` is MariaDB-only syntax —
// MySQL (every version, 8.x included) rejects it with SQLSTATE[42000] 1064
// "...near 'IF NOT EXISTS `attempts` INT DEFAULT 0'". The dev/CI database is
// MariaDB, so the syntax sailed past both the regression test and PHPStan's
// dba gate (which also introspects MariaDB), and the break only surfaced on
// self-hosters running stock MySQL.
//
// Guard each ADD with a portable information_schema existence check instead:
// same idempotent contract, runs on both engines, converges to the same
// schema. Fresh installs get the columns from install/includes/sql/struc.sql
// and never run the updater.
//
// `$this` is supplied by Updater::update(), which loads this file inside the
// Updater instance scope; PHPStan can't see that, so the two `$this->dbs`
// reads below are suppressed the same way every sibling migration is (the
// older ones live in phpstan-baseline.neon; new ignores go inline).

/**
 * Add a column to `:prefix_admins` only when it isn't already present.
 *
 * Portable across MySQL + MariaDB: the existence probe is plain
 * information_schema (no MariaDB-only `ADD IF NOT EXISTS`), and the table
 * name is bound as a value resolved from the live prefix rather than the
 * `:prefix` placeholder so the COLUMN lookup matches the real table.
 */
$ensureAdminColumn = static function (\Database $dbs, string $column, string $alterSql): void {
    $dbs->query(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() '
        . 'AND TABLE_NAME = :table '
        . 'AND COLUMN_NAME = :column'
    );
    $dbs->bind(':table', $dbs->getPrefix() . '_admins');
    $dbs->bind(':column', $column);
    $row = $dbs->single();

    if (is_array($row) && (int) ($row['c'] ?? 0) > 0) {
        return; // column already present — idempotent no-op
    }

    $dbs->query($alterSql);
    $dbs->execute();
};

// @phpstan-ignore variable.undefined
$ensureAdminColumn($this->dbs, 'attempts', 'ALTER TABLE `:prefix_admins` ADD COLUMN `attempts` INT DEFAULT 0');
// @phpstan-ignore variable.undefined
$ensureAdminColumn($this->dbs, 'lockout_until', 'ALTER TABLE `:prefix_admins` ADD COLUMN `lockout_until` DATETIME DEFAULT NULL');

return true;
