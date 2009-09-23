<?php
require_once READER;
require_once READERS_DIR . 'comments.php';
require_once READERS_DIR . 'demos.php';

class BansReader extends SBReader
{
  public $hideinactive = false;
  public $limit        = 0;
  public $order        = SORT_DESC;
  public $page         = 1;
  public $search;
  public $sort         = 'time';
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config  = Env::get('config');
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    $where   = 1;
    
    // Filter bans
    if(!empty($this->search) && !empty($this->type))
    {
      $search = $this->search;
      
      switch($this->type)
      {
        // Admin
        case 'admin':
          $where = 'admin_id = ' . $search;
          break;
        // Date
        case 'date':
          $date  = explode(',', $search);
          $time  = mktime(0,  0,  0,  $date[1], $date[0], $date[2]);
          $time2 = mktime(23, 59, 59, $date[1], $date[0], $date[2]);
          $where = 'time > ' . $time . ' AND time < ' . $time2;
          break;
        // Ban
        case 'id':
          $where = 'id = ' . $search;
          break;
        // Partial IP address
        case 'ip':
          $where = 'ip LIKE "%' . $search . '%"';
          break;
        // Length
        case 'length':
          $where = 'length = ' . $search;
          break;
        // Partial name
        case 'name':
          $where = 'name LIKE "%' . $search . '%"';
          break;
        // No demos
        case 'nodemo':
          $where = 'admin_id = ' . $search . ' AND NOT EXISTS (SELECT de.id FROM ' . Env::get('prefix') . '_demos AS de WHERE de.id = ba.id)';
          break;
        // Partial reason
        case 'reason':
          $where = 'reason LIKE "%' . $search . '%"';
          break;
        // Server
        case 'server':
          $where = 'server_id = ' . $search;
          break;
        // Partial Steam ID
        case 'steam':
          $where = 'steam REGEXP "^STEAM_[0-9]:' . substr($search, 8) . '"';
          break;
        // Steam ID
        case 'steamid':
          $where = 'steam REGEXP "^STEAM_[0-9]:' . substr($search, 8) . '$"';
          break;
        // Partial IP address, Steam ID, name or reason
        default:
          $where = 'ip LIKE "%' . $search . '%" OR steam REGEXP "^STEAM_[0-9]:' . substr($search, 8) . '" OR name LIKE "%' . $search . '%" OR reason LIKE "%' . $search . '%"';
      }
    }
    
    // Hide inactive bans
    if($this->hideinactive)
      $where .= ' AND (length = 0 OR time + length * 60 > UNIX_TIMESTAMP()) AND unban_admin_id IS NULL';
    
    // Fetch bans
    $ban_count = $db->GetOne('SELECT COUNT(*)
                              FROM   ' . Env::get('prefix') . '_bans AS ba
                              WHERE  ' . $where);
    $ban_list  = $db->GetAssoc('SELECT    ba.id, ba.type, ba.steam, ba.ip, ba.name, ba.reason, ba.country_code, ba.country_name, ba.length, ba.server_id, ba.admin_id, ba.admin_ip, ba.unban_admin_id, ba.unban_reason, ba.unban_time, ba.time,
                                          se.ip AS server_ip, se.port AS server_port, IFNULL(ad.name, "CONSOLE") AS admin_name, un.name AS unban_admin_name, mo.name AS mod_name, mo.icon AS mod_icon,
                                          76561197960265728 + CAST(SUBSTR(ba.steam, 9, 1) AS UNSIGNED) + CAST(SUBSTR(ba.steam, 11) * 2 AS UNSIGNED) AS community_id,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_bans   WHERE steam  = ba.steam OR  ip   = ba.ip) AS ban_count,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_blocks WHERE ban_id = ba.id)                     AS block_count,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_demos  WHERE ban_id = ba.id    AND type = ?)     AS demo_count
                                FROM      ' . Env::get('prefix') . '_bans    AS ba
                                LEFT JOIN ' . Env::get('prefix') . '_admins  AS ad ON ad.id = ba.admin_id
                                LEFT JOIN ' . Env::get('prefix') . '_admins  AS un ON un.id = ba.unban_admin_id
                                LEFT JOIN ' . Env::get('prefix') . '_servers AS se ON se.id = ba.server_id
                                LEFT JOIN ' . Env::get('prefix') . '_mods    AS mo ON mo.id = se.mod_id
                                WHERE     ' . $where             . '
                                ORDER BY  ' . $this->sort        . ' ' . ($this->order == SORT_DESC ? 'DESC' : 'ASC') .
                                ($this->limit ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''),
                                array(BAN_TYPE));    
    
    // Process bans
    foreach($ban_list as $id => &$ban)
    {
      // Check if ban has been either unbanned or expired
      if(!empty($ban['unban_admin_id']))
        $ban['status'] = $phrases['unbanned'];
      else if($ban['length'] && $ban['time'] + $ban['length'] * 60 < time())
        $ban['status'] = $phrases['expired'];
      
      // Fetch comments for this ban
      $comments_reader         = new CommentsReader();
      $comments_reader->ban_id = $id;
      $comments_reader->type   = BAN_TYPE;
      $ban['comments']         = $comments_reader->executeCached(ONE_DAY);
      
      // Fetch demos for this ban
      $demos_reader            = new DemosReader();
      $demos_reader->ban_id    = $id;
      $demos_reader->type      = BAN_TYPE;
      $ban['demos']            = $demos_reader->executeCached(ONE_DAY);
      
      // Format additional ban information
      $ban['length']           = ($ban['length'] ? Util::SecondsToString($ban['length'] * 60) : $phrases['permanent']);
    }
    
    list($ban_list, $ban_count) = SBPlugins::call('OnGetBans', $ban_list, $ban_count, $this->type, $this->search, $this->hideinactive);
    
    return array('count' => $ban_count,
                 'list'  => $ban_list);
  }
}
?>