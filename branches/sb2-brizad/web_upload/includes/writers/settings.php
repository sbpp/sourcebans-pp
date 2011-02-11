<?php
require_once READERS_DIR . 'settings.php';

class SettingsWriter
{
  /**
   * Updates one or more settings
   *
   * @param array $settings The list of settings to update
   * @noreturn
   */
  public static function update($settings = array())
  {
    $db = SBConfig::getEnv('db');
    
    $db->Execute('REPLACE INTO ' . SBConfig::getEnv('prefix') . '_settings (name, value)
                  VALUES       ("' . implode('", ?), ("', array_keys($settings)) . '", ?)',
                  array_values($settings));
    
    $settings_reader = new SettingsReader();
    $settings_reader->removeCacheFile();
    
    SBPlugins::call('OnUpdateSettings', $settings);
  }
}
?>