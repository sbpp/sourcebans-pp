<?php
require_once READER;

class PermissionsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db = Env::get('db');
    
    // Fetch permissions
    $permissions = $db->GetAssoc('SELECT id, name
                                  FROM   ' . Env::get('prefix') . '_permissions');
    
    SBPlugins::call('OnGetPermissions', &$permissions);
    
    return $permissions;
  }
}
?>