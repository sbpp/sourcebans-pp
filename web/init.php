<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

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
//Hotfix for dash_intro_text
if (isset($_POST['dash_intro_text'])) {
    $dash_intro_text = $_POST['dash_intro_text'];
}
//Filter all user inputs
//Should be changed to individual filtering
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
$_COOKIE = filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING);
//$_SERVER = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);

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

require_once(INCLUDES_PATH.'/auth/JWT.php');

require_once(INCLUDES_PATH.'/auth/handler/NormalAuthHandler.php');
require_once(INCLUDES_PATH.'/auth/handler/SteamAuthHandler.php');

require_once(INCLUDES_PATH.'/auth/Auth.php');
require_once(INCLUDES_PATH.'/auth/Host.php');

require_once(INCLUDES_PATH.'/CUserManager.php');
require_once(INCLUDES_PATH.'/AdminTabs.php');

$version = @json_decode(file_get_contents('configs/version.json'), true);
define('SB_VERSION', isset($version['version']) ? $version['version'] : 'N/A');
define('SB_GITREV', isset($version['git']) ? $version['git'] : 0);
define('SB_DEV', isset($version['dev']) ? $version['dev'] : false);

// ---------------------------------------------------
//  Setup our DB
// ---------------------------------------------------
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8');
}

if (!defined('SB_EMAIL')) {
    define('SB_EMAIL', '');
}

include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");
include_once(INCLUDES_PATH . "/adodb/adodb-errorhandler.inc.php");
require_once(INCLUDES_PATH.'/Database.php');
$GLOBALS['db'] = ADONewConnection("mysqli://".DB_USER.':'.urlencode(DB_PASS).'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);
$GLOBALS['PDO'] = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

if (!is_object($GLOBALS['db'])) {
    die();
}

$GLOBALS['db']->Execute("SET NAMES ".DB_CHARSET.";");

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
$theme->error_reporting = E_ALL ^ E_NOTICE;
$theme->use_sub_dirs = false;
$theme->compile_id = $theme_name;
$theme->caching = false;
$theme->template_dir = SB_THEMES . $theme_name;
$theme->compile_dir = SB_CACHE;
$theme->register_function('help_icon', 'smarty_function_help_icon');
$theme->register_function('sb_button', 'smarty_function_sb_button');

if ((isset($_GET['debug']) && $_GET['debug'] == 1) || DEBUG_MODE) {
    $theme->force_compile = true;
}
