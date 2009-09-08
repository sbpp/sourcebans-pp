<?php
require_once READER;

class ModsReader extends SBReader
{
  public $sort = 'name';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db   = Env::get('db');
    
    // Fetch mods
    $mods = $db->GetAssoc('SELECT   id, name, folder, icon, enabled
                           FROM     ' . Env::get('prefix') . '_mods
                           ORDER BY ' . $this->sort);
    
    list($mods) = SBPlugins::call('OnGetMods', $mods);
    
    return $mods;
  }
}
?>