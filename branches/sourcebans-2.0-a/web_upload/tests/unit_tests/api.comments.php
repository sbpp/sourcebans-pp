<?php
// This test will run specific comment calls through the API
class CApiComments extends CTest implements ITest
{
  public function runTest()
  {
    // Add comment
    $id = SB_API::addComment(1, BAN_TYPE, 'Test');
    
    // Edit comment
    SB_API::editComment($id, 'Testing');
    
    // Delete comment
    SB_API::deleteComment($id);
  }
}

return new CApiComments('API Comments (api.comments.php)');
?>