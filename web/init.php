<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

	You should have received a copy of the license along with this
	work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s):
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under CC BY-NC-SA 3.0
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
define('SB_DEMO_LOCATION', 'demos');
define('SB_ICON_LOCATION', 'images/games');
define('SB_MAP_LOCATION', ROOT . 'images/maps');
define('SB_ICONS', ROOT . SB_ICON_LOCATION);
define('SB_DEMOS', ROOT . SB_DEMO_LOCATION);

define('SB_THEMES', ROOT . 'themes/');
define('SB_THEMES_COMPILE', ROOT . 'themes_c/');

define('IN_SB', true);
define('SB_AID', isset($_COOKIE['aid'])?$_COOKIE['aid']:null);
define('XAJAX_REQUEST_URI', './index.php');

require_once(INCLUDES_PATH.'/SessionManager.php');
include_once(INCLUDES_PATH . "/CSystemLog.php");
include_once(INCLUDES_PATH . "/CUserManager.php");
include_once(INCLUDES_PATH . "/CUI.php");

\SessionManager::sessionStart('SourceBans');

// ---------------------------------------------------
//  Fix some $_SERVER vars
// ---------------------------------------------------
// Fix for IIS, which doesn't set REQUEST_URI
if (!isset($_SERVER['REQUEST_URI']) || trim($_SERVER['REQUEST_URI']) == '') {
    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
    }
}
// Fix for Dreamhost and other PHP as CGI hosts
if (strstr($_SERVER['SCRIPT_NAME'], 'php.cgi')) {
    unset($_SERVER['PATH_INFO']);
}

if (trim($_SERVER['PHP_SELF']) == '') {
    $_SERVER['PHP_SELF'] = preg_replace("/(\?.*)?$/", '', $_SERVER["REQUEST_URI"]);
}

// ---------------------------------------------------
//  Are we installed?
// ---------------------------------------------------
if (!file_exists(ROOT.'/config.php') || !include_once(ROOT . '/config.php')) {
    // No were not
    if ($_SERVER['HTTP_HOST'] != "localhost") {
        echo "SourceBans is not installed.";
        die();
    }
}
if (!defined("DEVELOPER_MODE") && !defined("IS_UPDATE") && file_exists(ROOT."/install")) {
    if ($_SERVER['HTTP_HOST'] != "localhost") {
        echo "Please delete the install directory before you use SourceBans";
        die();
    }
}

if (!defined("DEVELOPER_MODE") && !defined("IS_UPDATE") && file_exists(ROOT."/updater")) {
    if ($_SERVER['HTTP_HOST'] != "localhost") {
        echo "Please delete the updater directory before using SourceBans";
        die();
    }
}

// ---------------------------------------------------
//  Initial setup
// ---------------------------------------------------
define('SB_GIT', true);
if (!defined('SB_VERSION')) {
    if (file_exists('version.json')) {
        $json = json_decode(file_get_contents('version.json'), true);
        define('SB_VERSION', $json['version']);
        define('SB_GITREV', $json['git']);
        define('SB_DEV', $json['dev']);
    } else {
        define('SB_VERSION', 'N/A');
        define('SB_GITREV', '0');
        define('SB_DEV', false);
    }
}
define('LOGIN_COOKIE_LIFETIME', (60*60*24*7)*2);
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', false);
define('SB_SALT', 'SourceBans');

// ---------------------------------------------------
//  Setup PHP
// ---------------------------------------------------
ini_set('include_path', '.:/php/includes:' . INCLUDES_PATH .'/adodb');

if (defined("SB_MEM")) {
    ini_set('memory_limit', SB_MEM);
}

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);


// ---------------------------------------------------
//  Setup our DB
// ---------------------------------------------------
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");
include_once(INCLUDES_PATH . "/adodb/adodb-errorhandler.inc.php");
require_once(INCLUDES_PATH.'/Database.php');
$GLOBALS['db'] = ADONewConnection("mysqli://".DB_USER.':'.urlencode(DB_PASS).'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);
$GLOBALS['PDO'] = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX);
$GLOBALS['log'] = new CSystemLog();
$GLOBALS['sb-email'] = SB_EMAIL;

