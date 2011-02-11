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
   * @return integer The id of the added submission
   */
  public static function add($steam, $ip, $name, $reason, $subname, $subemail, $server = 0)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($steam)    || !preg_match(STEAM_FORMAT, $steam))
      throw new Exception($phrases['invalid_steam']);
    if(empty($ip)       || !preg_match(IP_FORMAT,    $ip))
      throw new Exception($phrases['invalid_ip']);
    if(empty($name)     || !is_string($name))
      throw new Exception('Invalid player name specified.');
    if(empty($reason)   || !is_string($reason))
      throw new Exception($phrases['invalid_reason']);
    if(empty($subname)  || !is_string($subname))
      throw new Exception($phrases['invalid_name']);
    if(empty($subemail) || !preg_match(EMAIL_FORMAT, $subemail))
      throw new Exception($phrases['invalid_email']);
    if(empty($server)   || !is_numeric($server))
      throw new Exception('Invalid server ID specified.');
    
    $db->Execute('INSERT INTO ' . SBConfig::getEnv('prefix') . '_submissions (name, steam, ip, reason, server_id, subname, subemail, subip, time)
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
   * @noreturn
   */
  public static function archive($id)
  {
    $db = SBConfig::getEnv('db');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_submissions
                  SET    archived = 1
                  WHERE  id       = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile(true);
    
    SBPlugins::call('OnArchiveSubmission', $id);
  }
  
  
  /**
   * Bans a submission
   *
   * @param  integer $id The id of the submission to ban
   * @return integer The id of the added ban
   */
  public static function ban($id)
  {
    require_once WRITERS_DIR . 'bans.php';
    
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    $sub = $db->GetRow('SELECT name, steam, ip, reason
                        FROM   ' . SBConfig::getEnv('prefix') . '_submissions
                        WHERE  archived = 0
                          AND  id       = ?',
                        array($id));
    
    if(empty($sub))
      throw new Exception($phrases['invalid_id']);
    
    $ban_id = BansWriter::add($sub['name'], STEAM_BAN_TYPE, $sub['steam'], $sub['ip'], 0, $sub['reason']);
    self::archive($id);
    
    return $ban_id;
  }
  
  
  /**
   * Deletes a submission
   *
   * @param integer $id The id of the submission to delete
   * @noreturn
   */
  public static function delete($id)
  {
    $db = SBConfig::getEnv('db');
    
    $db->Execute('DELETE FROM ' . SBConfig::getEnv('prefix') . '_submissions
                  WHERE       id  = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile(true);
    
    SBPlugins::call('OnDeleteSubmission', $id);
  }
  
  
  /**
   * Restores a submission from the archive
   *
   * @param integer $id The id of the submission to restore
   * @noreturn
   */
  public static function restore($id)
  {
    $db = SBConfig::getEnv('db');
    
    $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_submissions
                  SET    archived = 0
                  WHERE  id       = ?',
                  array($id));
    
    $submissions_reader = new SubmissionsReader();
    $submissions_reader->removeCacheFile(true);
    
    SBPlugins::call('OnRestoreSubmission', $id);
  }
}
?>