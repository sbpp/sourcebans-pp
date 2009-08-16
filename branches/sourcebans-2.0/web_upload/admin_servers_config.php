<?php
require_once 'init.php';

$userbank = Env::get('userbank');
$page     = new Page('Database Config');

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER')))
    throw new Exception('Access Denied');
  
  $page->assign('db_host', DB_HOST == 'localhost' ? $_SERVER['SERVER_ADDR'] : DB_HOST);
  $page->assign('db_user', DB_USER);
  $page->assign('db_pass', DB_PASS);
  $page->assign('db_name', DB_NAME);
  $page->assign('db_port', DB_PORT);
  $page->display('page_admin_servers_config');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>