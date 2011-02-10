<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');

try
{
  if(!$userbank->HasAccess(SM_RCON . SM_ROOT))
    throw new Exception($phrases['access_denied']);
  
  header('Content-Type: application/x-httpd-php php');
  header('Content-Disposition: attachment; filename="sourcebans.sslf"');
?>
SSLF - Shared Server List Format - Version 1.01

Name="SourceBans"

<?
  foreach(SB_API::getServers() as $server)
    printf('Server="" %s:%d "" ""%s', $server['ip'], $server['port'], PHP_EOL);
}
catch(Exception $e)
{
  $page = new Page($phrases['error']);
  
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>