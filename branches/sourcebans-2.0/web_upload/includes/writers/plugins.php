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
    $phrases = Env::get('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
    $db = Env::get('db');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_plugins (name)
                  VALUES      (?)',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnAddPlugin', $plugin);
  }
  
  /**
   * Deletes a plugin
   *
   * @param string $plugin The class name of the plugin to delete
   */
  public static function delete($plugin)
  {
    $phrases = Env::get('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
    $db = Env::get('db');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_plugins
                  WHERE       name = ?',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnDeletePlugin', $plugin);
  }
  
  
  /**
   * Disables a plugin
   *
   * @param string $plugin The class name of the plugin to disable
   */
  public static function disable($plugin)
  {
    $phrases = Env::get('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
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
    $phrases = Env::get('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
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