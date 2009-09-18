<?php
require_once READER;

class ActionsReader extends SBReader
{
  public $limit = 0;
  public $page  = 1;
  public $sort  = 'time DESC';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config  = Env::get('config');
    $db      = Env::get('db');
    
    // Fetch actions
    $actions = $db->GetAssoc('SELECT    ac.id, ac.name, ac.steam, ac.ip, ac.message, ac.admin_ip, ac.time, se.ip AS server_ip, se.port AS server_port,
                                        IFNULL(ad.name, "CONSOLE") AS admin_name, mo.name AS mod_name, mo.icon AS mod_icon,
                                        76561197960265728 + CAST(SUBSTR(ac.steam, 9, 1) AS UNSIGNED) + CAST(SUBSTR(ac.steam, 11) * 2 AS UNSIGNED) AS community_id
                              FROM      ' . Env::get('prefix') . '_actions AS ac
                              LEFT JOIN ' . Env::get('prefix') . '_admins  AS ad ON ad.id = ac.admin_id
                              LEFT JOIN ' . Env::get('prefix') . '_servers AS se ON se.id = ac.server_id
                              LEFT JOIN ' . Env::get('prefix') . '_mods    AS mo ON mo.id = se.mod_id
                              ORDER BY  ' . $this->sort        .
                              ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    list($actions) = SBPlugins::call('OnGetActions', $actions);
    
    return $actions;
  }
}
?>