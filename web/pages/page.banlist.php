<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

use Sbpp\View\BanListView;
use Sbpp\View\Renderer;
use SteamID\SteamID;

global $theme;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
$BansPerPage = SB_BANS_PER_PAGE;
$servers     = [];
global $userbank;
function setPostKey()
{
    if (isset($_SERVER['REMOTE_IP'])) {
        $_SESSION['banlist_postkey'] = md5($_SERVER['REMOTE_IP'] . time() . rand(0, 100000));
    } else {
        $_SESSION['banlist_postkey'] = md5(time() . rand(0, 100000));
    }
}
if (!isset($_SESSION['banlist_postkey']) || strlen((string) $_SESSION['banlist_postkey']) < 4) {
    setPostKey();
}

$page     = 1;
$pagelink = "";

PruneBans();

if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page     = (int) $_GET['page'];
    $pagelink = "&page=" . $page;
}
if (isset($_GET['a']) && $_GET['a'] == "unban" && isset($_GET['id'])) {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }
    // #1301: legacy GET path for unban no longer accepts an empty
    // `ureason`. v1.x prompted via sourcebans.js's UnbanBan() helper
    // and required a non-empty reason; v2.0 silently accepted '', so
    // the audit log lost the *why*. The new modal in page_bans.tpl
    // wires through the JSON `bans.unban` action (which has the same
    // guard); this branch is now the no-JS / hand-edited-URL fallback,
    // and we bounce empty reasons here too so the audit log is
    // consistent across both entry points.
    $unbanReasonRaw = trim((string) ($_GET['ureason'] ?? ''));
    if ($unbanReasonRaw === '') {
        echo "<script>ShowBox('Unban Reason Required', 'You must supply a reason when unbanning a player.', 'red', 'index.php?p=banlist$pagelink');</script>";
        PageDie();
    }
    //we have a multiple unban asking
    if (isset($_GET['bulk'])) {
        $bids = explode(",", $_GET['id']);
    } else {
        $bids = [$_GET['id']];
    }
    $ucount = 0;
    $fail   = 0;
    foreach ($bids as $bid) {
        $bid = (int) $bid;
        $GLOBALS['PDO']->query("SELECT a.aid, a.gid FROM `:prefix_bans` b INNER JOIN `:prefix_admins` a ON a.aid = b.aid WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $res = $GLOBALS['PDO']->single();
        if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban)) && !($userbank->HasAccess(WebPermission::UnbanOwnBans) && $res['aid'] == $userbank->GetAid()) && !($userbank->HasAccess(WebPermission::UnbanGroupBans) && $res['gid'] == $userbank->GetProperty('gid'))) {
            $fail++;
            if (!isset($_GET['bulk'])) {
                die("You don't have access to this");
            }
            continue;
        }

        $GLOBALS['PDO']->query("SELECT b.ip, b.authid,
										b.name, b.created, b.sid, b.type, m.steam_universe, UNIX_TIMESTAMP() as now
										FROM `:prefix_bans` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										LEFT JOIN `:prefix_mods` m ON m.mid = s.modid
										WHERE b.bid = :bid AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP()) AND b.RemoveType IS NULL");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $row = $GLOBALS['PDO']->single();
        if (empty($row) || !$row) {
            $fail++;
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player Not Unbanned', 'The player was not unbanned, either already unbanned or not a valid ban.', 'red', 'index.php?p=banlist$pagelink');</script>";
                PageDie();
            }
            continue;
        }
        $unbanReason = htmlspecialchars($unbanReasonRaw);
        $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET
										`RemovedBy` = :removedby,
										`RemoveType` = :rtype,
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = :ureason
										WHERE `bid` = :bid");
        $GLOBALS['PDO']->bindMultiple([
            ':removedby' => $userbank->GetAid(),
            ':rtype'     => BanRemoval::Unbanned->value,
            ':ureason'   => $unbanReason,
            ':bid'       => $bid,
        ]);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("UPDATE `:prefix_protests` SET archiv = '4' WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $GLOBALS['PDO']->execute();

        $GLOBALS['PDO']->query("SELECT s.sid, m.steam_universe FROM `:prefix_banlog` bl INNER JOIN `:prefix_servers` s ON s.sid = bl.sid INNER JOIN `:prefix_mods` m ON m.mid = s.modid WHERE bl.bid = :bid AND (UNIX_TIMESTAMP() - bl.time <= 300)");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $blocked = $GLOBALS['PDO']->resultset();
        $rowBanType = BanType::tryFrom((int) $row['type']) ?? BanType::Steam;
        foreach ($blocked as $tempban) {
            rcon(($rowBanType === BanType::Steam ? "removeid STEAM_" . $tempban['steam_universe'] . substr((string) $row['authid'], 7) : "removeip " . $row['ip']), $tempban['sid']);
        }
        if (((int) $row['now'] - (int) $row['created']) <= 300 && $row['sid'] != "0" && !in_array($row['sid'], $blocked)) {
            rcon(($rowBanType === BanType::Steam ? "removeid STEAM_" . $row['steam_universe'] . substr((string) $row['authid'], 7) : "removeip " . $row['ip']), $row['sid']);
        }

        if ($res) {
            $type = $rowBanType === BanType::Steam ? $row['authid'] : $row['ip'];
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player Unbanned', '" . $row['name'] . " ($type) has been unbanned from SourceBans.', 'green', 'index.php?p=banlist$pagelink');</script>";
            }
            Log::add(LogType::Message, "Player Unbanned", "$row[name] ($type) has been unbanned. Reason: $unbanReason");
            $ucount++;
        } else {
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Player NOT Unbanned', 'There was an error unbanning " . $row['name'] . "', 'red', 'index.php?p=banlist$pagelink', true);</script>";
            }
            $fail++;
        }
    }
    if (isset($_GET['bulk'])) {
        echo "<script>ShowBox('Players Unbanned', '$ucount players has been unbanned from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
    }
} elseif (isset($_GET['a']) && $_GET['a'] == "delete") {
    if ($_GET['key'] != $_SESSION['banlist_postkey']) {
        die("Possible hacking attempt (URL Key mismatch)");
    }

    if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan))) {
        echo "<script>ShowBox('Error', 'You do not have access to this.', 'red', 'index.php?p=banlist$pagelink');</script>";
        PageDie();
    }
    //we have a multiple ban delete asking
    if (isset($_GET['bulk'])) {
        $bids = explode(",", $_GET['id']);
    } else {
        $bids = [$_GET['id']];
    }
    $dcount = 0;
    $fail   = 0;
    foreach ($bids as $bid) {
        $bid    = (int) $bid;
        $GLOBALS['PDO']->query("SELECT filename FROM `:prefix_demos` WHERE `demid` = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $demres = $GLOBALS['PDO']->single();
        if ($demres) {
            @unlink(SB_DEMOS . "/" . $demres["filename"]);
        }
        $GLOBALS['PDO']->query("SELECT s.sid, m.steam_universe FROM `:prefix_banlog` bl INNER JOIN `:prefix_servers` s ON s.sid = bl.sid INNER JOIN `:prefix_mods` m ON m.mid = s.modid WHERE bl.bid = :bid AND (UNIX_TIMESTAMP() - bl.time <= 300)");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $blocked = $GLOBALS['PDO']->resultset();
        $GLOBALS['PDO']->query("SELECT b.name, b.authid, b.created, b.sid, b.RemoveType, b.ip, b.type, m.steam_universe, UNIX_TIMESTAMP() AS now
										FROM `:prefix_bans` b
										LEFT JOIN `:prefix_servers` s ON s.sid = b.sid
										LEFT JOIN `:prefix_mods` m ON m.mid = s.modid
										WHERE b.bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $steam = $GLOBALS['PDO']->single();
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_banlog` WHERE bid = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $GLOBALS['PDO']->execute();
        $GLOBALS['PDO']->query("DELETE FROM `:prefix_bans` WHERE `bid` = :bid");
        $GLOBALS['PDO']->bind(':bid', $bid);
        $res = $GLOBALS['PDO']->execute();
        $steamBanType = BanType::tryFrom((int) $steam['type']) ?? BanType::Steam;
        if (empty($steam['RemoveType'])) {
            foreach ($blocked as $tempban) {
                rcon(($steamBanType === BanType::Steam ? "removeid STEAM_" . $tempban['steam_universe'] . substr((string) $steam['authid'], 7) : "removeip " . $steam['ip']), $tempban['sid']);
            }
            if (((int) $steam['now'] - (int) $steam['created']) <= 300 && $steam['sid'] != "0" && !in_array($steam['sid'], $blocked)) {
                rcon(($steamBanType === BanType::Steam ? "removeid STEAM_" . $steam['steam_universe'] . substr((string) $steam['authid'], 7) : "removeip " . $steam['ip']), $steam['sid']);
            }
        }

        if ($res) {
            $type = $steamBanType === BanType::Steam ? $steam['authid'] : $steam['ip'];
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Ban Deleted', 'The ban for \'" . $steam['name'] . "\' ($type) has been deleted from SourceBans', 'green', 'index.php?p=banlist$pagelink');</script>";
            }
            Log::add(LogType::Message, "Ban Deleted", "Ban $steam[name] ($type) has been deleted.");
            $dcount++;
        } else {
            if (!isset($_GET['bulk'])) {
                echo "<script>ShowBox('Ban NOT Deleted', 'The ban for \'" . $steam['name'] . "\' had an error while being removed.', 'red', 'index.php?p=banlist$pagelink', true);</script>";
            }
            $fail++;
        }
    }
    if (isset($_GET['bulk'])) {
        echo "<script>ShowBox('Players Deleted', '$dcount players has been deleted from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
    }
}

$BansStart = (int) (($page - 1) * $BansPerPage);
$BansEnd   = (int) ($BansStart + $BansPerPage);

// hide inactive bans feature
if (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "true") { // hide
    $_SESSION["hideinactive"] = true;
    //ShowBox('Hide inactive bans', 'Inactive bans will be hidden from the banlist.', 'green', 'index.php?p=banlist', true);
} elseif (isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "false") { // show
    unset($_SESSION["hideinactive"]);
    //ShowBox('Show inactive bans', 'Inactive bans will be shown in the banlist.', 'green', 'index.php?p=banlist', true);
}
if (isset($_SESSION["hideinactive"])) {
    $hidetext      = "Show";
    $hideinactive  = " AND RemoveType IS NULL";
    $hideinactiven = " WHERE RemoveType IS NULL";
} else {
    $hidetext      = "Hide";
    $hideinactive  = "";
    $hideinactiven = "";
}

// #1226: public ?server=<sid> + ?time=<1d|7d|30d> filter parity with
// the commslist URL surface. Both are applied as additional AND
// predicates on top of whichever upper branch (searchText / no
// filter / advSearch) is active, so they compose with the existing
// search box AND with the legacy ?advType=where_banned shim.
//
// Mirrors the $hideinactive / $hideinactiven pattern: build two
// fragments — `$publicFilterAnd` (` AND a = ? AND b = ?`, appended
// to a query that already opens a WHERE) and `$publicFilterWheren`
// (` WHERE a = ? AND b = ?`, used when no other clause has opened
// one yet).
$serverFilter = isset($_GET['server']) ? trim((string) $_GET['server']) : '';
$timeFilter   = isset($_GET['time'])   ? trim((string) $_GET['time'])   : '';
$publicFilterTimeMap = [
    '1d'  => 86400,
    '7d'  => 7 * 86400,
    '30d' => 30 * 86400,
];

$publicFilterClauses = [];
$publicFilterArgs    = [];
$publicFilterLink    = '';
if ($serverFilter !== '' && ctype_digit($serverFilter)) {
    $publicFilterClauses[] = 'BA.sid = ?';
    $publicFilterArgs[]    = (int) $serverFilter;
    $publicFilterLink     .= '&server=' . urlencode($serverFilter);
}
if ($timeFilter !== '' && isset($publicFilterTimeMap[$timeFilter])) {
    $publicFilterClauses[] = 'BA.created >= ?';
    $publicFilterArgs[]    = time() - $publicFilterTimeMap[$timeFilter];
    $publicFilterLink     .= '&time=' . urlencode($timeFilter);
}

$publicFilterAnd     = '';
$publicFilterWheren  = '';
if ($publicFilterClauses) {
    $joined             = implode(' AND ', $publicFilterClauses);
    $publicFilterAnd    = ' AND ' . $joined;
    $publicFilterWheren = ' WHERE ' . $joined;
}

// #1352: server-side state filter — `?p=banlist&state=<permanent|
// active|expired|unbanned>`. Pre-#1352 the chip strip was a
// vanilla-JS row-hide layer (`web/scripts/banlist.js` flipped
// `display: none` on rows whose `data-state` didn't match) +
// `history.replaceState` for a sharable URL, with the server
// returning the same paginated rowset regardless of `?state=`.
// That broke two ways the v1.x list didn't:
//
//   1. Pre-2.0 admin-lifted bans whose `RemoveType IS NULL` (the
//      v1.x admin-unban write path didn't always populate the
//      column — `web/updater/data/810.php` is the paired backfill
//      migration) were mis-classified as "active" / "expired" by
//      the post-loop state computation, so the chip never matched
//      them — even on the page they happened to land on. The new
//      `unbanned` SQL fragment includes a defensive
//      `OR (RemovedOn IS NOT NULL AND RemoveType IS NULL AND
//       RemovedBy IS NOT NULL AND RemovedBy > 0)` clause to catch
//      those rows on un-migrated installs (the migration converges
//      them to `RemoveType = 'U'` so the OR clause becomes a no-op
//      afterwards). The `RemovedBy > 0` distinction keeps natural-
//      expiry rows (`RemovedBy = 0` from `PruneBans()`, or NULL on
//      truly ancient installs) out of the unbanned bucket — the
//      `expired` predicate picks them up via the symmetric
//      `OR (RemoveType IS NULL AND length > 0 AND ends < now)`
//      clause.
//
//   2. Server-side pagination + client-side filter: with 10k bans
//      of which 50 are unbanned, the unbanned rows might land on
//      page 50; the user clicked the chip on page 1, watched 30
//      rows disappear, and the chip read as broken. The server-
//      side filter narrows the rowset BEFORE the LIMIT clause so
//      page 1 of `?state=unbanned` is the first 30 unbanned rows.
//
// State filter composes ON TOP of `$searchText` / `$server` /
// `$time` / `?advSearch` like the `$publicFilterAnd` /
// `$publicFilterWheren` predicates above. When a state filter is
// explicit, it OVERRIDES the session-based "Hide inactive" toggle
// — `state=unbanned` and "Hide inactive" (which is
// `RemoveType IS NULL`) are mutually exclusive, so the chip (the
// explicit user gesture) wins. The toggle button on the page
// chrome is hidden when a state filter is active so the two
// surfaces don't visually compete.
//
// The four state predicates are written as bare SQL (no bound
// parameters) because each one is a static fragment — no user
// input flows in unescaped. UNIX_TIMESTAMP() is computed server-
// side per row, and the literal letter codes are bound at the
// disk layer (`varchar(3)`). Keeping these as bare SQL avoids a
// placeholder shape mismatch with the existing `$publicFilterArgs`
// / `$advcrit` / `$search_array` ordering — each query branch
// passes positional `?` placeholders, and adding state-bound `?`s
// would force a re-shuffle in every `array_merge` call.
$stateAllowlist = ['permanent', 'active', 'expired', 'unbanned'];
$stateFilter    = isset($_GET['state']) ? trim((string) $_GET['state']) : '';
if (!in_array($stateFilter, $stateAllowlist, true)) {
    $stateFilter = '';
}

$stateFilterAnd    = '';
$stateFilterWheren = '';
$stateFilterLink   = '';
if ($stateFilter !== '') {
    // Per-state SQL predicates. Two invariants the four arms share:
    //
    //   - `permanent` / `active` BOTH carry `RemovedOn IS NULL` so a
    //     row that's been ANY flavour of removed (admin-lifted via
    //     'U' / 'D', natural-expiry via 'E', or pre-2.0 lifted with
    //     `RemoveType IS NULL`) drops out of the live-row buckets.
    //     Without the `RemovedOn IS NULL` guard a pre-2.0 admin-lifted
    //     row (RemoveType IS NULL, RemovedBy > 0, ends > now) would
    //     match BOTH `active` AND `unbanned` — same row in two
    //     buckets, which is exactly the symmetry bug the per-row
    //     state classifier (the `match (true)` block ~lines 970-985)
    //     also has to defend against.
    //
    //   - `expired` / `unbanned` carry their own defensive OR
    //     clauses for pre-2.0 rows that the `web/updater/data/810.php`
    //     migration backfills to `RemoveType = 'E'` / `'U'`. The
    //     migration re-running on a converged install matches zero
    //     rows; the OR clauses re-running against migrated rows
    //     match the same rows the post-migration `RemoveType` arm
    //     already does (idempotent).
    $stateFragment = match ($stateFilter) {
        'permanent' => '(BA.RemoveType IS NULL AND BA.RemovedOn IS NULL AND BA.length = 0)',
        'active'    => '(BA.RemoveType IS NULL AND BA.RemovedOn IS NULL AND (BA.length = 0 OR BA.ends > UNIX_TIMESTAMP()))',
        // `expired` carries TWO defensive OR arms (parallel to
        // `unbanned`'s shape) so pre-2.0 natural-expiry rows AND
        // pre-2.0 PruneBans-shape rows both surface on un-migrated
        // installs:
        //   - Arm 1: `RemoveType = 'E'` — the post-migration shape
        //     (and the v2.0 PruneBans write).
        //   - Arm 2: `RemoveType IS NULL AND RemovedOn IS NULL AND
        //     length > 0 AND ends < now` — a v1.x row PruneBans
        //     never touched (no `RemovedOn` ever written), the
        //     panel infers expiry from the timestamps. The
        //     `RemovedOn IS NULL` here is what distinguishes this
        //     from arm 3.
        //   - Arm 3: `RemoveType IS NULL AND RemovedOn IS NOT NULL
        //     AND (RemovedBy IS NULL OR RemovedBy = 0)` — a v1.x
        //     row where the prune writer set `RemovedOn` but not
        //     `RemoveType` (the fork-divergence shape `810.php`
        //     pass 2 backfills to `'E'`). The `RemovedBy IS NULL
        //     OR = 0` distinguishes from `unbanned`'s arm 2 (which
        //     requires `RemovedBy > 0`).
        // Post-migration arms 2 + 3 become no-ops (the rows now
        // carry `RemoveType = 'E'` and hit arm 1 instead).
        'expired'   => "(BA.RemoveType = 'E'"
                       . " OR (BA.RemoveType IS NULL AND BA.length > 0 AND BA.ends < UNIX_TIMESTAMP() AND BA.RemovedOn IS NULL)"
                       . " OR (BA.RemoveType IS NULL AND BA.RemovedOn IS NOT NULL AND BA.length > 0 AND (BA.RemovedBy IS NULL OR BA.RemovedBy = 0)))",
        'unbanned'  => "(BA.RemoveType IN ('D', 'U') OR (BA.RemovedOn IS NOT NULL AND BA.RemoveType IS NULL AND BA.RemovedBy IS NOT NULL AND BA.RemovedBy > 0))",
    };
    $stateFilterAnd    = ' AND ' . $stateFragment;
    $stateFilterWheren = ' WHERE ' . $stateFragment;
    $stateFilterLink   = '&state=' . urlencode($stateFilter);
}

// #1352: when an explicit state filter is set, the session-based
// "Hide inactive" predicate (`RemoveType IS NULL`) is either
// redundant (state=permanent / state=active already pin
// `RemoveType IS NULL`) or contradictory (state=expired /
// state=unbanned ask for the OPPOSITE rowset). Either way, the
// explicit chip is the user's intent — drop hideinactive when
// state is set so the two surfaces never collide. The toggle
// button on the page chrome is also hidden while state is set
// so the surfaces don't visually compete.
if ($stateFilter !== '') {
    $hidetext      = "Hide";
    $hideinactive  = "";
    $hideinactiven = "";
}

// #1352: extend the public-filter strings with the state fragment.
// The query branches below already consume `$publicFilterAnd` /
// `$publicFilterWheren` plus `$publicFilterArgs`; folding state
// into the existing strings (rather than adding a new pair of
// variables to every branch) keeps the per-branch SQL diff
// minimal. State adds no bound args, so `$publicFilterArgs` is
// untouched.
//
// `$publicFilterLink` (the URL fragment used by the "Hide inactive"
// toggle, the pagination prev/next, and the search-form `$searchlink`)
// is intentionally NOT mutated here — the chip strip's per-chip
// anchor needs a "preserve other filters but swap state" base URL,
// which is exactly `$publicFilterLink` MINUS the state. The pagination
// URL and the toggle URL get state via the separate `$stateFilterLink`
// fragment composed below.
if ($stateFilter !== '') {
    if ($publicFilterAnd === '') {
        $publicFilterAnd    = $stateFilterAnd;
        $publicFilterWheren = $stateFilterWheren;
    } else {
        $publicFilterAnd    .= $stateFilterAnd;
        $publicFilterWheren .= $stateFilterAnd;
    }
}

if (isset($_GET['searchText'])) {
    $searchText = trim((string) $_GET['searchText']);

    // #1130: when the input parses as any Steam-ID format, match `authid`
    // via REGEXP so both STEAM_0:Y:Z and STEAM_1:Y:Z stored variants hit
    // (the SourceMod plugin can write either depending on the game). The
    // Y:Z tail is invariant across the normalisation block below, so the
    // ordering of the two calls is academic.
    $authidPattern = SteamID::toSearchPattern($searchText);

    try {
        SteamID::init();
        if (SteamID::isValidID($searchText)) {
            $conversionResult = SteamID::toSteam2($searchText);

            if ($conversionResult) {
                $searchText = $conversionResult;
            }
        }
    } catch (Exception $e) { }

    $search = "%{$searchText}%";

    if ($authidPattern !== null) {
        $authidClause = "BA.authid REGEXP ?";
        $authidParam  = $authidPattern;
    } else {
        $authidClause = "BA.authid LIKE ?";
        $authidParam  = $search;
    }

    // disable ip search if hiding player ips
    $search_ips   = "";
    $search_array = [];
    if (!Config::getBool('banlist.hideplayerips') || $userbank->is_admin()) {
        $search_ips     = "BA.ip LIKE ? OR ";
        $search_array[] = $search;
    }

    $res = $GLOBALS['PDO']->query("SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
            (SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
      WHERE " . $search_ips . $authidClause . " or BA.name LIKE ? or BA.reason LIKE ?" . $hideinactive . $publicFilterAnd . "
   ORDER BY BA.created DESC
   LIMIT ?,?")->resultset(array_merge(
        $search_array,
        [$authidParam, $search, $search],
        $publicFilterArgs,
        [(int) $BansStart, (int) $BansPerPage]
    ));


    $res_count  = $GLOBALS['PDO']->query("SELECT count(BA.bid) AS cnt FROM `:prefix_bans` AS BA WHERE " . $search_ips . $authidClause . " OR BA.name LIKE ? OR BA.reason LIKE ?" . $hideinactive . $publicFilterAnd)->resultset(array_merge(
        $search_array,
        [$authidParam, $search, $search],
        $publicFilterArgs
    ));
    $searchlink = "&searchText=" . urlencode($_GET["searchText"]) . $publicFilterLink . $stateFilterLink;
} elseif (!isset($_GET['advSearch'])) {
    // #1226: branch 2 has no upper-level WHERE; if hideinactive opened
    // one (`$hideinactiven` = " WHERE RemoveType IS NULL") we append
    // the public filters as " AND …", otherwise the public filters
    // open the WHERE themselves via `$publicFilterWheren`.
    $branch2WhereSuffix = $hideinactiven !== ''
        ? $hideinactiven . $publicFilterAnd
        : $publicFilterWheren;

    $res = $GLOBALS['PDO']->query("SELECT bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
			(SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
  " . $branch2WhereSuffix . "
   ORDER BY created DESC
   LIMIT ?,?")->resultset(array_merge(
        $publicFilterArgs,
        [(int) $BansStart, (int) $BansPerPage]
    ));

    $res_count  = $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_bans` AS BA" . $branch2WhereSuffix)->resultset($publicFilterArgs);
    $searchlink = $publicFilterLink . $stateFilterLink;
}

$advcrit = [];
if (isset($_GET['advSearch'])) {
    $value = trim((string) $_GET['advSearch']);

    try {
        SteamID::init();
        if (SteamID::isValidID($value)) {
            $conversionResult = SteamID::toSteam2($value);

            if ($conversionResult) {
                $value = $conversionResult;
            }
        }
    } catch (Exception $e) { }

    $type  = $_GET['advType'];
    switch ($type) {
        case "name":
            $where   = "WHERE BA.name LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "banid":
            $where   = "WHERE BA.bid = ?";
            $advcrit = [$value];
            break;
        case "steamid":
            // #1130: match both STEAM_0:Y:Z and STEAM_1:Y:Z stored variants;
            // see SteamID::toSearchPattern() for rationale. The pre-switch
            // normalisation block above has already canonicalised $value to
            // STEAM_0 form, but the Y:Z tail is invariant so the pattern is
            // the same either way.
            $authidPattern = SteamID::toSearchPattern($value);
            if ($authidPattern !== null) {
                $where   = "WHERE BA.authid REGEXP ?";
                $advcrit = [$authidPattern];
            } else {
                $where   = "WHERE BA.authid = ?";
                $advcrit = [$value];
            }
            break;
        case "steam":
            $where   = "WHERE BA.authid LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "ip":
            // disable ip search if hiding player ips
            if (Config::getBool('banlist.hideplayerips') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE BA.ip LIKE ?";
                $advcrit = ["%$value%"];
            }
            break;
        case "reason":
            $where   = "WHERE BA.reason LIKE ?";
            $advcrit = ["%$value%"];
            break;
        case "date":
            $date    = explode(",", $value);
            $time    = mktime(0, 0, 0, (int)$date[1], (int)$date[0], (int)$date[2]);
            $time2   = mktime(23, 59, 59, (int)$date[1], (int)$date[0], (int)$date[2]);
            $where   = "WHERE BA.created > ? AND BA.created < ?";
            $advcrit = [$time, $time2];
            break;
        case "length":
            $len         = explode(",", $value);
            $length_type = $len[0];
            $length      = (int)$len[1] * 60;
            $where       = "WHERE BA.length ";
            switch ($length_type) {
                case "e":
                    $where .= "=";
                    break;
                case "h":
                    $where .= ">";
                    break;
                case "l":
                    $where .= "<";
                    break;
                case "eh":
                    $where .= ">=";
                    break;
                case "el":
                    $where .= "<=";
                    break;
            }
            $where .= " ?";
            $advcrit = [$length];
            break;
        case "btype":
            $where   = "WHERE BA.type = ?";
            $advcrit = [$value];
            break;
        case "admin":
            if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
                $where   = "";
                $advcrit = [];
            } else {
                $where   = "WHERE BA.aid=?";
                $advcrit = [$value];
            }
            break;
        case "where_banned":
            $where   = "WHERE BA.sid=?";
            $advcrit = [$value];
            break;
        case "nodemo":
            $where   = "WHERE BA.aid = ? AND NOT EXISTS (SELECT DM.demid FROM " . DB_PREFIX . "_demos AS DM WHERE DM.demid = BA.bid)";
            $advcrit = [$value];
            break;
        case "bid":
            $where   = "WHERE BA.bid = ?";
            $advcrit = [$value];
            break;
        case "comment":
            if ($userbank->is_admin()) {
                $where   = "WHERE CO.type = 'B' AND CO.commenttxt LIKE ?";
                $advcrit = ["%$value%"];
            } else {
                $where   = "";
                $advcrit = [];
            }
            break;
        default:
            $where             = "";
            $_GET['advType']   = "";
            $_GET['advSearch'] = "";
            $advcrit           = [];
            break;
    }

    // Make sure we got a "WHERE" clause there, if we add the hide inactive condition
    if (empty($where) && isset($_SESSION["hideinactive"])) {
        $hideinactive = $hideinactiven;
    }

    // #1226: branch 3 may or may not have an upper-level WHERE. The
    // existing $where is empty iff advType resolved to a no-op
    // (default branch in the switch above) AND $hideinactive may
    // have been promoted to $hideinactiven by the guard right above.
    // Pick the right form of the public-filter SQL accordingly so
    // the trailing WHERE-vs-AND boundary stays well-formed.
    $branch3HasWhere     = ($where !== '') || (strpos($hideinactive, 'WHERE') !== false);
    $publicFilterBranch3 = $branch3HasWhere ? $publicFilterAnd : $publicFilterWheren;

    $res = $GLOBALS['PDO']->query("SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM `:prefix_demos` as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
            (SELECT (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL)) + (SELECT count(*) FROM `:prefix_bans` as BH WHERE (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL))) as history_count
	   FROM `:prefix_bans` AS BA
  LEFT JOIN `:prefix_servers` AS SE ON SE.sid = BA.sid
  LEFT JOIN `:prefix_mods` AS MO on SE.modid = MO.mid
  LEFT JOIN `:prefix_admins` AS AD ON BA.aid = AD.aid
  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CO ON BA.bid = CO.bid" : "") . "
      " . $where . $hideinactive . $publicFilterBranch3 . "
   ORDER BY BA.created DESC
   LIMIT ?,?")->resultset(array_merge(
        $advcrit,
        $publicFilterArgs,
        [(int) $BansStart, (int) $BansPerPage]
    ));

    $res_count  = $GLOBALS['PDO']->query("SELECT count(BA.bid) AS cnt FROM `:prefix_bans` AS BA
										  " . ($type == "comment" && $userbank->is_admin() ? "LEFT JOIN `:prefix_comments` AS CO ON BA.bid = CO.bid" : "") . " " . $where . $hideinactive . $publicFilterBranch3)->resultset(array_merge($advcrit, $publicFilterArgs));
    $searchlink = "&advSearch=" . urlencode($_GET['advSearch']) . "&advType=" . urlencode($_GET['advType']) . $publicFilterLink . $stateFilterLink;
}

$BanCount = isset($res_count[0]['cnt']) ? (int) $res_count[0]['cnt'] : 0;
if ($BansEnd > $BanCount) {
    $BansEnd = $BanCount;
}
// $res is the LIMIT-paginated result list. An empty result is the
// expected state on a fresh install (or when filters match nothing) —
// the redesigned page_bans.tpl renders its own "No bans match those
// filters." empty state inside the table (see the {foreachelse} block
// in the template). Short-circuiting with PageDie() here was a holdover
// from the legacy theme where the table didn't have an empty state, and
// it now suppresses the marquee chrome on empty installs.
//
// We can't reach `$res === false` because the PDO error mode is set to
// EXCEPTION (see Database::__construct), so a real SQL failure throws
// before we get here.

$canEditComment = false;
$view_comments = false;
$bans          = [];
foreach ($res as $row) {
    $data = [];

    $data['ban_id'] = $row['ban_id'];

    if (!empty($row['ban_ip']) && !Config::getBool('banlist.nocountryfetch')) {
        if (!empty($row['ban_country']) && $row['ban_country'] != ' ') {
            $data['country'] = '<img src="images/country/' . strtolower($row['ban_country']) . '.png" alt="' . $row['ban_country'] . '" border="0" align="absmiddle" />';
        } elseif (!Config::getBool('banlist.nocountryfetch')) {
            $country = FetchIp($row['ban_ip']);
            $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET country = ?
				                            WHERE bid = ?")->execute([
                $country,
                $row['ban_id'],
            ]);

            $countryFlag = empty($country) ? 'zz' : strtolower($country);
            $data['country'] = '<img src="images/country/' . $countryFlag . '.png" alt="' . $country . '" border="0" align="absmiddle" />';
        } else {
            $data['country'] = '<img src="images/country/zz.png" alt="Unknown Country" border="0" align="absmiddle" />';
        }
    } else {
        $data['country'] = '<img src="images/country/zz.png" alt="Unknown Country" border="0" align="absmiddle" />';
    }

    $data['ban_date']    = Config::time($row['ban_created']);

    // Fix #1008 - bug: Player Names Contain Unwanted Non-Standard Characters
    $raw_name = $row['player_name'];
    $cleaned_name = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);

    $data['player'] = addslashes($cleaned_name);
    $data['type']        = $row['type'];
    $data['steamid']     = $row['authid'];
    // Fix #900 - Bad SteamID Format broke the page view, so give them an null SteamID.
	if (isset($data['steamid']) && !empty($data['steamid']) && !\SteamID\SteamID::isValidID($data['steamid'])) {
		$data['steamid'] = 'STEAM_0:0:00000000';
	}
    $data['communityid'] = $row['community_id'];
    $data['steamid3']    = \SteamID\SteamID::toSteam3($data['steamid']);

    if (Config::getBool('banlist.hideadminname') && !$userbank->is_admin()) {
        $data['admin'] = false;
    } else {
        $data['admin'] = stripslashes($row['admin_name']);
    }
    $data['reason']     = stripslashes($row['ban_reason']);
    $data['ban_length'] = $row['ban_length'] == 0 ? 'Permanent' : SecondsToString((int) $row['ban_length']);

    // Custom "listtable_1_banned" & "listtable_1_permanent" addition entries
    // Comment the 14 lines below out if they cause issues
    if ($row['ban_length'] == 0) {
        $data['expires']   = 'never';
        $data['class']     = "listtable_1_permanent";
        $data['ub_reason'] = "";
        $data['unbanned']  = false;
    } else {
        $data['expires']   = Config::time($row['ban_ends']);
        $data['class']     = "listtable_1_banned";
        $data['ub_reason'] = "";
        $data['unbanned']  = false;
    }
    // End custom entries

    $rowRemoval = BanRemoval::tryFrom((string) ($row['row_type'] ?? ''));
    if ($rowRemoval !== null || ($row['ban_length'] && $row['ban_ends'] < time())) {
        $data['unbanned'] = true;
        $data['class']    = "listtable_1_unbanned";

        $data['ub_reason'] = match ($rowRemoval) {
            BanRemoval::Deleted  => "(Deleted)",
            BanRemoval::Unbanned => "(Unbanned)",
            default              => "(Expired)",
        };

        $data['ureason'] = stripslashes($row['unban_reason'] ?? '');

        $GLOBALS['PDO']->query("SELECT user FROM `:prefix_admins` WHERE aid = :aid");
        $GLOBALS['PDO']->bind(':aid', $row['RemovedBy']);
        $removedby         = $GLOBALS['PDO']->single();
        $data['removedby'] = "";
        if (!empty($removedby['user']) && $data['admin']) {
            $data['removedby'] = $removedby['user'];
        }
    }
    // Don't need this stuff.
    // Uncomment below if the modifications above cause issues
    //	else
    //	{
    //		$data['unbanned'] = false;
    //		$data['class'] = "listtable_1";
    //		$data['ub_reason'] = "";
    //	}

    $data['layer_id'] = 'layer_' . $row['ban_id'];
    $rowBanType = BanType::tryFrom((int) $data['type']) ?? BanType::Steam;
    if ($rowBanType === BanType::Steam) {
        $GLOBALS['PDO']->query("SELECT count(bid) as count FROM `:prefix_bans` WHERE authid = :authid AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0'");
        $GLOBALS['PDO']->bind(':authid', $data['steamid']);
        $alrdybnd = $GLOBALS['PDO']->single();
    } else {
        $GLOBALS['PDO']->query("SELECT count(bid) as count FROM `:prefix_bans` WHERE ip = :ip AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1'");
        $GLOBALS['PDO']->bind(':ip', $row['ban_ip']);
        $alrdybnd = $GLOBALS['PDO']->single();
    }
    if ($alrdybnd['count'] == 0) {
        // #1275 — admin-bans is Pattern A; the legacy `#^0` fragment
        // anchored the old page-toc add-ban section. The page handler
        // infers `add-ban` from `?rebanid=` directly (see the smarter-
        // default block in admin.bans.php), so the section param is
        // optional but explicit here for clarity + bookmark stability.
        $data['reban_link'] = CreateLinkR('<i class="fas fa-redo fa-lg"></i> Reban', "index.php?p=admin&c=bans&section=add-ban" . $pagelink . "&rebanid=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);
    } else {
        $data['reban_link'] = false;
    }
    // `#^0` was a page-toc anchor target for admin-comms's add form
    // (no Pattern B equivalent; admin-comms is single-section Pattern
    // A). Drop the dead fragment.
    $data['blockcomm_link']  = CreateLinkR('<i class="fas fa-ban fa-lg"></i> Block Comms', "index.php?p=admin&c=comms" . $pagelink . "&blockfromban=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);
    $data['details_link']    = CreateLinkR('click', 'getdemo.php?type=B&id=' . urlencode($row['ban_id']));
    // `#^4` was the old page-toc anchor for admin-bans's group-ban
    // section. Pattern A routes `?fid=…` to `?section=group-ban`
    // automatically (see the smarter-default block in admin.bans.php),
    // but we make it explicit + drop the dead fragment.
    $data['groups_link']     = CreateLinkR('<i class="fas fa-users fa-lg"></i> Show Groups', "index.php?p=admin&c=bans&section=group-ban&fid=" . urlencode($data['communityid']));
    $data['friend_ban_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Ban Friends', '#', '', '_self', false, "BanFriendsProcess('" . $data['communityid'] . "','" . $data['player'] . "');return false;");
    $data['edit_link']       = CreateLinkR('<i class="fas fa-edit fa-lg"></i> Edit Details', "index.php?p=admin&c=bans&o=edit" . $pagelink . "&id=" . $row['ban_id'] . "&key=" . $_SESSION['banlist_postkey']);

    $data['unban_link']  = CreateLinkR('<i class="fas fa-undo fa-lg"></i> Unban', "#", "", "_self", false, "UnbanBan('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 1, false);return false;");
    $data['delete_link'] = CreateLinkR('<i class="fas fa-trash fa-lg"></i> Delete Ban', "#", "", "_self", false, "RemoveBan('" . $row['ban_id'] . "', '" . $_SESSION['banlist_postkey'] . "', '" . $pagelink . "', '" . $data['player'] . "', 0, false);return false;");


    $data['server_id'] = $row['ban_server'];

    if (empty($row['mod_icon'])) {
        $modicon = "web.png";
    } else {
        $modicon = $row['mod_icon'];
    }

    $data['mod_icon'] = '<img src="images/games/' . $modicon . '" alt="MOD" border="0" align="absmiddle" />&nbsp;' . $data['country'];

    if ($row['history_count'] > 1) {
        $data['prevoff_link'] = $row['history_count'] . " " . CreateLinkR("&nbsp;(search)", "index.php?p=banlist&searchText=" . urlencode($rowBanType === BanType::Steam ? $data['steamid'] : $row['ban_ip']) . "&Submit");
    } else {
        $data['prevoff_link'] = "No previous bans";
    }



    if (strlen((string) $row['ban_ip']) < 7) {
        $data['ip'] = 'none';
    } else {
        $data['ip'] = $data['country'] . '&nbsp;' . $row['ban_ip'];
    }

    if ($row['ban_length'] == 0) {
        $data['expires'] = 'never';
    } else {
        $data['expires'] = Config::time($row['ban_ends']);
    }


    if ($row['demo_count'] == 0) {
        $data['demo_available'] = false;
        $data['demo_quick']     = 'N/A';
        $data['demo_link']      = CreateLinkR('<i class="fas fa-video-slash fa-lg"></i> No Demos', "#");
    } else {
        $data['demo_available'] = true;
        $data['demo_quick']     = CreateLinkR('Demo', "getdemo.php?type=B&id=" . urlencode($data['ban_id']));
        $data['demo_link']      = CreateLinkR('<i class="fas fa-video fa-lg"></i> Review Demo', "getdemo.php?type=B&id=" . urlencode($data['ban_id']));
    }



    $data['server_id'] = $row['ban_server'];

    $GLOBALS['PDO']->query("SELECT bl.time, bl.name, s.ip, s.port FROM `:prefix_banlog` AS bl LEFT JOIN `:prefix_servers` AS s ON s.sid = bl.sid WHERE bid = :bid");
    $GLOBALS['PDO']->bind(':bid', $data['ban_id']);
    $banlog             = $GLOBALS['PDO']->resultset();
    $data['blockcount'] = sizeof($banlog);
    $logstring          = "";
    foreach ($banlog as $logged) {
        if (!empty($logstring)) {
            $logstring .= ", ";
        }
        $logstring .= '<span title="Server: ' . $logged["ip"] . ':' . $logged["port"] . ', Date: ' . Config::time($logged["time"]) . '">' . ($logged["name"] != "" ? htmlspecialchars($logged["name"]) : "<i>no name</i>") . '</span>';
    }
    $data['banlog'] = $logstring;

    //COMMENT STUFF
    //-----------------------------------
    if (Config::getBool('config.enablepubliccomments') || $userbank->is_admin()) {
        $view_comments = true;
        $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE type = 'B' AND bid = :bid ORDER BY added desc");
        $GLOBALS['PDO']->bind(':bid', $data['ban_id']);
        $commentres    = $GLOBALS['PDO']->resultset();

        if (count($commentres) > 0) {
            $comment = [];
            $morecom = 0;
            foreach ($commentres as $crow) {
                $cdata            = [];
                $cdata['morecom'] = ($morecom == 1 ? true : false);
                if ($crow['aid'] == $userbank->GetAid() || $userbank->HasAccess(WebPermission::Owner)) {
                    $cdata['editcomlink'] = CreateLinkR('<i class="fas fa-edit fa-lg"></i>', 'index.php?p=banlist&comment=' . $data['ban_id'] . '&ctype=B&cid=' . $crow['cid'] . $pagelink, 'Edit Comment');
                    if ($userbank->HasAccess(WebPermission::Owner)) {
                        $cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"Delete Comment\" target=\"_self\" onclick=\"RemoveComment(" . $crow['cid'] . ",'B'," . (isset($_GET["page"]) ? $page : -1) . ");\"><i class='fas fa-trash fa-lg'></i></a>";
                    }
                } else {
                    $cdata['editcomlink'] = "";
                    $cdata['delcomlink']  = "";
                }

                $cdata['comname']    = $crow['comname'];
                $cdata['added']      = Config::time($crow['added']);
                $commentText         = html_entity_decode($crow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $commentText         = encodePreservingBr($commentText);
                // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
                $commentText         = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentText);
                $cdata['commenttxt'] = $commentText;

                if (!empty($crow['edittime'])) {
                    $cdata['edittime'] = Config::time($crow['edittime']);
                    $cdata['editname'] = $crow['editname'];
                } else {
                    $cdata['edittime'] = "";
                    $cdata['editname'] = "";
                }

                $morecom = 1;
                array_push($comment, $cdata);
            }
        } else {
            $comment = "None";
        }

        $data['commentdata'] = $comment;
    }


    $data['addcomment'] = CreateLinkR('<i class="fas fa-comment-dots fa-lg"></i> Add Comment', 'index.php?p=banlist&comment=' . $data['ban_id'] . '&ctype=B' . $pagelink);
    //-----------------------------------

    $data['ub_reason']   = (isset($data['ub_reason']) ? $data['ub_reason'] : "");
    $data['banlength']   = $data['ban_length'] . " " . $data['ub_reason'];
    $data['view_edit']   = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans)) || ($userbank->HasAccess(WebPermission::EditOwnBans) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(WebPermission::EditGroupBans) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_unban']  = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban)) || ($userbank->HasAccess(WebPermission::UnbanOwnBans) && $row['aid'] == $userbank->GetAid()) || ($userbank->HasAccess(WebPermission::UnbanGroupBans) && $row['gid'] == $userbank->GetProperty('gid')));
    $data['view_delete'] = ($userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan)));

    // ------------------------------------------------------------------
    // Per-row keys consumed by `page_bans.tpl`. Aliases of the legacy
    // fields above are also kept on each row so any third-party theme
    // that forked the pre-v2.0.0 default (and reads `$ban.player`,
    // `$ban.ban_id`, `$ban.class`, …) continues to work unchanged.
    // The shipped template uses `bid|name|steam|state|length_human|
    // banned_human|sname` plus pre-derived per-row permission booleans
    // (`can_edit_ban`, `can_unban`) that gate the inline action
    // buttons. SmartyTemplateRule does not introspect array contents,
    // so both shapes coexist on each item.
    //
    // `state` collapses the four UI states the design separates
    // (matches the 3px coloured left-border + status pill):
    //     permanent → length == 0 AND not removed
    //     active    → length > 0  AND ends >= now AND not removed
    //     expired   → length > 0  AND ends < now  AND not removed
    //     unbanned  → RemoveType set (D/U/E rows that aren't natural expiry)
    // Natural expiry (length>0 && ends<now && RemoveType IS NULL) is
    // surfaced as `expired` separately from admin-driven `unbanned`.
    //
    // #1352: the `removed_admin_lift` arm catches pre-2.0 admin-lifted
    // bans whose `RemoveType IS NULL` but `RemovedOn IS NOT NULL` and
    // `RemovedBy > 0` (some v1.x panels left the column NULL — see
    // `web/updater/data/810.php`'s backfill migration). Without this
    // arm the row would fall through to the default 'active' branch,
    // and a row the SQL `?state=unbanned` filter pulled in would
    // render with an "Active" pill — visibly broken. The arm must
    // sit between the explicit-RemoveType arms and the length /
    // ends inferences so an explicit `'D'` / `'U'` / `'E'` tag still
    // wins (those are the post-migration shape).
    $banLengthInt    = (int) $row['ban_length'];
    $banEndsInt      = (int) $row['ban_ends'];
    $stateRemoval    = BanRemoval::tryFrom((string) ($row['row_type'] ?? ''));
    $rowRemovedOn    = $row['RemovedOn'] !== null ? (int) $row['RemovedOn'] : 0;
    $rowRemovedBy    = $row['RemovedBy'] !== null ? (int) $row['RemovedBy'] : 0;
    $isPre2AdminLift = $stateRemoval === null
        && $rowRemovedOn > 0
        && $rowRemovedBy > 0;
    $state = match (true) {
        $stateRemoval === BanRemoval::Deleted, $stateRemoval === BanRemoval::Unbanned => 'unbanned',
        $stateRemoval === BanRemoval::Expired   => 'expired',
        $isPre2AdminLift                        => 'unbanned',
        $banLengthInt === 0                     => 'permanent',
        $banEndsInt < time()                    => 'expired',
        default                                 => 'active',
    };

    $data['bid']          = (int) $row['ban_id'];
    $data['name']         = $cleaned_name;
    $data['steam']        = (string) $data['steamid'];
    $data['ban_ip_raw']   = (string) ($row['ban_ip'] ?? '');
    $data['length']       = $banLengthInt;
    $data['length_human'] = $banLengthInt === 0 ? 'Permanent' : SecondsToString($banLengthInt);
    $data['banned']       = (int) $row['ban_created'];
    $data['banned_human'] = Config::time((int) $row['ban_created']);
    $data['banned_iso']   = date('c', (int) $row['ban_created']);
    if ((int) $row['ban_server'] === 0 || $row['server_ip'] === null || $row['server_ip'] === '') {
        $data['sname'] = (int) $row['ban_server'] === 0 ? 'Web Ban' : 'Server #' . (int) $row['ban_server'];
    } else {
        $data['sname'] = (string) $row['server_ip'];
    }
    $data['aname']        = is_string($data['admin']) ? $data['admin'] : '';
    $data['state']        = $state;
    $data['can_edit_ban'] = (bool) $data['view_edit'];
    $data['can_unban']    = (bool) $data['view_unban'];

    // Avatar metadata precomputed server-side so the template doesn't
    // have to lean on Smarty's `%` arithmetic operator (parses
    // ambiguously next to modifier syntax). `avatar_initials` is the
    // upper-cased first two grapheme bytes of the cleaned name (empty
    // if no nickname); `avatar_hue` is a deterministic 0–359 hue
    // derived from the bid so a player's avatar colour stays stable
    // across reloads / paginations.
    $data['avatar_initials'] = mb_strtoupper(mb_substr($cleaned_name !== '' ? $cleaned_name : '?', 0, 2));
    $data['avatar_hue']      = ((int) $row['ban_id'] * 47) % 360;

    array_push($bans, $data);
}

if (isset($_GET['advSearch'])) {
    $advSearchString = "&advSearch=" . urlencode(isset($_GET['advSearch']) ? $_GET['advSearch'] : '') . "&advType=" . urlencode(isset($_GET['advType']) ? $_GET['advType'] : '');
} else {
    $advSearchString = '';
}

// ---------------------------------------------------------------------
// Pagination markup ($ban_nav). Built server-side and emitted via
// {$ban_nav nofilter}; the shipped template drops it inside a card.
// Any third-party theme that forked the pre-v2.0.0 default also
// renders the same string.
//
// Notable invariants:
//
//   1. The prev/next anchors carry `data-testid="page-prev"` /
//      `page-next` so E2E hooks work without a per-theme view-model
//      detour. The attributes are inert in browsers that don't query
//      them.
//   2. The page-jump <select> sets `window.location` directly via
//      inline vanilla JS (no reach into legacy bulk JS, which is gone
//      since v2.0.0).
//   3. The advSearch/advType $_GET values are escaped through the
//      htmlspecialchars(addslashes(...)) double-pass added in #1113 —
//      this guards the JS-string-inside-HTML-attribute injection vector.
//   4. Plain "Prev" / "Next" + Unicode arrows; the v2.0.0 default
//      theme ships Lucide instead of FontAwesome.
//   5. #1225: when the result count is zero we short-circuit to an
//      empty string. The template's `{if !empty($ban_nav)}` guard
//      then collapses the pagination card so the empty state owns
//      the surface alone — otherwise a "displaying 0 - 0 of 0
//      results" shell renders below the empty state on every fresh
//      install.
// ---------------------------------------------------------------------

if ($BanCount === 0) {
    $ban_nav = '';
} else {
    $searchTextParam = isset($_GET['searchText']) ? '&searchText=' . urlencode((string) $_GET['searchText']) : '';
    // #1226: append the public ?server / ?time filter params so
    // pagination links keep the active filter when navigating.
    // #1352: also include `&state=` so paginating across an active
    // chip filter doesn't silently drop the chip on prev/next/jump.
    $pageQuerySuffix = $searchTextParam . $advSearchString . $publicFilterLink . $stateFilterLink;

    $prev = '';
    if ($page > 1) {
        $prevUrl = 'index.php?p=banlist&page=' . ($page - 1) . $pageQuerySuffix;
        $prev = '<a href="' . htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8')
            . '" data-testid="page-prev" rel="prev" aria-label="Previous page">&laquo; prev</a>';
    }

    $next = '';
    if ($BansEnd < $BanCount) {
        $nextUrl = 'index.php?p=banlist&page=' . ($page + 1) . $pageQuerySuffix;
        $next = '<a href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8')
            . '" data-testid="page-next" rel="next" aria-label="Next page">next &raquo;</a>';
    }

    $ban_nav = 'displaying&nbsp;' . $BansStart . '&nbsp;-&nbsp;' . $BansEnd . '&nbsp;of&nbsp;' . $BanCount . '&nbsp;results';

    if ($prev !== '') {
        $ban_nav .= ' | <b>' . $prev . '</b>';
    }
    if ($next !== '') {
        $ban_nav .= ' | <b>' . $next . '</b>';
    }

    $pages = (int) ceil($BanCount / $BansPerPage);
    if ($pages > 1) {
        // Page-jump select: vanilla inline navigation, no JS dependency.
        // The query-suffix template (`pageQuerySuffix`) is double-escaped
        // for the JS-string-inside-HTML-attribute boundary: addslashes
        // for the JS string layer, htmlspecialchars(ENT_QUOTES) for the
        // HTML attribute layer. Same defence-in-depth pattern as #1113.
        $pageQueryJs = htmlspecialchars(addslashes($pageQuerySuffix), ENT_QUOTES, 'UTF-8');
        $jumpHandler = "if(this.value!=='0')window.location.href='index.php?p=banlist&page='+this.value+'" . $pageQueryJs . "';";
        $ban_nav .= '&nbsp;<select aria-label="Jump to page" onchange="' . $jumpHandler . '">';
        for ($i = 1; $i <= $pages; $i++) {
            $selected = (isset($_GET['page']) && (int) $_GET['page'] === $i) ? ' selected="selected"' : '';
            $ban_nav .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }
        $ban_nav .= '</select>';
    }
}

