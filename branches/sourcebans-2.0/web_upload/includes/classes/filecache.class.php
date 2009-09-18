<?php
require_once CLASS_DIR . 'cache.class.php';


interface SBCompressor
{
  public function compress($string);
  public function decompress($string);
}


class SBGZCompressor implements SBCompressor
{
  public $level;
  public function __construct($level = 3)
  {
    $this->level = $level;
  }
  public function compress($string)
  {
    return gzcompress($string, $this->level);
  }
  public function decompress($string)
  {
    return gzuncompress($string);
  }
}


/**
 * Implementation of a file cacher that can save key -> value pairs on to hard disk space as files
 * This uses the functions defined by the SBCache interface which gives us a lot of flexibility with caching without having to change much code
 */
class SBFileCache extends SBCache
{
  private $tmp_dir;
  private $dir_length;
  private $max_data_length;
  private $max_key_length;
  private $compressor;
  
  
  public function __construct($tmp_dir, $compressor = null, $dir_length = 24, $max_data_length = 5242880, $max_key_length = 10240)
  {
    $this->compressor      = $compressor;
    $this->max_data_length = $max_data_length;
    $this->max_key_length  = $max_key_length;
    
    if(file_exists($tmp_dir))
      $this->tmp_dir = $tmp_dir;
    //else
    //  throw new SteambansCachingException('Tmp caching directory doesnt exist');
    
    if($dir_length > 7 && $dir_length < 33)
      $this->dir_length = $dir_length;
    //else
    //  throw new SteambansCachingException('The length of directories for the file cacher should be between 7 and 33 characters, you can change this if you want');
  }
  
  /**
   * Although these functions may causes SteambansCachingExceptions, it could be because of file locks, so we should ignore
   * them for now
   */
  public function add($key, $data, $uniqueIdentifier = '')
  {
    //try {
      return $this->set($key, $data, false, $uniqueIdentifier);
    //} catch (SteambansCachingException $e) {
    //  return true;
    //}
  }
  
  public function store($key, $data, $uniqueIdentifier = '')
  {
    //try {
      return $this->set($key, $data, true, $uniqueIdentifier);
    //} catch (SteambansCachingException $e) {
    //  return true;
    //}
  }
  
  public function fetch($key, $ttl = null, $uniqueIdentifier = '')
  {
    //try {
      return $this->get($key, $ttl, $uniqueIdentifier);
    //} catch (SteambansCachingException $e) {
    //  return false;
    //}
  }
  
  /**
   * Will sort out setting a key value by writing a file to disk
   * @returns true if it succeeds
   * @returns false if data already exists and overwrite is false
   * @throws SteambansCachingException if something goes wrong
   */
  private function set($key, $data, $overwrite, $uniqueIdentifier)
  {
    // Invalid key
    SBCache::validKey($key);
    
    // Key too long
    //if(strlen($key) > $this->max_key_length)
    //  throw new SteambansCachingException('The key is too large to be saved by the SBFileCache');
    
    // Attempt to save this data to a file
    $key_hash = substr(md5($uniqueIdentifier), 0, 3) . substr(md5($key), 0, $this->dir_length - 3) . strlen($key);
    
    // This gives us the file to save to
    $cnt = 0;
    $file_name = $this->tmp_dir . $key_hash;
    while(@file_exists($file_name))
    {
      // Ignore dirs
      if(!is_file($file_name))
        continue;
      
      // Have we found the file
      $fkey = $this->getFileKey($file_name);
      
      if($fkey === false)
        continue;
      
      if($key == $fkey)
      {
        // Data already exists
        if($overwrite)
          break;
        else
          return false;
      }
      $cnt++;
      $file_name = $this->$tmp_dir . $key_hash . '_' . $cnt;
    }
    
    // OK to write data to $file_name
    $file_data = $data;
    
    // Compress
    if(!is_null($this->compressor) && $this->compressor instanceof SBCompressor)
      $file_data = $this->compressor->compress($file_data);
    
    // Breach of maximum data saving
    //if(strlen($file_data) > $this->max_data_length)
    //  throw new SteambansCachingException('The data is too large to be saved by the SBFileCache');
    
    // Write the file
    $fp = @fopen($file_name, 'w');
    if($fp === false)
    {
      //throw new SteambansCachingException('The file couldn\'t be opened for writing');
    }
    else
    {
      $bytes = @fwrite($fp, $key . $file_data);
      @fclose($fp);
      if($bytes === false)
      {
        //throw new SteambansCachingException('Data failed to be written to this file');
      }
      else
        return true;
    }
  }
  
