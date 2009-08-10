<?php
require_once READERS_DIR . 'settings.php';

class SettingsWriter
{
  /**
   * Updates one or more settings
   *
   * @param array $settings The list of settings to update
   */
  public static function update($settings = array())
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->hasAccess(array('ADMIN_OWNER', 'ADMIN_SETTINGS')))
      throw new Exception('Access Denied');
    
    $db->Execute('REPLACE INTO ' . Env::get('prefix') . '_settings (name, value)
                  VALUES       ("' . implode('", ?), ("', array_keys($settings)) . '", ?)',
                  array_values($settings));
    
    $settings_reader = new SettingsReader();
    $settings_reader->removeCacheFile();
    
    SBPlugins::call('OnUpdateSettings', $settings);
  }
}
?>