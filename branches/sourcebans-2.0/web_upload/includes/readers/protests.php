<?php
require_once READER;
require_once READERS_DIR . 'comments.php';

class ProtestsReader extends SBReader
{
  public $archive = false;
  public $limit   = 0;
  public $page    = 1;
  public $sort    = 'time DESC';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config   = Env::get('config');
    $db       = Env::get('db');
    
    // Fetch protests
    $protests = $db->GetAssoc('SELECT    pr.id, pr.ban_id, pr.reason, pr.email, pr.time, ba.server_id, ba.steam AS ban_steam, ba.ip AS ban_ip, ba.name AS ban_name,
                                         ba.created AS ban_created, ba.ends AS ban_ends, ba.ends - ba.created AS ban_length, ba.reason AS ban_reason, ad.name AS ban_admin_name
                               FROM      ' . Env::get('prefix') . '_protests AS pr
                               LEFT JOIN ' . Env::get('prefix') . '_bans     AS ba ON ba.id = pr.ban_id
                               LEFT JOIN ' . Env::get('prefix') . '_admins   AS ad ON ad.id = ba.admin_id
                               LEFT JOIN ' . Env::get('prefix') . '_servers  AS se ON se.id = ba.server_id
                               WHERE     archived = ?
                               ORDER BY  ' . $this->sort        .
                               ($this->limit > 0 ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''),
                               array($this->archive ? 1 : 0));
    
    // Process protests
    foreach($protests as $id => &$protest)
    {
      // Fetch comments for this protest
      $comments_reader        = new CommentsReader();
      $comments_reader->bid   = $id;
      $comments_reader->type  = PROTEST_COMMENTS;
      $protest['comments']    = $comments_reader->executeCached(ONE_DAY);
    }
    
    SBPlugins::call('OnGetProtests', &$protests);
    
    return $protests;
  }
}
?>