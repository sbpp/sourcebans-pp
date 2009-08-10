<?php
require_once 'init.php';

$config   = Env::get('config');
$userbank = Env::get('userbank');

if($userbank->HasAccess(SM_RCON . SM_ROOT))
{
  require_once READERS_DIR . 'servers.php';
  
  $servers_reader = new ServersReader();
  
  $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  header('Content-Type: application/x-httpd-php php');
  header('Content-Disposition: attachment; filename="sourcebans.sslf"');
?>
SSLF - Shared Server List Format - Version 1.01

Name="SourceBans"

<?
  foreach($servers as $server)
    printf('Server="" %s:%d "" ""%s', $server['ip'], $server['port'], PHP_EOL);
}
?>