<?php
/**
 * SourceBans admins test
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Tests
 * @version    $Id$
 */
class SBAdminsTest extends BaseTest
{
  public function run()
  {
    // Add admin
    $id = SB_API::addAdmin('Test', STEAM_AUTH_TYPE, 'STEAM_0:1:23456789', 'test@test.com', 'test');
    
    // Edit admin
    SB_API::editAdmin($id, null, NAME_AUTH_TYPE, 'Test', null, null, true, array(1), 1);
    
    // Delete admin
    SB_API::deleteAdmin($id);
  }
}


return new SBAdminsTest('SourceBans Admins');