<?php
// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', __DIR__ . "/");
define('SCRIPT_PATH', ROOT . 'scripts');
define('TEMPLATES_PATH', ROOT . 'template');
define('INCLUDES_PATH', ROOT . 'includes');
define('IN_SB', true);
define('IN_INSTALL', true);

define('SB_VERSION', '1.8.0 | Installer');

// ---------------------------------------------------
//  Setup PHP
// ---------------------------------------------------
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create a blank config file
if (!file_exists("../config.php") && is_writable('../')) {
    $handle = fopen("../config.php", "w");
    fclose($handle);
}

$urlPath = $_SERVER['REQUEST_URI'];
if ($urlPath === '/install') {
    header('Location:  /install/');
    exit;
}