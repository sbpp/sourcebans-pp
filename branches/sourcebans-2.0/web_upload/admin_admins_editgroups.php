<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';
require_once READERS_DIR . 'groups.php';
require_once WRITERS_DIR . 'admins.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_groups']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    AdminsWriter::edit($_POST['id'], null, null, null, null, null, null, null, $_POST['srv_group'], $_POST['web_group']);
    
    Util::redirect();
  }
  
  $admins_reader       = new AdminsReader();
  $groups_reader       = new GroupsReader();
  
  $admins              = $admins_reader->executeCached(ONE_MINUTE * 5);
  
  $groups_reader->type = SERVER_ADMIN_GROUPS;
  $server_admin_groups = $groups_reader->executeCached(ONE_MINUTE * 5);
  
  $groups_reader->type = WEB_ADMIN_GROUPS;
  $web_admin_groups    = $groups_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($admins[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $id                  = $_GET['id'];
  
  $page->assign('admin_name',          $admins[$id]['name']);
  $page->assign('admin_srv_group',     $admins[$id]['srv_groups']);
  $page->assign('admin_web_group',     $admins[$id]['group_id']);
  $page->assign('server_admin_groups', $server_admin_groups);
  $page->assign('web_admin_groups',    $web_admin_groups);
  $page->display('page_admin_admins_editgroups');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>