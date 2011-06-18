<?php
/**
 * SourceBans admins model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Admins
 * @version    $Id$
 */
class SBAdmins extends BaseTableModel
{
  protected $_sort = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'admins';
  }
  
  
  public function encrypt_password($password)
  {
    return sha1(sha1($this->_registry->sb_salt . $password));
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $admins = array();
    foreach($this->_data as $row)
    {
      $admin            = new SBAdmin();
      $admin->web_group = $this->_registry->web_groups[$row['group_id']];
      Util::object_set_values($admin, $row);
      
      $admins[$row['id']] = $admin;
    }
    
    $this->_data = $admins;
  }
}