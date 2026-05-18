<?php

/**
 * Letter codes the audit-log writer pins on every row.
 *
 * Wraps `:prefix_log.type enum('m','w','e')` (see
 * `web/install/includes/sql/struc.sql`). The on-disk column stays a
 * MySQL `enum('m','w','e')`; this enum is the PHP-side typed wrapper
 * so call sites read as intent — `Log::add(LogType::Warning, …)` —
 * rather than as the bare `'w'` magic char they used to be.
 *
 * At every SQL bind site, pass `$type->value` (the column-typed
 * primitive) so phpstan-dba sees a string for the `enum('m','w','e')`
 * column. The case itself is for in-PHP type-safety only.
 *
 * Issue #1290 phase D.1.
 */
enum LogType: string
{
    /**
     * Informational audit entry — admin actions, ban changes, settings
     * tweaks, mod / server / group / submission / protest mutations.
     * Roughly 90% of audit-log volume.
     */
    case Message = 'm';

    /**
     * Warning entry — typically "hacking attempt" rows logged when a
     * user without the relevant permission flag tries to call a
     * privileged surface, plus a handful of `E_USER_WARNING` traps
     * from `sbError()`.
     */
    case Warning = 'w';

    /**
     * Error entry — `E_USER_ERROR` traps, mail send failures, RCON
     * connection failures, "data not found" surfaces in edit pages.
     */
    case Error = 'e';
}
