<?php

// Steam Networking (SDR / FakeIP) cleanup. Valve's SDK 2013 hands the server a
// placeholder 169.254.x.x address for SDR clients instead of a real IP. It is
// shared by every such player, so it identifies nobody; the SourceMod plugins
// no longer store it (see `SBPP_GetClientPublicIP` in `sbpp_server_ip.inc`).
// This migration removes the placeholder from rows written by older plugins.
//
//   1. IP bans (`type = 1`) of an SDR player: the "IP" was the shared
//      placeholder, so the row blocked (or, worse, would block) every SDR
//      client. The plugin's SteamID-only check can't see `type = 1` rows, so
//      convert the row to a SteamID ban (`type = 0`) when it carries a valid
//      SteamID, and clear the placeholder. Rows without a valid SteamID are
//      left as they are, only the placeholder is cleared below.
//   2. Any other `ip` still holding a placeholder is set to NULL, matching
//      the shape of SteamID bans written without an IP.
//   3. `adminIp` placeholders (admins connecting over SDR) are blanked in both
//      `:prefix_bans` and `:prefix_comms`.
//   4. In-game reports (`SBReportPlayer`) store the reporter's IP in
//      `:prefix_submissions.ip` and the target's in `sip`. Placeholders there
//      are blanked (`ip` is NOT NULL) or set to NULL (`sip`).
//
// `:prefix_protests.ip` is NOT touched: it holds the web visitor's address,
// not a game client's.
//
// Every pass is idempotent: once a row has been rewritten it no longer matches
// its own WHERE clause, so a re-run after a partial failure is safe.
//
// `$this` is supplied by Updater::update(); PHPStan can't see that, so each
// `$this->dbs` call is suppressed like every sibling migration.

// Pass 1: SDR IP bans with a real SteamID become SteamID bans.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_bans`"
    . " SET `type` = 0, `ip` = NULL"
    . " WHERE `type` = 1"
    . "   AND `ip` LIKE '169.254.%'"
    . "   AND `authid` REGEXP '^STEAM_[0-9]:[0-9]:[0-9]+$'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// Pass 2: clear any remaining placeholder IP.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_bans`"
    . " SET `ip` = NULL"
    . " WHERE `ip` LIKE '169.254.%'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// Pass 3: placeholder admin IPs.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_bans`"
    . " SET `adminIp` = ''"
    . " WHERE `adminIp` LIKE '169.254.%'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_comms`"
    . " SET `adminIp` = ''"
    . " WHERE `adminIp` LIKE '169.254.%'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// Pass 4: placeholder IPs from in-game reports.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_submissions`"
    . " SET `ip` = ''"
    . " WHERE `ip` LIKE '169.254.%'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_submissions`"
    . " SET `sip` = NULL"
    . " WHERE `sip` LIKE '169.254.%'"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
