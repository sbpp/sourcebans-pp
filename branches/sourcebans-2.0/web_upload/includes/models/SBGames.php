<?php
/**
 * SourceBans games model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Games
 * @version    $Id$
 */
class SBGames extends BaseTableModel
{
  protected $_sort = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'games';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_day);
    
    $games = array();
    foreach($this->_data as $row)
    {
      $game = new SBGame();
      Util::object_set_values($game, $row);
      
      $games[$row['id']] = $game;
    }
    
    $this->_data = $games;
  }
}