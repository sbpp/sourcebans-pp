<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');

try
{
  if(!$userbank->HasAccess(array('OWNER')) && !SB_API::getSetting('config.exportpublic'))
    throw new Exception($phrases['access_denied']);
  
  $type = isset($_GET['type']) && is_numeric($_GET['type']) ? $_GET['type'] : STEAM_BAN_TYPE;
  
  $bans = SB_API::getBans(true, 0, 1, null, null, 0, 'length');
  
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
}
catch(Exception $e)
{
  $page = new Page($phrases['error']);
  
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>