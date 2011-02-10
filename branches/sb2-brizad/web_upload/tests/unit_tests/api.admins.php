<?php
// This test will run specific admin calls through the API
class CApiAdmins extends CTest implements ITest
{
  public function runTest()
  {
    // Add admin
    $id = SB_API::addAdmin('Test', STEAM_AUTH_TYPE, 'STEAM_0:1:23456789', 'test@test.com', 'test');
    
    // Edit admin
    SB_API::editAdmin($id, null, NAME_AUTH_TYPE, 'Test', null, null, true, array(1), 1);
    
    // Delete admin
    SB_API::deleteAdmin($id);
  }
}

return new CApiAdmins('API Admins (api.admins.php)');
?>