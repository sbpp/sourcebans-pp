<?php
require_once 'init.php';

$exportpublic = (isset($GLOBALS['config']['config.exportpublic']) && $GLOBALS['config']['config.exportpublic'] == 1);
if(!$userbank->HasAccess(ADMIN_OWNER) && !$exportpublic)
{
  echo "You don't have access to this feature.";
}
else if(!isset($_GET['type']))
{
  echo "You have to specify the ban type. Only follow links!";
}
else if($_GET['type'] == 'steam')
{
  header('Content-Type: application/x-httpd-php php');
  header('Content-Disposition: attachment; filename="banned_user.cfg"');
  
  $bans = $GLOBALS['db']->GetAll('SELECT authid
                                  FROM   ' . DB_PREFIX . '_bans
                                  WHERE  length = 0
                                    AND  RemoveType IS NULL
                                    AND  type = 0');
  foreach($bans as $ban)
  {
    echo 'banid 0 ' . $ban['authid'] . "\r\n";
  }
}
else if($_GET['type'] == 'ip')
{
  header('Content-Type: application/x-httpd-php php');
  header('Content-Disposition: attachment; filename="banned_ip.cfg"');
  
  $bans = $GLOBALS['db']->GetAll('SELECT ip
                                  FROM   ' . DB_PREFIX . '_bans
                                  WHERE  length = 0
                                    AND RemoveType IS NULL
                                    AND type = 1');
  foreach($bans as $ban)
  {
    echo 'addip 0 ' . $ban['ip'] . "\r\n";
  }
}