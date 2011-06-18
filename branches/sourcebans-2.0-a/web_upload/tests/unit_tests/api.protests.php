<?php
// This test will run specific protest calls through the API
class CApiProtests extends CTest implements ITest
{
  public function runTest()
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

return new CApiProtests('API Protests (api.protests.php)');
?>