//COMMENT STUFF
//----------------------------------------
$commentMode    = false;
$commentType    = '';
$commentText    = '';
$commentCtype   = '';
$commentCid     = '';
$commentCanedit = false;
/** @var array<int, array<string, mixed>>|string $commentOthers */
$commentOthers  = '';
if (isset($_GET["comment"])) {
    $_GET["comment"] = (int) $_GET["comment"];
    $commentMode  = $_GET["comment"];
    $commentType  = isset($_GET["cid"]) ? "Edit" : "Add";
    if (isset($_GET["cid"])) {
        $_GET["cid"]    = (int) $_GET["cid"];
        $GLOBALS['PDO']->query("SELECT * FROM `:prefix_comments` WHERE cid = :cid");
        $GLOBALS['PDO']->bind(':cid', $_GET["cid"]);
        $ceditdata      = $GLOBALS['PDO']->single();
        $ctext          = html_entity_decode($ceditdata['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cotherdataedit = " AND cid != '" . $_GET["cid"] . "'";
    } else {
        $cotherdataedit = "";
        $ctext          = "";
    }

    $_GET["ctype"] = substr((string) ($_GET["ctype"] ?? ''), 0, 1);

    $cotherdata = $GLOBALS['PDO']->query("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `:prefix_admins` WHERE aid = C.editaid) AS editname
											FROM `:prefix_comments` AS C
											WHERE type = ? AND bid = ?" . $cotherdataedit . " ORDER BY added desc")->resultset([
        $_GET["ctype"],
        $_GET["comment"],
    ]);

    $ocomments = [];
    foreach ($cotherdata as $cdrow) {
        $coment               = [];
        $coment['comname']    = $cdrow['comname'];
        $coment['added']      = Config::time($cdrow['added']);
        $commentTextRow       = html_entity_decode($cdrow['commenttxt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $commentTextRow       = encodePreservingBr($commentTextRow);
        // Parse links and wrap them in a <a href=""></a> tag to be easily clickable
        $commentTextRow       = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $commentTextRow);
        $coment['commenttxt'] = $commentTextRow;

        if ($cdrow['editname'] != "") {
            $coment['edittime'] = Config::time($cdrow['edittime']);
            $coment['editname'] = $cdrow['editname'];
        } else {
            $coment['editname'] = "";
            $coment['edittime'] = "";
        }
        array_push($ocomments, $coment);
    }

    $commentText    = (string) (isset($ctext) ? $ctext : '');
    $commentCtype   = (string) $_GET["ctype"];
    $commentCid     = isset($_GET["cid"]) ? (string) $_GET["cid"] : '';
    $commentCanedit = $userbank->is_admin();
    $commentOthers  = $ocomments;
}

unset($_SESSION['CountryFetchHndl']);

// #1207 + #1226 + #1352: detect whether the current request is
// filtered (search box, advanced search, hide-inactive toggle,
// ?server, ?time, ?state chip). Drives the first-run-vs-filtered
// split in the empty-state shape — when zero rows AND no filter,
// the empty state shows "no bans recorded yet" with an "Add a ban"
// CTA gated on can_add_ban; with a filter active, it stays "No
// bans match those filters" + "Clear filters".
$banlistIsFiltered =
    (isset($_GET['searchText']) && (string) $_GET['searchText'] !== '')
    || (isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== '')
    || isset($_SESSION['hideinactive'])
    || $publicFilterClauses !== []
    || $stateFilter !== '';

// #1226: server filter dropdown — public banlist parity with the
// commslist URL surface. Mirrors the data shape page.commslist.php
// builds in $serversFilter + the box_admin_bans_search.tpl pattern;
// hostnames could be resolved asynchronously via
// sb.api.call(Actions.ServersHostPlayers) in a follow-up. The
// pre-resolution display is `ip:port`, which is what most public
// visitors recognise the server by anyway.
$serverRows = $GLOBALS['PDO']->query(
    "SELECT sid, ip, port FROM `:prefix_servers` WHERE enabled = 1 ORDER BY ip"
)->resultset();
$banlistServerList = [];
foreach ($serverRows as $sr) {
    $banlistServerList[] = [
        'sid'  => (int) $sr['sid'],
        'name' => (string) $sr['ip'] . ':' . (int) $sr['port'],
    ];
}

$banlistFilters = [
    'search' => isset($_GET['searchText']) ? (string) $_GET['searchText'] : '',
    'server' => $serverFilter,
    'time'   => (isset($publicFilterTimeMap[$timeFilter]) ? $timeFilter : ''),
    'state'  => $stateFilter,
];

// #1352: chip-strip base URL — every other active filter (search,
// server, time, advSearch+advType) preserved, but `&state=` stripped
// so each chip's anchor can append its own state value (or omit it
// entirely for the "All" chip). Computed here so the template stays
// presentation-only.
//
// `$advSearchString` is built later in this file (the `if (isset(
// $_GET['advSearch']))` block ~lines 1003-1007); it's safe to
// reference here because the if-branch falls through when the
// param is absent. We compute the same shape inline so the chip
// link renders correctly even on a `?advSearch=` URL — the user
// can swap state without losing the advanced-search context.
$banlistChipBaseLink =
    (isset($_GET['searchText']) && (string) $_GET['searchText'] !== ''
        ? '&searchText=' . urlencode((string) $_GET['searchText']) : '')
    . (isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== ''
        ? '&advSearch=' . urlencode((string) $_GET['advSearch'])
            . '&advType=' . urlencode((string) ($_GET['advType'] ?? '')) : '')
    . $publicFilterLink;

// #1315: auto-open the advanced-search disclosure on a post-submit
// paint. Bare `?p=banlist` and simple-bar filters
// (`?searchText=` / `?server=` / `?time=`) leave it closed so the
// unfiltered list reaches above the fold. The legacy ?advSearch shim
// is the only surface that re-opens it, mirroring v1.x behaviour
// where the form was always-open below the row table — the v2.0
// disclosure is the post-#1303 collapsed shape with the same
// post-submit affordance the admin-admins page uses.
$banlistAdvancedOpen =
    isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== '';

Renderer::render($theme, new BanListView(
    ban_list:        $bans,
    ban_nav:         $ban_nav,
    total_bans:      $BanCount,
    view_bans:       (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans, WebPermission::EditOwnBans, WebPermission::EditGroupBans, WebPermission::Unban, WebPermission::UnbanOwnBans, WebPermission::UnbanGroupBans, WebPermission::DeleteBan)),
    view_comments:   $view_comments,
    comment:         $commentMode === false ? false : (int) $commentMode,
    commenttype:     $commentType,
    commenttext:     $commentText,
    ctype:           $commentCtype,
    cid:             $commentCid,
    page:            isset($_GET["page"]) ? $page : -1,
    canedit:         $commentCanedit,
    othercomments:   $commentOthers,
    searchlink:      $searchlink,
    hidetext:        $hidetext,
    hideadminname:   Config::getBool('banlist.hideadminname') && !$userbank->is_admin(),
    hideplayerips:   Config::getBool('banlist.hideplayerips') && !$userbank->is_admin(),
    groupban:        Config::getBool('config.enablegroupbanning') && (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)),
    friendsban:      Config::getBool('config.enablefriendsbanning') && (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)),
    general_unban:   (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::Unban, WebPermission::UnbanOwnBans, WebPermission::UnbanGroupBans)),
    can_delete:      (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::DeleteBan)),
    can_export:      (bool) $userbank->HasAccess(WebPermission::Owner) || Config::getBool('config.exportpublic'),
    admin_postkey:   $_SESSION['banlist_postkey'],
    can_add_ban:     (bool) $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)),
    is_filtered:             $banlistIsFiltered,
    server_list:             $banlistServerList,
    filters:                 $banlistFilters,
    is_advanced_search_open: $banlistAdvancedOpen,
    active_state:            $stateFilter,
    chip_base_link:          $banlistChipBaseLink,
));
