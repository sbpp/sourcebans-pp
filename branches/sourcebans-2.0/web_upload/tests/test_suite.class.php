<?php
/**
 * Test suite container that contains a set of tests
 * 
 * @author $LastChangedBy$
 * @version $LastChangedRevision$
 * @copyright http://www.InterwaveStudios.com
 * @package SourceBans
 * $Id$
 */
 
class CTestSuite
{
	private $m_pTests = array();
	private $m_szSuiteName = "";
	private $m_error = 0;
	
	public function __construct( $name )
	{
		$this->m_szSuiteName = $name;
	}
	
	public function getName()
	{
		return $this->m_szSuiteName;
	}
	
	public function addTest(&$pTest)
	{
		$this->m_pTests[] = $pTest;
	}
	
	public function runTests()
	{
		echo "=== Starting Suite: " . $this->m_szSuiteName . " - " . count($this->m_pTests) . " tests  ===\n";
		foreach( $this->m_pTests as $test)
		{
			$startTime = microtime(true);
			$testName = $test->GetName();
			echo "-- Starting test: " . $testName . " --\n";
			$result = $test->RunTest();
			$endTime = microtime(true);
			
			$resStr = "Success";
			if( !$result )
			{
				$this->m_error++;
				$resStr = "Failed";
			}
			
			echo "-- Finished test: " . $testName;
			echo " [" . $resStr . " - ";
			
			echo  number_format($endTime - $startTime,3) . "ms] --\n";
		}
		echo "=== Finished Suite: " . $this->m_szSuiteName . " ===\n";
		
		if($this->m_error > 0)
			exit(1); // exit code 1 - causes fail
		else
			exit(0); // exit code 0 - causes success
	}
}
?>
