<?php
require_once READER;

class CommentsReader extends SBReader
{
  public $ban_id;
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db       = Env::get('db');
    
    // Fetch comments
		$comments = $db->GetAssoc('SELECT    co.id, co.admin_id, co.message, co.time, co.edit_time, ad.name AS admin_name, ed.name AS edit_admin_name
                               FROM      ' . Env::get('prefix') . '_comments AS co
                               LEFT JOIN ' . Env::get('prefix') . '_admins   AS ad ON ad.id = co.admin_id
                               LEFT JOIN ' . Env::get('prefix') . '_admins   AS ed ON ed.id = co.edit_admin_id
                               WHERE     co.ban_id = ?
                                 AND     co.type   = ?
                               ORDER BY  time DESC',
                               array($this->ban_id, $this->type));
    
    list($comments) = SBPlugins::call('OnGetComments', $comments, $this->ban_id, $this->type);
    
    return $comments;
  }
}
?>