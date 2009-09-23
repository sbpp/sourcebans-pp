<?php
require_once 'init.php';

$userbank = Env::get('userbank');

if($userbank->is_admin())
  Util::redirect('admin.php');
if($userbank->is_logged_in())
  Util::redirect('account.php');
if($_SERVER['REQUEST_METHOD'] == 'POST')
{
  try
  {
    if(!$userbank->login($_POST['username'], $_POST['password'], isset($_POST['remember'])))
      throw new Exception('Invalid username or password specified.');
    
    exit(json_encode(array(
      'redirect' => $userbank->is_admin() ? 'admin.php' : 'account.php'
    )));
  }
  catch(Exception $e)
  {
    exit(json_encode(array(
      'error' => $e->getMessage()
    )));
  }
}

$phrases = Env::get('phrases');
$page    = new Page($phrases['login'], !isset($_GET['nofullpage']));
$page->display('page_login');
?>