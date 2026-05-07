<?php
declare(strict_types=1);

/**
 * Issue #1270 profiling driver — run inside the docker `web` service:
 *
 *   ./sbpp.sh exec php /var/www/html/web/tests/scripts/profile-admin-home.php
 *
 * Mirrors the request shape `?p=admin` (no `c=`): bootstraps init.php,
 * forces an admin login, and times each suspected hot-spot in
 * `web/pages/page.admin.php` against the seeded dev DB. Runs the whole
 * sequence twice and reports the warm pass — the first hit pays Smarty
 * compile cost which is unrelated to the issue (per the agent prompt's
 * "When measuring, use a warm cache" guidance).
 *
 * Reports four wall-clock measurements per pass, in milliseconds:
 *
 *   - boot:        init.php + system-functions.php boot (per-include)
 *   - counts:      the 9-COUNT subquery in `page.admin.php`
 *   - getDirSize:  recursive walk over `web/demos/`
 *   - render:      `Renderer::render($theme, new AdminHomeView(...))`
 *
 * Plus the wall-clock for two end-to-end include passes:
 *
 *   - default theme (#1270 gate skips the compute)
 *   - forked theme (`theme_legacy_admin_counts` defined → compute runs)
 *
 * Output is plain key=value lines so the PR body table can copy/paste
 * the numbers without re-formatting.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

// Mirror web/index.php's request shape so init.php's "are you on
// localhost?" guard (line 51) doesn't bail. The dev container always
// runs on the localhost vhost — the production guard only matters for
// real installs sitting behind a proxy.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/index.php?p=admin';
$_SERVER['REQUEST_METHOD'] = 'GET';

chdir(__DIR__ . '/../../');

$bootStart = microtime(true);
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/system-functions.php';
require_once __DIR__ . '/../../includes/page-builder.php';
$bootMs = (microtime(true) - $bootStart) * 1000;

// Force-login as the seeded admin (aid=1 by data.sql) without going
// through the JWT cookie path. CUserManager(null) → no admin; we mint
// a token in-process the way ApiTestCase does, then build a fresh
// CUserManager from it.
$key    = \Lcobucci\JWT\Signer\Key\InMemory::plainText(str_repeat('x', 32));
$config = \Lcobucci\JWT\Configuration::forSymmetricSigner(new \Lcobucci\JWT\Signer\Hmac\Sha256(), $key);
$adminAid = (int) ($GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE user = 'admin' LIMIT 1")->single()['aid'] ?? 1);
$token = $config->builder()->withClaim('aid', $adminAid)->getToken($config->signer(), $config->signingKey());
$GLOBALS['userbank'] = new \CUserManager($token);
$GLOBALS['username'] = 'admin';

function timeMs(callable $fn): array
{
    $t0 = microtime(true);
    $out = $fn();
    return [(microtime(true) - $t0) * 1000, $out];
}

function isolatedCounts(): array
{
    return $GLOBALS['PDO']->query("SELECT
                                 (SELECT COUNT(bid) FROM `:prefix_banlog`)                       AS blocks,
                                 (SELECT COUNT(bid) FROM `:prefix_bans`)                         AS bans,
                                 (SELECT COUNT(bid) FROM `:prefix_comms`)                        AS comms,
                                 (SELECT COUNT(aid) FROM `:prefix_admins`  WHERE aid > 0)        AS admins,
                                 (SELECT COUNT(subid) FROM `:prefix_submissions` WHERE archiv = '0') AS subs,
                                 (SELECT COUNT(subid) FROM `:prefix_submissions` WHERE archiv > 0)   AS archiv_subs,
                                 (SELECT COUNT(pid) FROM `:prefix_protests`    WHERE archiv = '0') AS protests,
                                 (SELECT COUNT(pid) FROM `:prefix_protests`    WHERE archiv > 0)   AS archiv_protests,
                                 (SELECT COUNT(sid) FROM `:prefix_servers`)                      AS servers")->single();
}

function renderOnly(array $counts, string $demosize): void
{
    global $userbank, $theme;
    $perms = \Sbpp\View\Perms::for($userbank);
    \Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminHomeView(
        can_admins:    $perms['can_list_admins']  || $perms['can_add_admins']  || $perms['can_edit_admins']  || $perms['can_delete_admins'],
        can_groups:    $perms['can_list_groups']  || $perms['can_add_group']   || $perms['can_edit_groups']  || $perms['can_delete_groups'],
        can_servers:   $perms['can_list_servers'] || $perms['can_add_server']  || $perms['can_edit_servers'] || $perms['can_delete_servers'],
        can_bans:      $perms['can_add_ban'] || $perms['can_edit_own_bans'] || $perms['can_edit_group_bans'] || $perms['can_edit_all_bans'] || $perms['can_ban_protests'] || $perms['can_ban_submissions'],
        can_mods:      $perms['can_list_mods']    || $perms['can_add_mods']    || $perms['can_edit_mods']    || $perms['can_delete_mods'],
        can_overrides: $perms['can_add_admins'],
        can_settings:  $perms['can_web_settings'],
        can_audit:     $perms['can_owner'],
        access_admins:        $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_ADMINS  | ADMIN_ADD_ADMINS  | ADMIN_EDIT_ADMINS  | ADMIN_DELETE_ADMINS),
        access_servers:       $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_SERVERS | ADMIN_ADD_SERVER  | ADMIN_EDIT_SERVERS | ADMIN_DELETE_SERVERS),
        access_bans:          $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_ALL_BANS | ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS),
        access_groups:        $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_GROUPS  | ADMIN_ADD_GROUP   | ADMIN_EDIT_GROUPS  | ADMIN_DELETE_GROUPS),
        access_settings:      $userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS),
        access_mods:          $userbank->HasAccess(ADMIN_OWNER | ADMIN_LIST_MODS    | ADMIN_ADD_MODS    | ADMIN_EDIT_MODS    | ADMIN_DELETE_MODS),
        demosize:             $demosize,
        total_admins:         (int) $counts['admins'],
        total_bans:           (int) $counts['bans'],
        total_comms:          (int) $counts['comms'],
        total_blocks:         (int) $counts['blocks'],
        total_servers:        (int) $counts['servers'],
        total_protests:       (int) $counts['protests'],
        archived_protests:    (int) $counts['archiv_protests'],
        total_submissions:    (int) $counts['subs'],
        archived_submissions: (int) $counts['archiv_subs'],
    ));
}

function fullPageInclude(): float
{
    ob_start();
    $t0 = microtime(true);
    require ROOT . 'pages/page.admin.php';
    $ms = (microtime(true) - $t0) * 1000;
    ob_end_clean();
    return $ms;
}

// --- WARM PASS ---------------------------------------------------------
// Discard the first call's output (Smarty compile + opcache prime) so
// the numbers reflect steady-state. Average each measurement over 5
// passes so a one-off GC pause doesn't dominate.
ob_start();
fullPageInclude();
ob_end_clean();

function avg(array $samples): float
{
    return array_sum($samples) / count($samples);
}

$N = 5;

$countsSamples = [];
$dirSizeSamples = [];
$renderSamples = [];
$countsResult = null;
$demoSizeStr  = '0 B';
for ($i = 0; $i < $N; $i++) {
    [$ms, $countsResult] = timeMs('isolatedCounts');
    $countsSamples[] = $ms;
    [$ms, $demoSizeStr] = timeMs(function () { return getDirSize(SB_DEMOS); });
    $dirSizeSamples[] = $ms;
    [$ms, ] = timeMs(function () use ($countsResult, $demoSizeStr) {
        ob_start();
        renderOnly($countsResult, $demoSizeStr);
        ob_end_clean();
    });
    $renderSamples[] = $ms;
}

$totalDefaultSamples = [];
\Sbpp\Theme::resetLegacyComputeCount();
for ($i = 0; $i < $N; $i++) {
    $totalDefaultSamples[] = fullPageInclude();
}
$gatedTook = \Sbpp\Theme::legacyComputeCount();

define(\Sbpp\Theme::LEGACY_ADMIN_COUNTS_CONSTANT, true);
$totalForkSamples = [];
\Sbpp\Theme::resetLegacyComputeCount();
for ($i = 0; $i < $N; $i++) {
    $totalForkSamples[] = fullPageInclude();
}
$forkTook = \Sbpp\Theme::legacyComputeCount();

$demoFileCount = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SB_DEMOS, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY));
$banCount     = (int) ($GLOBALS['PDO']->query("SELECT COUNT(bid) AS n FROM `:prefix_bans`")->single()['n'] ?? 0);
$banlogCount  = (int) ($GLOBALS['PDO']->query("SELECT COUNT(bid) AS n FROM `:prefix_banlog`")->single()['n'] ?? 0);

printf("=== #1270 profile, %d bans / %d banlog, demos=%d files, warm pass (N=%d) ===\n", $banCount, $banlogCount, $demoFileCount, $N);
printf("boot.ms                = %7.2f   (one-shot)\n", $bootMs);
printf("counts.ms              = %7.2f   avg of %d   (%s)\n", avg($countsSamples), $N, json_encode($countsResult));
printf("getDirSize.ms          = %7.2f   avg of %d   (%s)\n", avg($dirSizeSamples), $N, $demoSizeStr);
printf("render.ms              = %7.2f   avg of %d\n", avg($renderSamples), $N);
printf("total.default.ms       = %7.2f   avg of %d   (gated branch fired %d times across all passes)\n", avg($totalDefaultSamples), $N, $gatedTook);
printf("total.fork.ms          = %7.2f   avg of %d   (gated branch fired %d times across all passes)\n", avg($totalForkSamples), $N, $forkTook);
printf("default-vs-fork.delta  = %7.2f ms (saved per request)\n", avg($totalForkSamples) - avg($totalDefaultSamples));
