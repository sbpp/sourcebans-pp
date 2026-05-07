<?php
// Scale-data seeder for the v2.0.0 upgrade-dry-run fixtures (issue #1166).
//
// This script is run by `capture.sh` inside an ephemeral `php:8.2-cli`
// container against an ephemeral `mariadb:10.11` container. It assumes
// the version's `install/includes/sql/struc.sql` + `data.sql` have
// already been loaded against the target DB, and seeds production-shaped
// volume on top:
//
//   - 200 admins across 5 web groups (one of them includes a 4-byte
//     UTF-8 nickname; passwords are bcrypt-hashed `admin`).
//   - 5 SourceMod srvgroups (Owner / Senior Admin / Admin / Moderator / Mute Mod).
//   - 30 servers across 5 mods (TF2 / CSGO / CSS / L4D2 / GMod), each
//     mapped to a randomly-picked srvgroup.
//   - 5000 bans split as ~30% perm, ~50% temp, ~15% manually unbanned,
//     ~5% appealed (linked to a row in :prefix_protests). Mix of
//     STEAM-id, IP, and combined records.
//   - 500 comm blocks (mute/gag mix, similar removal distribution).
//   - 50 protests, 80 submissions, 1000 banlog rows, 200 comments.
//
// Real-ish player names cover ASCII, Latin-1 diacritics, combining
// diacritics, CJK, and 4-byte UTF-8 (emoji + supplementary-plane CJK)
// per #1108. The dataset is deterministic given a fixed RNG seed; the
// default seed is pinned below so two operators capturing the same
// version end up with byte-for-byte identical dumps.
//
// Required environment variables (set by capture.sh):
//   DB_HOST  - hostname of the ephemeral MariaDB container (within the
//              capture network).
//   DB_PORT  - 3306.
//   DB_NAME  - the schema to seed into.
//   DB_USER  - DB credentials.
//   DB_PASS
//   DB_PREFIX - table prefix (we use `sb` to mirror the dev stack).
//   SEED_VERSION - "1.7.0" or "1.8.4"; gates the small per-version
//                  schema differences (1.7.0 lacks attempts/lockout_until
//                  on :prefix_admins; both ship the same comms shape but
//                  with adminIp varchar(32) on 1.7.0 vs varchar(128) on
//                  1.8.4 — the seeder writes payloads that fit either).
//   SEED_RNG - optional integer; defaults to 11660.

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = (int)(getenv('DB_PORT') ?: '3306');
$name = getenv('DB_NAME') ?: 'sourcebans';
$user = getenv('DB_USER') ?: 'sourcebans';
$pass = getenv('DB_PASS') ?: 'sourcebans';
$prefix = getenv('DB_PREFIX') ?: 'sb';
$version = getenv('SEED_VERSION') ?: '1.7.0';
$rng = (int)(getenv('SEED_RNG') ?: '11660');

if (!in_array($version, ['1.7.0', '1.8.4'], true)) {
    fwrite(STDERR, "seed.php: unsupported SEED_VERSION={$version}\n");
    exit(2);
}

// Stable Unix epoch for "now" so timestamps don't drift run-to-run.
// 2023-05-07 14:25:00 UTC ≈ moment 1.7.0 was released, which puts the
// scale data inside a window the upgrade dry-run would actually meet.
$now = 1683469500;

mt_srand($rng);

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "seed.php: cannot connect: " . $e->getMessage() . "\n");
    exit(3);
}

$pdo->exec('SET SESSION sql_mode = "NO_AUTO_VALUE_ON_ZERO"');

// ---------------------------------------------------------------------------
// Dictionaries.

