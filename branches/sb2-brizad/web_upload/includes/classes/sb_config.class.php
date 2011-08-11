<?php
/**
 * This file sets up the engine depending on the environment that the system is currently running on.
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * $Id$
 */

/**
 * This class sorts out where we are and sets up environment variables for different urls
 * Environmental vars can be used anywhere in the code.  For example the database object
 * to perform mysql stuff.
 */
class SBConfig
{
  const GETTYPE_ALL = 0;
  const GETTYPE_GET = 1;
  const GETTYPE_POST = 2;
  const GETTYPE_COOKIE = 3;
  const GETDATA_ANY = 0;
  const GETDATA_BOOL = 1;
  const GETDATA_INT = 2;
  const GETDATA_FLOAT = 3;
  const GETDATA_STRING = 4;
  
  private static $envdata = array();
  
  
  /**
   * Add the specified key=>value to the environmental data array
   *
   * @param string $key this is the identifier of the value you are adding
   * @param string $value The value to add into the array
   */
  public static function setEnv($key, $value)
  {
    self::$envdata[$key] = $value;
  }
  
  /**
   * Gets the current value from the data array
   *
   * @param string $key The key to lookup in the array
   * @return mixed null if the key cannot be found, or the value that was stored in the array
   */
  public static function getEnv($key)
  {
    return array_key_exists($key, self::$envdata) ? self::$envdata[$key] : null;
  }

  public static function getVar($name, $default = null, $method = self::GETTYPE_ALL, $datatype = self::GETDATA_ANY)
  {
    $value = null;

    if ((empty($method) || $method == self::GETTYPE_COOKIE) && array_key_exists($name, $_COOKIE))
      $value = $_COOKIE[$name];
    else if ((empty($method) || $method == self::GETTYPE_POST) && array_key_exists($name, $_POST))
      $value = $_POST[$name];
    else if ((empty($method) || $method == self::GETTYPE_GET) && array_key_exists($name, $_GET))                    
      $value = $_GET[$name];

    if (is_null($value) && !is_null($default))
      $value = $default;

    if (is_array($value))
      $arrayvalue = &$value;
    else
      $arrayvalue = array($value);

    foreach ($arrayvalue as $item)
      switch ($datatype)
      {
        case self::GETDATA_BOOL:
          $item = ($item ? true : false);
          break;
        case self::GETDATA_INT:
          $item = (int)$item;
          break;
        case self::GETDATA_FLOAT:
          $item = (float)$item;
          break;
        case self::GETDATA_STRING:
          $item = (string)$item;
          break;
      }

    if (!is_array($value))
      $value = $arrayvalue[0];

    return $value;
  }

