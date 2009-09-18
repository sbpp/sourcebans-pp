<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';
require_once WRITERS_DIR . 'admins.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_details']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      if($_POST['password'] != $_POST['password_confirm'])
          throw new Exception('The passwords don\'t match.');
      
      AdminsWriter::edit($_POST['id'], $_POST['name'], $_POST['auth'], $_POST['identity'], $_POST['email'], $_POST['password']);
      
      exit(json_encode(array(
        'redirect' => 'admin_admins.php'
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $admins_reader = new AdminsReader();
  $admins        = $admins_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($admins[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $admin         = $admins[$_GET['id']];
  
  $page->assign('admin_name',             $admin['name']);
  $page->assign('admin_type',             $admin['auth']);
  $page->assign('admin_identity',         $admin['identity']);
  $page->assign('admin_email',            $admin['email']);
  $page->assign('permission_change_pass', $userbank->HasAccess(array('OWNER')) || $_GET['id'] == $userbank->GetID());
  $page->display('page_admin_admins_editdetails');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>