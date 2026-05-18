<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.
/**
 * E2E shim — orphan a `:prefix_bans` row by setting its `aid` to a
 * non-existent admin id.
 *
 * Drives the `page.banlist.php` capital-NOT branch
 * (`'Player NOT Unbanned'`, L159 post-#1409) which fires when the
 * `INNER JOIN :prefix_admins` lookup at L72 returns empty even
 * though the bans row itself still exists. This is the documented
 * "destructive action FAILED" branch the #1409 follow-up converts
 * to a persistent toast (`duration_ms: 0`); the
 * `toast-persistent-duration.spec.ts` E2E spec uses this shim to
 * trigger it without faking the wire format client-side.
 *
 * The "real" production shape of an orphan ban is a bans row whose
 * `aid` points at an admin row that was later deleted (the SQL
 * has no foreign-key constraint between the two tables — the panel
 * keeps the historical bans even after their owner leaves). The
 * cheapest way to reproduce this in e2e is to mint a normal ban
 * via `seedBanViaApi`, then UPDATE its `aid` to a value that
 * doesn't exist in `:prefix_admins`. We don't want to delete the
 * `admin/admin` row (that would break every other spec sharing
 * the e2e DB), so the cleaner shape is the orphan-by-update.
 *
 * Why not INSERT directly into `:prefix_bans` instead of UPDATE-ing
 * a previously-seeded row? Two reasons:
 *   1. The bans schema requires a lot of fields with non-obvious
 *      shape constraints (ends/created timestamps, the type/authid
 *      pair, steam_universe-aware aid mapping). Driving the seed
 *      through the existing `seedBanViaApi` (Actions.BansAdd) gets
 *      every constraint right by definition; the UPDATE is a
 *      single-field surgical mutation on top.
 *   2. Direct INSERT would duplicate the validation logic the
 *      panel-side handler already encapsulates. The AGENTS.md
 *      "Why 'via API' instead of an INSERT shim" rationale in
 *      `seeds.ts` documents the same trade-off.
 *
 * Usage (inside the web container):
 *
 *   echo '{"bid":42,"new_aid":99999}' | php orphan-ban-aid-e2e.php
 *
 * - `bid` is the bid returned from `seedBanViaApi`
 * - `new_aid` is the orphan target. Pick a large integer that's
 *   clearly outside any realistic seq range (99999 is the
 *   convention this spec uses; matches the NONEXISTENT_BID
 *   constant in `banlist-getfallback-toast.spec.ts`).
 *
 * Mirror of the `set-setting-e2e.php` / `seed-comms-e2e.php`
 * stdin-JSON pattern; same e2e-only DB refusal guard.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "orphan-ban-aid-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to orphan a ban on DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "orphan-ban-aid-e2e.php: empty stdin payload.\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "orphan-ban-aid-e2e.php: stdin is not a JSON object.\n");
    exit(2);
}

$bid    = isset($decoded['bid'])     ? (int) $decoded['bid']     : 0;
$newAid = isset($decoded['new_aid']) ? (int) $decoded['new_aid'] : 0;

if ($bid <= 0) {
    fwrite(STDERR, "orphan-ban-aid-e2e.php: missing / non-positive 'bid' key.\n");
    exit(2);
}
if ($newAid <= 0) {
    fwrite(STDERR, "orphan-ban-aid-e2e.php: missing / non-positive 'new_aid' key.\n");
    exit(2);
}

// Defensive sanity: make sure `new_aid` REALLY doesn't exist in
// :prefix_admins, otherwise the test scenario degrades from
// "orphan ban → capital-NOT branch" to "ban owned by another admin
// → some other code path entirely". Throw loudly if the caller
// picks a colliding id.
$GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE aid = :aid");
$GLOBALS['PDO']->bind(':aid', $newAid);
$exists = $GLOBALS['PDO']->single();
if ($exists !== false && $exists !== null && $exists !== []) {
    fwrite(STDERR, "orphan-ban-aid-e2e.php: new_aid=$newAid already exists in :prefix_admins — "
        . "pick a larger value (the spec convention is 99999).\n");
    exit(2);
}

$GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET aid = :new_aid WHERE bid = :bid");
$GLOBALS['PDO']->bindMultiple([
    ':new_aid' => $newAid,
    ':bid'     => $bid,
]);
$GLOBALS['PDO']->execute();

fwrite(STDOUT, "orphaned bid=$bid (aid → $newAid) on " . DB_NAME . "\n");
