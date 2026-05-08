<?php
declare(strict_types=1);

/**
 * Issue #1270 profiling driver — run inside the docker `web` service:
 *
 *   ./sbpp.sh exec php /var/www/html/web/tests/scripts/profile-admin-home.php
 *
 * Mirrors the request shape `?p=admin` (no `c=`): bootstraps init.php,
 * forces an admin login, and times each suspected hot-spot in
 * `web/pages/page.admin.php` against the seeded dev DB.
 *
 * The script runs **two** measurement modes:
 *
 *   --mode=handler-only  Times just the `page.admin.php` include — the
 *                        narrowest unit the gate (`Sbpp\Theme::wantsLegacyAdminCounts()`)
 *                        actually controls. Useful for "what did the
 *                        gate save us, exactly?".
 *
 *   --mode=end-to-end    Times the full `?p=admin` request the way
 *                        `web/index.php` would dispatch it: route()
 *                        (incl. CheckAdminAccess) + the chrome (header
 *                        / navbar / title / page / footer) + the page
 *                        handler itself. This is the number the
 *                        acceptance criterion (`< 500ms` end-to-end)
 *                        is measured against, so the PR body's
 *                        `total` column should cite this — not the
 *                        narrower handler-only number.
 *
 *   --mode=both          Default. Runs both modes back-to-back so the
 *                        PR body table has both columns from one
 *                        invocation.
 *
 * For each mode we report:
 *
 *   - boot:        init.php + system-functions.php boot (one-shot)
 *   - counts:      the 9-COUNT subquery (isolated micro-measurement)
 *   - getDirSize:  recursive walk over `web/demos/` (isolated)
 *   - render:      `Renderer::render($theme, new AdminHomeView(...))`
 *                  (isolated)
 *   - total.default.ms / total.fork.ms — the unit-of-work time for
 *     the chosen mode, default-theme path vs the fork opt-in (`define('theme_legacy_admin_counts', true)`).
 *
 * Why `require` (not `require_once`) inside the end-to-end timer:
 * production PHP-FPM serves each request from a fresh PHP context so
 * `require_once` runs the file every time. The in-process loop here
 * uses plain `require` so each pass re-executes the chrome + page,
 * matching the per-request cost a real worker pays. Without this the
 * second-pass measurement would be ~0ms (require_once short-circuit)
 * and the warm-pass mean would be a meaningless underestimate.
 *
 * Output is plain `key = value` lines so the PR body table can copy/paste
 * the numbers without re-formatting.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$mode = 'both';
foreach ($argv as $arg) {
    if (preg_match('/^--mode=(handler-only|end-to-end|both)$/', $arg, $m)) {
        $mode = $m[1];
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDERR, "Usage: php profile-admin-home.php [--mode=handler-only|end-to-end|both]\n");
        exit(0);
    }
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/index.php?p=admin';
$_SERVER['REQUEST_METHOD'] = 'GET';

chdir(__DIR__ . '/../../');

$bootStart = microtime(true);
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/system-functions.php';
require_once __DIR__ . '/../../includes/page-builder.php';
$bootMs = (microtime(true) - $bootStart) * 1000;

$key    = \Lcobucci\JWT\Signer\Key\InMemory::plainText(str_repeat('x', 32));
$config = \Lcobucci\JWT\Configuration::forSymmetricSigner(new \Lcobucci\JWT\Signer\Hmac\Sha256(), $key);
$adminAid = (int) ($GLOBALS['PDO']->query("SELECT aid FROM `:prefix_admins` WHERE user = 'admin' LIMIT 1")->single()['aid'] ?? 1);
$token = $config->builder()->withClaim('aid', $adminAid)->getToken($config->signer(), $config->signingKey());
$GLOBALS['userbank'] = new \CUserManager($token);
$GLOBALS['username'] = 'admin';

function timeMs(callable $fn): array
{
    $t0  = microtime(true);
    $out = $fn();
    return [(microtime(true) - $t0) * 1000, $out];
}

function avg(array $samples): float
{
    return array_sum($samples) / count($samples);
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
        access_admins:        $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListAdmins, WebPermission::AddAdmins, WebPermission::EditAdmins, WebPermission::DeleteAdmins)),
        access_servers:       $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListServers, WebPermission::AddServer, WebPermission::EditServers, WebPermission::DeleteServers)),
        access_bans:          $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddBan, WebPermission::EditOwnBans, WebPermission::EditGroupBans, WebPermission::EditAllBans, WebPermission::BanProtests, WebPermission::BanSubmissions)),
        access_groups:        $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListGroups, WebPermission::AddGroup, WebPermission::EditGroups, WebPermission::DeleteGroups)),
        access_settings:      $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::WebSettings)),
        access_mods:          $userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::ListMods, WebPermission::AddMods, WebPermission::EditMods, WebPermission::DeleteMods)),
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

/**
 * Handler-only mode: time just the `page.admin.php` include — the
 * narrowest unit the gate actually controls.
 */
