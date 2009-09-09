<?php
require_once READERS_DIR . 'servers.php';

class ServersWriter
{
  /**
   * Adds a server
   *
   * @param  string  $ip     The IP address of the server
   * @param  integer $port   The port number of the server
   * @param  string  $rcon   The RCON password of the server
   * @param  integer $mod    The id of the server mod
   * @param  array   $groups The list of server groups to add the server to
   * @return The id of the added server
   */
  public static function add($ip, $port, $rcon, $mod, $groups = array())
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS')))
      throw new Exception('Access Denied.');
    if(empty($ip)   || !is_string($ip))
      throw new Exception('Invalid IP address supplied.');
    if(empty($port) || !is_numeric($port))
      throw new Exception('Invalid port number supplied.');
    if(empty($mod)  || !is_numeric($mod))
      throw new Exception('Invalid mod ID supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_servers (ip, port, rcon, mod_id)
                  VALUES      (?, ?, ?, ?)',
                  array($ip, $port, $rcon, $mod));
    
    $id       = $db->Insert_ID();
    
    if(is_array($groups) && !empty($groups))
    {
      $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_servers_srvgroups (server_id, group_id)
                             VALUES      (?, ?)');
      
      foreach($groups as $group)
        $db->Execute($query, array($id, $group));
    }
    
    $servers_reader = new ServersReader();
    $servers_reader->removeCacheFile();
    
    SBPlugins::call('OnAddServer', $id, $ip, $port, $rcon, $mod, $groups);
    
    return $id;
  }
  
  
  /**
   * Deletes a server
   *
   * @param integer $id The id of the server to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_SERVERS')))
      throw new Exception('Access Denied.');
    if(empty($id)   || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE se, gs
                  FROM   ' . Env::get('prefix') . '_servers           AS se,
                         ' . Env::get('prefix') . '_servers_srvgroups AS gs
                  WHERE  se.id = gs.server_id
                    AND  se.id = ?',
                  array($id));
    
    $servers_reader = new ServersReader();
    $servers_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteServer', $id);
  }
  
  
  /**
   * Edits a server
   *
   * @param integer $id     The id of the server to edit
   * @param string  $ip     The IP address of the server
   * @param integer $port   The port number of the server
   * @param string  $rcon   The RCON password of the server
   * @param integer $mod    The id of the server mod
   * @param array   $groups The list of servers groups to add the server to
   */
  public static function edit($id, $ip, $port, $rcon, $mod, $groups)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_SERVERS')))
      throw new Exception('Access Denied.');
    if(empty($id)   || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(empty($ip)   || !is_string($ip))
      throw new Exception('Invalid IP address supplied.');
    if(empty($port) || !is_numeric($port))
      throw new Exception('Invalid port number supplied.');
    if(empty($mod)  || !is_numeric($mod))
      throw new Exception('Invalid mod ID supplied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_servers
                  SET    ip     = ?,
                         port   = ?,
                         rcon   = ?,
                         mod_id = ?
                  WHERE  id     = ?',
                  array($ip, $port, $rcon, $mod, $id));
    
    if(!empty($groups))
    {
      $db->Execute('DELETE FROM ' . Env::get('prefix') . '_servers_srvgroups
                    WHERE       server_id = ?',
                    array($id));
      
      $query = $db->Prepare('INSERT INTO ' . Env::get('prefix') . '_servers_srvgroups (server_id, group_id)
                             VALUES      (?, ?)');
      
      foreach($groups as $group)
        $db->Execute($query, array($id, $group));
    }
    
    $servers_reader = new ServersReader();
    $servers_reader->removeCacheFile();
    
    SBPlugins::call('OnEditServer', $id, $ip, $port, $rcon, $mod, $groups);
  }
  
  
  /**
   * Imports one or more servers
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function import($file, $tmp_name = '')
  {
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_SERVERS')))
      throw new Exception('Access Denied.');
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception('File does not exist.');
    
    if(pathinfo($name, PATHINFO_EXTENSION) != 'sslf')
      throw new Exception('Unsupported file format.');
    
    $contents = file($tmp_name);
    preg_match_all('Server=([a-zA-Z0-9]+) ([0-9\.]+):([0-9]+)', $contents, $servers);
    
    foreach($servers as $server)
      self::add($server[1], $server[2], '', 0);
  }
}
?>