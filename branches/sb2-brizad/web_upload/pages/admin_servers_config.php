<?php
require_once 'api.php';

$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page('Database Config', !isset($_GET['nofullpage']));

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