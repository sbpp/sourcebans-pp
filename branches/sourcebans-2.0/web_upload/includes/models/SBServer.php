<?php
/**
 * SourceBans server model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Servers
 * @version    $Id$
 */
class SBServer extends BaseRowModel
{
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'servers';
  }
}