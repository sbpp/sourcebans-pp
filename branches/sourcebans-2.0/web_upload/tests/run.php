<?php
/**
 * Runs test suite
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
error_reporting(E_ALL);


/**
 * Defines
 */
define('TESTS_DIR', dirname(__FILE__) . '/');


/**
 * Includes
 */
require_once TESTS_DIR . '../bootstrap.php';
AutoLoader::add(TESTS_DIR . 'includes/');


/**
 * Add suites
 */
$suites = array();

// PHP MVC suite
$suite = new TestSuite('php-mvc');
$suite->add('controllers');
$suite->add('languages');

$suites[] = $suite;

// SourceBans suite
require_once BASE_PATH . 'api.php';
SB_API::clearCache();

$suite = new TestSuite('sourcebans');
$suite->add('admins');
$suite->add('bans');
$suite->add('comments');
$suite->add('games');
$suite->add('groups');
$suite->add('protests');
$suite->add('servers');
$suite->add('submissions');

$suites[] = $suite;


/**
 * Run suite
 */
if(!empty($argv[1]))
{
  $suiteName = $argv[1];
}

while(true)
{
  if(!isset($suiteName))
  {
    fwrite(STDOUT, 'Suite name: ');
    $suiteName = trim(fgets(STDIN));
  }
  
  foreach($suites as $suite)
  {
    if($suite != $suiteName)
      continue;
    
    $suite->run();
  }
  
  fwrite(STDOUT, 'Invalid suite name specified.' . PHP_EOL . PHP_EOL);
  unset($suiteName);
}