<?php
// This test will run specific group calls through the API
class CApiGroups extends CTest implements ITest
{
  public function runTest()
  {
    // Add group
    $id = SB_API::addGroup(SERVER_GROUPS, 'Test', 'z', 99);
    
    // Edit group
    SB_API::editGroup($id, SERVER_GROUPS, null, 'abcde', 10);
    
    // Delete group
    SB_API::deleteGroup($id, SERVER_GROUPS);
  }
}

return new CApiGroups('API Groups (api.groups.php)');
?>