<?php
require_once READER;

class PluginsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db      = Env::get('db');
    
    // Fetch plugins
    $plugins = $db->GetAssoc('SELECT   name, enabled
                              FROM     ' . Env::get('prefix') . '_plugins
                              ORDER BY name');
    
    list($plugins) = SBPlugins::call('OnGetPlugins', $plugins);
    
    return $plugins;
  }
}
?>