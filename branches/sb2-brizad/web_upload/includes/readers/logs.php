<?php
require_once READER;

class LogsReader extends SBReader
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
    
    // Fetch logs
    $log_count = $db->GetOne('SELECT COUNT(id)
                              FROM   ' . Env::get('prefix') . '_log');
    $log_list  = $db->GetAssoc('SELECT    lo.id, lo.type, lo.title, lo.message, lo.function, lo.query, lo.admin_ip, lo.time, ad.name AS admin_name
                                FROM      ' . Env::get('prefix') . '_log    AS lo
                                LEFT JOIN ' . Env::get('prefix') . '_admins AS ad ON ad.id = lo.admin_id
                                ORDER BY  ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                                ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    list($log_list, $log_count) = SBPlugins::call('OnGetLogs', $log_list, $log_count, $this->limit, $this->page, $this->sort, $this->order);
    
    return array('count' => $log_count,
                 'list'  => $log_list);
  }
}
?>