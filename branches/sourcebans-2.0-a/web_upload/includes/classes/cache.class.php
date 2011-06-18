<?php
abstract class SBCache
{
  private static $bad_key_characters = array('~');
  
  public static function validKey($key)
  {
    foreach(self::$bad_key_characters as $char)
    {
      if(strpos($key, $char) !== false)
      {
        throw new SteambansCachingException("The key contains bad characters and hence cannot be used with the steambans caching system");
      }
    }
    return true;
  }
  
  public abstract function add($key, $data);					// Adds data if it's not already in the cache, returns false if it's already in the cache
  public abstract function store($key, $data);				// Same as add, but will overwrite any previous value in the cache
  public abstract function fetch($key, $ttl = null);	// Gets the cache value out, or returns false if none exists or it has expired
  public abstract function delete($key);							// Removes this cache value
}

class SBNoCache extends SBCache
{
  public function add($key, $data)
  {
    return true;
  }
  public function store($key, $data)
  {
    return true;
  }
  public function fetch($key, $ttl = null)
  {
    return false;
  }
  public function delete($key)
  {
    return true;
  }
}
?>