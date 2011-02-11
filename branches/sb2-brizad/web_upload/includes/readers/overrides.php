<?php
require_once READER;

class OverridesReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db        = SBConfig::getEnv('db');
    
    // Fetch overrides
    $overrides = $db->GetAll('SELECT type, name, flags
                              FROM   ' . SBConfig::getEnv('prefix') . '_overrides');
    
    list($overrides) = SBPlugins::call('OnGetOverrides', $overrides);
    
    return $overrides;
  }
}
?>