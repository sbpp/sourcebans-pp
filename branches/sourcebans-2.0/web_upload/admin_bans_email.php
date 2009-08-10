<?php
require_once 'init.php';

$userbank = Env::get('userbank');
$page     = new Page('Email');

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS', 'ADMIN_BAN_SUBMISSIONS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    Util::mail($_POST['email'], 'noreply@' . $_SERVER['HTTP_HOST'], $_POST['subject'], $_POST['message']);
    
    Util::redirect();
  }
  
  $page->display('page_admin_bans_email');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>