<?php

define('ROOT', dirname(__FILE__).'/');
define('FRAMEWORK', ROOT.'framework/');
define('CACHE', ROOT.'cache/');
define('THEMES', ROOT.'themes/');
define('TEMPLATES', ROOT.'templates/');
define('DEMOS', ROOT.'demos/');

require_once(ROOT.'config.php');

require_once(FRAMEWORK.'flight/Flight.php');

//Define all Flight functions, routes, etc here

$data = @json_decode(file_get_contents(ROOT.'version.json'), true);
Flight::set('version', (isset($data['version'])) ? $data['version'] : 'N/A');
Flight::set('git', (isset($data['git'])) ? $data['git'] : 0);
Flight::set('dev', (isset($data['dev'])) ? $data['dev'] : false);

require_once(FRAMEWORK.'AccessManager.php');

Flight::register('access', 'AccessManager', [(isset($_SESSION['aid'])) ? $_SESSION['aid'] : 1]);
Flight::register('db', 'Database', [DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET]);
Flight::register('config', 'Config', [Flight::db()]);

require_once(FRAMEWORK.'functions.php');
require_once(FRAMEWORK.'templating.php');
require_once(FRAMEWORK.'routes.php');

Flight::start();
