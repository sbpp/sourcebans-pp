<?php
require_once READERS_DIR . 'groups.php';

class GroupsWriter
{
  /**
   * Adds a group
   *
   * @param  string  $type     The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param  string  $name     The name of the group
   * @param  mixed   $flags    The access flags of the group
   * @param  integer $immunity The immunity level of the group
   * @param  array   $overrides The overrides of the group
   * @return The id of the added group
   */
  public static function add($type, $name, $flags = '', $immunity = 0, $overrides = array())
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS')))
      throw new Exception('Access Denied.');
    if(empty($name) || !is_string($name))
      throw new Exception('Invalid group name supplied.');
    if(!is_numeric($immunity))
      throw new Exception('Invalid group immunity supplied.');
    
    switch($type)
    {
      case SERVER_GROUPS:
        $db->Execute('INSERT INTO ' . Env::get('prefix') . '_srvgroups (name, flags, immunity)
                      VALUES      (?, ?, ?)',
                      array($name, $flags, $immunity));
        $id = $db->Insert_ID();
        
        if(is_array($overrides) && !empty($overrides))
        {
          $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_srvgroups_overrides (groupd_id, type, name, access)
                                 VALUES      (?, ?, ?, ?)');
          
          foreach($overrides as $override)
            $db->Execute($query, array($id, $override['type'], $override['name'], $override['access']));
        }
        
        break;
      case WEB_GROUPS:
        $db->Execute('INSERT INTO ' . Env::get('prefix') . '_groups (name)
                      VALUES      (?)',
                      array($name));
        $id = $db->Insert_ID();
        
        if(is_array($flags) && !empty($flags))
          self::setFlags($id, $flags);
        
        break;
      default:
        throw new Exception('Invalid group type specified.');
    }
    
    $groups_reader = new GroupsReader();
    $groups_reader->removeCacheFile();
    
    SBPlugins::call('OnAddGroup', $id, $type, $name, $flags, $immunity, $overrides);
    
    return $id;
  }
  
  
  /**
   * Deletes a group
   *
   * @param integer $id   The id of the group to delete
   * @param string  $type The type of the group to delete (SERVER_GROUPS, WEB_GROUPS)
   */
  public static function delete($id, $type)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_GROUPS')))
      throw new Exception('Access Denied.');
    
    switch($type)
    {
      case SERVER_GROUPS:
        $db->Execute('DELETE sg, ag
                      FROM   ' . Env::get('prefix') . '_srvgroups        AS sg,
                             ' . Env::get('prefix') . '_admins_srvgroups AS ag
                      WHERE  sg.id = ag.group_id
                        AND  sg.id = ?',
                      array($id));
        break;
      case WEB_GROUPS:
        $db->Execute('UPDATE ' . Env::get('prefix') . '_admins
                      SET    group_id = NULL
                      WHERE  group_id = ?',
                      array($id));
        $db->Execute('DELETE wg, gp
                      FROM   ' . Env::get('prefix') . '_groups             AS wg,
                             ' . Env::get('prefix') . '_groups_permissions AS gp
                      WHERE  wg.id = gp.group_id
                        AND  wg.id = ?',
                      array($id));
        break;
      default:
        throw new Exception('Invalid group type specified.');
    }
    
    $groups_reader = new GroupsReader();
    $groups_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteGroup', $id, $type);
  }
  
  
  /**
   * Edits a group
   *
   * @param integer $id        The id of the group to edit
   * @param string  $type      The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param string  $name      The name of the group
   * @param mixed   $flags     The access flags of the group
   * @param integer $immunity  The immunity level of the group
   * @param array   $overrides The overrides of the group
   */
  public static function edit($id, $type, $name, $flags, $immunity, $overrides)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUPS')))
      throw new Exception('Access Denied.');
    
    switch($type)
    {
      case SERVER_GROUPS:
        $db->Execute('UPDATE ' . Env::get('prefix') . '_srvgroups
                      SET    name     = ?,
                             flags    = ?,
                             immunity = ?
                      WHERE  id       = ?',
                      array($name, $flags, $immunity, $id));
        
        if(!is_null($overrides) && is_array($overrides))
        {
          $db->Execute('DELETE FROM ' . Env::get('prefix') . '_srvgroups_overrides
                        WHERE       group_id = ?',
                        array($id));
          
          $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_srvgroups_overrides (groupd_id, type, name, access)
                                 VALUES      (?, ?, ?, ?)');
          
          foreach($overrides as $override)
            $db->Execute($query, array($id, $override['type'], $override['name'], $override['access']));
        }
        
        break;
      case WEB_GROUPS:
        $db->Execute('UPDATE ' . Env::get('prefix') . '_groups
                      SET    name  = ?
                      WHERE  id    = ?',
                      array($name, $id));
        
        if(!is_null($flags) && is_array($flags))
        {
          $db->Execute('DELETE FROM ' . Env::get('prefix') . '_groups_permissions
                        WHERE       group_id = ?',
                        array($id));
          
          self::setFlags($id, $flags);
        }
        
        break;
      default:
        throw new Exception('Invalid group type specified.');
    }
    
    $groups_reader = new GroupsReader();
    $groups_reader->removeCacheFile();
    
    SBPlugins::call('OnEditGroup', $id, $type, $name, $flags, $immunity, $overrides);
  }
  
  
  /**
   * Imports one or more groups
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function import($file, $tmp_name = '')
  {
    require_once UTILS_DIR . 'keyvalues/kvutil.php';
    
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_GROUPS')))
      throw new Exception('Access Denied.');
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception('File does not exist.');
    
    $reader   = new KVReader($tmp_name);
    switch(basename($file))
    {
      // SourceMod
      case 'admin_groups.cfg':
        foreach($reader->Values['Groups'] as $name => $group)
          self::add(SERVER_GROUPS,
                    $name,
                    isset($group['flags'])    ? $group['flags']    : '',
                    isset($group['immunity']) ? $group['immunity'] : 0);
        
        break;
      // Mani Admin Plugin
      case 'clients.txt':
        foreach($reader->Values['clients.txt']['groups'] as $name => $group)
          self::add(SERVER_GROUPS, $name);
        
        break;
      default:
        throw new Exception('Unsupported file format.');
    }
  }
  
  
  /**
   * Sets a web group's flags
   *
   * @param integer $id    The id of the web group to set the flags for
   * @param mixed   $flags The flags for the group
   */
  private static function setFlags($id, $flags)
  {
    require_once READERS_DIR . 'permissions.php';
    
    $db                 = Env::get('db');
    $query              = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_groups_permissions (groupd_id, permission_id)
                                        VALUES      (?, ?)');
    
    $permissions_reader = new PermissionsReader();
    $permissions        = $permissions_reader->executeCached(ONE_MINUTE * 5);
    
    foreach($permissions as $permission_id => $permission_name)
      if(in_array($permission_name, $flags))
        $db->Execute($query, array($id, $permission_id));
  }
}
?>