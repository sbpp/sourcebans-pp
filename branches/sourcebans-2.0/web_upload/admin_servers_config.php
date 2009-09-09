<?php
require_once 'init.php';

$userbank = Env::get('userbank');
$page     = new Page('Database Config');

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER')))
    throw new Exception('Access Denied');
  
  $page->display('page_admin_servers_config');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>