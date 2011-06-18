<?php
/**
 * Bootstrap
 *
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
error_reporting(E_ALL);


// Defines
define('START_TIME',    microtime()); // DEBUG
define('IN_SITE',       true);
define('SITE_DIR',      dirname(__FILE__) . '/');
define('INCLUDES_DIR',  SITE_DIR          . 'includes/');
define('LIBRARIES_DIR', INCLUDES_DIR      . 'libraries/');


// Includes
require_once INCLUDES_DIR . 'AutoLoader.php';
Includes::requireOnce(SITE_DIR . 'config.php');


// AutoLoader
AutoLoader::add(INCLUDES_DIR);
AutoLoader::add(INCLUDES_DIR . 'controllers/');
AutoLoader::add(INCLUDES_DIR . 'models/');


// Registry
$registry                = Registry::getInstance();
$registry->db_prefix     = DB_PREFIX;
$registry->includes_dir  = INCLUDES_DIR;
$registry->libraries_dir = LIBRARIES_DIR;
$registry->site_dir      = SITE_DIR;

$registry->sb_salt    = 'SourceBans';
$registry->sb_version = '2.0.0';

$registry->one_minute = 60;
$registry->half_hour  = $registry->one_minute * 30;
$registry->one_hour   = $registry->one_minute * 60;
$registry->one_day    = $registry->one_hour   * 24;
$registry->one_week   = $registry->one_day    * 7;
$registry->one_year   = $registry->one_week   * 52;
$registry->session_lifetime = $registry->one_week * 2;

$registry->email_format = '/^([a-zA-Z0-9])+([a-zA-Z0-9\._\-\+])*@([a-zA-Z0-9_\-])+([a-zA-Z0-9\._\-]+)+$/';
$registry->ip_format    = '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';
$registry->steam_format = '/^STEAM_[0-9]:[0-9]:[0-9]+$/';
$registry->url_format   = '/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i';
$registry->status_parse = '/#[ ]*([0-9 ]+) "(.+)" (STEAM_[0-9]:[0-9]:[0-9]+)[ ]{1,2}([0-9]+[:[0-9]+) ([0-9]+)[ ]([0-9]+) ([a-zA-Z]+) ([0-9.:]+)/';

$registry->steam_auth_type   = 'steam';	// Steam ID based authentication
$registry->ip_auth_type      = 'ip';			// IP address based authentication
$registry->name_auth_type    = 'name';		// Name based authentication
$registry->steam_ban_type    = 0;
$registry->ip_ban_type       = 1;
$registry->server_group_type = 'srv';
$registry->web_group_type    = 'web';
$registry->error_log_type    = 'e';
$registry->info_log_type     = 'm';
$registry->warning_log_type  = 'w';
$registry->ban_type          = 'B';
$registry->protest_type      = 'P';
$registry->submission_type   = 'S';

$registry->sm_reservation = 'a';
$registry->sm_generic     = 'b';
$registry->sm_kick        = 'c';
$registry->sm_ban         = 'd';
$registry->sm_unban       = 'e';
$registry->sm_slay        = 'f';
$registry->sm_changemap   = 'g';
$registry->sm_cbar        = 'h';
$registry->sm_config      = 'i';
$registry->sm_chat        = 'j';
$registry->sm_vote        = 'k';
$registry->sm_password    = 'l';
$registry->sm_rcon        = 'm';
$registry->sm_cheats      = 'n';
$registry->sm_custom1     = 'o';
$registry->sm_custom2     = 'p';
$registry->sm_custom3     = 'q';
$registry->sm_custom4     = 'r';
$registry->sm_custom5     = 's';
$registry->sm_custom6     = 't';
$registry->sm_root        = 'z';


// URI
$registry->uri = Uri::parse();


// Router
$registry->router = new Router();


// Database
$registry->database = new AdoDatabase(DB_USER, DB_PASS, DB_NAME, DB_HOST . ':' . DB_PORT, DB_TYPE);


// Cache
$registry->cache = new FileCache(new GzCompressor());


// Models
$registry->actions       = new SBActions();
$registry->admins        = new SBAdmins();
$registry->bans          = new SBBans();
$registry->blocks        = new SBBlocks();
$registry->countries     = new SBCountries();
$registry->games         = new SBGames();
$registry->languages     = new Languages();
$registry->permissions   = new SBPermissions();
$registry->plugins       = new SBPlugins();
$registry->protests      = new SBProtests();
$registry->quotes        = new SBQuotes();
$registry->server_groups = new SBServerGroups();
$registry->servers       = new SBServers();
$registry->settings      = new Settings();
$registry->submissions   = new SBSubmissions();
$registry->themes        = new Themes();
$registry->users         = new Users();
$registry->web_groups    = new SBWebGroups();


// User
$registry->user = new SBUser(new Session());


// Template
$registry->template = new SBTemplate($registry->user->theme);


// Timezone
$timezone = $registry->settings->timezone + $registry->settings->summer_time;
putenv('TZ=GMT' . ($timezone >= 0 ? '+' : '') . $timezone);