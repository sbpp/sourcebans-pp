<?php
/**
 * SourceBans servers model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Servers
 * @version    $Id$
 */
class SBServers extends BaseTableModel
{
  protected $_sort = 'game_id';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'servers';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute);
    
    $servers = array();
    foreach($this->_data as $row)
    {
      $server       = new SBServer();
      $server->game = $this->_registry->games[$row['game_id']];
      Util::object_set_values($server, $row);
      
      $servers[$row['id']] = $server;
    }
    
    $this->_data = $servers;
  }
}