  private function get($key, $ttl = null, $uniqueIdentifier)
  {
    if(is_null($ttl))
      $ttl = 0;
    
    // Invalid key
    SBCache::validKey($key);
    
    // Key too long
    //if(strlen($key) > $this->max_key_length)
    //  throw new SteambansCachingException('The key is too large to be opened by the SBFileCache');
    
    // Attempt to get this data from the file
    $key_hash = substr(md5($uniqueIdentifier), 0, 3) . substr(md5($key), 0, $this->dir_length - 3) . strlen($key);

    // This gives us the file to read from
    $cnt = 0;
    $file_name = $this->tmp_dir . $key_hash;
    //die($this->dir_length);
    while(@file_exists($file_name))
    {
      //Ignore dirs
      if(!is_file($file_name))
        continue;
      
      //Have we found the file?
      $fkey = $this->getFileKey($file_name);
      
      if($fkey === false)
        continue;
      
      if($key == $fkey)
      {
        // Data file exists
        // Check for expiry
        if($ttl > 0 && (date('U') - filemtime($file_name)) > $ttl)
          return false; // Notice that this will not remove the value from the cache
        
        $all_data = file_get_contents($file_name);
        //if($all_data === false)
        //  throw new SteambansCachingException('Couldn\'t read from cache file');
        
        $dat_data = substr($all_data, strlen($key));
        
        // Decompress
        if(!is_null($this->compressor) && $this->compressor instanceof SBCompressor)
          $dat_data = $this->compressor->decompress($dat_data);
        
        return $dat_data;
      }
      // Try another file
      $cnt++;
      $file_name = $this->$tmp_dir . $key_hash . '_' . $cnt;
    }
    
    // Didn't find any appropriate file
    return false;
  }
  
  public function delete($key, $uniqueIdentifier = '')
  {
    // Invalid key
    SBCache::validKey($key);
    
    // Key too long
    //if(strlen($key) > $this->max_key_length)
    //  throw new SteambansCachingException('The key is too large to be deleted by the SBFileCache');
    
    // Attempt to find this data file
    $key_hash = substr(md5($uniqueIdentifier), 0, 3) . substr(md5($key), 0, $this->dir_length - 3) . strlen($key);
    
    // This gives us the file to read from
    $cnt = 0;
    $file_name = $this->tmp_dir . $key_hash;
    while(@file_exists($file_name))
    {
      // Ignore dirs
      if(!is_file($file_name))
        continue;
      
      // Have we found the file?
      $fkey = $this->getFileKey($file_name);
      
      if($fkey === false)
        continue;
      
      if($key == $fkey)
      {
        // Data file exists
        // Delete the file
        @unlink($file_name);
        return; // Ignore if it fails
      }
      // Try another file
      $cnt++;
      $file_name = $this->tmp_dir . $key_hash . '_' . $cnt;
    }
    
    // Didn't find any appropriate file but it doesnt really matter
  }
  
  public function deleteAllFromClass($key, $uniqueIdentifier)
  {
    // Invalid key
    SBCache::validKey($key);
    
    // Key too long
    //if(strlen($key) > $this->max_key_length)
    //  throw new SteambansCachingException('The key is too large to be deleted by the SBFileCache');
    
    $cachefiles = dir(substr($this->tmp_dir, 0, strlen($this->tmp_dir)-1));
    while(false !== ($file_name = $cachefiles->read())) {
      // Ignore dirs
      if(!is_file($cachefiles->path . '/' . $file_name))
        continue;
      
      if(substr(md5($uniqueIdentifier), 0, 3) == substr($file_name, 0, 3))
      {
        // Data file exists
        // Delete the file
        @unlink($cachefiles->path . '/' . $file_name);
      }
    }
  }
  
  /**
   * This opens up the file and obtains the key from the header of the file
   * Returns false if it cant, or throws an exception if the file isnt a value temp file cacher file
   */
  private function getFileKey($file_name)
  {
    // First, obtain the length of the key from the file name
    $base = basename($file_name);
    
    // Since all cache files will have the format hhhhh...hhhhlll or hhhh...hhhhhlll_xxx
    // Where h is hex, l is key length, and x is a unique id if the file already exists
    $matches = array();
    if(preg_match('/[a-f0-9]{' . $this->dir_length . '}(\d+).?/', $base, $matches))
    {
      $key_length = $matches[1];
      // Open the file and get the key
      $key_data = file_get_contents($file_name, 2, null, 0, $key_length);
      //if($key_data === false)
      //  throw new SteambansCachingException('Couldnt read cache file');
      
      return $key_data;
    }
    //else
    //  throw new SteambansException('You have got the regular expression wrong for file ' . $file_name);
  }
}
?>