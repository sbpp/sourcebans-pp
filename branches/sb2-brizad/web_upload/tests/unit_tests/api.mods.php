<?php
// This test will run specific mod calls through the API
class CApiMods extends CTest implements ITest
{
  public function runTest()
  {
    // Add mod
    $id = SB_API::addMod('Test', 'test', 'test.gif');
    
    // Edit mod
    SB_API::editMod($id, null, null, null, false);
    
    // Delete mod
    SB_API::deleteMod($id);
  }
}

return new CApiMods('API Mods (api.mods.php)');
?>