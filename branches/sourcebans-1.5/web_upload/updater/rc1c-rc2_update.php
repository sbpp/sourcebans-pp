<?php
/**
 * =============================================================================
 * Update the database structure from RC1c -> RC2
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */
define('IN_SB', true);
define('ROOT', dirname(__FILE__) . '/../');
define('INCLUDES_PATH', ROOT . 'includes/');

require_once ROOT . '../config.php';
require_once INCLUDES_PATH . 'adodb/adodb.inc.php';

echo '- Starting <b>SourceBans</b> database update from RC1c to RC1d -<br />';
$db = ADONewConnection('mysql://' . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

$db->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
              VALUES      ("config.dateformat", "m-d-y H:i")');
$db->Execute('INSERT INTO ' . DB_PREFIX . '_settings (setting, value)
              VALUES      ("config.timezone", "Europe/London")');

$db->Execute('ALTER TABLE   ' . DB_PREFIX . '_admins
              MODIFY COLUMN validate VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL');

$res = $db->GetRow('SELECT immunity
                    FROM   ' . DB_PREFIX . '_admins');
if($res)
{
  die('The table structure is already up-to-date. Please delete this file.');
}

$res = $db->Execute('ALTER TABLE ' . DB_PREFIX . '_admins
                     ADD         immunity INT( 10 ) NOT NULL DEFAULT "0",
                     ADD         srv_group VARCHAR( 128 ) NULL ,
                     ADD         srv_flags VARCHAR( 64 ) NULL,
                     ADD         srv_password VARCHAR( 128 ) NULL');
if(!$res)
{
  die('There was an error altering the table structure.');
}

echo 'Table structure successfully altered...<br />';

$srvadmins = $db->GetAll('SELECT *
                          FROM ' . DB_PREFIX . '_srvadmins');
echo 'Found: ' . count($srvadmins) . ' admins...<br /><br /><br />';

$error = true;
foreach($srvadmins AS $sa)
{
  echo 'Updating entry for: ' . $sa['name'] . '...';
  $res = $db->Execute('UPDATE ' . DB_PREFIX . '_admins
                       SET    immunity     = ?,
                              srv_group    = ?,
                              srv_flags    = ?,
                              srv_password = ?
                       WHERE  authid       = ?',
                      array($sa['immunity'], $sa['groups'], $sa['flags'], $sa['password'], $sa['identity']));
  
  echo $res ? ' <b>Done</b><br />' : ' <b>Failed</b><br />';
  if($res)
    continue;
  
  $error = true;  
}
if($error)
{
  echo '<br /><br />There were some failed admin imports. Old admins table will <b>not</b> be deleted.<br />';
}
else
{
  echo '<br /><br />Deleting old admins table...';
  $res = $db->Execute('DROP TABLE ' . DB_PREFIX . '_srvadmins');
  
  echo $res ? ' <b>Done</b><br />' : ' <b>Failed</b><br />';
}

echo 'Done updating. Please delete this file.<br />';