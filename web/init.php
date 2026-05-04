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
require_once(INCLUDES_PATH.'/security/Crypto.php');
require_once(INCLUDES_PATH.'/security/CSRF.php');

require_once(INCLUDES_PATH.'/auth/JWT.php');

require_once(INCLUDES_PATH.'/auth/handler/NormalAuthHandler.php');
require_once(INCLUDES_PATH.'/auth/handler/SteamAuthHandler.php');

require_once(INCLUDES_PATH.'/auth/Auth.php');
require_once(INCLUDES_PATH.'/auth/Host.php');

require_once(INCLUDES_PATH.'/CUserManager.php');
require_once(INCLUDES_PATH.'/AdminTabs.php');

$version = is_readable('configs/version.json')
    ? @json_decode(file_get_contents('configs/version.json'), true)
    : null;

if (!$version) {
    $tag = trim((string) @shell_exec('git describe --tags --always 2>/dev/null'));
    $sha = trim((string) @shell_exec('git rev-parse --short HEAD 2>/dev/null'));
    if ($tag !== '' || $sha !== '') {
        $version = [
            'version' => $tag !== '' ? $tag : 'N/A',
            'git' => $sha,
            'dev' => true,
        ];
    }
}

define('SB_VERSION', $version['version'] ?? 'N/A');
define('SB_GITREV', $version['git'] ??  0);
define('SB_DEV', $version['dev'] ?? false);

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

require_once(INCLUDES_PATH.'/Database.php');
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

require_once(INCLUDES_PATH.'/Log.php');
Log::init($GLOBALS['PDO'], $userbank);

// ---------------------------------------------------
//  Setup our custom error handler
// ---------------------------------------------------
set_error_handler('sbError');
function sbError($errno, $errstr, $errfile, $errline)
{
    switch ($errno) {
        case E_USER_ERROR:
            Log::add('e', 'PHP Error', "[$errno] $errstr\nFatal Error on line $errline in file $errfile");
            return true;
        case E_USER_WARNING:
            Log::add('w', 'PHP Warning', "[$errno] $errstr\nError on line $errline in file $errfile");
            return true;
        case E_USER_NOTICE:
            Log::add('m', 'PHP Notice', "[$errno] $errstr\nNotice on line $errline in file $errfile");
            return true;
        default:
            return false;
    }
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
$theme->registerPlugin('modifier', 'smarty_stripslashes', 'smarty_stripslashes');
$theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

$theme->assign('csrf_token', CSRF::token());
$theme->assign('csrf_field_name', CSRF::FIELD_NAME);

if ((isset($_GET['debug']) && $_GET['debug'] == 1) || DEBUG_MODE) {
    $theme->setForceCompile(true);
}
