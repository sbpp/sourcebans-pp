<?php
require_once 'init.php';

$userbank = Env::get('userbank');
$page     = new Page('Server RCON');

try
{
  if(!$userbank->HasAccess(SM_RCON . SM_ROOT))
    throw new Exception('Access Denied');
  
  $page->display('page_admin_servers_rcon');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>