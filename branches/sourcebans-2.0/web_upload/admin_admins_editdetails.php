<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';
require_once WRITERS_DIR . 'admins.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_details']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    if($_POST['password'] != $_POST['password_confirm'])
        throw new Exception('The passwords don\'t match.');
    
    AdminsWriter::edit($_POST['id'], $_POST['name'], $_POST['auth'], $_POST['identity'], $_POST['email'], $_POST['password']);
    
    Util::redirect();
  }
  
  $admins_reader = new AdminsReader();
  $admins        = $admins_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($admins[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $id            = $_GET['id'];
  
  $page->assign('admin_name',             $admins[$id]['name']);
  $page->assign('admin_type',             $admins[$id]['auth']);
  $page->assign('admin_identity',         $admins[$id]['identity']);
  $page->assign('admin_email',            $admins[$id]['email']);
  $page->assign('permission_change_pass', $userbank->HasAccess(array('ADMIN_OWNER')) || $id == $userbank->GetID());
  $page->display('page_admin_admins_editdetails');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>