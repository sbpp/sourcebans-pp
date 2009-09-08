<?php
require_once READER;
require_once READERS_DIR . 'comments.php';
require_once LIB_DIR     . 'geoip/geoip.inc.php';

class BansReader extends SBReader
{
  public $hideinactive = false;
  public $limit        = 0;
  public $page         = 1;
  public $search;
  public $sort         = 'created DESC';
  public $type;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $config  = Env::get('config');
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    $geoip   = geoip_open(LIB_DIR . 'geoip/GeoIP.dat', GEOIP_STANDARD);
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
          $where = 'created > ' . $time . ' AND created < ' . $time2;
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
      $where .= ' AND (ends - created = 0 OR ends > UNIX_TIMESTAMP()) AND unban_admin_id IS NULL';
    
    // Fetch bans
    $ban_count = $db->GetOne('SELECT COUNT(*)
                              FROM   ' . Env::get('prefix') . '_bans AS ba
                              WHERE  ' . $where);
    $ban_list  = $db->GetAssoc('SELECT    ba.id, ba.type, ba.steam, ba.ip, ba.name, ba.created, ba.ends, ba.ends - ba.created AS length, ba.reason, ba.server_id, ba.admin_id, ba.admin_ip, ba.unban_admin_id,
                                          ba.unban_reason, ba.unban_time, se.ip AS server_ip, se.port AS server_port, IFNULL(ad.name, "CONSOLE") AS admin_name, un.name AS unban_admin_name, mo.name AS mod_name, mo.icon AS mod_icon,
                                          76561197960265728 + CAST(SUBSTR(ba.steam, 9, 1) AS UNSIGNED) + CAST(SUBSTR(ba.steam, 11) * 2 AS UNSIGNED) AS community_id,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_bans   WHERE steam  = ba.steam OR  ip   = ba.ip) AS ban_count,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_blocks WHERE ban_id = ba.id)                     AS block_count,
                                          (SELECT COUNT(*) FROM ' . Env::get('prefix') . '_demos  WHERE ban_id = ba.id    AND type = "B")   AS demo_count
                                FROM      ' . Env::get('prefix') . '_bans    AS ba
                                LEFT JOIN ' . Env::get('prefix') . '_admins  AS ad ON ad.id = ba.admin_id
                                LEFT JOIN ' . Env::get('prefix') . '_admins  AS un ON un.id = ba.unban_admin_id
                                LEFT JOIN ' . Env::get('prefix') . '_servers AS se ON se.id = ba.server_id
                                LEFT JOIN ' . Env::get('prefix') . '_mods    AS mo ON mo.id = se.mod_id
                                WHERE     ' . $where             . '
                                ORDER BY  ' . $this->sort        .
                                ($this->limit > 0 ? ' LIMIT ' . ($this->page - 1) * $this->limit . ',' . $this->limit : ''));
    
    // Process bans
    foreach($ban_list as $id => &$ban)
    {
      // If ban contains an IP address, fetch country information
      if(!empty($ban['ip']))
      {
        $ban['country_code'] = geoip_country_code_by_addr($geoip, $ban['ip']);
        $ban['country_name'] = geoip_country_name_by_addr($geoip, $ban['ip']);
      }
      
      // Check if ban has been either unbanned or expired
      if(!empty($ban['unban_admin_id']))
        $ban['status'] = $phrases['unbanned'];
      else if($ban['length'] && $ban['ends'] < time())
        $ban['status'] = $phrases['expired'];
      
      // Fetch comments for this ban
      $comments_reader         = new CommentsReader();
      $comments_reader->ban_id = $id;
      $comments_reader->type   = BAN_COMMENTS;
      $ban['comments']         = $comments_reader->executeCached(ONE_DAY);
      
      // Format additional ban information
      $ban['length']           = ($ban['length'] ? Util::SecondsToString($ban['length']) : $phrases['permanent']);
    }
    
    geoip_close($geoip);
    
    list($ban_list, $ban_count) = SBPlugins::call('OnGetBans', $ban_list, $ban_count, $this->type, $this->search, $this->hideinactive);
    
    return array('count' => $ban_count,
                 'list'  => $ban_list);
  }
}
?>