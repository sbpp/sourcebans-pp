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
    
    /**
     * Fetch comments
     */
		$comments = $db->GetAssoc('SELECT   co.id, co.admin_id, co.message, co.time, co.edit_time,
                                        (SELECT name FROM ' . Env::get('prefix') . '_admins WHERE id = co.admin_id)      AS admin_name,
                                        (SELECT name FROM ' . Env::get('prefix') . '_admins WHERE id = co.edit_admin_id) AS edit_admin_name
                               FROM     ' . Env::get('prefix') . '_comments AS co
                               WHERE    co.ban_id = ? AND co.type = ?
                               ORDER BY ' . $this->sort,
                               array($this->ban_id, $this->type));
    
    SBPlugins::call('OnGetComments', &$comments, $this->ban_id, $this->type);
    
    return $comments;
  }
}
?>