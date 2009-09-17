<?php
require_once READERS_DIR . 'actions.php';

class ActionsWriter
{  
  /**
   * Clears the actions
   */
  public static function clear()
  {
    $db       = Env::get('db');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER')))
      throw new Exception('Access Denied.');
    
    $db->Execute('TRUNCATE TABLE ' . Env::get('prefix') . '_actions');
    
    $actions_reader = new ActionsReader();
    $actions_reader->removeCacheFile();
    
    SBPlugins::call('OnClearActions');
  }
}
?>