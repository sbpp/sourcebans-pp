<?php

// Issue #1352: backfill `:prefix_bans.RemoveType` for pre-2.0 rows that
// carry `RemovedOn IS NOT NULL` but `RemoveType IS NULL`. Two flavours
// of pre-existing row land in this bucket, distinguished by `RemovedBy`:
//
//   1. Admin-lifted bans (`RemovedBy IS NOT NULL AND RemovedBy > 0`):
//      v1.x panels older than the SourceBans++ fork wrote `RemovedOn` +
//      `RemovedBy` + `ureason` on unban but didn't always populate
//      `RemoveType` (the column predates the panel's earliest released
//      schema by a wide margin, but the v1.x code paths that set it
//      were inconsistent — some forks set 'U', others left it NULL).
//      The v2.0 `?p=banlist&state=unbanned` server-side filter classifies
//      these rows by the disjunction defined in `page.banlist.php`, but
//      the on-disk normalisation here means the index hit (`KEY
//      RemoveType`) lights up post-upgrade and the API parity surface
//      in `api_bans_detail` returns `state: 'unbanned'` instead of
//      mis-classifying as `'active'` (the row's `ends` may still be in
//      the future).
//
//   2. Naturally-expired bans (`RemovedBy IS NULL OR RemovedBy = 0`):
//      `PruneBans()` (`web/includes/system-functions.php`) writes
//      `RemovedBy = 0, RemoveType = 'E', RemovedOn = NOW()` on every
//      banlist render, but pre-475 installs that ran the v1.x prune
//      logic — or rows where an admin manually populated `RemovedOn`
//      via the legacy AMXBans import — may have `RemovedOn` set with
//      no `RemoveType`. Tag them as `'E'` so the chip filter and the
//      API surface treat them as natural expiry.
//
// Both updates are idempotent. Each WHERE pins `RemoveType IS NULL`,
// so a second run matches zero rows. The order matters: the admin-lift
// pass runs first so a row with both `RemovedBy > 0` AND `RemovedOn`
// can never be mis-tagged as `'E'`. Re-running after a partial failure
// is safe — the second pass picks up anything the first missed and
// neither pass touches a row that was already tagged.
//
// `RemoveType IN ('D', 'U', 'E')` rows are deliberately untouched: the
// migration's contract is "rows the panel hasn't already classified".
// A row tagged `'D'` (deleted), `'U'` (admin unban), or `'E'` (natural
// expiry) by a previous panel run carries the right tag already; the
// `RemoveType IS NULL` guard makes that invariant visible at the SQL
// layer.
//
// `$this` is supplied by Updater::update() which loads this file inside
// the Updater instance scope; PHPStan can't see that, so each
// `$this->dbs` call is suppressed in the same way every sibling
// migration would be (the others live in phpstan-baseline.neon).

// Pass 1: admin-lifted rows.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_bans`"
    . " SET `RemoveType` = 'U'"
    . " WHERE `RemovedOn` IS NOT NULL"
    . "   AND `RemoveType` IS NULL"
    . "   AND `RemovedBy` IS NOT NULL"
    . "   AND `RemovedBy` > 0"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

// Pass 2: naturally-expired rows. `RemovedBy = 0` is the post-475
// PruneBans() shape; `RemovedBy IS NULL` covers pre-475 installs whose
// expired rows never went through the prune writer.
// @phpstan-ignore variable.undefined
$this->dbs->query(
    "UPDATE `:prefix_bans`"
    . " SET `RemoveType` = 'E'"
    . " WHERE `RemovedOn` IS NOT NULL"
    . "   AND `RemoveType` IS NULL"
    . "   AND (`RemovedBy` IS NULL OR `RemovedBy` = 0)"
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
