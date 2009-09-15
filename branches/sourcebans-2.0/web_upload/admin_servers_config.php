<?php
require_once 'init.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page('Database Config');

try
{
  if(!$userbank->HasAccess(array('OWNER')))
    throw new Exception($phrases['access_denied']);
  
  $page->display('page_admin_servers_config');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>