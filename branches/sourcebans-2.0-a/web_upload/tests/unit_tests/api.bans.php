<?php
// This test will run specific ban calls through the API
class CApiBans extends CTest implements ITest
{
  public function runTest()
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

return new CApiBans('API Bans (api.bans.php)');
?>