<?php
require_once READER;

class PluginsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db      = SBConfig::getEnv('db');
    
    // Fetch plugins
    $plugins = $db->GetAssoc('SELECT   name, enabled
                              FROM     ' . SBConfig::getEnv('prefix') . '_plugins
                              ORDER BY name');
    
    list($plugins) = SBPlugins::call('OnGetPlugins', $plugins);
    
    return $plugins;
  }
}
?>