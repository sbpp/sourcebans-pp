<?php
/**
 * Updater class
 *
 * @author     InterWave Studios
 * @copyright  SourceBans (C)2007-2011 InterWaveStudios.com. All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Updater
 * @version    $Id$
 */
class CUpdater
{
  function __construct()
  {
    $version = $this->getCurrentVersion();
    // If current version is not set, set to 0 to run all updates
    if(empty($version))
    {
      $this->setCurrentVersion(0);
    }
  }
  
  
  public function update()
  {
    // If already fully updated, ignore
    if($this->getLatestVersion() <= $this->getCurrentVersion())
      return array();
    
    $updates = array();
    foreach($this->_data() as $version)
    {
      // If update was already applied, ignore
      if($version <= $this->getCurrentVersion())
        continue;
      
      $updates[$version] = include(ROOT . 'updater/data/' . $version . '.php');
      // If update was not executed successfully, stop updating
      if(!$updates[$version])
        break;
      
      // Otherwise, update current version number
      $this->setCurrentVersion($version);
    }
    
    return $updates;
  }
  
  public function getCurrentVersion()
  {
    if(isset($GLOBALS['config']['config.version']))
      return $GLOBALS['config']['config.version'];
    
    return null;
  }
  
  public function getLatestVersion()
  {
    static $latest = 0;
    
    if(!$latest)
    {
      foreach($this->_data() as $version)
      {
        if($version <= $latest)
          continue;
        
        $latest = $version;
      }
    }
    
    return $latest;
  }
  
  public function setCurrentVersion($version)
  {
    $ret = $GLOBALS['db']->Execute(
      'INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
       VALUES ("config.version", ?)
       ON DUPLICATE KEY UPDATE value = VALUES(value)',
       array($version)
    );
    
    return !empty($ret);
  }
  
  
  protected function _data()
  {
    static $data = array();
    
    if(empty($data))
    {
      foreach(glob(ROOT . 'updater/data/*.php') as $file)
      {
        $data[] = pathinfo($file, PATHINFO_FILENAME);
      }
    }
    
    return $data;
  }
}