<?php

// Issue #1165: per-player notes scratchpad surfaced by the player-detail
// drawer's Notes tab. Fresh installs get the table from
// install/includes/sql/struc.sql; this migration backfills it on
// upgrades so admins on existing panels see the same Notes pane the
// drawer's Notes tab tries to populate.
//
// CREATE TABLE IF NOT EXISTS makes this idempotent — re-running is a
// no-op. The shape mirrors `:prefix_comments` (long text body, admin
// id, created timestamp) but is keyed off `steam_id` rather than `bid`
// so notes survive ban-row churn (a re-ban / unban makes a new bid;
// the player's history is still the same Steam ID).
//
// `$this` is supplied by Updater::update() which loads this file inside
// the Updater instance scope; PHPStan can't see that, so the next two
// calls are suppressed in the same way every sibling migration would
// be (the others live in phpstan-baseline.neon).
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "CREATE TABLE IF NOT EXISTS `:prefix_notes` ("
    . "  `nid` int(10) NOT NULL AUTO_INCREMENT,"
    . "  `steam_id` varchar(64) NOT NULL DEFAULT '',"
    . "  `aid` int(6) NOT NULL,"
    . "  `body` text NOT NULL,"
    . "  `created` int(11) NOT NULL DEFAULT '0',"
    . "  PRIMARY KEY (`nid`),"
    . "  KEY `steam_id` (`steam_id`),"
    . "  KEY `aid` (`aid`),"
    . "  KEY `created` (`created`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
