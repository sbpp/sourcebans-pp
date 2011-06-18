<?php
/**
 * SourceBans protests test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBProtestsTest extends BaseTest
{
  public function run()
  {
    // Add protest
    $id = SB_API::addProtest('Test', STEAM_BAN_TYPE, 'STEAM_0:1:23456789', '127.0.0.1', 'Testing', 'test@test.com');
    
    // Archive protest
    SB_API::archiveProtest($id);
    
    // Restore protest
    SB_API::restoreProtest($id);
    
    // Delete protest
    SB_API::deleteProtest($id);
  }
}


return new SBProtestsTest('SourceBans Protests');