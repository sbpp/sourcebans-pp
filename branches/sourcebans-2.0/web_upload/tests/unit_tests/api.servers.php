<?php
// This test will run specific server calls through the API
class CApiServers extends CTest implements ITest
{
  public function runTest()
  {
    // Add server
    $id = SB_API::addServer('127.0.0.1', 27015, 'testing', 1);
    
    // Edit server
    SB_API::editServer($id, null, 27016, null, 2, null, false, array(1));
    
    // Delete server
    SB_API::deleteServer($id);
  }
}

return new CApiServers('API Servers (api.servers.php)');
?>