  /**
   * Main init function that sets all of our defines, and calls other setup functions later on
   */
  public static function init()
  {
    self::generic_defines();
    self::generic_requires();
    self::setup_globals();
    
    SBPlugins::init();
  }
  
  
  /**
   * Define some things that will stay the same no-matter where the website is running from
   */
  private static function generic_defines()
  {
    define('BASE_PATH',     dirname(__FILE__) . '/../../');
    define('CACHE_DIR',     BASE_PATH     . 'file_cache/');
    define('DEMOS_DIR',     BASE_PATH     . 'demos/');
    define('INCLUDES_PATH', BASE_PATH     . 'includes/');
    define('LANGUAGES_DIR', BASE_PATH     . 'languages/');
    define('PLUGINS_DIR',   BASE_PATH     . 'plugins/');
    define('THEMES_DIR',    BASE_PATH     . 'themes/');
    define('UPDATER_DIR',   BASE_PATH     . 'updater/');
    define('PAGES_DIR',     BASE_PATH     . 'pages/');
    define('CLASS_DIR',     INCLUDES_PATH . 'classes/');
    define('LIB_DIR',       INCLUDES_PATH . 'libs/');
    define('READERS_DIR',   INCLUDES_PATH . 'readers/');
    define('UTILS_DIR',     INCLUDES_PATH . 'utils/');
    define('WRITERS_DIR',   INCLUDES_PATH . 'writers/');
    define('READER',        CLASS_DIR     . 'reader.class.php');
    
    define('IN_SB',         true);
    define('SB_SALT',       'SourceBans');
    define('SB_VERSION',    '2.0.0');
    define('ONE_MINUTE',    60);
    define('HALF_HOUR',     ONE_MINUTE * 30);
    define('ONE_HOUR',      ONE_MINUTE * 60);
    define('ONE_DAY',       ONE_HOUR   * 24);
    define('ONE_WEEK',      ONE_DAY    * 7);
    define('ONE_YEAR',      ONE_WEEK   * 52);
    define('URL_FORMAT',    '/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i');
    define('EMAIL_FORMAT',  '/^([a-zA-Z0-9])+([a-zA-Z0-9\._\-\+])*@([a-zA-Z0-9_\-])+([a-zA-Z0-9\._\-]+)+$/');
    define('IP_FORMAT',     '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/');
    define('STEAM_FORMAT',  '/^STEAM_[0-9]:[0-9]:[0-9]+$/');
    define('STATUS_PARSE',  '/#[ ]*([0-9 ]+) "(.+)" (STEAM_[0-9]:[0-9]:[0-9]+)[ ]{1,2}([0-9]+[:[0-9]+) ([0-9]+)[ ]([0-9]+) ([a-zA-Z]+) ([0-9.:]+)/');
    define('LOGIN_COOKIE_LIFETIME', ONE_WEEK * 2);
    
    define('STEAM_AUTH_TYPE',  'steam');	// Steam ID based authentication
    define('IP_AUTH_TYPE',     'ip');			// IP address based authentication
    define('NAME_AUTH_TYPE',   'name');		// Name based authentication
    define('STEAM_BAN_TYPE',   0);
    define('IP_BAN_TYPE',      1);
    define('SERVER_GROUPS',    'srv');
    define('WEB_GROUPS',       'web');
    define('ERROR_LOG_TYPE',   'e');
    define('INFO_LOG_TYPE',    'm');
    define('WARNING_LOG_TYPE', 'w');
    define('BAN_TYPE',         'B');
    define('PROTEST_TYPE',     'P');
    define('SUBMISSION_TYPE',  'S');
    
    // Server admin flags
    define('SM_RESERVATION', 'a');
    define('SM_GENERIC',     'b');
    define('SM_KICK',        'c');
    define('SM_BAN',         'd');
    define('SM_UNBAN',       'e');
    define('SM_SLAY',        'f');
    define('SM_CHANGEMAP',   'g');
    define('SM_CVAR',        'h');
    define('SM_CONFIG',      'i');
    define('SM_CHAT',        'j');
    define('SM_VOTE',        'k');
    define('SM_PASSWORD',    'l');
    define('SM_RCON',        'm');
    define('SM_CHEATS',      'n');
    define('SM_CUSTOM1',     'o');
    define('SM_CUSTOM2',     'p');
    define('SM_CUSTOM3',     'q');
    define('SM_CUSTOM4',     'r');
    define('SM_CUSTOM5',     's');
    define('SM_CUSTOM6',     't');
    define('SM_ROOT',        'z');
  }
  
  
  /**
   * Import some files that will be required to run the website
   */
  private static function generic_requires()
  {
    require_once LIB_DIR     . 'adodb/adodb-exceptions.inc.php';
    require_once LIB_DIR     . 'adodb/adodb.inc.php';
    require_once LIB_DIR     . 'PHPMailer/class.phpmailer.php';
    require_once LIB_DIR     . 'smarty/Smarty.class.php';
    require_once LIB_DIR     . 'utf8/utf8.php';
    require_once CLASS_DIR   . 'page.class.php';
    require_once CLASS_DIR   . 'plugins.class.php';
    require_once CLASS_DIR   . 'tabs.class.php';
    require_once CLASS_DIR   . 'sb_debug.class.php';
    require_once UTILS_DIR   . 'util.php';
    require_once WRITERS_DIR . 'logs.php';
    require_once BASE_PATH   . 'config.php';
  }
  
  
  /**
   * Create connection to the mysql, create some cache things, and insert them into environmental data array
   */
  private static function setup_globals()
  {
    // Set up global variables
    self::setEnv('active',   basename($_SERVER['PHP_SELF']));
    self::setEnv('prefix',   DB_PREFIX);
    
    // Set up database connection
    $GLOBALS['ADODB_FETCH_MODE'] = ADODB_FETCH_ASSOC;
    if (!defined('DB_TYPE'))
      define('DB_TYPE', 'mysql');

    $db = NewADOConnection(DB_TYPE . '://' . DB_USER . ':' . rawurlencode(DB_PASS) . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);
    $db->Execute('SET NAMES "UTF8"');
    self::setEnv('db', $db);
    
    // Set up caching
    require_once CLASS_DIR   . 'filecache.class.php';
    self::setEnv('sbcache', new SBFileCache(CACHE_DIR, new SBGZCompressor(1)));
    
    // Fetch settings
    require_once READERS_DIR . 'settings.php';
    $settings = new SettingsReader();
    $config   = $settings->executeCached(ONE_DAY);
    self::setEnv('config',   $config);
    
    // Set timezone
    $timezone = $config['config.timezone'] + $config['config.summertime'];
    putenv('TZ=GMT' . ($timezone >= 0 ? '+' : '') . $timezone);
    
    // Set up user manager
    require_once UTILS_DIR   . 'users/userbank.php';
    $userbank = new CUserManager();
    self::setEnv('userbank', $userbank);
    
    // Fetch translations
    require_once READERS_DIR . 'translations.php';
    $translations_reader           = new TranslationsReader();
    $translations_reader->language = $userbank->is_logged_in() ? $userbank->GetProperty('language') : $config['config.language'];
    $translations                  = $translations_reader->executeCached(ONE_DAY);
    self::setEnv('phrases',  $translations['phrases']);
    
    // Set ADODB language
    if(file_exists(LIB_DIR . 'adodb/lang/adodb-' . $translations_reader->language . '.inc.php'))
      $GLOBALS['ADODB_LANG'] = $translations_reader->language;
    
    // Fetch quotes
    require_once READERS_DIR . 'quotes.php';
    $quotes   = new QuotesReader();
    self::setEnv('quotes',   $quotes->executeCached(ONE_DAY));
  }
}
?>