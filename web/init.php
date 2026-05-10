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
use Smarty\Smarty;

// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', dirname(__FILE__) . "/");
define('SCRIPT_PATH', ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'pages');
define('INCLUDES_PATH', ROOT . 'includes');
define('SB_MAP_LOCATION',  'images/maps');
define('SB_DEMO_LOCATION', 'demos');
define('SB_ICON_LOCATION', 'images/games');
define('SB_MAPS',  ROOT . SB_MAP_LOCATION);
define('SB_DEMOS', ROOT . SB_DEMO_LOCATION);
define('SB_ICONS', ROOT . SB_ICON_LOCATION);

define('SB_THEMES', ROOT . 'themes/');
define('SB_CACHE', ROOT . 'cache/');

define("MMDB_PATH", ROOT . 'data/GeoLite2-Country.mmdb');

define('IN_SB', true);

// ---------------------------------------------------
//  Are we installed?
// ---------------------------------------------------
#DB Config
if (!file_exists(ROOT.'/config.php')) {
    die('SourceBans++ is not installed.');
}
require_once(ROOT.'/config.php');

if ($_SERVER['HTTP_HOST'] != "localhost" && !defined("IS_UPDATE")) {
    if (file_exists(ROOT."/install")) {
        die('Please delete the install directory before you use SourceBans++.');
    } else if (file_exists(ROOT."/updater")) {
        die('Please delete the updater directory before using SourceBans++.');
    }
}

#Composer autoload
if (!file_exists(INCLUDES_PATH.'/vendor/autoload.php')) {
    die('Compose autoload not found! Run `composer install` in the root directory of your SourceBans++ installation.');
}
require_once(INCLUDES_PATH.'/vendor/autoload.php');

// ---------------------------------------------------
//  Initial setup
// ---------------------------------------------------
// All classes below now live under Sbpp\… namespaces (issue #1290 phase B)
// and are PSR-4 autoloaded from web/includes/. The require_once chain is
// retained so each file's class_alias() shim runs eagerly — the legacy
// global names (`Database`, `CUserManager`, `Auth`, `Log`, `CSRF`, …) need
// to be registered before procedural code references them, and the
// autoloader can't trigger those aliases on a global-name lookup. New
// code can reference the namespaced symbols (e.g. `Sbpp\Db\Database`)
// directly without any explicit require.
require_once(INCLUDES_PATH.'/Security/Crypto.php');
require_once(INCLUDES_PATH.'/Security/CSRF.php');

require_once(INCLUDES_PATH.'/Auth/JWT.php');

require_once(INCLUDES_PATH.'/Auth/Handler/NormalAuthHandler.php');
require_once(INCLUDES_PATH.'/Auth/Handler/SteamAuthHandler.php');

require_once(INCLUDES_PATH.'/Auth/Auth.php');
require_once(INCLUDES_PATH.'/Auth/Host.php');

require_once(INCLUDES_PATH.'/Auth/UserManager.php');
require_once(INCLUDES_PATH.'/View/AdminTabs.php');

// Three-tier version resolution (#1207 CC-5): tarball JSON, git describe,
// then the literal 'dev' sentinel. See \Sbpp\Version::resolve() for the
// full rationale; this block just unpacks the result into the constants
// the chrome and views consume. \Sbpp\Version is PSR-4 autoloaded via
// composer (Sbpp\\ -> includes/), so no explicit require is needed.
$version = \Sbpp\Version::resolve(ROOT . 'configs/version.json');

define('SB_VERSION', $version['version']);
define('SB_GITREV', $version['git']);

// ---------------------------------------------------
//  Setup our DB
// ---------------------------------------------------
// utf8mb4 is the project-wide default so multi-byte player names (CJK,
// Cyrillic, emoji) survive inserts. The narrower `utf8` alias is the 3-byte
// subset MariaDB kept for back-compat. The updater wizard at
// `web/updater/data/600.php` already converts every table with
// `ALTER TABLE … CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
// and rewrites `config.php` to define `DB_CHARSET = 'utf8mb4'`, so any
// operator who has run the updater past version 600 is already on
// utf8mb4. This define is the safety net for the (unlikely) case of a
// `config.php` written without the constant — it does NOT override an
// operator who explicitly set `'utf8'` in their config.
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('SB_EMAIL')) {
    define('SB_EMAIL', '');
}

require_once(INCLUDES_PATH.'/Db/Database.php');
$GLOBALS['PDO'] = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

require_once(INCLUDES_PATH.'/SteamID/bootstrap.php');
\SteamID\SteamID::init($GLOBALS['PDO']);

require_once(INCLUDES_PATH.'/Config.php');
Config::init($GLOBALS['PDO']);

define("DEBUG_MODE", Config::getBool('config.debug'));

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL ^ E_NOTICE);
}

Auth::init($GLOBALS['PDO']);

// ---------------------------------------------------
// Setup our user manager
// ---------------------------------------------------

$userbank = new CUserManager(Auth::verify());

// ---------------------------------------------------
// Bind a CSRF token to the session (must run before any form is
// rendered so the token is available, and before any state-changing
// API request reaches the dispatcher).
// ---------------------------------------------------
CSRF::init();

require_once(INCLUDES_PATH.'/LogType.php');
require_once(INCLUDES_PATH.'/LogSearchType.php');
require_once(INCLUDES_PATH.'/BanType.php');
require_once(INCLUDES_PATH.'/BanRemoval.php');
require_once(INCLUDES_PATH.'/WebPermission.php');
require_once(INCLUDES_PATH.'/Log.php');
Log::init($GLOBALS['PDO'], $userbank);

