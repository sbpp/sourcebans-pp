<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';

$userbank = Env::get('userbank');
$page     = new Page('Server Admins');

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_SERVERS')))
    throw new Exception('Access Denied');
  if(!isset($_GET['id']) || !is_numeric($_GET['id']))
    throw new Exception('Invalid ID specified.');
  
  $admins_reader            = new AdminsReader();
  
  $admins_reader->server_id = $_GET['id'];
  $admins                   = $admins_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('admins',      $admins);
  $page->assign('admin_count', count($admins));
  $page->display('page_admin_servers_admins');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>