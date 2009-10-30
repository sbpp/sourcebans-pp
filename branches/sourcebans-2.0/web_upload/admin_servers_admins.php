<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['server_admins']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'LIST_SERVERS')))
    throw new Exception($phrases['access_denied']);
  if(!isset($_GET['id']) || !is_numeric($_GET['id']))
    throw new Exception($phrases['invalid_id']);
  
  $admins_reader         = new AdminsReader();
  
  $admins_reader->search = $_GET['id'];
  $admins_reader->type   = 'server';
  $admins                = $admins_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('admins', $admins['list']);
  $page->display('page_admin_servers_admins');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>