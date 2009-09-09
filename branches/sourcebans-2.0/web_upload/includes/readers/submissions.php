<?php
require_once READER;

class SubmissionsReader extends SBReader
{
  public $archive = false;
  public $limit   = 0;
  public $page    = 1;
  public $sort    = 'time DESC';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config      = Env::get('config');
    $db          = Env::get('db');
    
    // Fetch submissions
    $submissions = $db->GetAssoc('SELECT    id, name, steam, ip, reason, server_id, subname, subemail, subip, time
                                  FROM      ' . Env::get('prefix') . '_submissions
                                  WHERE     archived = ?
                                  ORDER BY  ' . $this->sort        .
                                  ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''),
                                  array($this->archive ? 1 : 0));
    
    // Process submissions
    foreach($submissions as $id => &$submission)
    {
      // Fetch comments for this submission
      $comments_reader        = new CommentsReader();
      $comments_reader->bid   = $id;
      $comments_reader->type  = SUBMISSION_TYPE;
      $submission['comments'] = $comments_reader->executeCached(ONE_DAY);
    }
    
    list($submissions) = SBPlugins::call('OnGetSubmissions', $submissions);
    
    return $submissions;
  }
}
?>