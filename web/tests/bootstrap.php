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
define('DB_PREFIX',  getenv('DB_PREFIX')  ?: 'sb_');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('STEAMAPIKEY', '');
define('SB_DEV',      false);
define('SB_VERSION',  'test');
define('SB_GITREV',   0);
define('SB_THEME',    'default');
define('DEBUG_MODE',  true);
define('SB_EMAIL',    'test@example.com');
define('SB_BANS_PER_PAGE', 50);
define('MIN_PASS_LENGTH',  6);

require_once INCLUDES_PATH . '/vendor/autoload.php';
require_once INCLUDES_PATH . '/security/Crypto.php';
require_once INCLUDES_PATH . '/security/CSRF.php';
require_once INCLUDES_PATH . '/auth/JWT.php';
require_once INCLUDES_PATH . '/auth/handler/NormalAuthHandler.php';
require_once INCLUDES_PATH . '/auth/handler/SteamAuthHandler.php';
require_once INCLUDES_PATH . '/auth/Auth.php';
require_once INCLUDES_PATH . '/auth/Host.php';
require_once INCLUDES_PATH . '/CUserManager.php';
require_once INCLUDES_PATH . '/AdminTabs.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/SteamID/bootstrap.php';
require_once INCLUDES_PATH . '/Config.php';
require_once INCLUDES_PATH . '/Log.php';
require_once INCLUDES_PATH . '/system-functions.php';
require_once INCLUDES_PATH . '/Api.php';
require_once INCLUDES_PATH . '/ApiError.php';

// Permissions constants used by handlers.
foreach (json_decode((string)file_get_contents(ROOT . 'configs/permissions/web.json'), true) ?? [] as $flag => $perm) {
    if (!defined($flag)) define($flag, $perm['value']);
}
foreach (json_decode((string)file_get_contents(ROOT . 'configs/permissions/sourcemod.json'), true) ?? [] as $flag => $perm) {
    if (!defined($flag)) define($flag, $perm['value']);
}

require_once __DIR__ . '/Fixture.php';
require_once __DIR__ . '/ApiTestCase.php';

// One-shot DB bring-up. Tests then truncate and re-seed per case.
\Sbpp\Tests\Fixture::install();
