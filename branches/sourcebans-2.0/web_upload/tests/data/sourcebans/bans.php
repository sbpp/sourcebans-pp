<?php
/**
 * SourceBans bans test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBBansTest extends BaseTest
{
  public function run()
  {
    // Add ban
    $id = SB_API::addBan(STEAM_BAN_TYPE, 'STEAM_0:0:0', '127.0.0.1', 'Test', 'Testing', 60);
    
    // Edit ban
    SB_API::editBan($id, IP_BAN_TYPE, null, null, null, null, 240);
    
    // Unban ban
    SB_API::unbanBan($id, 'Test');
    
    // Delete ban
    SB_API::deleteBan($id);
  }
}


return new SBBansTest('SourceBans Bans');