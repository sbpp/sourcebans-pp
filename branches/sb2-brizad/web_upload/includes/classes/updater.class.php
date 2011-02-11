<?php
/**
 * =============================================================================
 * Updater Class
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * $Id$
 * =============================================================================
 */

class SBUpdater
{
  function __construct()
  {
    if(self::getCurrentVersion() == -1)
      self::setCurrentVersion(0); // Set at 0 initially, this will cause all database updates to be run
  }
  
  function doUpdates()
  {
    $updates = array();
    
    foreach(self::getStore() as $version)
    {
      if($version <= self::getCurrentVersion())
        continue;
      
      $updates[$version] = !include(UPDATER_DIR . 'data/' . $version . '.php');
      // File was executed successfully
      if($updates[$version])
        self::setCurrentVersion($version);
      // OHSHI! Something went tits up :(
      else
        break;
    }
    
    return $updates;
  }
  
  function getCurrentVersion()
  {
    $config  = SBConfig::getEnv('config');
    $version = $config['config.version'];
    
    return isset($version) && is_numeric($version) ? $version : -1;
  }
  
  function getLatestVersion()
  {
    static $latest = 0;
    
    if(!$latest)
    {
      foreach(self::getStore() as $version)
      {
        if($version > $latest)
          $latest = $version;
      }
    }
    
    return $latest;
  }
  
  function getStore()
  {
    static $store = array();
    
    if(empty($store))
    {
      foreach(glob(UPDATER_DIR . 'data/*.php') as $file)
        self::$store[] = pathinfo($file, PATHINFO_FILENAME);
    }
    
    return $store;
  }
  
  function needsUpdate()
  {
    return self::getLatestVersion() > self::getCurrentVersion();
  }
  
  function setCurrentVersion($version)
  {
    require_once BASE_PATH . 'api.php';
    
    SB_API::updateSettings(array(
      'config.version' => $version
    ));
  }
}
?>