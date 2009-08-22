<?php
require_once READER;

class CountsReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db     = Env::get('db');
    
    // Fetch counts
    $counts = $db->GetRow('SELECT (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_admins)                         AS admins,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_bans)                           AS bans,
                                  (SELECT COUNT(ban_id) FROM ' . Env::get('prefix') . '_blocks)                         AS blocks,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_mods)                           AS mods,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_protests    WHERE archived = 0) AS protests,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_protests    WHERE archived = 1) AS archived_protests,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_servers)                        AS servers,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_submissions WHERE archived = 0) AS submissions,
                                  (SELECT COUNT(id)     FROM ' . Env::get('prefix') . '_submissions WHERE archived = 1) AS archived_submissions');
    
    SBPlugins::call('OnGetCounts', &$counts);
    
    return $counts;
  }
}
?>