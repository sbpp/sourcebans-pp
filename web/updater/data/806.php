<?php

// Issue #1272: bit-31 web permission flags (`ADMIN_UNBAN_GROUP_BANS = 2^31`,
// `ALL_WEB = 4294966783`) were silently corrupted by JS Int32 coercion in
// the master-detail flag grid (fixed in `web/themes/default/page_admin_groups_list.tpl`)
// AND by the `INT(10)` SIGNED storage in `:prefix_groups.flags` /
// `:prefix_admins.extraflags`. The signed range tops out at `2^31 - 1`,
// so anything with bit 31 set was either truncated, clamped, or
// round-tripped only by the bit-pattern coincidence of two-complement
// arithmetic between PHP int64 and MariaDB int32.
//
// This migration widens both columns to `INT UNSIGNED` so the on-disk
// representation matches the spec'd flag range. Fresh installs land on
// the same column type via `install/includes/sql/struc.sql`.
//
// Step ordering (per column):
//   1. ALTER MODIFY ... BIGINT NOT NULL — widens the column to a type
//      that can hold both the legacy SIGNED bit-pattern AND the
//      target unsigned values without out-of-range errors. This is
//      reversible and preserves every existing row's value verbatim.
//   2. UPDATE ... SET flags = flags + 4294967296 WHERE flags < 0 —
//      reinterprets the legacy negative bit-pattern as its unsigned
//      32-bit equivalent. `2^32 = 4294967296` is exactly the modulus
//      that maps the SIGNED-INT negative half-range back onto the
//      UNSIGNED-INT upper half-range (e.g. -2147483648 → 2147483648 =
//      ADMIN_UNBAN_GROUP_BANS, -513 → 4294966783 = ALL_WEB).
//      We can't `CAST(flags AS UNSIGNED)` directly — the cast yields
//      the right number, but assigning it back to a still-SIGNED
//      column would raise `1264 Out of range value` under
//      `STRICT_TRANS_TABLES` (the default in MariaDB 10.x). The
//      BIGINT widening above is what makes the assignment legal.
//   3. ALTER MODIFY ... INT UNSIGNED NOT NULL — narrows the column to
//      the target shape. Every value now fits because step 2
//      converted the negatives.
//
// Idempotency:
//   - Step 1 is naturally idempotent: a MODIFY to the same shape is a
//     no-op (MariaDB only rebuilds the table when the shape actually
//     changes). On a re-run after a successful first run, step 1
//     re-widens INT UNSIGNED → BIGINT (preserving values, all of
//     which fit in BIGINT SIGNED), step 2 matches no rows
//     (no negatives), and step 3 re-narrows BIGINT → INT UNSIGNED.
//   - Step 2's `WHERE flags < 0` matches no rows once data is corrected.
//   - Step 3 leaves the column unchanged on a re-run with all values
//     already in the unsigned range.
//
// `:prefix_admins.extraflags` carries the same flag mask for per-admin
// permission deltas (set via `web/pages/admin.edit.adminperms.php`),
// so it has the same bit-31 problem and gets the same treatment in
// the same migration.
//
// `$this` is supplied by Updater::update() which loads this file inside
// the Updater instance scope; PHPStan can't see that, so the
// `$this->dbs` calls are suppressed in the same way every sibling
// migration would be.

// ---- :prefix_groups.flags ------------------------------------------

// 1) Widen flags to BIGINT so legacy negatives survive AND the
//    unsigned-equivalent values fit during the rewrite.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "ALTER TABLE `:prefix_groups` MODIFY COLUMN `flags` BIGINT NOT NULL"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// 2) Rewrite negative bit-patterns to their unsigned 32-bit
//    equivalents (`x + 2^32` for x < 0).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_groups` SET `flags` = `flags` + 4294967296 WHERE `flags` < 0"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// 3) Narrow flags to the target INT UNSIGNED shape.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "ALTER TABLE `:prefix_groups` MODIFY COLUMN `flags` INT UNSIGNED NOT NULL"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// ---- :prefix_admins.extraflags -------------------------------------

// @phpstan-ignore variable.undefined
$this->dbs->query(
    "ALTER TABLE `:prefix_admins` MODIFY COLUMN `extraflags` BIGINT NOT NULL"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_admins` SET `extraflags` = `extraflags` + 4294967296 WHERE `extraflags` < 0"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// @phpstan-ignore variable.undefined
$this->dbs->query(
    "ALTER TABLE `:prefix_admins` MODIFY COLUMN `extraflags` INT UNSIGNED NOT NULL"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
