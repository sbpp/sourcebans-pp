<?php
require_once READER;

class LogsReader extends SBReader
{
  public $limit = 0;
  public $page  = 1;
  public $sort  = 'time DESC';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config = Env::get('config');
    $db     = Env::get('db');
    
    // Fetch logs
    $logs   = $db->GetAssoc('SELECT    lo.id, lo.type, lo.title, lo.message, lo.function, lo.query, lo.admin_ip, lo.time, ad.name AS admin_name
                             FROM      ' . Env::get('prefix') . '_log    AS lo
                             LEFT JOIN ' . Env::get('prefix') . '_admins AS ad ON ad.id = lo.admin_id
                             ORDER BY  ' . $this->sort        .
                             ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    list($logs) = SBPlugins::call('OnGetLogs', $logs);
    
    return $logs;
  }
}
?>