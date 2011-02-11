<?php
require_once READER;

class AdminsReader extends SBReader
{
  public $limit = 0;
  public $order = SORT_ASC;
  public $page  = 1;
  public $search;
  public $sort  = 'name';
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db     = SBConfig::getEnv('db');
    $config = SBConfig::getEnv('config');
    
    $where  = 1;
    
    // Filter admins
    if(!empty($this->search) && !empty($this->type))
    {
      $search = $db->qstr($this->search);
      
      switch($this->type)
      {
        // E-mail
        case 'email':
          $where = 'ad.email LIKE "%' . $search . '%"';
          break;
        // Exact Identity
        case 'identity':
          $where = 'ad.identity = "' . $search . '"';
          break;
        // Partial Identity
        case 'identity_part':
          $where = 'ad.identity LIKE "%' . $search . '%"';
          break;
        // Name
        case 'name':
          $where = 'ad.name LIKE "%' . $search . '%"';
          break;
        // Server
        case 'server':
          $where = 'gs.server_id = ' . $search;
          break;
        // Server Group
        case 'srvgroup':
          $where = 'ag.group_id = ' . $search;
          break;
        // Web Group
        case 'webgroup':
          $where = 'ad.group_id = ' . $search;
      }
    }
    
    // Fetch admins
    $admin_count = $db->GetOne('SELECT    COUNT(id)
                                FROM      ' . SBConfig::getEnv('prefix') . '_admins AS ad
                                LEFT JOIN ' . SBConfig::getEnv('prefix') . '_admins_srvgroups  AS ag ON ag.admin_id = ad.id
                                LEFT JOIN ' . SBConfig::getEnv('prefix') . '_servers_srvgroups AS gs ON gs.group_id = ag.group_id
                                WHERE     ' . $where);
    $admin_list  = $db->GetAssoc('SELECT    ad.id, ad.name, ad.auth, ad.identity, ad.password, ad.group_id, ad.email, ad.language, ad.theme,
                                            ad.srv_password, ad.validate, ad.lastvisit, wg.name AS web_group, GROUP_CONCAT(DISTINCT pe.name ORDER BY pe.name) AS web_flags,
                                            GROUP_CONCAT(DISTINCT sg.id ORDER BY sg.id) AS srv_groups, GROUP_CONCAT(DISTINCT sg.flags SEPARATOR "") AS srv_flags, IFNULL(MAX(sg.immunity), 0) AS srv_immunity,
                                            (SELECT COUNT(id) FROM ' . SBConfig::getEnv('prefix') . '_bans       WHERE admin_id    = ad.id)                                                                                           AS ban_count,
                                            (SELECT COUNT(id) FROM ' . SBConfig::getEnv('prefix') . '_bans AS ba WHERE ba.admin_id = ad.id AND NOT EXISTS (SELECT ban_id FROM ' . SBConfig::getEnv('prefix') . '_demos WHERE ban_id = ba.id)) AS nodemo_count
                                  FROM      ' . SBConfig::getEnv('prefix') . '_admins             AS ad
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_admins_srvgroups   AS ag ON ag.admin_id = ad.id
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_groups             AS wg ON wg.id       = ad.group_id
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_groups_permissions AS gp ON gp.group_id = ad.group_id
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_permissions        AS pe ON pe.id       = gp.permission_id
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_servers_srvgroups  AS gs ON gs.group_id = ag.group_id
                                  LEFT JOIN ' . SBConfig::getEnv('prefix') . '_srvgroups          AS sg ON sg.id       = ag.group_id
                                  WHERE     ' . $where             . '
                                  GROUP BY  id
                                  ORDER BY  ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                                  ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    // Process admins
    foreach($admin_list as &$admin)
    {
      // Remove duplicate server flags
      $srv_flags           = str_split($admin['srv_flags']);
      sort($srv_flags);
      $admin['srv_flags']  = preg_replace('/(.)\1+/i', '$1', implode($srv_flags));
      
      // Split up server admin groups and web admin flags
      $admin['srv_groups'] = empty($admin['srv_groups']) ? array() : explode(',', $admin['srv_groups']);
      $admin['web_flags']  = empty($admin['web_flags'])  ? array() : explode(',', $admin['web_flags']);
    }
    
    list($admin_list, $admin_count) = SBPlugins::call('OnGetAdmins', $admin_list, $admin_count, $this->limit, $this->page, $this->sort, $this->order, $this->search, $this->type);
    
    return array('count' => $admin_count,
                 'list'  => $admin_list);
  }
}
?>