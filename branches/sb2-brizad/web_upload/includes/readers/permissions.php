<?php
require_once READER;

class PermissionsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db = SBConfig::getEnv('db');
    
    // Fetch permissions
    $permissions = $db->GetAssoc('SELECT   id, name
                                  FROM     ' . SBConfig::getEnv('prefix') . '_permissions
                                  ORDER BY id');
    
    list($permissions) = SBPlugins::call('OnGetPermissions', $permissions);
    
    return $permissions;
  }
}
?>