<?php
require_once READER;

class BlocksReader extends SBReader
{
  public $limit = 0;
  public $order = SORT_DESC;
  public $page  = 1;
  public $sort  = 'time';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config = Env::get('config');
    $db     = Env::get('db');
    
    // Fetch blocks
    $blocks = $db->GetAll('SELECT    bl.ban_id, bl.name, bl.time, ba.steam 
                           FROM      ' . Env::get('prefix') . '_blocks AS bl
                           LEFT JOIN ' . Env::get('prefix') . '_bans   AS ba ON ba.id = bl.ban_id
                           ORDER BY  ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                           ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    list($blocks) = SBPlugins::call('OnGetBlocks', $blocks);
    
    return $blocks;
  }
}
?>