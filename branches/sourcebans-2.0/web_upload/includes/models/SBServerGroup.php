<?php
/**
 * SourceBans server group model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Server Groups
 * @version    $Id$
 */
class SBServerGroup extends BaseRowModel
{
  protected $_key = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'srvgroups';
  }
}