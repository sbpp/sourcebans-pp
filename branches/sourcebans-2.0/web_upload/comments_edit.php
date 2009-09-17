<?php
require_once 'init.php';
require_once READERS_DIR . 'comments.php';
require_once WRITERS_DIR . 'comments.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page('Edit Comment');

try
{
  if(!$userbank->HasAccess(array('OWNER')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      CommentsWriter::edit($_POST['id'], $_POST['message']);
      
      switch($_POST['type'])
      {
        case BAN_COMMENTS:
          $redirect = 'banlist.php';
          break;
        case PROTEST_COMMENTS:
          $redirect = 'admin_bans.php#protests/current';
          break;
        case SUBMISSION_COMMENTS:
          $redirect = 'admin_bans.php#submissions/current';
          break;
        default:
          $redirect = Env::get('active');
          break;
      }
      
      exit(json_encode(array(
        'redirect' => $redirect
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $comments_reader = new CommentsReader();
  $comments        = $comments_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($comments[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $comment         = $comments[$_GET['id']];
  
  $page->assign('comment_message', $comment['message']);
  $page->assign('comment_type',    $comments[$id]['type']);
  $page->assign('comments',        $comments);
  $page->display('page_comments_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>