<?php
/**
 * SourceBans comments test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBCommentsTest extends BaseTest
{
  public function run()
  {
    // Add comment
    $id = SB_API::addComment(1, BAN_TYPE, 'Test');
    
    // Edit comment
    SB_API::editComment($id, 'Testing');
    
    // Delete comment
    SB_API::deleteComment($id);
  }
}


return new SBCommentsTest('SourceBans Comments');