<?php
require_once 'init.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page('Server RCON', !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(SM_RCON . SM_ROOT))
    throw new Exception($phrases['access_denied']);
  
  $page->display('page_admin_servers_rcon');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>