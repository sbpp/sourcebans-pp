<?php
require_once READERS_DIR . 'plugins.php';

class PluginsWriter
{
  /**
   * Adds a plugin
   *
   * @param string $plugin The class name of the plugin to add
   */
  public static function add($plugin)
  {
    if(empty($plugin) || !is_string($plugin))
      throw new Exception('Invalid plugin name supplied.');
    
    $db = Env::get('db');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_plugins (name)
                  VALUES      (?)',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnAddPlugin', $plugin);
  }
  
  
  /**
   * Disables a plugin
   *
   * @param string $plugin The class name of the plugin to disable
   */
  public static function disable($plugin)
  {
    if(empty($plugin) || !is_string($plugin))
      throw new Exception('Invalid plugin name supplied.');
    
    $db = Env::get('db');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_plugins
                  SET    enabled = 0
                  WHERE  name  = ?',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnDisablePlugin', $plugin);
  }
  
  
  /**
   * Enables a plugin
   *
   * @param string $plugin The class name of the plugin to enable
   */
  public static function enable($plugin)
  {
    if(empty($plugin) || !is_string($plugin))
      throw new Exception('Invalid plugin name supplied.');
    
    $db = Env::get('db');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_plugins
                  SET    enabled = 1
                  WHERE  name  = ?',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnEnablePlugin', $plugin);
  }
}
?>