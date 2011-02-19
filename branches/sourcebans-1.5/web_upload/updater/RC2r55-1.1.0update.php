<?php
/**
 * =============================================================================
 * Update the database structure from RC1d -> RC2
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

echo '- Starting <b>SourceBans</b> database update from RC2 to RC3 -<br />';
$db = ADONewConnection('mysql://' . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

echo '- Altering bans table -<br />';
$res = $db->Execute('ALTER TABLE ' . DB_PREFIX . '_bans
                     ADD         RemovedBy int(8) NULL,
                     ADD         RemoveType VARCHAR(3) NULL,
                     ADD         RemovedOn int(10) NULL,
                     DROP INDEX  authid');
if(!$res)
{
  die('Error altering bans table');
}

echo '- Converting old bans -<br />';
$banhistory = $db->GetAll('SELECT *
                           FROM   ' . DB_PREFIX . '_banhistory');
$ins = $db->Prepare('INSERT INTO ' . DB_PREFIX . '_bans (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, RemovedBy, RemoveType)
                     VALUES      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, "U")');
$upd = $db->Prepare('UPDATE ' . DB_PREFIX . '_demos
                     SET    demid   = ?
                     WHERE  demtype = "B"
                       AND  demid   = ?');

foreach($banhistory as $row)
{
  $db->Execute($ins, array(
    $row['IP'],
    $row['AuthId'],
    $row['Name'],
    $row['Created'],
    $row['Ends'],
    $row['Length'],
    $row['Reason'],
    $row['AdminId'],
    $row['AdminIp'],
    $row['SId'],
    $row['country'],
  ));
  echo '> Updated ban for: <b>' . $row['Name'] . '</b><br />';
  
  $id  = $GLOBALS['db']->Insert_ID();
  $res = $GLOBALS['db']->Execute($upd, array(
    $id,
    $row['HistId'],
  ));
  if(empty($res))
    continue;
  
  echo '  >> Updated demo: <b>' . $row['HistId'] . '</b> > ' . $id . '<br />';
}

echo 'Done updating. Please delete this file.<br />';