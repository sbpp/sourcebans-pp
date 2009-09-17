<?php
require_once READER;

class CommentsReader extends SBReader
{
  public $ban_id;
  public $sort = 'time DESC';
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db       = Env::get('db');
    $where    = "";
    if($this->ban_id&&$this->type)
        $where = sprintf(' WHERE    co.ban_id = %d AND co.type = \'%s\'', $this->ban_id, $this->type);
    // Fetch comments
    $comments = $db->GetAssoc('SELECT   co.id, co.admin_id, co.message, co.time, co.edit_time, co.type, co.ban_id,
                                        (SELECT name FROM ' . Env::get('prefix') . '_admins WHERE id = co.admin_id)      AS admin_name,
                                        (SELECT name FROM ' . Env::get('prefix') . '_admins WHERE id = co.edit_admin_id) AS edit_admin_name
                               FROM     ' . Env::get('prefix') . '_comments AS co' .
                               $where . '
                               ORDER BY ' . $this->sort);
    
    list($comments) = SBPlugins::call('OnGetComments', $comments, $this->ban_id, $this->type);
    
    return $comments;
  }
}
?>