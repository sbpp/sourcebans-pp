<?php
require_once READER;
require_once READERS_DIR . 'comments.php';
require_once READERS_DIR . 'demos.php';

class SubmissionsReader extends SBReader
{
  public $archive = false;
  public $limit   = 0;
  public $order   = SORT_DESC;
  public $page    = 1;
  public $sort    = 'time';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db               = Env::get('db');
    
    // Fetch submissions
    $submission_count = $db->GetOne('SELECT COUNT(id)
                                     FROM   ' . Env::get('prefix') . '_submissions
                                     WHERE  archived = ?',
                                     array($this->archive ? 1 : 0));
    $submission_list  = $db->GetAssoc('SELECT   id, name, steam, ip, reason, server_id, subname, subemail, subip, time
                                       FROM     ' . Env::get('prefix') . '_submissions
                                       WHERE    archived = ?
                                       ORDER BY ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                                       ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''),
                                       array($this->archive ? 1 : 0));
    
    // Process submissions
    foreach($submission_list as $id => &$submission)
    {
      // Fetch comments for this submission
      $comments_reader         = new CommentsReader();
      $comments_reader->ban_id = $id;
      $comments_reader->type   = SUBMISSION_TYPE;
      $submission['comments']  = $comments_reader->executeCached(ONE_DAY);
      
      // Fetch demos for this submission
      $demos_reader            = new DemosReader();
      $demos_reader->ban_id    = $id;
      $demos_reader->type      = SUBMISSION_TYPE;
      $submission['demos']     = $demos_reader->executeCached(ONE_DAY);
    }
    
    list($submission_list, $submission_count) = SBPlugins::call('OnGetSubmissions', $submission_list, $submission_count, $this->archive, $this->limit, $this->page, $this->sort, $this->order);
    
    return array('count' => $submission_count,
                 'list'  => $submission_list);
  }
}
?>