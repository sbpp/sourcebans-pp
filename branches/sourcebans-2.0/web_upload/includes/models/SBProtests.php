<?php
/**
 * SourceBans protests model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Protests
 * @version    $Id$
 */
class SBProtests extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'protests';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $protests = array();
    foreach($this->_data as $row)
    {
      $protest      = new SBProtest();
      $protest->ban = $this->_registry->bans[$row['ban_id']];
      Util::object_set_values($protest, $row);
      
      $protests[$row['id']] = $protest;
    }
    
    $this->_data = $protests;
  }
}