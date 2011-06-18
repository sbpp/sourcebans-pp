<?php
/**
 * SourceBans groups test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBGroupsTest extends BaseTest
{
  public function run()
  {
    // Add group
    $id = SB_API::addGroup(SERVER_GROUPS, 'Test', 'z', 99);
    
    // Edit group
    SB_API::editGroup($id, SERVER_GROUPS, null, 'abcde', 10);
    
    // Delete group
    SB_API::deleteGroup($id, SERVER_GROUPS);
  }
}


return new SBGroupsTest('SourceBans Groups');