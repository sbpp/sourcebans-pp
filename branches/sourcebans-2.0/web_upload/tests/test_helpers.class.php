<?php
/**
 * This file contains the main framework classes to get the unit-testing on the go
 * 
 * @author $LastChangedBy$
 * @version $LastChangedRevision$
 * @copyright http://www.InterwaveStudios.com
 * @package SourceBans
 * $Id$
 */

/**
 * This class is the main interface that every test needs to
 * implement!
 */
interface ITest
{
  public function runTest();
}

class CTest
{
  private $testName;
  
  public function __construct($name)
  {
    $this->testName = $name;
  }
  
  public function getName()
  {
    return $this->testName;
  }
}
?>