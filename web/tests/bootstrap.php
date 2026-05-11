<?php
/*************************************************************************
This file is part of SourceBans++

PHPUnit bootstrap. Sets up env constants the panel expects without
requiring config.php (production installer artifact).
*************************************************************************/

// Don't barf on the installer guard.
define('IN_SB',     true);
define('IS_UPDATE', true);

define('ROOT',           dirname(__DIR__) . '/');
define('SCRIPT_PATH',    ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'pages');
define('INCLUDES_PATH',  ROOT . 'includes');
define('SB_MAP_LOCATION',  'images/maps');
define('SB_DEMO_LOCATION', 'demos');
define('SB_ICON_LOCATION', 'images/games');
define('SB_MAPS',  ROOT . SB_MAP_LOCATION);
define('SB_DEMOS', ROOT . SB_DEMO_LOCATION);
define('SB_ICONS', ROOT . SB_ICON_LOCATION);
define('SB_THEMES', ROOT . 'themes/');
define('SB_CACHE',  ROOT . 'cache/');
define('MMDB_PATH', ROOT . 'data/GeoLite2-Country.mmdb');

define('DB_HOST',    getenv('DB_HOST')    ?: 'db');
define('DB_PORT',    (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME',    getenv('DB_NAME')    ?: 'sourcebans_test');
define('DB_USER',    getenv('DB_USER')    ?: 'sourcebans');
define('DB_PASS',    getenv('DB_PASS')    ?: 'sourcebans');
// Default matches docker/db-init/00-render-schema.sh, sbpp.sh, and CI.
// Database::setPrefix() turns ":prefix_admins" into "{DB_PREFIX}_admins",
// so the right value here is "sb" (no trailing underscore).
define('DB_PREFIX',  getenv('DB_PREFIX')  ?: 'sb');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('STEAMAPIKEY', '');
define('SB_NEW_SALT', 'test-salt');
// JWT signing key used by Auth::login() / Auth::verify() inside auth.login.
// Production reads this from config.php (written by the install wizard at
// web/install/pages/page.5.php, or by the Docker entrypoint when running
// against the dev stack). Tests just need a valid base64-encoded HS256 key
// so the lcobucci config object can sign + verify the issued tokens.
define('SB_SECRET_KEY', base64_encode(str_repeat('test-jwt-secret!', 4)));

// Some handlers (and Log::add) read $_SERVER. Default these so warnings
// don't fire under failOnWarning.
$_SERVER['REMOTE_ADDR']     = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
$_SERVER['HTTP_HOST']       = $_SERVER['HTTP_HOST']       ?? 'localhost';
$_SERVER['REQUEST_METHOD']  = $_SERVER['REQUEST_METHOD']  ?? 'POST';
define('SB_VERSION',  'test');
define('SB_GITREV',   0);
define('SB_THEME',    'default');
define('DEBUG_MODE',  true);
define('SB_EMAIL',    'test@example.com');
define('SB_BANS_PER_PAGE', 50);
define('MIN_PASS_LENGTH',  6);

require_once INCLUDES_PATH . '/vendor/autoload.php';
// Issue #1290 phase B: classes below live under Sbpp\… namespaces and
// are PSR-4 autoloaded; the explicit require_once chain stays so each
// file's `class_alias()` shim runs eagerly. Keeping this list in sync
// with init.php and phpstan-bootstrap.php is load-bearing — any class
// loaded here but not in init.php would only resolve under PHPUnit.
require_once INCLUDES_PATH . '/Security/Crypto.php';
require_once INCLUDES_PATH . '/Security/CSRF.php';
require_once INCLUDES_PATH . '/Auth/JWT.php';
require_once INCLUDES_PATH . '/Auth/Handler/NormalAuthHandler.php';
require_once INCLUDES_PATH . '/Auth/Handler/SteamAuthHandler.php';
require_once INCLUDES_PATH . '/Auth/Auth.php';
require_once INCLUDES_PATH . '/Auth/Host.php';
require_once INCLUDES_PATH . '/Auth/UserManager.php';
require_once INCLUDES_PATH . '/View/AdminTabs.php';
require_once INCLUDES_PATH . '/Db/Database.php';
require_once INCLUDES_PATH . '/SteamID/bootstrap.php';
require_once INCLUDES_PATH . '/Config.php';
require_once INCLUDES_PATH . '/LogType.php';
require_once INCLUDES_PATH . '/LogSearchType.php';
require_once INCLUDES_PATH . '/BanType.php';
require_once INCLUDES_PATH . '/BanRemoval.php';
require_once INCLUDES_PATH . '/WebPermission.php';
require_once INCLUDES_PATH . '/Log.php';
require_once INCLUDES_PATH . '/system-functions.php';
require_once INCLUDES_PATH . '/Api/ApiError.php';
require_once INCLUDES_PATH . '/Api/Api.php';

// Permissions constants used by handlers.
foreach (json_decode((string)file_get_contents(ROOT . 'configs/permissions/web.json'), true) ?? [] as $flag => $perm) {
    if (!defined($flag)) define($flag, $perm['value']);
}
foreach (json_decode((string)file_get_contents(ROOT . 'configs/permissions/sourcemod.json'), true) ?? [] as $flag => $perm) {
    if (!defined($flag)) define($flag, $perm['value']);
}

require_once __DIR__ . '/Fixture.php';
require_once __DIR__ . '/ApiTestCase.php';

// DB bring-up is lazy: ApiTestCase::setUp() calls Fixture::reset(),
// which calls Fixture::install() the first time it's invoked. This
// keeps `phpunit --list-tests` (test discovery in IDEs and CI) from
// requiring a live database.
