<?php
/**
 * SourceBans quotes model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Quotes
 * @version    $Id$
 */
class SBQuotes extends BaseTableModel
{
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'quotes';
  }
  
  
  public function getRandom()
  {
    $this->_fetch($this->_registry->one_day);
    
    return $this->_data[array_rand($this->_data)];
  }
}