<?php
/**
 * SourceBans countries model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Countries
 * @version    $Id$
 */
class SBCountries extends BaseTableModel
{
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'countries';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $countries = array();
    foreach($this->_data as $row)
    {
      $country = new SBCountry();
      Util::object_set_values($country, $row);
      
      $countries[$row['ip']] = $country;
    }
    
    $this->_data = $countries;
  }
}