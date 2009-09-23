<?php
require_once 'init.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['email'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS', 'BAN_SUBMISSIONS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      Util::mail($_POST['email'], 'noreply@' . $_SERVER['HTTP_HOST'], $_POST['subject'], $_POST['message']);
      
      exit(json_encode(array(
        'redirect' => 'admin_bans.php'
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $page->display('page_admin_bans_email');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>