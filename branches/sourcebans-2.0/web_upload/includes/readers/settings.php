<?php
require_once READER;

class SettingsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db       = Env::get('db');
    
    // Fetch settings
    $settings = $db->GetAssoc('SELECT name, value
                               FROM   ' . Env::get('prefix') . '_settings');
    
    list($settings) = SBPlugins::call('OnGetSettings', $settings);
    
    return $settings;
  }
}
?>