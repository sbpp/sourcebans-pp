<?php
/**
 * SourceBans submissions test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBSubmissionsTest extends BaseTest
{
  public function run()
  {
    // Add submission
    $id = SB_API::addSubmission('STEAM_0:1:23456789', '127.0.0.1', 'Test', 'Testing', 'Tester', 'test@test.com', 1);
    
    // Archive submission
    SB_API::archiveSubmission($id);
    
    // Restore submission
    SB_API::restoreSubmission($id);
    
    // Delete submission
    SB_API::deleteSubmission($id);
  }
}


return new SBSubmissionsTest('SourceBans Submissions');