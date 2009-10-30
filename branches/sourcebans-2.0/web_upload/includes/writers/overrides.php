<?php
require_once READERS_DIR . 'overrides.php';

class OverridesWriter
{
  /**
   * Adds an override
   *
   * @param string $type  The type of the override
   * @param string $name  The name of the override
   * @param string $flags The flags of the override
   */
  public static function add($type, $name, $flags)
  {
    $db = Env::get('db');
    
    if(empty($type)  || !is_string($type))
      throw new Exception($phrases['invalid_type']);
    if(empty($name)  || !is_string($name))
      throw new Exception($phrases['invalid_name']);
    if(empty($flags) || !is_string($flags))
      throw new Exception($phrases['invalid_flags']);
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_overrides (type, name, flags)
                  VALUES      (?, ?, ?)',
                  array($type, $name, $flags));
    
    $overrides_reader = new OverridesReader();
    $overrides_reader->removeCacheFile();
    
    SBPlugins::call('OnAddOverride', $type, $name, $flags);
    
    return $id;
  }
  
  
  /**
   * Clears the overrides
   */
  public static function clear()
  {
    $db = Env::get('db');
    
    $db->Execute('TRUNCATE TABLE ' . Env::get('prefix') . '_overrides');
    
    $overrides_reader = new OverridesReader();
    $overrides_reader->removeCacheFile();
    
    SBPlugins::call('OnClearOverrides');
  }
}
?>