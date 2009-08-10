<?php
require_once 'init.php';
require_once READERS_DIR . 'comments.php';
require_once WRITERS_DIR . 'comments.php';

$userbank = Env::get('userbank');
$page     = new Page('Edit Comment');

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    CommentsWriter::edit($_POST['id'], $_POST['message']);
  }
  
  $comments_reader = new CommentsReader();
  $comments        = $comments_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']))
    throw new Exception('Invalid ID specified.');
  else
    $id = $_GET['id'];
  
  $page->assign('comment_message', $comments[$id]['message']);
  $page->assign('comments',        $comments);
  $page->display('page_comments_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>