// Api / ApiError are loaded here (rather than at the top of the require
// chain) because Sbpp\Api\Api `use Sbpp\Log;` and the legacy alias for
// Log only exists once Log.php has been required above. ApiError must
// come first: Sbpp\Api\Api references it internally (Api::error() etc.).
// Without these requires the legacy global aliases (`Api`, `ApiError`)
// would only resolve when web/api.php's own require_once fires — page
// handlers that call `Api::redirect()` outside the JSON dispatcher
// would die at runtime even though phpstan-bootstrap.php loads them
// eagerly and the analyser would be happy.
require_once(INCLUDES_PATH.'/Api/ApiError.php');
require_once(INCLUDES_PATH.'/Api/Api.php');

// ---------------------------------------------------
//  Setup our custom error handler
// ---------------------------------------------------
set_error_handler('sbError');
function sbError($errno, $errstr, $errfile, $errline)
{
    // Map E_USER_* into a (log-level, log-title, error-word) triplet so the
    // dispatch is one table read; a `default => null` arm preserves the
    // legacy switch's "unknown errno → return false" fall-through. `match`
    // arms can't host `Log::add(...) + return true` directly because the
    // expression must yield a single value — the logging side effect runs
    // outside the match below the lookup.
    $entry = match ($errno) {
        E_USER_ERROR   => [LogType::Error,   'PHP Error',   'Fatal Error'],
        E_USER_WARNING => [LogType::Warning, 'PHP Warning', 'Error'],
        E_USER_NOTICE  => [LogType::Message, 'PHP Notice',  'Notice'],
        default        => null,
    };
    if ($entry === null) {
        return false;
    }
    [$logLevel, $logTitle, $errorWord] = $entry;
    Log::add($logLevel, $logTitle, "[$errno] $errstr\n$errorWord on line $errline in file $errfile");
    return true;
}

$webflags = json_decode(file_get_contents(ROOT.'/configs/permissions/web.json'), true);
foreach ($webflags as $flag => $perm) {
    define($flag, $perm['value']);
}
$smflags = json_decode(file_get_contents(ROOT.'/configs/permissions/sourcemod.json'), true);
foreach ($smflags as $flag => $perm) {
    define($flag, $perm['value']);
}

define('SB_BANS_PER_PAGE', Config::get('banlist.bansperpage'));
define('MIN_PASS_LENGTH', Config::get('config.password.minlength'));

// ---------------------------------------------------
// Setup our templater
// ---------------------------------------------------

global $theme, $userbank;

$theme_name = (Config::getBool('config.theme')) ? Config::get('config.theme') : 'default';
if (defined("IS_UPDATE")) {
    $theme_name = "default";
}
define('SB_THEME', $theme_name);

if (!@file_exists(SB_THEMES . $theme_name . "/theme.conf.php")) {
    die("Theme Error: <b>".$theme_name."</b> is not a valid theme. Must have a valid <b>theme.conf.php</b> file.");
}
if (!@is_writable(SB_CACHE)) {
    die("Theme Error: <b>".SB_CACHE."</b> MUST be writable.");
}

require_once(INCLUDES_PATH.'/SmartyCustomFunctions.php');

$theme = new Smarty();
$theme->setErrorReporting(E_ALL);
$theme->setUseSubDirs(false);
$theme->setCompileId($theme_name);
$theme->setCaching(Smarty::CACHING_OFF);
$theme->setTemplateDir(SB_THEMES . $theme_name);
$theme->setCacheDir(SB_CACHE);
$theme->setEscapeHtml(true);
$theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'help_icon', 'smarty_function_help_icon');
$theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'sb_button', 'smarty_function_sb_button');
$theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'load_template', 'smarty_function_load_template');
$theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field', 'smarty_function_csrf_field');
$theme->registerPlugin(Smarty::PLUGIN_BLOCK, 'has_access', 'smarty_block_has_access');
$theme->registerPlugin('modifier', 'smarty_stripslashes', 'smarty_stripslashes');
$theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

$theme->assign('csrf_token', CSRF::token());
$theme->assign('csrf_field_name', CSRF::FIELD_NAME);
// Public web path to the active theme directory (e.g. "themes/default").
// Templates use it to reference theme-local CSS / JS / fonts / images
// without hardcoding the theme name. SB_THEMES is an absolute filesystem
// path; the public-facing equivalent is just "themes/<theme>" because
// web/index.php is the document root.
$theme->assign('theme_url', 'themes/' . $theme_name);

if ((isset($_GET['debug']) && $_GET['debug'] == 1) || DEBUG_MODE) {
    $theme->setForceCompile(true);
}

// Anonymous opt-out daily telemetry (#1126). Registered last so the
// settings cache, version constants, theme name, and DB are all warm
// — Telemetry::tickIfDue() reads each of them. Both index.php and
// api.php require_once init.php, so registering once here covers
// the panel + JSON API surfaces. tickIfDue() wraps its own body in
// try/catch(\Throwable), so a misbehaving collector or a flapping
// endpoint never leaks an exception out of the shutdown function.
// On FPM, Telemetry calls fastcgi_finish_request() before the cURL
// POST so the user's TCP socket closes first; non-FPM SAPIs fall
// back to ob_end_flush + flush.
register_shutdown_function([\Sbpp\Telemetry\Telemetry::class, 'tickIfDue']);
