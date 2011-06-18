<?php
/**
 * SourceBans permissions model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Permissions
 * @version    $Id$
 */
class SBPermissions extends BaseTableModel
{
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'permissions';
  }
  
  
  protected function _fetch()
  {
    parent::_fetch($this->_registry->one_day);
  }
}