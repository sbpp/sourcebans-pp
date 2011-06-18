<?php
/**
 * SourceBans servers test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBServersTest extends BaseTest
{
  public function run()
  {
    // Add server
    $id = SB_API::addServer('127.0.0.1', 27015, 'testing', 1);
    
    // Edit server
    SB_API::editServer($id, null, 27016, null, 2, null, false, array(1));
    
    // Delete server
    SB_API::deleteServer($id);
  }
}


return new SBServersTest('SourceBans Servers');