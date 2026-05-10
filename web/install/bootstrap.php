<?php
declare(strict_types=1);

// Composer + Smarty bootstrap for the install wizard.
//
// Required from `web/install/index.php` AFTER the autoload-check
// short-circuits to `recovery.php` if vendor/ is missing (#1332 C3).
// Anything that pulls in `Sbpp\…` (or the legacy global aliases like
// `Database`) MUST happen here, never in `web/install/init.php`.
//
// Mirrors the require_once chain at the top of `web/init.php` /
// `web/tests/bootstrap.php` / `web/phpstan-bootstrap.php` so the
// legacy global names (`Database`, `CUserManager`, `Log`, …) resolve
// for procedural install code that hasn't been migrated to the
// namespaced symbols. Anti-pattern guard in AGENTS.md ("Removing the
// eager `require_once` chain") spells out why every file is
// required eagerly rather than relying on the autoloader: the
// autoloader resolves the namespaced name; the `class_alias()` call
// at the bottom of each namespaced file is what registers the
// global-name shim, and that call is a runtime statement the
// autoloader can't trigger on a global-name lookup.

if (!defined('IN_SB') || !defined('PANEL_INCLUDES_PATH')) {
    // Defense-in-depth: install/init.php should already have run.
    http_response_code(500);
    die('Installer bootstrap reached without paths-init.');
}

require_once PANEL_INCLUDES_PATH . '/vendor/autoload.php';

// Cherry-pick the subset of the panel's eager-load chain the wizard
// needs. The wizard never logs in a user (no Auth/UserManager/JWT/
// Crypto), never gates by web permissions (no WebPermission /
// LogType / Config / Log), never dispatches a JSON API call (no
// Api / ApiError). What it DOES need:
//
//   - Sbpp\Db\Database (legacy global `Database`) — every step from
//     2 onward instantiates one against the operator-supplied creds.
require_once PANEL_INCLUDES_PATH . '/Db/Database.php';

// Shared step-handler helpers (prefix validation, DB-open with raw
// PDO probe, KeyValues escaping). Required after Database.php so
// sbpp_install_open_db() can reference \Database. See helpers.php
// for the per-helper rationale.
require_once INCLUDES_PATH . '/helpers.php';

// Smarty for the View DTO render path. Same defaults as
// `web/init.php` (escape HTML on, caching off, force-compile on).
//
// Compile + cache dirs default to `web/templates_c/install_*` and
// `web/cache/install_smarty`. The wizard tries the panel-runtime
// dirs first because they're already configured-writable by every
// reasonable installer recipe (the docker entrypoint chowns them
// for dev; the requirements page on step 3 surfaces a fail if
// `cache/` isn't writable in production), and falls back to a
// per-step temp dir if both fail. Without this fallback an operator
// who uploaded files via `cp -R` (preserving root-owned dirs) would
// hit "Smarty: unable to create directory" with no actionable
// recovery path before they ever see the requirements page.
$installCompileDir = sbpp_install_pick_writable_dir([
    PANEL_ROOT . 'templates_c/install_smarty_compile',
    PANEL_ROOT . 'cache/install_smarty_compile',
    sys_get_temp_dir() . '/sbpp_install_smarty_compile',
]);
$installCacheDir = sbpp_install_pick_writable_dir([
    PANEL_ROOT . 'cache/install_smarty_cache',
    PANEL_ROOT . 'templates_c/install_smarty_cache',
    sys_get_temp_dir() . '/sbpp_install_smarty_cache',
]);

$installTheme = new \Smarty\Smarty();
$installTheme->setErrorReporting(E_ALL);
$installTheme->setUseSubDirs(false);
$installTheme->setCompileId('install');
$installTheme->setCaching(\Smarty\Smarty::CACHING_OFF);
$installTheme->setTemplateDir(PANEL_ROOT . 'themes/default');
$installTheme->setCompileDir($installCompileDir);
$installTheme->setCacheDir($installCacheDir);
$installTheme->setEscapeHtml(true);
// Force-compile — the wizard runs a handful of times in a single
// install's lifetime, so the recompile cost is irrelevant and the
// safety against stale compiled templates (e.g. a re-run after the
// admin edited a template) is worth it.
$installTheme->setForceCompile(true);

$GLOBALS['installTheme'] = $installTheme;

/**
 * Return the first writable directory from $candidates, creating it
 * if necessary. Falls back to the last candidate if none are
 * already-writable (lets Smarty surface its own clearer error if
 * even the temp dir can't be written).
 *
 * @param list<string> $candidates
 */
function sbpp_install_pick_writable_dir(array $candidates): string
{
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }
    return end($candidates) ?: sys_get_temp_dir();
}