// Mix ASCII + Latin-1 + combining + CJK + 4-byte (emoji, supplementary CJK).
// The 4-byte rows are the #1108 regression — the v1.x default of
// DB_CHARSET=utf8 (3-byte alias) silently mangled these; an upgraded
// install on utf8mb4 must round-trip them through the migrator unmolested.
$nameDict = [
    'ScoutMain', 'PyroChampion', 'SpyKing', 'EngiNerd', 'DemoKnight',
    'HeavyWeapons', 'SoldierBro', 'SniperLad', 'MedicMain', 'Pootis',
    'RedTeam_01', 'BluTeam_07', 'NoLifeKing', 'BotKiller', 'FrenchFry',
    'Bigus_Dickus', 'AnonymousGuy', 'AFK4Life', 'StatsTracker', 'AwesomeGuy',
    'José_Müller', 'François', 'Łukasz', 'Niño', 'Ñoño',
    'Açaí_Maníaco', 'Renée', 'Søren', 'Björk_42', 'Dvořák',
    "Ka\xCC\x88se",          // "Käse" with combining diaeresis (NFD)
    "côte\xCC\x81 d\xE2\x80\x99or", // combining acute + apostrophe
    'Ναυτίλος', 'Сергей', 'Владимир', 'Україна', 'Ξανθός',
    '玩家_007', '電子_忍者', '神_ニャル子', '한국_김치왕', '李雷的弟弟',
    'プレイヤー一号', '管理員老王', '🎮GamerKing🎮', '🔥FireMage🔥', '💀DeathDealer💀',
    '🚀RocketJump🚀', '💯ProGamer💯', '🎯HeadshotPro🎯', '🌶️SpicyMain🌶️', '👑KingOfHill👑',
    "𠀋玩家",                  // 4-byte CJK extension B (U+2000B)
    "𩸽_寿司",                  // 4-byte CJK extension B (U+29E3D)
    'STEAM_friend', 'old.dial.up.kid', 'modder.4ever', 'casualgaming', 'tryhard.god',
    'Notch_Junior', 'JustALurker', 'GabeNewell_Fan', 'half.life.3', 'crateopener',
];

$reasonDict = [
    'Aimbot', 'Wallhack', 'Speedhack', 'Triggerbot', 'Bunnyhop',
    'Crashing the server', 'Spamming chat', 'Mic spam', 'Racism', 'Toxic behaviour',
    'Team killing', 'Griefing', 'Exploiting map glitch', 'Verbal harassment', 'Harassment of admin',
    'Refusing to switch teams', 'AFK farming', 'Doxxing another player', 'Cheating (auto report)', 'Cheating (manual review)',
    "Crashing teammates' games", 'Loud mic abuse', 'Texture exploit', 'No-clip glitch', 'Unbecoming conduct',
    "Multiple offences — see notes", "Banned by SBPP_Checker (linked alt)", "Banned by SBPP_Sleuth (shared account)",
    'Vac-banned account', 'Steam group spam',
    "Reasonable doubt of cheating — pending appeal",
    'Multiple wallhack reports from staff',
];

$urAppealDict = [
    "Mistaken identity — appeal accepted by senior admin",
    "Account compromised, owner regained access",
    "Bot misfire — checker plugin had stale data",
    "Accidental ban during rcon test",
    "Cleared on appeal — see protest #_PID_",
];

$modIcons = [
    ['mid' => 12, 'name' => 'Team Fortress 2', 'folder' => 'tf'],
    ['mid' => 21, 'name' => 'Counter-Strike: Global Offensive', 'folder' => 'csgo'],
    ['mid' => 2,  'name' => 'Counter-Strike: Source', 'folder' => 'cstrike'],
    ['mid' => 16, 'name' => 'Left 4 Dead 2', 'folder' => 'left4dead2'],
    ['mid' => 14, 'name' => "Garry's Mod", 'folder' => 'garrysmod'],
];

$srvgroups = [
    ['name' => 'Owner',      'flags' => 'abcdefghijklmnopqrst', 'immunity' => 100],
    ['name' => 'Senior Admin', 'flags' => 'bcdefghijklmnopq',    'immunity' => 80],
    ['name' => 'Admin',       'flags' => 'bcdefijklmnoq',        'immunity' => 50],
    ['name' => 'Moderator',   'flags' => 'bcdefij',              'immunity' => 30],
    ['name' => 'Mute Mod',    'flags' => 'd',                    'immunity' => 10],
];

// 5 web groups. flags is a 32-bit bitmask — the exact bits don't matter
// for upgrade-path testing but should be plausible. ADMIN_OWNER = 1<<24.
$webgroups = [
    ['name' => 'Lead Owners',   'flags' => (1 << 24)],
    ['name' => 'Senior Admins', 'flags' => (1 << 24) | (1 << 0) | (1 << 1) | (1 << 2)],
    ['name' => 'Admins',        'flags' => (1 << 0) | (1 << 1) | (1 << 2)],
    ['name' => 'Mods',          'flags' => (1 << 1) | (1 << 2)],
    ['name' => 'View-Only',     'flags' => 0],
];

