<?php
require_once "tests/test_helpers.class.php";
require_once "tests/test_suite.class.php";
$arrSuites = array();


/* ===== Database Suite ===== */
$dataBaseSuite = new CTestSuite("database_tests");
$arrSuites[] = &$dataBaseSuite;

$test = include "unit_tests/database.language.php";
$dataBaseSuite->addTest($test);

/* ===== Database Suite ===== */






/* ================================ */
/* == DONT CHANGE BELOW HERE! == */
/* ================================ */
function findSuite($name)
{
	global $arrSuites; 
	foreach( $arrSuites as &$s )
	{
		if( $s->getName() == $name )
			return $s;
	}
	return -1;
}

if( $argv[1] != "" )
{
	$runSuite = findSuite($argv[1]);
	if( $runSuite != -1 )
		$runSuite->runTests();
}
else
{
	echo "useage: run_suite.php [suite name]\n";
	exit(2);
}

?>
