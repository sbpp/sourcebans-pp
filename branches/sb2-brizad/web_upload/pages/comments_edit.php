<?php
require_once 'api.php';

$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page(ucwords($phrases['edit_comment']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      SB_API::editComment($_POST['id'], $_POST['message']);
      
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
          $redirect = SBConfig::getEnv('active');
          break;
      }
      
      exit(json_encode(array(
        'redirect' => Util::buildUrl(array(
          '_' => $redirect
        ))
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $comment = SB_API::getComment($_GET['id']);
  
  $page->assign('comment_message', $comment['message']);
  $page->assign('comment_type',    $comment['type']);
  $page->assign('comments',        $comments);
  $page->display('page_comments_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>