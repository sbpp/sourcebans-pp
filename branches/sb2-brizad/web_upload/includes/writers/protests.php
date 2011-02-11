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
   * @return integer The id of the added protest
   */
  public static function add($name, $type, $steam, $ip, $reason, $email)
  {
    $db     = SBConfig::getEnv('db');
    $ban_id = $db->GetOne('SELECT id
                           FROM   ' . SBConfig::getEnv('prefix') . '_bans
                           WHERE  name = ?
                              OR  (type = ? AND steam = ?)
                              OR  (type = ? AND ip    = ?)',
                           array($name, STEAM_BAN_TYPE, $steam, IP_BAN_TYPE, $ip));
    
    if(is_null($ban_id))
      throw new Exception('This Steam ID or IP address is not banned.');
    
    $db->Execute('INSERT INTO ' . SBConfig::getEnv('prefix') . '_protests (ban_id, reason, email, ip, time)
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
   * @noreturn
   */
  public static function archive($id)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_protests
                  SET    archived = 1
                  WHERE  id       = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile(true);
    
    SBPlugins::call('OnArchiveProtest', $id);
  }
  
  
  /**
   * Deletes a protest
   *
   * @param integer $id The id of the protest to delete
   * @noreturn
   */
  public static function delete($id)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    $db->Execute('DELETE FROM ' . SBConfig::getEnv('prefix') . '_protests
                  WHERE       id = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile(true);
    
    SBPlugins::call('OnDeleteProtest', $id);
  }
  
  
  /**
   * Restores a protest from the archive
   *
   * @param integer $id The id of the protest to restore
   * @noreturn
   */
  public static function restore($id)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_protests
                  SET    archived = 0
                  WHERE  id       = ?',
                  array($id));
    
    $protests_reader = new ProtestsReader();
    $protests_reader->removeCacheFile(true);
    
    SBPlugins::call('OnRestoreProtest', $id);
  }
}
?>