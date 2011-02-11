<?php
require_once 'api.php';

$userbank = SBConfig::getEnv('userbank');
$phrases = SBConfig::getEnv('phrases');

if($userbank->is_admin())
  Util::redirect('admin.php');
if($userbank->is_logged_in())
  Util::redirect('account.php');
if($_SERVER['REQUEST_METHOD'] == 'POST')
{
  try
  {
    if(!$userbank->login($_POST['username'], $_POST['password'], isset($_POST['remember'])))
      throw new Exception($phrases['invalid_login']);
    
    exit(json_encode(array(
      'redirect' => Util::buildUrl(array('_' => $userbank->is_admin() ? 'admin.php' : 'account.php'
      ))
    )));
  }
  catch(Exception $e)
  {
    exit(json_encode(array('error' => $e->getMessage())));
  }
}

$page    = new Page($phrases['login'], !isset($_GET['nofullpage']));
$page->display('page_login');
?>