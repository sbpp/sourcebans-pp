<?php
/**
 * SourceBans country model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Countries
 * @version    $Id$
 */
class SBCountry extends BaseRowModel
{
  protected $_key = 'ip';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'countries';
  }
  
  public function __toString()
  {
    return $this->name;
  }
}