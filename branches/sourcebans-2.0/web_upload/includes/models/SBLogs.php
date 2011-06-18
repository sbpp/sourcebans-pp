<?php
/**
 * SourceBans logs model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Logs
 * @version    $Id$
 */
class SBLogs extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'logs';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_day);
    
    $logs = array();
    foreach($this->_data as $row)
    {
      $log        = new SBLog();
      $log->admin = $this->_registry->admins[$row['admin_id']];
      Util::object_set_values($log, $row);
      
      $logs[$row['id']] = $log;
    }
    
    $this->_data = $logs;
  }
}