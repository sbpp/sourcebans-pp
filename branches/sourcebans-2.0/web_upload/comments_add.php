<?php
require_once 'init.php';
require_once WRITERS_DIR . 'comments.php';

$userbank = Env::get('userbank');
$phrases  = Env::get('phrases');
$page     = new Page(ucwords($phrases['add_comment']));

try
{
  if(!$userbank->is_admin())
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      CommentsWriter::add($_POST['bid'], $_POST['type'], $_POST['message']);
      
      exit(json_encode(array(
        'redirect' => Env::get('active')
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $page->display('page_comments_add');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>