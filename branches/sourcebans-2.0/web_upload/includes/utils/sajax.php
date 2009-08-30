<?php
/**
 * =============================================================================
 * AJAX Callback handler
 * 
 * @author InterWave Studios Development Team
 * @version 2.0.0
 * @copyright SourceBans (C)2008 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: sajax.php 140 2009-02-11 18:30:00Z tsunami
 * =============================================================================
 */
$userbank = Env::get('userbank');

if($userbank->is_logged_in())
{
  sAJAX::register('AddServerGroup');
  sAJAX::register('ApplyTheme');
  sAJAX::register('ClearCache');
  sAJAX::register('KickPlayer');
  sAJAX::register('Reban');
  sAJAX::register('SendRCON');
  sAJAX::register('ServerAdmins');
  sAJAX::register('SelectTheme');
  sAJAX::register('SetWebGroup');
  sAJAX::register('Version');
}

sAJAX::register('BanExpires');
sAJAX::register('SearchBans');
sAJAX::register('ServerInfo');
sAJAX::register('ServerPlayers');

function AddServerGroup($admins, $id)
{
  try
  {
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')))
      throw new Exception('Access Denied.');
    
    require_once WRITERS_DIR . 'admins.php';
    
    foreach($admins as $admin)
      AdminsWriter::addServerGroup($admin, $id);
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'name'  => $name,
      'error' => $e->getMessage()
    );
  }
}

function SetServerGroup($admins, $id)
{
  try
  {
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')))
      throw new Exception('Access Denied.');
    
    require_once WRITERS_DIR . 'admins.php';
    
    foreach($admins as $admin)
      AdminsWriter::edit($admin, array(
        'group_id' => $id
      ));
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'name'  => $name,
      'error' => $e->getMessage()
    );
  }
}

function BanExpires($id, $ends)
{
  $phrases = Env::get('phrases');
  $secs    = $ends - time();
  
  return array(
    'id'      => $id,
    'expires' => $secs <= 0 ? $phrases['expired'] : Util::SecondsToString($secs)
  );
}

function KickPlayer($id, $name)
{
  try
  {
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(SM_ROOT . SM_KICK))
      throw new Exception('Access Denied.');
    
    require_once READERS_DIR . 'servers.php';
    require_once UTILS_DIR   . 'servers/server_rcon.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    if(empty($servers[$id]['rcon']))
      throw new Exception('Can\'t kick ' . $name . '. No RCON password set!');
    
    $server_rcon    = new CServerRcon($servers[$id]['ip'], $servers[$id]['port'], $servers[$id]['rcon']);
    if(!$server_rcon->Auth())
    {
      require_once WRITERS_DIR . 'servers.php';
      $servers_writer = new ServersWriter();
      $servers_writer->edit($id, $servers[$id]['ip'], $servers[$id]['port'], '', $servers[$id]['mod_id']);
      throw new Exception('Invalid RCON password.');
    }
    
    $players = array();
    preg_match_all(STATUS_PARSE, $server_rcon->rconCommand('status'), $players);
    
    foreach($players[2] AS $index => $player)
    {
      if($player == $name)
      {
        $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], 'scripts/sajax.php'));
        $server_rcon->rconCommand('kickid ' . $players[3][$index] . ' You have been kicked by this server. Check http://' . $_SERVER['HTTP_HOST'] . $requri . ' for more info.');
        
        return array(
          'id'   => $id,
          'name' => $name
        );
      }
    }
    
    throw new Exception('Can\'t kick ' . addslashes($name) . '. Player not on the server anymore!');
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'name'  => $name,
      'error' => $e->getMessage()
    );
  }
}

function ServerAdmins($id)
{
  try
  {
    require_once READERS_DIR . 'admins.php';
    require_once READERS_DIR . 'servers.php';
    require_once UTILS_DIR   . 'servers/server_rcon.php';
    
    $admins_reader  = new AdminsReader();
    $servers_reader = new ServersReader();
    
    $admin_list     = array();
    $server_admins  = array();
    $admins         = $admins_reader->executeCached(ONE_MINUTE  * 5);
    $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
    
    foreach($admins as $admin_id => $admin)
      $admin_list[$admin['identity']] = $admin_id;
    
    $authids        = array_keys($admin_list);
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_rcon    = new CServerRcon($servers[$id]['ip'], $servers[$id]['port'], $servers[$id]['rcon']);
    
    if(!$server_rcon->Auth())
    {
      require_once WRITERS_DIR . 'servers.php';
      $servers_writer = new ServersWriter();
      $servers_writer->edit($id, $servers[$id]['ip'], $servers[$id]['port'], '', $servers[$id]['mod_id']);
      throw new Exception('Invalid RCON password.');
    }
    
    preg_match_all(STATUS_PARSE, $server_rcon->rconCommand('status'), $players);
    
    foreach($players[3] AS $authid)
      if(in_array($authid, $authids))
        $server_admins[$admin_list[$authid]] = array('name'  => $players[2][$i],
                                                     'steam' => $players[3][$i],
                                                     'ip'    => strtok($players[8][$i], ':'),
                                                     'time'  => $players[4][$i],
                                                     'ping'  => $players[5][$i]);
    
    return array(
      'id'     => $id,
      'admins' => $server_admins
    );
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'error' => $e->getMessage()
    );
  }
}

function ServerInfo($id)
{
  try
  {
    require_once READERS_DIR . 'server_query.php';
    require_once READERS_DIR . 'servers.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_INFO;
    $server_info               = $server_query_reader->executeCached(ONE_MINUTE);
    
    if(empty($server_info))
      throw new Exception('Error connecting (' . $servers[$id]['ip'] . ':' . $servers[$id]['port'] . ')');
    if($server_info['hostname'] == "anned by server\n")
      throw new Exception('Banned by server (' . $servers[$id]['ip'] . ':' . $servers[$id]['port'] . ')');
    
    $map_image = 'images/maps/' . $servers[$id]['mod_folder'] . '/' . $server_info['map'] . '.jpg';
    
    return array(
      'id'         => $id,
      'hostname'   => preg_replace('/[\x00-\x09]/', null, $server_info['hostname']),
      'numplayers' => $server_info['numplayers'],
      'maxplayers' => $server_info['maxplayers'],
      'map'        => $server_info['map'],
      'os'         => $server_info['os'],
      'secure'     => $server_info['secure'] == 1,
      'map_image'  => file_exists(BASE_PATH . $map_image) ? $map_image : null
    );
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'error' => $e->getMessage()
    );
  }
}

function ServerPlayers($id)
{
  try
  {
    require_once READERS_DIR . 'server_query.php';
    require_once READERS_DIR . 'servers.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_PLAYERS;
    $server_players            = $server_query_reader->executeCached(ONE_MINUTE);
    
    return array(
      'id'      => $id,
      'players' => $server_players
    );
  }
  catch(Exception $e)
  {
    return array(
      'id'    => $id,
      'error' => $e->getMessage()
    );
  }
}

function Version()
{
  try
  {
    $version = @file_get_contents('http://www.sourcebans.net/public/versionchecker/');
    
    if(empty($version) || strlen($version) > 8)
      throw new Exception('Error retrieving latest release.');
    
    return array(
      'version' => $version,
      'update'  => version_compare($ver, SB_VERSION) > 0
    );
  }
  catch(Exception $e)
  {
    return array(
      'error' => $e->getMessage()
    );
  }
}
?>