<?php
/**
 * SourceBans bans model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Bans
 * @version    $Id$
 */
class SBBans extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'bans';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    // TODO: CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id
    parent::_fetch($this->_registry->one_minute * 5);
    
    $bans = array();
    foreach($this->_data as $row)
    {
      $ban              = new SBBan();
      $ban->admin       = $this->_registry->admins[$row['admin_id']];
      $ban->country     = $this->_registry->countries[$row['ip']];
      $ban->server      = $this->_registry->servers[$row['server_id']];
      $ban->unban_admin = $this->_registry->admins[$row['unban_admin_id']];
      Util::object_set_values($ban, $row);
      
      $bans[$row['id']] = $ban;
    }
    
    $this->_data = $bans;
  }
}