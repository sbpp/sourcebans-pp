<?php
/**
 * SourceBans admin model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Admins
 * @version    $Id$
 */
class SBAdmin extends BaseRowModel
{
  protected $_key = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'admins';
  }
  
  
  public function hasAccess($flags)
  {
    // TODO: Add permission check
    return true;
  }
}