function handlerOnlyInclude(): float
{
    ob_start();
    $t0 = microtime(true);
    require ROOT . 'pages/page.admin.php';
    $ms = (microtime(true) - $t0) * 1000;
    ob_end_clean();
    return $ms;
}

/**
 * End-to-end mode: time the full `?p=admin` request the way
 * `web/index.php` dispatches it — `route()` (auth check + page dispatch)
 * + the chrome (header / navbar / title / page / footer) + the page
 * handler itself. Mirrors `build()` in `web/includes/page-builder.php`
 * but uses `require` (not `require_once`) so each warm pass actually
 * re-executes the chrome the way a fresh PHP-FPM worker would.
 */
function endToEndRequest(): float
{
    $_GET['p'] = 'admin';
    unset($_GET['c']);

    ob_start();
    $t0 = microtime(true);

    [$title, $page] = route('home');

    require TEMPLATES_PATH . '/core/header.php';
    require TEMPLATES_PATH . '/core/navbar.php';
    require TEMPLATES_PATH . '/core/title.php';
    require TEMPLATES_PATH . $page;
    require TEMPLATES_PATH . '/core/footer.php';

    $ms = (microtime(true) - $t0) * 1000;
    ob_end_clean();
    return $ms;
}

// Discard the first call's output so the numbers reflect steady-state
// (Smarty compile + opcache prime are unrelated to #1270 — see the
// agent prompt's "warm cache" guidance). Warm both paths so neither
// pays a one-shot Smarty compile inside its own measurement window.
ob_start(); handlerOnlyInclude(); ob_end_clean();
ob_start(); endToEndRequest();    ob_end_clean();

$N = 5;

$countsSamples  = [];
$dirSizeSamples = [];
$renderSamples  = [];
$countsResult   = null;
$demoSizeStr    = '0 B';
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

/**
 * Run a unit-of-work N times, returning [avg_ms, gated_count]. Resets
 * the legacy-compute counter at the start so the gated_count reflects
 * just this batch.
 */
function measure(callable $unitOfWork, int $n): array
{
    \Sbpp\Theme::resetLegacyComputeCount();
    $samples = [];
    for ($i = 0; $i < $n; $i++) {
        $samples[] = $unitOfWork();
    }
    return [avg($samples), \Sbpp\Theme::legacyComputeCount()];
}

// PHP `define()` is process-permanent — once we flip the per-theme
// opt-in constant on, every subsequent pass takes the legacy compute
// branch regardless of which mode is timing. So the script schedules
// **all default-theme passes first**, then defines the constant once,
// then runs **all fork passes**. This keeps the default-theme numbers
// honest without needing a separate process per mode.

$handlerDefault = $handlerE2eDefault = null;
if ($mode === 'handler-only' || $mode === 'both') {
    [$avg, $gated] = measure('handlerOnlyInclude', $N);
    $handlerDefault = ['avg' => $avg, 'gated' => $gated];
}
if ($mode === 'end-to-end' || $mode === 'both') {
    [$avg, $gated] = measure('endToEndRequest', $N);
    $handlerE2eDefault = ['avg' => $avg, 'gated' => $gated];
}

