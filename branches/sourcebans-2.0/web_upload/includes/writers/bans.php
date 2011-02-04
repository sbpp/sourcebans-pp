<?php
require_once READERS_DIR . 'bans.php';
require_once WRITERS_DIR . 'countries.php';

class BansWriter
{
  /**
   * Adds a ban
   *
   * @param  integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param  string  $steam  The Steam ID of the banned player
   * @param  string  $ip     The IP address of the banned player
   * @param  string  $name   The name of the banned player
   * @param  string  $reason The reason of the ban
   * @param  integer $length The length of the ban in minutes
   * @param  integer $server The server id on which the ban was performed, or 0 for a web ban
   * @return The id of the added ban
   */
  public static function add($type, $steam, $ip, $name, $reason, $length, $server = 0)
  {
    $db       = Env::get('db');
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!is_numeric($type))
      throw new Exception($phrases['invalid_type']);
    if($type == STEAM_BAN_TYPE && !preg_match(STEAM_FORMAT, $steam))
      throw new Exception($phrases['invalid_steam']);
    if($type == IP_BAN_TYPE    && !preg_match(IP_FORMAT,    $ip))
      throw new Exception($phrases['invalid_ip']);
    if(empty($name)   || !is_string($name))
      throw new Exception($phrases['invalid_name']);
    if(empty($reason) || !is_string($reason))
      throw new Exception($phrases['invalid_reason']);
    if(!is_numeric($length))
      throw new Exception($phrases['invalid_length']);
    
    // If an IP address was specified, store country information
    if(!empty($ip))
      CountriesWriter::store($ip);
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_bans (type, steam, ip, name, reason, length, server_id, admin_id, admin_ip, time)
                  VALUES      (?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())',
                  array($type, $steam, $ip, $name, $reason, $length, $server, $userbank->GetID(), $_SERVER['REMOTE_ADDR']));
    
    $id          = $db->Insert_ID();
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile();
    
    SBPlugins::call('OnAddBan', $id, $type, $steam, $ip, $name, $reason, $length, $server);
    
    return $id;
  }
  
  
  /**
   * Deletes a ban
   *
   * @param integer $id The id of the ban to delete
   * @noreturn
   */
  public static function delete($id)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($id) || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_bans
                  WHERE       id = ?',
                  array($id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile(true);
    
    SBPlugins::call('OnDeleteBan', $id);
  }
  
  
  /**
   * Edits a ban
   *
   * @param integer $id     The id of the ban to edit
   * @param integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param string  $steam  The Steam ID of the banned player
   * @param string  $ip     The IP address of the banned player
   * @param string  $name   The name of the banned player
   * @param string  $reason The reason of the ban
   * @param integer $length The length of the ban in minutes
   * @noreturn
   */
  public static function edit($id, $type = null, $steam = null, $ip = null, $name = null, $reason = null, $length = null)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    $ban     = array();
    
    if(empty($id)        || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    if(!is_null($type)   && is_numeric($type))
      $ban['type']   = $type;
    if(!is_null($steam)  && preg_match(STEAM_FORMAT, $steam))
      $ban['steam']  = $steam;
    if(!is_null($ip)     && preg_match(IP_FORMAT,    $ip))
    {
      $ban['ip']     = $ip;
      
      // Store country information
      CountriesWriter::store($ip);
    }
    if(!is_null($name)   && is_string($name))
      $ban['name']   = $name;
    if(!is_null($reason) && is_string($reason))
      $ban['reason'] = $reason;
    if(!is_null($length) && is_numeric($length))
      $ban['length'] = $length;
    
    $db->AutoExecute(Env::get('prefix') . '_bans', $ban, 'UPDATE', 'id = ' . $id);
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile(true);
    
    SBPlugins::call('OnEditBan', $id, $type, $steam, $ip, $name, $reason, $length);
  }
  
  
  /**
   * Imports one or more bans
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   * @noreturn
   */
  public static function import($file, $tmp_name = '')
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception($phrases['file_does_not_exist']);
    
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
        throw new Exception($phrases['unsupported_format']);
    }
  }
  
  
  /**
   * Rebans a ban
   *
   * @param integer $id The id of the ban to reban
   * @noreturn
   */
  public static function reban($id)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($id) || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_bans SET
                         unban_admin_id = NULL,
                         unban_reason   = NULL,
                         unban_time     = NULL
                  WHERE  id             = ?
                    AND  unban_admin_id IS NOT NULL',
                  array($id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile(true);
    
    SBPlugins::call('OnReban', $id);
  }
  
  
  /**
   * Unbans a ban
   *
   * @param integer $id     The id of the ban to unban
   * @param string  $reason The reason for unbanning the ban
   * @noreturn
   */
  public static function unban($id, $reason)
  {
    $db       = Env::get('db');
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(empty($id)     || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    if(empty($reason) || !is_string($reason))
      throw new Exception($phrases['invalid_reason']);
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_bans SET
                         unban_admin_id = ?,
                         unban_reason   = ?,
                         unban_time     = UNIX_TIMESTAMP()
                  WHERE  id             = ?
                    AND  unban_admin_id IS NULL',
                  array($userbank->GetID(), $reason, $id));
    
    $bans_reader = new BansReader();
    $bans_reader->removeCacheFile(true);
    
    SBPlugins::call('OnUnban', $id, $reason);
  }
}
?>