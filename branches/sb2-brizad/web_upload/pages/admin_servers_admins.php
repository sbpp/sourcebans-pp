<?php
require_once 'api.php';

$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page(ucwords($phrases['server_admins']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'LIST_SERVERS')))
    throw new Exception($phrases['access_denied']);
  if(!isset($_GET['id']) || !is_numeric($_GET['id']))
    throw new Exception($phrases['invalid_id']);
  
  $admins = SB_API::getAdmins(0, 1, null, null, 'server', $_GET['id']);
  
  $page->assign('admins', $admins['list']);
  $page->display('page_admin_servers_admins');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>