<?php
/**
 * SourceBans actions model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Actions
 * @version    $Id$
 */
class SBActions extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'actions';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $actions = array();
    foreach($this->_data as $row)
    {
      $action         = new SBAction();
      $action->admin  = $this->_registry->admins[$row['admin_id']];
      $action->server = $this->_registry->servers[$row['server_id']];
      Util::object_set_values($action, $row);
      
      $actions[$row['id']] = $action;
    }
    
    $this->_data = $actions;
  }
}