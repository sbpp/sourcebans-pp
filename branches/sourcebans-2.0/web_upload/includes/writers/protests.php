<?php
require_once READERS_DIR . 'protests.php';

class ProtestsWriter
{
  /**
   * Adds a protest
   *
   * @param  string  $name   The name of the banned player
   * @param  integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param  string  $steam  The Steam ID of the banned player
   * @param  string  $ip     The IP address of the banned player
   * @param  string  $reason The reason of the protest
   * @param  string  $email  The e-mail address of the protester
   * @return The id of the added protest
   */
  public static function add($name, $type, $steam, $ip, $reason, $email)
  {
    $db     = Env::get('db');
    $ban_id = $db->GetOne('SELECT id
                           FROM   ' . Env::get('prefix') . '_bans
                           WHERE  (type = ? AND steam = ?)
                              OR  (type = ? AND ip    = ?)',
                           array(STEAM_BAN_TYPE, $steam, IP_BAN_TYPE, $ip));
    
    if(!$db->RecordCount())
      throw new Exception('This Steam ID or IP address is not banned.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_protests (ban_id, reason, email, ip, time)
                  VALUES      (?, ?, ?, ?, UNIX_TIMESTAMP())',
                  array($ban_id, $reason, $email, $_SERVER['REMOTE_ADDR']));
    
    $id              = $db->Insert_ID();
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile();
    
    SBPlugins::call('OnAddProtest', $id, $type, $steam, $ip, $reason, $email);
    
    return $id;
  }
  
  
  /**
   * Archives a protest
   *
   * @param integer $id The id of the protest to archive
   */
  public static function archive($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_protests
                  SET    archiv = 1
                  WHERE  pid    = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile();
    
    SBPlugins::call('OnArchiveProtest', $id);
  }
  
  
  /**
   * Deletes a protest
   *
   * @param integer $id The id of the protest to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_protests
                  WHERE       pid = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteProtest', $id);
  }
  
  
  /**
   * Restores a protest from the archive
   *
   * @param integer $id The id of the protest to restore
   */
  public static function restore($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_protests
                  SET    archiv = 0
                  WHERE  pid    = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile();
    
    SBPlugins::call('OnRestoreProtest', $id);
  }
}
?>