// Flip the opt-in constant on. From here every gated path fires.
if (!defined(\Sbpp\Theme::LEGACY_ADMIN_COUNTS_CONSTANT)) {
    define(\Sbpp\Theme::LEGACY_ADMIN_COUNTS_CONSTANT, true);
}

$handlerFork = $handlerE2eFork = null;
if ($mode === 'handler-only' || $mode === 'both') {
    [$avg, $gated] = measure('handlerOnlyInclude', $N);
    $handlerFork = ['avg' => $avg, 'gated' => $gated];
}
if ($mode === 'end-to-end' || $mode === 'both') {
    [$avg, $gated] = measure('endToEndRequest', $N);
    $handlerE2eFork = ['avg' => $avg, 'gated' => $gated];
}

$handlerStats  = ($handlerDefault    && $handlerFork)    ? ['default' => $handlerDefault['avg'],    'fork' => $handlerFork['avg'],    'gated_default' => $handlerDefault['gated'],    'gated_fork' => $handlerFork['gated']]    : null;
$endToEndStats = ($handlerE2eDefault && $handlerE2eFork) ? ['default' => $handlerE2eDefault['avg'], 'fork' => $handlerE2eFork['avg'], 'gated_default' => $handlerE2eDefault['gated'], 'gated_fork' => $handlerE2eFork['gated']] : null;

$demoFileCount = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SB_DEMOS, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY));
$banCount    = (int) ($GLOBALS['PDO']->query("SELECT COUNT(bid) AS n FROM `:prefix_bans`")->single()['n']    ?? 0);
$banlogCount = (int) ($GLOBALS['PDO']->query("SELECT COUNT(bid) AS n FROM `:prefix_banlog`")->single()['n'] ?? 0);

printf("=== #1270 profile, %d bans / %d banlog, demos=%d files, mode=%s, warm pass (N=%d) ===\n",
    $banCount, $banlogCount, $demoFileCount, $mode, $N);
printf("boot.ms                       = %7.2f   (one-shot)\n", $bootMs);
printf("counts.ms (isolated)          = %7.2f   avg of %d   (%s)\n", avg($countsSamples), $N, json_encode($countsResult));
printf("getDirSize.ms (isolated)      = %7.2f   avg of %d   (%s)\n", avg($dirSizeSamples), $N, $demoSizeStr);
printf("render.ms (isolated)          = %7.2f   avg of %d\n", avg($renderSamples), $N);

if ($handlerStats !== null) {
    printf("\n--- handler-only (page.admin.php include only) ---\n");
    printf("total.handler.default.ms      = %7.2f   avg of %d   (gate fired %d / %d passes)\n",
        $handlerStats['default'], $N, $handlerStats['gated_default'], $N);
    printf("total.handler.fork.ms         = %7.2f   avg of %d   (gate fired %d / %d passes)\n",
        $handlerStats['fork'], $N, $handlerStats['gated_fork'], $N);
    printf("handler.delta.ms              = %7.2f   (saved per request, fork - default)\n",
        $handlerStats['fork'] - $handlerStats['default']);
}

if ($endToEndStats !== null) {
    printf("\n--- end-to-end (route + chrome + page + footer; the acceptance-criterion unit) ---\n");
    printf("total.e2e.default.ms          = %7.2f   avg of %d   (gate fired %d / %d passes)\n",
        $endToEndStats['default'], $N, $endToEndStats['gated_default'], $N);
    printf("total.e2e.fork.ms             = %7.2f   avg of %d   (gate fired %d / %d passes)\n",
        $endToEndStats['fork'], $N, $endToEndStats['gated_fork'], $N);
    printf("e2e.delta.ms                  = %7.2f   (saved per request, fork - default)\n",
        $endToEndStats['fork'] - $endToEndStats['default']);
}