if (!is_object($GLOBALS['db'])) {
    die();
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8');
}
$GLOBALS['db']->Execute("SET NAMES ".DB_CHARSET.";");
$GLOBALS['PDO']->query("SET NAMES :charset");
$GLOBALS['PDO']->bind(':charset', DB_CHARSET);
$GLOBALS['PDO']->execute();

$mysql_server_info = $GLOBALS['db']->ServerInfo();
$GLOBALS['db_version'] = $mysql_server_info['version'];

$debug = $GLOBALS['db']->Execute("SELECT value FROM `".DB_PREFIX."_settings` WHERE setting = 'config.debug';");
if ($debug->fields['value']=="1") {
    define("DEVELOPER_MODE", true);
}

// ---------------------------------------------------
//  Setup our custom error handler
// ---------------------------------------------------
set_error_handler('sbError');
function sbError($errno, $errstr, $errfile, $errline)
{
    if (!is_object($GLOBALS['log'])) {
        return false;
    }
    switch ($errno) {
        case E_USER_ERROR:
            $msg = "[$errno] $errstr<br />\n";
            $msg .= "Fatal error on line $errline in file $errfile";
            $log = new CSystemLog("e", "PHP Error", $msg);
            exit(1);
            break;

        case E_USER_WARNING:
            $msg = "[$errno] $errstr<br />\n";
            $msg .= "Error on line $errline in file $errfile";
            $GLOBALS['log']->AddLogItem("w", "PHP Warning", $msg);
            break;

        case E_USER_NOTICE:
            $msg = "[$errno] $errstr<br />\n";
            $msg .= "Notice on line $errline in file $errfile";
            $GLOBALS['log']->AddLogItem("m", "PHP Notice", $msg);
            break;

        default:
            return false;
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}
// ---------------------------------------------------
//  Some defs
// ---------------------------------------------------
define('EMAIL_FORMAT', "/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/");
define('URL_FORMAT', "/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i");
define('STEAM_FORMAT', "/^STEAM_[0-9]:[0-9]:[0-9]+$/");
define('STATUS_PARSE', '/#.* +([0-9]+) +"(.+)" +(STEAM_[0-9]:[0-9]:[0-9]+|\[U:[0-9]:[0-9]+\]) +([0-9:]+) +([0-9]+) +([0-9]+) +([a-zA-Z]+).* +([0-9.:]+)/');
define('IP_FORMAT', '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/');
define('SERVER_QUERY', 'http://www.sourcebans.net/public/query/');

// Web admin-flags
define('ADMIN_LIST_ADMINS', (1<<0));
define('ADMIN_ADD_ADMINS', (1<<1));
define('ADMIN_EDIT_ADMINS', (1<<2));
define('ADMIN_DELETE_ADMINS', (1<<3));

define('ADMIN_LIST_SERVERS', (1<<4));
define('ADMIN_ADD_SERVER', (1<<5));
define('ADMIN_EDIT_SERVERS', (1<<6));
define('ADMIN_DELETE_SERVERS', (1<<7));

define('ADMIN_ADD_BAN', (1<<8));
define('ADMIN_EDIT_OWN_BANS', (1<<10));
define('ADMIN_EDIT_GROUP_BANS', (1<<11));
define('ADMIN_EDIT_ALL_BANS', (1<<12));
define('ADMIN_BAN_PROTESTS', (1<<13));
define('ADMIN_BAN_SUBMISSIONS', (1<<14));
define('ADMIN_DELETE_BAN', (1<<25));
define('ADMIN_UNBAN', (1<<26));
define('ADMIN_BAN_IMPORT', (1<<27));
define('ADMIN_UNBAN_OWN_BANS', (1<<30));
define('ADMIN_UNBAN_GROUP_BANS', (1<<31));

define('ADMIN_LIST_GROUPS', (1<<15));
define('ADMIN_ADD_GROUP', (1<<16));
define('ADMIN_EDIT_GROUPS', (1<<17));
define('ADMIN_DELETE_GROUPS', (1<<18));

define('ADMIN_WEB_SETTINGS', (1<<19));

define('ADMIN_LIST_MODS', (1<<20));
define('ADMIN_ADD_MODS', (1<<21));
define('ADMIN_EDIT_MODS', (1<<22));
define('ADMIN_DELETE_MODS', (1<<23));

define('ADMIN_NOTIFY_SUB', (1<<28));
define('ADMIN_NOTIFY_PROTEST', (1<<29));

define('ADMIN_OWNER', (1<<24));

// Server admin-flags
define('SM_RESERVED_SLOT', "a");
define('SM_GENERIC', "b");
define('SM_KICK', "c");
define('SM_BAN', "d");
define('SM_UNBAN', "e");
define('SM_SLAY', "f");
define('SM_MAP', "g");
define('SM_CVAR', "h");
define('SM_CONFIG', "i");
define('SM_CHAT', "j");
define('SM_VOTE', "k");
define('SM_PASSWORD', "l");
define('SM_RCON', "m");
define('SM_CHEATS', "n");
define('SM_ROOT', "z");

define('SM_CUSTOM1', "o");
define('SM_CUSTOM2', "p");
define('SM_CUSTOM3', "q");
define('SM_CUSTOM4', "r");
define('SM_CUSTOM5', "s");
define('SM_CUSTOM6', "t");


define('ALL_WEB', ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|
                  ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|
                  ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|
                  ADMIN_DELETE_GROUPS|ADMIN_WEB_SETTINGS|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS|ADMIN_OWNER|
                  ADMIN_DELETE_BAN|ADMIN_UNBAN|ADMIN_BAN_IMPORT|ADMIN_UNBAN_OWN_BANS|ADMIN_UNBAN_GROUP_BANS|ADMIN_NOTIFY_SUB|ADMIN_NOTIFY_PROTEST);

define('ALL_SERVER', SM_RESERVED_SLOT.SM_GENERIC.SM_KICK.SM_BAN.SM_UNBAN.SM_SLAY.SM_MAP.SM_CVAR.SM_CONFIG.SM_VOTE.SM_PASSWORD.SM_RCON.
                     SM_CHEATS.SM_CUSTOM1.SM_CUSTOM2.SM_CUSTOM3. SM_CUSTOM4.SM_CUSTOM5.SM_CUSTOM6.SM_ROOT);

$res = $GLOBALS['db']->Execute("SELECT * FROM ".DB_PREFIX."_settings GROUP BY `setting`, `value`");
$GLOBALS['config'] = array();
while (!$res->EOF) {
    $setting = array($res->fields['setting'] => $res->fields['value']);
    $GLOBALS['config'] = array_merge_recursive($GLOBALS['config'], $setting);
    $res->MoveNext();
}

define('SB_BANS_PER_PAGE', $GLOBALS['config']['banlist.bansperpage']);
define('MIN_PASS_LENGTH', $GLOBALS['config']['config.password.minlength']);
$dateformat = !empty($GLOBALS['config']['config.dateformat'])?$GLOBALS['config']['config.dateformat']:"m-d-y H:i";

// ---------------------------------------------------
// Setup our templater
// ---------------------------------------------------
require(INCLUDES_PATH . '/smarty/Smarty.class.php');

global $theme, $userbank;

$theme_name = isset($GLOBALS['config']['config.theme'])?$GLOBALS['config']['config.theme']:'default';
if (defined("IS_UPDATE")) {
    $theme_name = "default";
}
define('SB_THEME', $theme_name);

if (!@file_exists(SB_THEMES . $theme_name . "/theme.conf.php")) {
    die("Theme Error: <b>".$theme_name."</b> is not a valid theme. Must have a valid <b>theme.conf.php</b> file.");
}
if (!@is_writable(SB_THEMES_COMPILE)) {
    die("Theme Error: <b>".SB_THEMES_COMPILE."</b> MUST be writable.");
}

$theme = new Smarty();
$theme->error_reporting = E_ALL ^ E_NOTICE;
$theme->use_sub_dirs = false;
$theme->compile_id = $theme_name;
$theme->caching = false;
$theme->template_dir = SB_THEMES . $theme_name;
$theme->compile_dir = SB_THEMES_COMPILE;

if ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined("DEVELOPER_MODE")) {
    $theme->force_compile = true;
}
// ---------------------------------------------------
// Setup our user manager
// ---------------------------------------------------
$userbank = new CUserManager(isset($_SESSION['aid']) ? $_SESSION['aid'] : -1);
