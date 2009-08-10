<?php
require_once 'init.php';

$userbank = Env::get('userbank');

if($userbank->is_admin())
  header('Location: admin.php');
if($userbank->is_logged_in())
  header('Location: account.php');
if($_SERVER['REQUEST_METHOD'] == 'POST')
{
  if($userbank->login($_POST['username'], $_POST['password'], isset($_POST['remember'])))
    header('Location: admin.php');
  else
    header('Location: login.php');
}
else
{
  $phrases = Env::get('phrases');
  $page    = new Page(ucwords($phrases['login']));
  $page->display('page_login');
}
?>