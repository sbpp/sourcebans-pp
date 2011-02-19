<?php
define('IN_SB', true);

require_once '../config.php';
require_once '../includes/adodb/adodb.inc.php';
include_once '../includes/adodb/adodb-errorhandler.inc.php';

function convertAmxbans($fromdsn, $todsn, $fromprefix, $toprefix)
{
  set_time_limit(0); // Never time out
  ob_start();
  
  $olddb = ADONewConnection($fromdsn);
  if(!$olddb)
    die('Failed to connect to AMXBans database');
  
  $newdb = ADONewConnection($todsn);
  if(!$newdb)
    die('Failed to connect to SourceBans database');
  
  $olddb->Execute('SET NAMES utf8');
  $newdb->Execute('SET NAMES utf8');
  
  // Bans
  echo 'Converting ' . $fromprefix . '_bans...';
  ob_flush();
  flush();
  
  $bans = $olddb->GetAll('SELECT player_ip, player_id, player_nick, ban_created, ban_length, ban_reason, admin_ip
                          FROM ' . $fromprefix . '_bans');
  $q = $newdb->Prepare('INSERT INTO ' . $toprefix . '_bans (ip, authid, name, created, ends, length, reason, adminIp, aid)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
  
  $time = time();
  foreach($bans as $row)
  {
    // If ban has expired, ignore
    if($time > $row['ban_created'] + $row['ban_length'] && $row['ban_length'] > 0)
      continue;
    
    $values = array(
      $row['player_ip'],
      $row['player_id'],
      $row['player_nick'],
      $row['ban_created'],
      $row['ban_length'] > 0 ? $row['ban_created'] + $row['ban_length'] : 0,
      $row['ban_length'],
      $row['ban_reason'],
      $row['admin_ip'],
    );
    
    foreach($values as $i => $value)
    {
      if(!is_null($value))
        continue;
      
      $values[$i] = '';
    }
    
    $newdb->Execute($q, $values);
  }
  echo ' Done<br />';
  
  /*
  // Ban History
  echo 'Converting ' . $fromprefix . '_banhistory...';
  ob_flush();
  
  $banhistory = $olddb->GetAll('SELECT player_ip, player_id, player_nick, ban_created, ban_length, ban_reason, admin_ip, admin_id, admin_nick, server_ip, server_name, unban_created
                                FROM ' . $fromprefix . '_banhistory');
  $q = $newdb->Prepare('INSERT INTO ' . $toprefix . '_banhistory (Type, ip, authid, name, created, ends, length, reason, adminIp, Adminid, RemovedOn, RemovedBy)
                        VALUES ("U", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  
  foreach($banhistory as $row)
  {
    $values = array(
      $row['player_ip'],
      $row['player_id'],
      $row['player_nick'],
      $row['ban_created'],
      $row['ban_length'] > 0 ? $row['ban_created'] + $row['ban_length'] : 0,
      $row['ban_length'],
      $row['ban_reason'],
      $row['admin_ip'],
      $row['admin_id'],
      $row['admin_nick'],
      $row['admin_id'],
    );
    
    foreach($values as $i => $value)
    {
      if(!is_null($value))
        continue;
      
      $values[$i] = '';
    }
    
    $newdb->Execute($q, $values);
  }
  echo ' Done<br />';
  
  // Levels
  echo 'Converting ' . $fromprefix . '_levels...';
  ob_flush();
  
  $levels = $olddb->GetAll('SELECT level, bans_add, bans_edit, bans_delete, bans_unban, bans_import, bans_export,
                                   amxadmins_view, amxadmins_edit, webadmins_view, webadmins_edit, permissions_edit, servers_edit
                            FROM ' . $fromprefix . '_levels');
  $q = $newdb->Prepare('INSERT INTO ' . $toprefix . '_groups (type, name, flags)
                        VALUES (1, ?, ?)');
  
  $groups = array();
  foreach($levels as $row)
  {
    $flags = 0;
    if($row['bans_add'] == 'yes' || $row['bans_edit'] == 'yes' || $row['bans_delete'] == 'yes' || $row['bans_unban'] == 'yes')
    {
      $flags |= ADMIN_WEB_BANS;
    }
    // amxadmins_view is ignored
    if($row['amxadmins_view'] == 'yes')
    {
      $flags |= ADMIN_SERVER_ADMINS;
    }
    // webadmins_view is ignored
    if($row['webadmins_view'] == 'yes')
    {
      $flags |= ADMIN_WEB_AGROUPS;
    }
    if($row['permissions_edit'] == 'yes')
    {
      $flags |= ADMIN_WEB_AGROUPS | ADMIN_SERVER_AGROUPS;
    }
    if($row['servers_edit'] == 'yes')
    {
      $flags |= ADMIN_SERVER_ADD | ADMIN_SERVER_REMOVE | ADMIN_SERVER_GROUPS;
    }
    if($row['level'] == '1')
    {
      $flags |= ADMIN_OWNER;
    }
    
    $newdb->Execute($q, array(
      'AMXBANS_' . $row['level'],
      $flags,
    ));
    $groups[$row['level']] = $newdb->Insert_ID();
  }
  echo ' Done<br />';
  
  // Admins
  echo 'Converting ' . $fromprefix . '_admins...';
  ob_flush();
  
  $admins = $olddb->GetAll('SELECT username, level
                            FROM ' . $fromprefix . '_webadmins');
  $q = $newdb->Prepare('INSERT INTO ' . $toprefix . '_admins (user, gid)
                        VALUES (?, ?)');
  
  foreach($admins as $row)
  {
    $newdb->Execute($q, array(
      $row['username'],
      $groups[$row['level']],
    ));
  }
  echo ' Done<br />';
  */
}