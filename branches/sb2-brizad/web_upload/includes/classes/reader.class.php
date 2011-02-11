<?php
/**
 * This file sets up the engine depending on the environment that the system is currently running on.
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
 * This class will allow us to read data from different places, and cache them to speed up the next read.
 * 
 * Filenames do not need to be specified with readers as this is all handled by the caching system in use,
 * If two readers of the same type have identical class variables, then they are getting the same peice of data.
 */
abstract class SBReader
{
  private $prepared = false;
  abstract public function prepare();    
  
  
  /**
   * This function will check if the child class has been prepared or not
   * If its not, then it will prepare
   *
   * @return null
   */
  public function &execute()
  {
    if(!$this->prepared)
    {
      $this->prepared = true;
      $this->prepare();
    }
    return null;
  }
  
  
  /**
   * This will read the cache for any previously run queries
   * If they exist, it will return them, otherwise it will run 
   * the child execute(), cache and return the result.
   *
   * @param integer $seconds Amount of seconds before the cache is marked as expired
   * @return mixed
   */
  public function &executeCached($seconds)
  {
    if(!is_null($seconds))
    {
      $cached = $this->getCachedData($seconds);
      if(!is_null($cached))
      {
        if(!$this->prepared)
        {
          $this->prepared = true;
          $this->prepare();
        }
        $data = unserialize($cached);
        return $data;
      }
    }
    $data = $this->execute();
    //try {
    $this->writeCacheFile($data);
    //} catch (SteambansCacherException $sbce) {
    //    throw $e;
      //ignore the fact that the cache failed
    //} catch(Exception $e) {
    //  throw $e; // Its something more serious
    //}
    return $data;
  }
  
  
  /**
   * Get the data from the cache
   *
   * @param integer $age Amount of seconds before the cache is marked as expired
   * @access private
   * @return mixed, or null if cache is not found
   */
  private function getCachedData($age)
  {
    if (defined('SB_NOCACHE') && SB_NOCACHE == true)
      $age = null;

    $sbcache = SBConfig::getEnv('sbcache');
    $key     = $this->getUniqueKey();
    $data    = $sbcache->fetch($key, $age, get_class($this));
    
    return $data === false ? null : $data;
  }
  
  
  /**
   * Write the specified data to the current reader's cache
   *
   * @throws SteamBansException if the cache fails
   * @param mixed $data the data to write to the cache
   */
  public function writeCacheFile($data)
  {
    if (defined('SB_NOCACHE') && SB_NOCACHE == true)
      return;
    
    $sbcache = SBConfig::getEnv('sbcache');
    $key     = $this->getUniqueKey();
    $data    = serialize($data); // Since SBCache objects can only take strings
    $result  = $sbcache->store($key, $data, get_class($this));
    
    //if($result === false)
    //  throw new SteambansException('The cache object failed');
  }
  
  
  /**
   * Removed old cache files
   * Used for flushing the cache to get instant update
   * REMEMBER THAT THIS WILL ONLY REMOVE THE LOCAL SERVER CACHE, SO ONLY THE MIRROR YOU ARE ON WILL REMOVE ITS CACHE
   */
  public function removeCacheFile($allFromClass = false)
  {
    $sbcache = SBConfig::getEnv('sbcache');
    $key     = $this->getUniqueKey();
    
    if($allFromClass)
      $sbcache->deleteAllFromClass($key, get_class($this));
    else
      $sbcache->delete($key, get_class($this));
  }
  
  
  /**
   * Return array of class arguments and values in alphabetical order
   *
   * @return array
   */
  private function getAlphabeticalArguments()
  {
    // Gets the currently setup arguments sorted by key
    $args = get_object_vars($this);
    unset($args['prepared']);
    ksort($args);
    return $args;
  }
  
  
  /**
   * Returns the unique key value for the cacher to use for this reader
   */
  private function getUniqueKey()
  {
    return 'queries/' . get_class($this) . serialize($this->getAlphabeticalArguments());
  }
}
?>