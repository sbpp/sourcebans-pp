<?php
require_once READER;

class ActionsReader extends SBReader
{
  public $limit = 0;
  public $order = SORT_DESC;
  public $page  = 1;
  public $search;
  public $sort  = 'time';
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config = Env::get('config');
    $db     = Env::get('db');
    
    $where  = 1;
    
    // Filter actions
    if(!empty($this->search) && !empty($this->type))
    {
      $search = $db->qstr($this->search);
      
      switch($this->type)
      {
        // Admin
        case 'admin':
          $where = 'ac.admin_id = ' . $search;
          break;
        // Exact IP Address
        case 'ip':
          $where = 'ac.ip = "' . $search . '"';
          break;
        // Partial IP Address
        case 'ip_part':
          $where = 'ac.ip LIKE "%' . $search . '%"';
          break;
        // Message
        case 'message':
          $where = 'ac.message LIKE "%' . $search . '%"';
          break;
        // Name
        case 'name':
          $where = 'ac.name LIKE "%' . $search . '%"';
          break;
        // Server
        case 'server':
          $where = 'ac.server_id = ' . $search;
          break;
        // Exact Steam
        case 'steam':
          $where = 'ac.steam = "' . $search . '"';
          break;
        // Partial Steam
        case 'steam_part':
          $where = 'ac.steam LIKE "%' . $search . '%"';
      }
    }
    
    // Fetch actions
    $action_count = $db->GetOne('SELECT COUNT(id)
                                 FROM   ' . Env::get('prefix') . '_actions AS ac
                                 WHERE  ' . $where);
    $action_list  = $db->GetAssoc('SELECT    ac.id, ac.name, ac.steam, ac.ip, ac.message, ac.admin_ip, ac.time, se.ip AS server_ip, se.port AS server_port,
                                             IFNULL(ad.name, "CONSOLE") AS admin_name, mo.name AS mod_name, mo.icon AS mod_icon,
                                             76561197960265728 + CAST(SUBSTR(ac.steam, 9, 1) AS UNSIGNED) + CAST(SUBSTR(ac.steam, 11) * 2 AS UNSIGNED) AS community_id
                                   FROM      ' . Env::get('prefix') . '_actions AS ac
                                   LEFT JOIN ' . Env::get('prefix') . '_admins  AS ad ON ad.id = ac.admin_id
                                   LEFT JOIN ' . Env::get('prefix') . '_servers AS se ON se.id = ac.server_id
                                   LEFT JOIN ' . Env::get('prefix') . '_mods    AS mo ON mo.id = se.mod_id
                                   WHERE     ' . $where             . '
                                   ORDER BY  ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                                   ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    list($action_list, $action_count) = SBPlugins::call('OnGetActions', $action_list, $action_count, $this->limit, $this->page, $this->sort, $this->order, $this->search, $this->type);
    
    return array('count' => $action_count,
                 'list'  => $action_list);
  }
}
?>