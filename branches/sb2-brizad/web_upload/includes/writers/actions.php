<?php
require_once READERS_DIR . 'actions.php';

class ActionsWriter
{  
  /**
   * Clears the actions
   *
   * @noreturn
   */
  public static function clear()
  {
    $db = SBConfig::getEnv('db');
    
    $db->Execute('TRUNCATE TABLE ' . SBConfig::getEnv('prefix') . '_actions');
    
    $actions_reader = new ActionsReader();
    $actions_reader->removeCacheFile();
    
    SBPlugins::call('OnClearActions');
  }
}
?>