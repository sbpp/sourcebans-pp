<?php
/**
 * SourceBans games test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBGamesTest extends BaseTest
{
  public function run()
  {
    // Add game
    $id = SB_API::addGame('Test', 'test', 'test.gif');
    
    // Edit game
    SB_API::editGame($id, null, null, null, false);
    
    // Delete game
    SB_API::deleteGame($id);
  }
}


return new SBGamesTest('SourceBans Games');