// ---------------------------------------------------------------------------
// Helpers.

function pick(array $a)
{
    return $a[mt_rand(0, count($a) - 1)];
}

function fakeSteamId(): string
{
    $y = mt_rand(0, 1);
    // STEAM_0:0:N is the canonical legacy format SBPP stores.
    return sprintf('STEAM_0:%d:%d', $y, mt_rand(1, 9_999_999_999));
}

function fakeIp(): string
{
    return sprintf('%d.%d.%d.%d', mt_rand(1, 223), mt_rand(0, 255), mt_rand(0, 255), mt_rand(1, 254));
}

// ---------------------------------------------------------------------------
// Wipe rows we are about to insert. The script is idempotent — re-running
// after a partial run (or against an already-seeded DB) yields the same
// post-state. The static seed rows (mods, default settings, CONSOLE
// admin) come from data.sql and are left alone.
$truncatables = [
    'admins_servers_groups', 'banlog', 'bans', 'comments', 'comms',
    'protests', 'servers_groups', 'servers', 'srvgroups',
    'srvgroups_overrides', 'submissions', 'overrides',
    'login_tokens', // 1.7.0+ ships this; 1.8.4 also has it.
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($truncatables as $t) {
    $pdo->exec("DELETE FROM `{$prefix}_{$t}`");
    // Reset autoincrement so re-running yields stable PKs.
    try {
        $pdo->exec("ALTER TABLE `{$prefix}_{$t}` AUTO_INCREMENT = 1");
    } catch (PDOException $_) {
        // Some tables (banlog) are composite-keyed and have no AI; ignore.
    }
}
// Wipe the non-CONSOLE admins (aid > 0). Keep aid=0 (CONSOLE) as data.sql
// seeded it.
$pdo->exec("DELETE FROM `{$prefix}_admins` WHERE aid > 0");
$pdo->exec("ALTER TABLE `{$prefix}_admins` AUTO_INCREMENT = 1");
$pdo->exec("DELETE FROM `{$prefix}_groups`");
$pdo->exec("ALTER TABLE `{$prefix}_groups` AUTO_INCREMENT = 1");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ---------------------------------------------------------------------------
// Web admin groups.
$insGrp = $pdo->prepare("INSERT INTO `{$prefix}_groups` (`name`, `flags`, `type`) VALUES (:n, :f, 1)");
$gids = [];
foreach ($webgroups as $g) {
    $insGrp->execute([':n' => $g['name'], ':f' => $g['flags']]);
    $gids[] = (int)$pdo->lastInsertId();
}

// ---------------------------------------------------------------------------
// SourceMod srvgroups.
$insSg = $pdo->prepare(
    "INSERT INTO `{$prefix}_srvgroups` (`flags`, `immunity`, `name`, `groups_immune`) "
    . "VALUES (:f, :i, :n, '')"
);
$sgids = [];
foreach ($srvgroups as $sg) {
    $insSg->execute([':f' => $sg['flags'], ':i' => $sg['immunity'], ':n' => $sg['name']]);
    $sgids[] = (int)$pdo->lastInsertId();
}

// A handful of representative srvgroup overrides so the table has
// something — replicates the "deny sm_kick to Mute Mod" shape an admin
// might have set up by hand.
$insSgo = $pdo->prepare(
    "INSERT INTO `{$prefix}_srvgroups_overrides` (`group_id`, `type`, `name`, `access`) "
    . "VALUES (:g, :t, :n, :a)"
);
foreach ([
    ['g' => $sgids[3], 't' => 'command', 'n' => 'sm_kick',     'a' => 'deny'],
    ['g' => $sgids[3], 't' => 'command', 'n' => 'sm_ban',      'a' => 'deny'],
    ['g' => $sgids[4], 't' => 'command', 'n' => 'sm_ban',      'a' => 'deny'],
    ['g' => $sgids[4], 't' => 'command', 'n' => 'sm_kick',     'a' => 'deny'],
    ['g' => $sgids[2], 't' => 'group',   'n' => 'Default',     'a' => 'allow'],
] as $o) {
    $insSgo->execute([':g' => $o['g'], ':t' => $o['t'], ':n' => $o['n'], ':a' => $o['a']]);
}

// ---------------------------------------------------------------------------
// Servers.
$insSrv = $pdo->prepare(
    "INSERT INTO `{$prefix}_servers` (`ip`, `port`, `rcon`, `modid`, `enabled`) "
    . "VALUES (:ip, :port, :rcon, :mid, 1)"
);
$insSrvGroups = $pdo->prepare(
    "INSERT INTO `{$prefix}_servers_groups` (`server_id`, `group_id`) VALUES (:s, :g)"
);
$serverIds = [];
for ($i = 0; $i < 30; $i++) {
    $mod = $modIcons[$i % count($modIcons)];
    // Deterministic, obviously-fake rcon string. Real installs would
    // have a real secret here; the upgrade dry-run does not exercise
    // the rcon column's value, only its presence and read-back.
    $rcon_hex = '';
    for ($r = 0; $r < 12; $r++) {
        $rcon_hex .= sprintf('%x', mt_rand(0, 15));
    }
    $insSrv->execute([
        ':ip'   => sprintf('203.0.113.%d', 1 + ($i * 7) % 240),
        ':port' => 27015 + ($i * 11) % 200,
        ':rcon' => 'fixture-rcon-' . $rcon_hex,
        ':mid'  => $mod['mid'],
    ]);
    $sid = (int)$pdo->lastInsertId();
    $serverIds[] = $sid;
    // Assign each server to a random srvgroup so admin-server-group
    // mappings have something realistic to anchor on.
    $insSrvGroups->execute([':s' => $sid, ':g' => $sgids[$i % count($sgids)]]);
}

// ---------------------------------------------------------------------------
// Admins.
//
// 1.7.0's :prefix_admins is missing the `attempts` / `lockout_until`
// columns; 1.8.4 added them in 705. We always write the columns common
// to both schemas and let the columns specific to 1.8.4 take their
// declared defaults (0 / NULL).
//
// Hardcoded bcrypt hash of "admin" with a fixed salt so the dump is
// byte-for-byte reproducible. password_hash() generates a CSPRNG
// salt every call which would drift the dump between captures, and
// the actual hash value doesn't matter for the upgrade dry-run —
// the migrator never validates passwords. If you need to log into
// a fixture-loaded panel for manual smoke-testing, the hash below
// IS valid for the password "admin"; verified via
// crypt('admin', '$2y$04$fixturefixturefixturefi').
$bcryptAdmin = '$2y$04$fixturefixturefixtureeBeJkkgPg9ctAIPhS6RaV2zO4gky7sUa';

$insAdmin = $pdo->prepare(
    "INSERT INTO `{$prefix}_admins` "
    . "(`user`, `authid`, `password`, `gid`, `email`, `extraflags`, `immunity`, "
    . " `srv_group`, `srv_flags`, `srv_password`, `lastvisit`) "
    . "VALUES (:u, :a, :p, :g, :e, :x, :i, :sg, :sf, :sp, :lv)"
);
$insAsg = $pdo->prepare(
    "INSERT INTO `{$prefix}_admins_servers_groups` "
    . "(`admin_id`, `group_id`, `srv_group_id`, `server_id`) "
    . "VALUES (:a, :g, :sg, :s)"
);

// First admin is the operator's "main owner" account (aid=1, gid=-1) —
// matches what the 1.7.0 wizard's page.5.php does.
$insAdmin->execute([
    ':u'  => 'admin',
    ':a'  => 'STEAM_0:0:1',
    ':p'  => $bcryptAdmin,
    ':g'  => -1,
    ':e'  => 'admin@example.test',
    ':x'  => (1 << 24),
    ':i'  => 100,
    ':sg' => $srvgroups[0]['name'],
    ':sf' => null,
    ':sp' => null,
    ':lv' => $now - 86400,
]);
$adminIds = [(int)$pdo->lastInsertId()];

// 199 more admins distributed across the 5 web groups + 5 srvgroups.
for ($i = 0; $i < 199; $i++) {
    $u = 'admin_' . $i;
    // ~10% of generated admins use a 4-byte UTF-8 nickname in their email
    // local-part (low-cost #1108 coverage on round-tripping).
    if (mt_rand(0, 9) === 0) {
        $u = 'admin_' . pick(['🎮', '李雷', 'プレイヤー', '𠀋', '管理員']) . '_' . $i;
    }
    $gidPick = $gids[mt_rand(0, count($gids) - 1)];
    $sgPick = $srvgroups[mt_rand(0, count($srvgroups) - 1)];
    $sgIdPick = $sgids[mt_rand(0, count($sgids) - 1)];
    $insAdmin->execute([
        ':u'  => $u,
        ':a'  => fakeSteamId(),
        ':p'  => $bcryptAdmin,
        ':g'  => $gidPick,
        ':e'  => sprintf('admin%d@example.test', $i),
        ':x'  => $webgroups[array_search($gidPick, $gids, true)]['flags'],
        ':i'  => $sgPick['immunity'],
        ':sg' => $sgPick['name'],
        ':sf' => null,
        ':sp' => null,
        ':lv' => $now - mt_rand(0, 90 * 86400),
    ]);
    $aid = (int)$pdo->lastInsertId();
    $adminIds[] = $aid;
    // Map each admin to 1-3 server-group rows.
    $maps = mt_rand(1, 3);
    for ($m = 0; $m < $maps; $m++) {
        $insAsg->execute([
            ':a'  => $aid,
            ':g'  => $gidPick,
            ':sg' => $sgIdPick,
            ':s'  => $serverIds[mt_rand(0, count($serverIds) - 1)],
        ]);
    }
}

// ---------------------------------------------------------------------------
// Bans.

$insBan = $pdo->prepare(
    "INSERT INTO `{$prefix}_bans` "
    . "(`ip`, `authid`, `name`, `created`, `ends`, `length`, `reason`, "
    . " `aid`, `adminIp`, `sid`, `country`, `RemovedBy`, `RemoveType`, "
    . " `RemovedOn`, `type`, `ureason`) "
    . "VALUES (:ip, :a, :n, :cr, :en, :ln, :r, :aid, :aip, :sid, :co, "
    . "        :rb, :rt, :ron, :type, :ur)"
);

$bansBatch = $pdo->prepare("SELECT 1"); // unused; keep code shape consistent
$bansToInsert = 5000;

$banIds = [];
$pdo->beginTransaction();
for ($i = 0; $i < $bansToInsert; $i++) {
    // Distribution:
    //  type=0  STEAM-id ban  (~85%)
    //  type=1  IP ban        (~15%)
    $type = mt_rand(0, 99) < 85 ? 0 : 1;
    $hasIp = $type === 1 || mt_rand(0, 1) === 1;
    $hasSteam = $type === 0;

    $created = $now - mt_rand(0, 365 * 86400 * 2); // up to 2 years back
    $lengthRoll = mt_rand(0, 99);
    if ($lengthRoll < 30) {
        $length = 0; // permanent
        $ends = 0;
    } elseif ($lengthRoll < 80) {
        $length = pick([60, 240, 1440, 10080, 43200, 525600]); // mins
        $ends = $created + ($length * 60);
    } else {
        $length = mt_rand(60, 525600);
        $ends = $created + ($length * 60);
    }

    // ~15% manually unbanned, ~5% appealed (resolved by admin).
    $unbanRoll = mt_rand(0, 99);
    $removedBy = null;
    $removeType = null;
    $removedOn = null;
    $ureason = null;
    if ($unbanRoll < 15) {
        $removedBy = $adminIds[mt_rand(0, count($adminIds) - 1)];
        $removeType = pick(['U', 'E']);
        $removedOn = $created + mt_rand(60, 30 * 86400);
        $ureason = pick($urAppealDict);
    } elseif ($unbanRoll < 20) {
        $removedBy = $adminIds[mt_rand(0, count($adminIds) - 1)];
        $removeType = 'U';
        $removedOn = $created + mt_rand(86400, 14 * 86400);
        $ureason = "Appealed and reviewed — admin agreed; lifted manually";
    }

    $insBan->execute([
        ':ip'  => $hasIp ? fakeIp() : null,
        ':a'   => $hasSteam ? fakeSteamId() : '',
        ':n'   => pick($nameDict),
        ':cr'  => $created,
        ':en'  => $ends,
        ':ln'  => $length,
        ':r'   => pick($reasonDict),
        ':aid' => $adminIds[mt_rand(0, count($adminIds) - 1)],
        ':aip' => fakeIp(),
        ':sid' => $serverIds[mt_rand(0, count($serverIds) - 1)],
        ':co'  => pick(['US', 'DE', 'FR', 'CN', 'JP', 'KR', 'BR', 'RU', 'IN', '']),
        ':rb'  => $removedBy,
        ':rt'  => $removeType,
        ':ron' => $removedOn,
        ':type' => $type,
        ':ur'  => $ureason,
    ]);
    $banIds[] = (int)$pdo->lastInsertId();

    // Commit in chunks of 500 so the transaction log doesn't blow up.
    if ($i > 0 && $i % 500 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

// ---------------------------------------------------------------------------
// Comm blocks (mute / gag).
$insComm = $pdo->prepare(
    "INSERT INTO `{$prefix}_comms` "
    . "(`authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`, "
    . " `adminIp`, `sid`, `RemovedBy`, `RemoveType`, `RemovedOn`, `type`, `ureason`) "
    . "VALUES (:a, :n, :cr, :en, :ln, :r, :aid, :aip, :sid, :rb, :rt, :ron, :type, :ur)"
);
$pdo->beginTransaction();
for ($i = 0; $i < 500; $i++) {
    $created = $now - mt_rand(0, 365 * 86400);
    $length = pick([0, 30, 60, 1440, 10080]);
    $ends = $length === 0 ? 0 : $created + ($length * 60);
    $type = pick([1, 2]); // 1 = mute, 2 = gag
    $unbanRoll = mt_rand(0, 99);
    $removedBy = null;
    $removeType = null;
    $removedOn = null;
    $ureason = null;
    if ($unbanRoll < 20) {
        $removedBy = $adminIds[mt_rand(0, count($adminIds) - 1)];
        $removeType = pick(['U', 'E']);
        $removedOn = $created + mt_rand(60, 7 * 86400);
        $ureason = pick($urAppealDict);
    }
    $insComm->execute([
        ':a'   => fakeSteamId(),
        ':n'   => pick($nameDict),
        ':cr'  => $created,
        ':en'  => $ends,
        ':ln'  => $length,
        ':r'   => pick(['Mic spam', 'Chat spam', 'Toxic language', 'Racism', 'Bot-like chatter', 'Slur abuse']),
        ':aid' => $adminIds[mt_rand(0, count($adminIds) - 1)],
        ':aip' => fakeIp(),
        ':sid' => $serverIds[mt_rand(0, count($serverIds) - 1)],
        ':rb'  => $removedBy,
        ':rt'  => $removeType,
        ':ron' => $removedOn,
        ':type' => $type,
        ':ur'  => $ureason,
    ]);
}
$pdo->commit();

// ---------------------------------------------------------------------------
// Protests (50). Half link to a real ban; the other half are
// vexatious / out-of-scope and have a synthetic bid.

$insProtest = $pdo->prepare(
    "INSERT INTO `{$prefix}_protests` "
    . "(`bid`, `datesubmitted`, `reason`, `email`, `archiv`, `archivedby`, `pip`) "
    . "VALUES (:b, :d, :r, :e, :a, :ab, :p)"
);
for ($i = 0; $i < 50; $i++) {
    $bid = $i < 25
        ? $banIds[mt_rand(0, count($banIds) - 1)]
        : mt_rand(900_000, 999_999); // dangling bid — mirror real-world rubbish
    $archived = mt_rand(0, 1);
    $insProtest->execute([
        ':b'  => $bid,
        ':d'  => $now - mt_rand(0, 180 * 86400),
        ':r'  => "I shouldn't be banned, " . pick($nameDict) . ' said it would be ok',
        ':e'  => 'appellant' . $i . '@example.test',
        ':a'  => $archived,
        ':ab' => $archived ? $adminIds[mt_rand(0, count($adminIds) - 1)] : null,
        ':p'  => fakeIp(),
    ]);
}

// ---------------------------------------------------------------------------
// Submissions (80) — public ban-report submissions.
$insSub = $pdo->prepare(
    "INSERT INTO `{$prefix}_submissions` "
    . "(`submitted`, `ModID`, `SteamId`, `name`, `email`, `reason`, `ip`, "
    . " `subname`, `sip`, `archiv`, `archivedby`, `server`) "
    . "VALUES (:s, :m, :sid, :n, :e, :r, :i, :sn, :sip, :a, :ab, :sv)"
);
for ($i = 0; $i < 80; $i++) {
    $archived = mt_rand(0, 1);
    $mod = $modIcons[mt_rand(0, count($modIcons) - 1)];
    $insSub->execute([
        ':s'   => $now - mt_rand(0, 90 * 86400),
        ':m'   => $mod['mid'],
        ':sid' => fakeSteamId(),
        ':n'   => pick($nameDict),
        ':e'   => 'tipster' . $i . '@example.test',
        ':r'   => pick($reasonDict),
        ':i'   => fakeIp(),
        ':sn'  => 'reporter_' . $i,
        ':sip' => fakeIp(),
        ':a'   => $archived,
        ':ab'  => $archived ? $adminIds[mt_rand(0, count($adminIds) - 1)] : null,
        ':sv'  => $serverIds[mt_rand(0, count($serverIds) - 1)],
    ]);
}

// ---------------------------------------------------------------------------
// Banlog (1000 rows) — per-server enforcement events. Sampled from
// existing bans so the foreign references resolve.
$insBl = $pdo->prepare(
    "INSERT INTO `{$prefix}_banlog` (`sid`, `time`, `name`, `bid`) VALUES (:s, :t, :n, :b)"
);
$seenBl = [];
for ($i = 0; $i < 1000; $i++) {
    $sid = $serverIds[mt_rand(0, count($serverIds) - 1)];
    $bid = $banIds[mt_rand(0, count($banIds) - 1)];
    // banlog PK is (sid, time, bid); randomise time to avoid collisions
    // even with a fixed seed.
    $t = $now - mt_rand(0, 365 * 86400) - $i;
    $key = "$sid|$t|$bid";
    if (isset($seenBl[$key])) continue;
    $seenBl[$key] = true;
    $insBl->execute([':s' => $sid, ':t' => $t, ':n' => pick($nameDict), ':b' => $bid]);
}

// ---------------------------------------------------------------------------
// Comments (200) — threaded notes on existing bans.
$insCmt = $pdo->prepare(
    "INSERT INTO `{$prefix}_comments` "
    . "(`bid`, `type`, `aid`, `commenttxt`, `added`, `editaid`, `edittime`) "
    . "VALUES (:b, :t, :a, :c, :ad, :ea, :et)"
);
for ($i = 0; $i < 200; $i++) {
    $bid = $banIds[mt_rand(0, count($banIds) - 1)];
    $insCmt->execute([
        ':b'  => $bid,
        ':t'  => 'B', // ban-comment
        ':a'  => $adminIds[mt_rand(0, count($adminIds) - 1)],
        ':c'  => pick([
            'Reviewed alongside #SBPP_Sleuth — alt confirmed.',
            'Recidivist; previously banned 2x. No appeal entertained.',
            'Player apologised on Discord — see ticket #847.',
            'Will revisit at end of season; demo on file.',
            "Player's brother claimed account — denied.",
            "User contacted via Steam — no reply within 14d.",
        ]),
        ':ad' => $now - mt_rand(0, 60 * 86400),
        ':ea' => null,
        ':et' => null,
    ]);
}

// ---------------------------------------------------------------------------
// Sanity assertions — the script blows up loudly if the dataset shrunk.
$expected = [
    "{$prefix}_admins"  => 201,             // 200 generated + CONSOLE
    "{$prefix}_groups"  => 5,
    "{$prefix}_srvgroups" => 5,
    "{$prefix}_servers" => 30,
    "{$prefix}_bans"    => 5000,
    "{$prefix}_comms"   => 500,
    "{$prefix}_protests" => 50,
    "{$prefix}_submissions" => 80,
];
$bad = false;
foreach ($expected as $tbl => $count) {
    $row = $pdo->query("SELECT COUNT(*) AS n FROM `{$tbl}`")->fetch();
    if ((int)$row['n'] !== $count) {
        fwrite(STDERR, "seed.php: {$tbl} has " . $row['n'] . ", expected {$count}\n");
        $bad = true;
    }
}
if ($bad) {
    exit(4);
}

fwrite(STDOUT, "seeded {$version} fixture: 200 admins / 5 groups / 30 servers / "
    . "5000 bans / 500 comms / 50 protests / 80 submissions / 200 comments / 1000 banlog\n");
