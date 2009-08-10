<?php
require_once READERS_DIR . 'admins.php';

class AdminsWriter
{
  /**
   * Adds an admin
   *
   * @param  string  $name         The name of the admin
   * @param  string  $auth         The authentication type of the admin (STEAM_AUTH_TYPE, IP_AUTH_TYPE, NAME_AUTH_TYPE)
   * @param  string  $identity     The identity of the admin
   * @param  string  $email        The e-mail address of the admin
   * @param  string  $password     The password of the admin
   * @param  bool    $srv_password Whether or not the password should be used as server password
   * @param  array   $srv_groups   The list of server admin groups of the admin
   * @param  integer $web_group    The web admin group of the admin
   * @return The id of the added admin
   */
  public static function add($name, $auth, $identity, $email = '', $password = '', $srv_password = false, $srv_groups = array(), $web_group = -1)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS')))
      throw new Exception('Access Denied.');
    if(empty($name)          || !is_string($name))
      throw new Exception('Invalid name supplied.');
    if(empty($auth)          || !is_string($auth))
      throw new Exception('Invalid authentication type supplied.');
    if(empty($identity)      || !is_string($identity) ||
       ($auth == STEAM_AUTH_TYPE && !preg_match(STEAM_FORMAT, $identity)) ||
       ($auth == IP_AUTH_TYPE    && !preg_match(IP_FORMAT,    $identity)))
      throw new Exception('Invalid identity supplied.');
    if(!empty($email)        && !preg_match(EMAIL_FORMAT, $email))
      throw new Exception('Invalid e-mail address supplied.');
    if(!is_string($password))
      throw new Exception('Invalid password supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_admins (name, auth, identity, password, group_id, email, srv_password)
                  VALUES      (?, ?, ?, ?, ?, ?, ?)',
                  array($name, $auth, $identity, empty($password) ? null : CUserManager::encrypt_password($password), $web_group, $email, $srv_password ? $password : null));
    
    $id            = $db->Insert_ID();
    $admins_reader = new AdminsReader();
    $admins_reader->removeCacheFile();
    
    if(is_array($srv_groups) && !empty($srv_groups))
    {
      $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_admins_srvgroups (admin_id, group_id, inherit_order)
                             VALUES      (?, ?, ?)');
      
      for($i = 0; $i < count($srv_groups); $i++)
        $db->Execute($query, array($id, $srv_groups[$i], $i));
    }
    
    SBPlugins::call('OnAddAdmin', $id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group);
    
    return $id;
  }
  
  
  /**
   * Deletes an admin
   *
   * @param integer $id The id of the admin to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_ADMINS')))
      throw new Exception('Access Denied.');
    if(empty($id) || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE ad, ag
                  FROM   ' . Env::get('prefix') . '_admins           AS ad,
                         ' . Env::get('prefix') . '_admins_srvgroups AS ag
                  WHERE  ad.id = ag.admin_id
                    AND  ad.id = ?',
                  array($id));
    
    $admins_reader = new AdminsReader();
    $admins_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteAdmin', $id);
  }
  
  
  /**
   * Edits an admin
   *
   * @param integer $id           The id of the admin to edit
   * @param string  $name         The name of the admin
   * @param string  $auth         The authentication type of the admin (STEAM_AUTH_TYPE, IP_AUTH_TYPE, NAME_AUTH_TYPE)
   * @param string  $identity     The identity of the admin
   * @param string  $email        The e-mail address of the admin
   * @param string  $password     The password of the admin
   * @param bool    $srv_password Whether or not the password should be used as server password
   * @param array   $srv_groups   The list of server admin groups of the admin
   * @param integer $web_group    The web admin group of the admin
   */
  public static function edit($id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group, $theme, $language)
  {
    $db       = Env::get('db');
    $plugins  = Env::get('plugins');
    $userbank = Env::get('userbank');
    
    $admin    = array();
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')))
      throw new Exception('Access Denied.');
    if(empty($id)       || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(!is_null($name)         && is_string($name))
      $admin['name']         = $name;
    if(!is_null($auth)         && is_string($auth))
      $admin['auth']         = $auth;
    if(!is_null($identity)     && is_string($identity))
      $admin['identity']     = $identity;
    if(!is_null($email)        && is_string($email))
      $admin['email']        = $email;
    if(!is_null($password)     && is_string($password))
      $admin['password']     = $password;
    if(!is_null($srv_password) && is_bool($srv_password) && $srv_password)
      $admin['srv_password'] = $password;
    if(!is_null($language)     && is_string($language))
      $admin['language']     = $language;
    if(!is_null($theme)        && is_string($theme))
      $admin['theme']        = $theme;
    if(!is_null($web_group)    && is_numeric($web_group))
      $admin['group_id']     = $web_group;
    if(!is_null($srv_groups)   && is_array($srv_groups))
    {
      $db->Execute('DELETE FROM ' . Env::get('prefix') . '_admins_srvgroups
                    WHERE       admin_id = ?',
                    array($id));
      
      $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_admins_srvgroups (admin_id, group_id, inherit_order)
                             VALUES      (?, ?, ?)');
      
      for($i = 0; $i < count($srv_groups); $i++)
        $db->Execute($query, array($id, $srv_groups[$i], $i));
    }
    
    $db->AutoExecute(Env::get('prefix') . '_admins', $admin, 'UPDATE', 'id = ' . $id);
    
    $admins_reader = new AdminsReader();
    $admins_reader->removeCacheFile();
    
    SBPlugins::call('OnEditAdmin', $id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group, $theme, $language);
  }
  
  
  /**
   * Imports one or more admins
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function import($file, $tmp_name = '')
  {
    require_once UTILS_DIR . 'keyvalues/kvutil.php';
    
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_ADMINS')))
      throw new Exception('Access Denied.');
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception('File does not exist.');
    
    $reader   = new KVReader($tmp_name);
    switch(basename($file))
    {
      // SourceMod
      case 'admins.cfg':
        $groups_reader       = new GroupsReader();
        $groups_reader->type = SERVER_ADMIN_GROUPS;
        $server_admin_groups = $groups_reader->executeCached(ONE_MINUTE * 5);
        
        foreach($server_admin_groups as $group_id => $group)
          $group_list[$group['name']] = $group_id;
        
        foreach($reader->Values['Admins'] as $name => $admin)
          self::add($name,
                    $admin['auth'],
                    $admin['identity'],
                    '',
                    isset($admin['password']) ? CUserManager::encrypt_password($admin['password']) : '',
                    isset($admin['password']),
                    isset($admin['group'])    ? array($group_list[$admin['group']])                : array());
        
        break;
      // Mani Admin Plugin
      case 'clients.txt':
        foreach($reader->Values['clients.txt']['players'] as $name => $admin)
          self::add($name, STEAM_AUTH_TYPE, $admin['steam']);
        
        break;
      default:
        throw new Exception('Unsupported file format.');
    }
  }
}
?>