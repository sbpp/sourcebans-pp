<?php

/**
 * Ban "subject" classifier.
 *
 * Wraps `:prefix_bans.type TINYINT NOT NULL DEFAULT '0'` (see
 * `web/install/includes/sql/struc.sql`). The on-disk schema stays
 * `int`-shaped; this enum is the PHP-side typed wrapper so call sites
 * read `BanType::Steam` / `BanType::Ip` instead of bare `0` / `1`
 * literals.
 *
 * At every SQL bind site, pass `$type->value` (the column-typed
 * primitive) so phpstan-dba sees an int for the `TINYINT` column.
 * Wire-format crossings (form POST, query string, JSON `params`) parse
 * the value with `(int)` first and then call `BanType::from($int)` —
 * the same untyped int still reaches the disk; the enum is purely
 * an in-PHP type-safety layer.
 *
 * Issue #1290 phase D.2.
 */
enum BanType: int
{
    /**
     * Ban keyed on the player's Steam ID (`bans.authid`). Created via
     * the Add-Ban form's `type=0` radio option, the per-player drawer's
     * "Ban this account" CTA, and the friends/group bulk paths.
     */
    case Steam = 0;

    /**
     * Ban keyed on the player's IP address (`bans.ip`). Created via the
     * Add-Ban form's `type=1` radio option and the legacy
     * `banned_ip.cfg` import in `admin.bans.php`.
     */
    case Ip = 1;
}
