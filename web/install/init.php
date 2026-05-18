<?php
declare(strict_types=1);

// Paths-only bootstrap for the install wizard.
//
// This file MUST stay free of any dependency on
// `web/includes/vendor/` so it can run before the autoload-check in
// `web/install/index.php` decides whether to short-circuit to the
// recovery surface (#1332 C3). All Composer-loaded code lives in
// `web/install/bootstrap.php`, which is conditionally required from
// the entry point AFTER the vendor/ check has passed.
//
// The constants here mirror the ones `web/init.php` defines for the
// main panel runtime, but scoped to the installer's directory layout
// so the installer can `require_once ROOT.'../includes/Db/Database.php'`
// without each call site rebuilding the relative path.

define('IN_SB',      true);
define('IN_INSTALL', true);

define('ROOT',           __DIR__ . '/');
define('TEMPLATES_PATH', ROOT . 'pages');
define('INCLUDES_PATH',  ROOT . 'includes');

// `PANEL_ROOT` (web/) and `PANEL_INCLUDES_PATH` (web/includes/) are
// the installer's escape hatches into the main panel tree — we share
// the panel's Smarty default theme + Composer vendor with it instead
// of duplicating either.
define('PANEL_ROOT',          dirname(__DIR__) . '/');
define('PANEL_INCLUDES_PATH', PANEL_ROOT . 'includes');

// SB_VERSION is loud about the wizard surface so any chrome that
// renders the version (e.g. the install footer) makes the context
// obvious. The main panel's Sbpp\Version::resolve() reads
// configs/version.json, but we deliberately don't touch that path
// from the installer — config.php may not exist yet, and we don't
// want install-time renders polluting telemetry-shaped surfaces.
if (!defined('SB_VERSION')) {
    define('SB_VERSION', '2.0.0 | Installer');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

// We deliberately do NOT pre-create config.php to make the requirements
// check happy. Page 3's check is `is_writable($configPath) ||
// is_writable(PANEL_ROOT)` — the second clause already covers the
// "file doesn't exist but the directory does, so it'll be writable"
// case. Pre-creating an empty config.php has a sharp edge: if the
// operator deletes install/ mid-wizard, web/init.php's gate at
// `if (!file_exists(ROOT.'/config.php')) die('not installed')` sees
// the empty file as "installed", require()s zero bytes, and the next
// `DB_HOST` reference dies with `Undefined constant` (#1332 review:
// major 4).

// Forgive a missing trailing slash on `/install` (cPanel / nginx
// configs sometimes pass the URL through without it; the wizard's
// form actions assume the trailing slash for relative paths).
$urlPath = $_SERVER['REQUEST_URI'] ?? '';
if ($urlPath === '/install') {
    header('Location: /install/');
    exit;
}
