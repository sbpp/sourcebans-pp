<?php

/**
 * Ban / comm-block removal classifier.
 *
 * Wraps `:prefix_bans.RemoveType` and `:prefix_comms.RemoveType`, both
 * `varchar(3) NULL` (see `web/install/includes/sql/struc.sql`). The
 * on-disk schema is a nullable single-letter code — `NULL` for active
 * rows, one of the cases below for terminal states. Diverging from the
 * issue brief's "int" framing on purpose: the actual column is a
 * `varchar`, so the enum is **string-backed** to keep `$enum->value`
 * type-aligned with the column at every bind site.
 *
 * Reading from disk: an empty string or `NULL` means the ban is still
 * active — DO NOT call `BanRemoval::from('')` on those rows; either
 * branch on the empty-string check first or use `tryFrom()` and treat
 * `null` as "active". Writing: `INSERT … VALUES (NULL, …)` for fresh
 * bans; bind `$removal->value` for terminal-state writes.
 *
 * Issue #1290 phase D.3.
 */
enum BanRemoval: string
{
    /**
     * Ban / block was deleted by an admin (the row stays in
     * `:prefix_bans` but is hidden from the active list). The
     * "Delete ban" surface in the banlist Edit page emits this.
     */
    case Deleted = 'D';

    /**
     * Ban / block was lifted by an admin. The "Unban" / "Unmute" /
     * "Ungag" surfaces emit this; `RemovedBy` carries the admin's
     * `aid` and `ureason` carries the unban reason.
     */
    case Unbanned = 'U';

    /**
     * Ban / block was retired by `PruneBans()` because its `length`
     * elapsed (`length != 0 AND ends < UNIX_TIMESTAMP()`). The hourly
     * sweep in `system-functions.php::PruneBans()` writes this; it is
     * never set by an admin action.
     */
    case Expired = 'E';
}
