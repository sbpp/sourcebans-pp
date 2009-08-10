<?php
require_once READERS_DIR . 'demos.php';

class DemosWriter
{
  /**
   * Adds a demo
   *
   * @param  integer $ban_id   The id of the ban/protest/submission to add the demo to
   * @param  integer $type     The type of the demo (BAN_DEMO, SUBMISSION_DEMO)
   * @param  string  $filename The filename of the demo
   * @return The id of the added demo
   */
  public static function add($ban_id, $type, $filename)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(empty($ban_id)   || !is_numeric($ban_id))
      throw new Exception('Invalid ban ID supplied.');
    if(empty($type)     || !is_string($type))
      throw new Exception('Invalid type supplied.');
    if(empty($filename) || !is_string($filename))
      throw new Exception('Invalid filename supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_demos (ban_id, type, filename)
                  VALUES      (?, ?, ?)',
                  array($ban_id, $type, $filename));
    
    $id           = $db->Insert_ID();
    $demos_reader = new DemosReader();
    $demos_reader->removeCacheFile();
    
    SBPlugins::call('OnAddDemo', $id, $ban_id, $type, $filename);
    
    return $id;
  }
  
  
  /**
   * Deletes a demo
   *
   * @param integer $id The id of the demo to delete
   */
  public static function delete($id)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id)     || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_demos
                  WHERE       id = ?',
                  array($id));
    
    $demos_reader = new DemosReader();
    $demos_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteDemo', $id);
  }
  
  
  /**
   * Edits a demo
   *
   * @param integer $id       The id of the demo to edit
   * @param string  $filename The filename of the demo
   */
  public static function edit($id, $filename)
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_BANS')))
      throw new Exception('Access Denied.');
    if(empty($id)       || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(empty($filename) || !is_string($filename))
      throw new Exception('Invalid filename supplied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_mods
                  SET    filename = ?
                  WHERE  id       = ?',
                  array($filename, $id));
    
    $demos_reader = new DemosReader();
    $demos_reader->removeCacheFile();
    
    SBPlugins::call('OnEditDemo', $id, $filename);
  }
}
?>