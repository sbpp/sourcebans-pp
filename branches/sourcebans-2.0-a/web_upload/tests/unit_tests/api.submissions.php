<?php
// This test will run specific submission calls through the API
class CApiSubmissions extends CTest implements ITest
{
  public function runTest()
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

return new CApiSubmissions('API Submissions (api.submissions.php)');
?>