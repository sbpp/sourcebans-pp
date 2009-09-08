<?php
require_once READERS_DIR . 'bans.php';

class BansWriter
{
  /**
   * Adds a ban
   *
   * @param  string  $name   The name of the banned player
   * @param  integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param  string  $steam  The Steam ID of the banned player
   * @param  string  $ip     The IP address of the banned player
   * @param  integer $length The length of the ban in minutes
   * @param  string  $reason The reason of the ban
   * @param  integer $server The server id on which the ban was performed, or 0 for a web ban
   * @return The id of the added ban
   */
  public static function add($name, $type, $steam, $ip, $length, $reason, $server = 0)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS')))
      throw new Exception('Access Denied.');
    if(empty($name)   || !is_string($name))
      throw new Exception('Invalid name supplied.');
    if(!is_numeric($type))
      throw new Exception('Invalid ban type supplied.');
    if($type == STEAM_BAN_TYPE && !preg_match(STEAM_FORMAT, $steam))
      throw new Exception('Invalid Steam ID supplied.');
    if($type == IP_BAN_TYPE    && !preg_match(IP_FORMAT,    $ip))
      throw new Exception('Invalid IP address supplied.');
    if(!is_numeric($length))
      throw new Exception('Invalid ban length supplied.');
    if(empty($reason) || !is_string($reason))
      throw new Exception('Invalid ban reason supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_bans (name, type, steam, ip, created, ends, reason, server_id, admin_id, admin_ip)
                  VALUES      (?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?, ?, ?, ?, ?)',
                  array($name, $type, $steam, $ip, $length * 60, $reason, $server, $userbank->GetID(), $_SERVER['REMOTE_ADDR']));
    
    $id          = $db->Insert_ID();
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnAddBan', $id, $name, $type, $steam, $ip, $length, $reason, $server);
    
    return $id;
  }
  
  
  /**
   * Deletes a ban
   *
   * @param integer $id The id of the ban to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER','ADMIN_DELETE_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id) || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_bans
                  WHERE       id = ?',
                  array($id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteBan', $id);
  }
  
  
  /**
   * Edits a ban
   *
   * @param integer $id     The id of the ban to edit
   * @param string  $name   The name of the banned player
   * @param integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param string  $steam  The Steam ID of the banned player
   * @param string  $ip     The IP address of the banned player
   * @param integer $length The length of the ban in minutes
   * @param string  $reason The reason of the ban
   */
  public static function edit($id, $name, $type, $steam, $ip, $length, $reason)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    $ban      = array();
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ALL_BANS', 'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_OWN_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id)     || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(!is_null($name)   && is_string($name))
      $ban['name']   = $name;
    if(!is_null($type)   && is_numeric($type))
      $ban['type']   = $type;
    if(!is_null($steam)  && is_string($steam))
      $ban['steam']  = $steam;
    if(!is_null($ip)     && is_string($ip))
      $ban['ip']     = $ip;
    if(!is_null($length) && is_numeric($length))
      $ban['ends']   = 'created + ' . $length; // Add created to length
    if(!is_null($reason) && is_string($reason))
      $ban['reason'] = $reason;
    
    $db->AutoExecute(Env::get('prefix') . '_bans', $ban, 'UPDATE', 'id = ' . $id);
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnEditBan', $id, $name, $type, $steam, $ip, $length, $reason);
  }
  
  
  /**
   * Imports one or more bans
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function import($file, $tmp_name = '')
  {
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_BANS')))
      throw new Exception('Access Denied.');
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception('File does not exist.');
    
    $lines    = file($tmp_name);
    switch(basename($file))
    {
      // IP Addresses
      case 'banned_ip.cfg':
        foreach($lines as $line)
        {
          $ban = explode(' ', $line);
          self::add('',
                    BAN_IP_TYPE,
                    '',
                    $ban[2],
                    $ban[1],
                    'banned_ip.cfg import',
                    $userbank->GetID(),
                    $_SERVER['REMOTE_ADDR']);
        }
        
        break;
      // Steam IDs
      case 'banned_user.cfg':
        foreach($lines as $line)
        {
          $ban = explode(' ', $line);
          self::add('',
                    BAN_STEAM_TYPE,
                    $ban[2],
                    '',
                    $ban[1],
                    'banned_user.cfg import',
                    $userbank->GetID(),
                    $_SERVER['REMOTE_ADDR']);
        }
        
        break;
      default:
        throw new Exception('Unsupported file format.');
    }
  }
  
  
  /**
   * Rebans a ban
   *
   * @param integer $id The id of the ban to reban
   */
  public static function reban($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id) || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_bans
                         unban_admin_id = NULL,
                         unban_reason   = NULL,
                         unban_time     = NULL
                  WHERE  id             = ?
                    AND  unban_admin_id IS NOT NULL',
                  array($id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnReban', $id);
  }
  
  
  /**
   * Unbans a ban
   *
   * @param integer $id     The id of the ban to unban
   * @param string  $reason The reason for unbanning the ban
   */
  public static function unban($id, $reason)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_ALL_BANS', 'ADMIN_UNBAN_GROUP_BANS', 'ADMIN_UNBAN_OWN_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id)     || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(empty($reason) || !is_string($reason))
      throw new Exception('Invalid unban reason supplied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_bans
                         unban_admin_id = ?,
                         unban_reason   = ?,
                         unban_time     = UNIX_TIMESTAMP()
                  WHERE  id             = ?
                    AND  unban_admin_id IS NULL',
                  array($userbank->GetID(), $reason, $id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnUnban', $id, $reason);
  }
}
?>