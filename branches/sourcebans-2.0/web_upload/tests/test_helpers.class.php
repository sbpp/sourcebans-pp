<?php
/**
 * This file contains the main framework classes to get the unit-testing on the go
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
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