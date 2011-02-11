<?php
require_once READER;

class GroupsReader extends SBReader
{
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db = SBConfig::getEnv('db');
    
    // Fetch groups, depending on type
    switch($this->type)
    {
      case SERVER_GROUPS:
        $groups = $db->GetAssoc('SELECT    sg.id, sg.name, sg.flags, sg.immunity, COUNT(ag.admin_id) AS admin_count
                                 FROM      ' . SBConfig::getEnv('prefix') . '_srvgroups        AS sg
                                 LEFT JOIN ' . SBConfig::getEnv('prefix') . '_admins_srvgroups AS ag ON ag.group_id = sg.id
                                 GROUP BY  ag.group_id
                                 ORDER BY  name');
        
        // Fetch group overrides
        foreach($groups as $id => &$group)
          $group['overrides'] = $db->GetAll('SELECT type, name, access
                                             FROM   ' . SBConfig::getEnv('prefix') . '_srvgroups_overrides
                                             WHERE  group_id = ?',
                                             array($id));
        
        break;
      case WEB_GROUPS:
        $groups = $db->GetAssoc('SELECT    wg.id, wg.name, GROUP_CONCAT(DISTINCT pe.name ORDER BY pe.name) AS flags, COUNT(ad.id) AS admin_count
                                 FROM      ' . SBConfig::getEnv('prefix') . '_groups             AS wg
                                 LEFT JOIN ' . SBConfig::getEnv('prefix') . '_admins             AS ad ON ad.group_id = wg.id
                                 LEFT JOIN ' . SBConfig::getEnv('prefix') . '_groups_permissions AS gp ON gp.group_id = wg.id
                                 LEFT JOIN ' . SBConfig::getEnv('prefix') . '_permissions        AS pe ON pe.id       = gp.permission_id
                                 GROUP BY  id
                                 ORDER BY  name');
        
        // Parse group flags
        foreach($groups as &$group)
          $group['flags'] = explode(',', $group['flags']);
        
        break;
      default:
        throw new Exception($phrases['invalid_type']);
    }
    
    list($groups) = SBPlugins::call('OnGetGroups', $groups, $this->type);
    
    return $groups;
  }
}
?>