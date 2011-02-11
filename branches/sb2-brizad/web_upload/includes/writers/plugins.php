<?php
require_once READERS_DIR . 'plugins.php';

class PluginsWriter
{
  /**
   * Adds a plugin
   *
   * @param string $plugin The class name of the plugin to add
   * @noreturn
   */
  public static function add($plugin, $enabled = 0)
  {
    $phrases = SBConfig::getEnv('phrases');
    $enabled = ($enabled == 1 ? 1 : 0);
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);

    $db = SBConfig::getEnv('db');
    
    $db->Execute('INSERT INTO ' . SBConfig::getEnv('prefix') . '_plugins (name, enabled)
                  VALUES      (?, ?)',
                  array($plugin, $enabled));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnAddPlugin', $plugin);
  }
  
  /**
   * Deletes a plugin
   *
   * @param string $plugin The class name of the plugin to delete
   * @noreturn
   */
  public static function delete($plugin)
  {
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
    $db = SBConfig::getEnv('db');
    
    $db->Execute('DELETE FROM ' . SBConfig::getEnv('prefix') . '_plugins
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
   * @noreturn
   */
  public static function disable($plugin)
  {
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
    $db = SBConfig::getEnv('db');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_plugins
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
   * @noreturn
   */
  public static function enable($plugin)
  {
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($plugin) || !is_string($plugin))
      throw new Exception($phrases['invalid_plugin']);
    
    $db = SBConfig::getEnv('db');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_plugins
                  SET    enabled = 1
                  WHERE  name  = ?',
                  array($plugin));
    
    $plugins_reader = new PluginsReader();
    $plugins_reader->removeCacheFile();
    
    SBPlugins::call('OnEnablePlugin', $plugin);
  }
}
?>