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
  public static function add($name, $auth, $identity, $email = '', $password = '', $srv_password = false, $srv_groups = array(), $web_group = null)
  {
    $db       = Env::get('db');
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(empty($name)          || !is_string($name))
      throw new Exception('Invalid name specified.');
    if(empty($auth)          || !is_string($auth))
      throw new Exception('Invalid authentication type specified.');
    if(empty($identity)      || !is_string($identity) ||
       ($auth == STEAM_AUTH_TYPE && !preg_match(STEAM_FORMAT, $identity)) ||
       ($auth == IP_AUTH_TYPE    && !preg_match(IP_FORMAT,    $identity)))
      throw new Exception('Invalid identity specified.');
    if(!empty($email)        && !preg_match(EMAIL_FORMAT, $email))
      throw new Exception('Invalid e-mail address specified.');
    if(!is_string($password))
      throw new Exception('Invalid password specified.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_admins (name, auth, identity, password, group_id, email, srv_password)
                  VALUES      (?, ?, ?, ?, ?, ?, ?)',
                  array($name, $auth, $identity, empty($password) ? null : $userbank->encrypt_password($password), $web_group, $email, $srv_password ? $password : null));
    
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
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($id) || !is_numeric($id))
      throw new Exception('Invalid ID specified.');
    
    $db->Execute('DELETE    ad, ag
                  FROM      ' . Env::get('prefix') . '_admins           AS ad
                  LEFT JOIN ' . Env::get('prefix') . '_admins_srvgroups AS ag ON ag.admin_id = ad.id 
                  WHERE     ad.id = ?',
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
   * @param string  $theme        The theme setting of the admin
   * @param string  $language     The language setting of the admin
   */
  public static function edit($id, $name = null, $auth = null, $identity = null, $email = null, $password = null, $srv_password = null, $srv_groups = null, $web_group = null, $theme = null, $language = null)
  {
    $db       = Env::get('db');
    $phrases  = Env::get('phrases');
    $plugins  = Env::get('plugins');
    $userbank = Env::get('userbank');
    
    $admin    = array();
    
    if(empty($id)              || !is_numeric($id))
      throw new Exception('Invalid ID specified.');
    if(!is_null($name)         && is_string($name))
      $admin['name']         = $name;
    if(!is_null($auth)         && is_string($auth))
      $admin['auth']         = $auth;
    if(!is_null($identity)     && is_string($identity))
      $admin['identity']     = $identity;
    if(!is_null($email)        && is_string($email))
      $admin['email']        = $email;
    if(!is_null($password)     && is_string($password))
      $admin['password']     = $userbank->encrypt_password($password);
    if(!is_null($srv_password) && is_bool($srv_password))
      $admin['srv_password'] = $srv_password ? $password : null;
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
    
    $phrases = Env::get('phrases');
    
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception('File does not exist.');
    
    switch(basename($file))
    {
      // SourceMod
      case 'admins_simple.ini':
      case 'admins.cfg':
        $groups_reader       = new GroupsReader();
        $groups_reader->type = SERVER_GROUPS;
        $server_groups       = $groups_reader->executeCached(ONE_MINUTE * 5);
        
        foreach($server_groups as $group_id => $group)
          $group_list[$group['name']] = $group_id;
        
        if(basename($file) == 'admins.cfg')
        {
          $reader = new KVReader($tmp_name);
          
          foreach($reader->Values['Admins'] as $name => $admin)
            self::add($name,
                      $admin['auth'],
                      $admin['identity'],
                      '',
                      isset($admin['password']) ? $admin['password']                  : '',
                      isset($admin['password']),
                      isset($admin['group'])    ? array($group_list[$admin['group']]) : array());
        }
        else
        {
          preg_match_all('~"(.+?)"[ \t]*"(.+?)"([ \t]*"(.+?)")?~', file_get_contents($tmp_file), $admins);
          
          for($i = 0; $i < count($admins[0]); $i++)
          {
            list($identity, $flags, $password) = array($admins[1][$i], $admins[2][$i], $admins[4][$i]);
            
            // Parse authentication type depending on identity
            if(preg_match(STEAM_FORMAT, $identity))
              $auth = STEAM_AUTH_TYPE;
            else if($identity{0} == '!' && preg_match(IP_FORMAT, $identity))
              $auth = IP_AUTH_TYPE;
            else
              $auth = NAME_AUTH_TYPE;
            
            // Parse flags
            if($flags{0} == '@')
              $group = substr($flags, 1);
            else if(strpos($flags, ':') !== false)
              list($immunity, $flags) = explode(':', $flags);
            
            self::add($identity,
                      $auth,
                      $identity,
                      '',
                      $password,
                      !empty($password),
                      isset($group) ? array($group_list[$group]) : array());
          }
        }
        
        break;
      // Mani Admin Plugin
      case 'clients.txt':
        $reader = new KVReader($tmp_name);
        
        foreach($reader->Values['clients.txt']['players'] as $name => $admin)
          self::add($name, STEAM_AUTH_TYPE, $admin['steam']);
        
        break;
      default:
        throw new Exception('Unsupported file format.');
    }
  }
}
?>