<?php
require_once READER;

class ModsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db   = SBConfig::getEnv('db');
    
    // Fetch mods
    $mods = $db->GetAssoc('SELECT   id, name, folder, icon
                           FROM     ' . SBConfig::getEnv('prefix') . '_mods
                           ORDER BY name');
    
    list($mods) = SBPlugins::call('OnGetMods', $mods);
    
    return $mods;
  }
}
?>