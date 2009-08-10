<?php
require_once READERS_DIR . 'submissions.php';

class SubmissionsWriter
{
  /**
   * Adds a submission
   *
   * @param  string  $steam    The Steam ID of the player to ban
   * @param  string  $ip       The IP address of the player to ban
   * @param  string  $name     The name of the player to ban
   * @param  string  $reason   The reason of the submission
   * @param  string  $subname  The name of the submitter
   * @param  string  $subemail The e-mail address of the submitter
   * @param  integer $server   The server id on which the player was playing
   * @return The id of the added submission
   */
  public static function add($name, $steam, $ip, $reason, $server, $subname, $subemail)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception('Access Denied.');
    if(empty($steam)    && empty($ip))
      throw new Exception('You must supply a Steam ID or IP address.');
    if(empty($name)     || !is_string($name))
      throw new Exception('Invalid player name supplied.');
    if(empty($reason)   || !is_string($reason))
      throw new Exception('Invalid ban reason supplied.');
    if(empty($subname)  || !is_string($subname))
      throw new Exception('Invalid name supplied.');
    if(empty($subemail) || !is_string($subemail))
      throw new Exception('Invalid e-mail address supplied.');
    if(empty($server)   || !is_numeric($server))
      throw new Exception('Invalid server ID supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_submissions (name, steam, ip, reason, server_id, subname, subemail, subip, time)
                  VALUES      (?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())',
                  array($name, $steam, $ip, $reason, $server, $subname, $subemail, $_SERVER['REMOTE_ADDR']));
    
    $id                 = $db->Insert_ID();
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile();
    
    SBPlugins::call('OnAddSubmission', $id, $steam, $ip, $name, $reason, $subname, $subemail, $server);
    
    return $id;
  }
  
  
  /**
   * Archives a submission
   *
   * @param integer $id The id of the submission to archive
   */
  public static function archive($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_submissions
                  SET    archived = 1
                  WHERE  id       = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile();
    
    SBPlugins::call('OnArchiveSubmission', $id);
  }
  
  
  /**
   * Bans a submission
   *
   * @param integer $id The id of the submission to ban
   */
  public static function ban($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception('Access Denied.');
    
    require_once WRITERS_DIR . 'bans.php';
    
    $sub      = $db->GetRow('SELECT name, steam, ip, reason
                             FROM   ' . Env::get('prefix') . '_submissions
                             WHERE  archived = 0
                               AND  id       = ?',
                             array($id));
    
    if(!$db->RecordCount())
      throw new Exception('Invalid ID specified.');
    
    BansWriter::add($sub['name'], STEAM_BAN_TYPE, $sub['steam'], $sub['ip'], 0, $sub['reason']);
    self::delete($id);
  }
  
  
  /**
   * Deletes a submission
   *
   * @param integer $id The id of the submission to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_submissions
                  WHERE       id  = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteSubmission', $id);
  }
  
  
  /**
   * Restores a submission from the archive
   *
   * @param integer $id The id of the submission to restore
   */
  public static function restore($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception('Access Denied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_submissions
                  SET    archived = 0
                  WHERE  id       = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile();
    
    SBPlugins::call('OnRestoreSubmission', $id);
  }
}
?>