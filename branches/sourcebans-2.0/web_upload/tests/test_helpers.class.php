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
 * impliment!
 */
 interface ITest
 {	
	public function RunTest();
 }
 
 class CTest
 {
	public $testName = "";
	
	public function __construct($name)
	{
		$this->testName;
	}
	
	public function GetName()
	{
		return $this->testName;
	}
 }
 
 ?>
 