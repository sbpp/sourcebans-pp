<?php
/**
 * SourceBans game model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Games
 * @version    $Id$
 */
class SBGame extends BaseRowModel
{
  protected $_key = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'games';
  }
}