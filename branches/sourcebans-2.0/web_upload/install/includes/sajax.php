<?php
/**
 * =============================================================================
 * AJAX Callback handler
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * $Id: sajax.php 140 2009-02-11 18:30:00Z tsunami $
 * =============================================================================
 */

require_once LIB_DIR . 'adodb/adodb-exceptions.inc.php';
require_once LIB_DIR . 'adodb/adodb.inc.php';

sAJAX::register('SetupAdmin');
sAJAX::register('SetupDatabase');

function SetupAdmin($username, $password, $confirm_password, $email, $auth, $identity)
{
  try
  {
    if($password != $confirm_password)
      throw new Exception($phrases['passwords_do_not_match']);
    
    require_once BASE_PATH . 'api.php';
    
    $web_group = GroupsWriter::add(WEB_GROUPS, 'Owner', array('OWNER'));
    AdminsWriter::add($username, $auth, $identity, $email, $password, array(), $web_group);
  }
  catch(Exception $e)
  {
    return array(
      'error' => $e->getMessage()
    );
  }
}

function SetupDatabase($host, $port, $user, $pass, $name, $prefix)
{
  try
  {
    $db      = NewADOConnection('mysql://' . $user . ':' . $pass . '@' . $host . ':' . $port . '/' . $name);
    
    // Setup database
    $queries = file_get_contents('data/install.sql');
    $queries = str_replace('{prefix}', $prefix, $queries);
    foreach(explode(';', $queries) as $query)
      $db->Execute($query);
    
    // Setup config file
    $config  = file_get_contents('data/config.php.template');
    $config  = str_replace('{host}',   $host,   $config);
    $config  = str_replace('{port}',   $port,   $config);
    $config  = str_replace('{user}',   $user,   $config);
    $config  = str_replace('{pass}',   $pass,   $config);
    $config  = str_replace('{name}',   $name,   $config);
    $config  = str_replace('{prefix}', $prefix, $config);
    $file    = fopen(BASE_PATH . 'config.php', 'w');
    fwrite($file, $config);
    fclose($file);
  }
  catch(Exception $e)
  {
    return array(
      'error' => $e->getMessage()
    );
  }
}