<?php
require_once READER;

class SettingsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db       = SBConfig::getEnv('db');
    
    // Fetch settings
    $settings = $db->GetAssoc('SELECT   name, value
                               FROM     ' . SBConfig::getEnv('prefix') . '_settings
                               ORDER BY name');
    
    list($settings) = SBPlugins::call('OnGetSettings', $settings);
    
    return $settings;
  }
}
?>