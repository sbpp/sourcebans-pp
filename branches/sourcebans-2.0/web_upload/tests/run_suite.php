<?php
require_once dirname(__FILE__) . '/../init.php';
require_once BASE_PATH . 'tests/test_helpers.class.php';
require_once BASE_PATH . 'tests/test_suite.class.php';
$arrSuites = array();

/* ===== API suite ===== */
require_once BASE_PATH . 'api.php';
SB_API::clearCache();

$apiSuite = new CTestSuite('api');
$apiSuite->addTest('api.admins');
$apiSuite->addTest('api.bans');
$apiSuite->addTest('api.comments');
$apiSuite->addTest('api.groups');
$apiSuite->addTest('api.mods');
$apiSuite->addTest('api.protests');
$apiSuite->addTest('api.servers');
$apiSuite->addTest('api.submissions');
$apiSuite->addTest('api.translations');

$arrSuites[] = &$apiSuite;
/* ===== API suite ===== */


/* ================================ */
/* === DON'T CHANGE BELOW HERE! === */
/* ================================ */
if(!empty($argv[1]))
  $suiteName = $argv[1];

while(true)
{
  if(!isset($suiteName))
  {
    fwrite(STDOUT, 'Suite name: ');
    $suiteName = trim(fgets(STDIN));
  }
  
  foreach($arrSuites as &$suite)
  {
    if($suite->getName() == $suiteName)
      $suite->runTests();
  }
  
  fwrite(STDOUT, 'Invalid suite name specified.' . PHP_EOL . PHP_EOL);
  unset($suiteName);
}
?>