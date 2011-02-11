<?php
require_once READER;

class DemosReader extends SBReader
{
  public $ban_id;
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db    = SBConfig::getEnv('db');
    
    // Fetch demos
    $demos = $db->GetAssoc('SELECT   id, filename
                            FROM     ' . SBConfig::getEnv('prefix') . '_demos
                            WHERE    ban_id = ?
                              AND    type   = ?
                            ORDER BY id',
                            array($this->ban_id, $this->type));
    
    list($demos) = SBPlugins::call('OnGetDemos', $demos, $this->ban_id, $this->type);
    
    return $demos;
  }
}
?>