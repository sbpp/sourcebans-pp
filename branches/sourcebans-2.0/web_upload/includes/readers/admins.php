<?php
require_once READER;

class AdminsReader extends SBReader
{
  public $limit     = 0;
  public $page      = 1;
  public $server_id = 0;
  public $sort      = 'name';
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db     = Env::get('db');
    $config = Env::get('config');
    $where  = 1;
    
    if($this->server_id > 0)
      $where = 'gs.server_id = ' . $this->server_id;
    
    /**
     * Fetch admins
     */
    $admins = $db->GetAssoc('SELECT    ad.id, ad.name, ad.auth, ad.identity, ad.password, ad.group_id, ad.email, ad.language, ad.theme,
                                       ad.srv_password, ad.validate, ad.lastvisit, wg.name AS web_group, GROUP_CONCAT(DISTINCT pe.name ORDER BY pe.name) AS web_flags,
                                       GROUP_CONCAT(DISTINCT sg.name ORDER BY sg.name) AS srv_groups, GROUP_CONCAT(DISTINCT sg.flags SEPARATOR "") AS srv_flags, IFNULL(MAX(sg.immunity), 0) AS srv_immunity,
                                       (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_bans       WHERE admin_id    = ad.id)                                                                                           AS ban_count,
                                       (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_bans AS ba WHERE ba.admin_id = ad.id AND NOT EXISTS (SELECT ban_id FROM ' . Env::get('prefix') . '_demos WHERE ban_id = ba.id)) AS nodemo_count
                             FROM      ' . Env::get('prefix') . '_admins             AS ad
                             LEFT JOIN ' . Env::get('prefix') . '_admins_srvgroups   AS ag ON ag.admin_id = ad.id
                             LEFT JOIN ' . Env::get('prefix') . '_groups             AS wg ON wg.id       = ad.group_id
                             LEFT JOIN ' . Env::get('prefix') . '_groups_permissions AS gp ON gp.group_id = ad.group_id
                             LEFT JOIN ' . Env::get('prefix') . '_permissions        AS pe ON pe.id       = gp.permission_id
                             LEFT JOIN ' . Env::get('prefix') . '_servers_srvgroups  AS gs ON gs.group_id = ag.group_id
                             LEFT JOIN ' . Env::get('prefix') . '_srvgroups          AS sg ON sg.id       = ag.group_id
                             WHERE     ' . $where             . '
                             GROUP BY  id, name, auth, identity, password, group_id, email, language, theme, srv_password, validate, lastvisit, web_group
                             ORDER BY  ' . $this->sort        .
                             ($this->limit > 0 ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    /**
     * Process admins
     */
    foreach($admins as &$admin)
    {
      /**
       * Remove duplicate server flags
       */
      $srv_flags           = str_split($admin['srv_flags']);
      sort($srv_flags);
      $admin['srv_flags']  = preg_replace('/(.)\1+/i', '$1', implode($srv_flags));
      
      /**
       * Split up server admin groups and web admin flags
       */
      $admin['srv_groups'] = empty($admin['srv_groups']) ? array() : explode(',', $admin['srv_groups']);
      $admin['web_flags']  = empty($admin['web_flags'])  ? array() : explode(',', $admin['web_flags']);
    }
    
    SBPlugins::call('OnGetAdmins', &$admins);
    
    return $admins;
  }
}
?>