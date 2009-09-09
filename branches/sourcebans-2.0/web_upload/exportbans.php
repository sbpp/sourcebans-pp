<?php
require_once 'init.php';

$config   = Env::get('config');
$userbank = Env::get('userbank');

if(!$userbank->HasAccess(array('ADMIN_OWNER')) && !$config['config.exportpublic'])
  throw new Exception('Access Denied.');

require_once READERS_DIR . 'bans.php';

$type                      = isset($_GET['type']) && is_numeric($_GET['type']) ? $_GET['type'] : STEAM_BAN_TYPE;

$bans_reader               = new BansReader();

$bans_reader->search       = 0;
$bans_reader->type         = 'length';
$bans_reader->hideinactive = true;
$bans                      = $bans_reader->executeCached(ONE_MINUTE * 5);

header('Content-Type: application/x-httpd-php php');
header('Content-Disposition: attachment; filename="banned_' . ($type == IP_BAN_TYPE ? 'ip' : 'user') . '.cfg"');

foreach($bans['list'] as $ban)
{
  if($ban['type'] != $type)
    continue;
  
  if($type      == STEAM_BAN_TYPE)
    echo 'banid 0 ' . $ban['steam'] . "\t\t\t// " . $ban['name'] . PHP_EOL;
  else if($type == IP_BAN_TYPE)
    echo 'banip 0 ' . $ban['ip']    . "\t\t\t// " . $ban['name'] . PHP_EOL;
}
?>