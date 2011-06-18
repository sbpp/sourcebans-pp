<?php
/**
 * Test suite container that contains a set of tests
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * $Id$
 */

class CTestSuite
{
  private $name;
  private $tests = array();
  
  public function __construct($name)
  {
    $this->name = $name;
  }
  
  public function getName()
  {
    return $this->name;
  }
  
  public function addTest($file)
  {
    $test          = include 'unit_tests/' . $file . '.php';
    $this->tests[] = &$test;
  }
  
  public function runTests()
  {
    $error = null;
    $total_time = 0;
    fwrite(STDOUT, PHP_EOL . 'Starting suite: ' . $this->name . ' (' . count($this->tests) . ' tests)' . PHP_EOL);
    
    foreach($this->tests as $test)
    {
      $start = microtime(true);
      $name = $test->getName();
      fwrite(STDOUT, PHP_EOL . '- Starting test: ' . $name . PHP_EOL);
      
      try
      {
        $test->runTest();
      }
      catch(Exception $e)
      {
        $error = trim($e->getMessage());
        fwrite(STDERR, '  - Error: ' . $error . PHP_EOL);
      }
      
      $time = microtime(true) - $start;
      fwrite(STDOUT, '- Finished test: ' . $name);
      fwrite(STDOUT, ' [' . number_format($time, 3) . 's]' . PHP_EOL);
      
      $total_time += $time;
    }
    
    fwrite(STDOUT, PHP_EOL . 'Finished suite: ' . $this->name);
    fwrite(STDOUT, ' [' . number_format($total_time, 3) . 's]' . PHP_EOL);
    exit(empty($error) ? 0 : 1);
  }
}
?>