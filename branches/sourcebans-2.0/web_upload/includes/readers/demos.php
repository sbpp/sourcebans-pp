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
    $db   = Env::get('db');
    
    /**
     * Fetch demo
     */
    $demo = $db->GetAssoc('SELECT filename
                           FROM   ' . Env::get('prefix') . '_demos
                           WHERE  ban_id = ?
                             AND  type   = ?',
                           array($this->ban_id, $this->type));
    
    if(!$db->RecordCount())
      throw new Exception('No such demo.');
    
    return $demo['filename'];
  }
}
?>