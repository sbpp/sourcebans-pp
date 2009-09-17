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
  sAJAX::register('ArchiveProtest');
  sAJAX::register('ArchiveSubmission');
  sAJAX::register('ClearActions');
  sAJAX::register('ClearCache');
  sAJAX::register('ClearLogs');
  sAJAX::register('DeleteAdmin');
  sAJAX::register('DeleteBan');
  sAJAX::register('DeleteGroup');
  sAJAX::register('DeleteMod');
  sAJAX::register('DeleteProtest');
  sAJAX::register('DeleteServer');
  sAJAX::register('DeleteSubmission');
  sAJAX::register('KickPlayer');
  sAJAX::register('Reban');
  sAJAX::register('RestoreProtest');
  sAJAX::register('RestoreSubmission');
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
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
      throw new Exception($phrases['access_denied']);
    
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

function SetWebGroup($admins, $id)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
      throw new Exception($phrases['access_denied']);
    
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

function ArchiveProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'protests.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    ProtestsWriter::archive($id);
    return array(
      'headline' => 'Protest archived',
      'message' => "The protest for '$name' has been moved to the archive.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error moving the protest for '$name' to the archive: ",
      'error' => $e->getMessage()
    );
  }
}

function ArchiveSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'submissions.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    SubmissionsWriter::archive($id);
    return array(
      'headline' => 'Submission archived',
      'message' => "The submission for '$name' has been moved to the archive.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error moving the submission for '$name' to the archive: ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteAdmin($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_ADMINS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'admins.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    AdminsWriter::delete($id);
    return array(
      'headline' => 'Admin deleted',
      'message' => "The admin '$name' has been deleted.",
      'redirect' => 'admin_admins.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the admin '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteBan($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_BANS')))
      throw new Exception($phrases['access_denied']);
    
    require_once READERS_DIR . 'bans.php';
    require_once WRITERS_DIR . 'bans.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    $bans_reader = new BansReader();
    $bans        = $bans_reader->executeCached(ONE_MINUTE * 5);
    
    $identity = ($bans['list'][$id]['type']==0?$bans['list'][$id]['steam']:$bans['list'][$id]['ip']);
    
    BansWriter::delete($id);
    return array(
      'headline' => 'Ban deleted',
      'message' => "The ban for '$name' (".$identity.") has been deleted.",
      'redirect' => 'banlist.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the ban for '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteMod($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_MODS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'mods.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    ModsWriter::delete($id);
    return array(
      'headline' => 'Mod deleted',
      'message' => "The mod '$name' has been successfully deleted.",
      'redirect' => 'admin_mods.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the mod '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteGroup($id, $name, $type)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_GROUPS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'groups.php';
    
    $id = (int)$id;
    $type = substr($type,0,3);
    $name = addslashes(htmlspecialchars($name));
    
    GroupsWriter::delete($id, $type);
    return array(
      'headline' => 'Group deleted',
      'message' => "The group '$name' has been successfully deleted.",
      'redirect' => 'admin_groups.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the group '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'protests.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    ProtestsWriter::delete($id);
    return array(
      'headline' => 'Protest deleted',
      'message' => "The protest for '$name' has been successfully deleted.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the protest for '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteServer($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_SERVERS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'servers.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    ServersWriter::delete($id);
    return array(
      'headline' => 'Server deleted',
      'message' => "The server '$name' has been successfully deleted.",
      'redirect' => 'admin_servers.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the server '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function DeleteSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'submissions.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    SubmissionsWriter::delete($id);
    return array(
      'headline' => 'Submission deleted',
      'message' => "The submission for '$name' has been successfully deleted.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error deleting the submission for '$name': ",
      'error' => $e->getMessage()
    );
  }
}

function RestoreProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTEST')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'protests.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    ProtestsWriter::restore($id);
    return array(
      'headline' => 'Protest restored',
      'message' => "The protest for '$name' has been successfully restored from the archive.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error restoring the protest for '$name' from the archive: ",
      'error' => $e->getMessage()
    );
  }
}

function RestoreSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'submissions.php';
    
    $id = (int)$id;
    $name = addslashes(htmlspecialchars($name));
    
    SubmissionsWriter::restore($id);
    return array(
      'headline' => 'Submission restored',
      'message' => "The submission for '$name' has been successfully restored from the archive.",
      'redirect' => 'admin_bans.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => "There was an error restoring the submission for '$name' from the archive: ",
      'error' => $e->getMessage()
    );
  }
}

function KickPlayer($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(SM_ROOT . SM_KICK))
      throw new Exception($phrases['access_denied']);
    
    require_once READERS_DIR . 'servers.php';
    require_once UTILS_DIR   . 'servers/server_rcon.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    if(empty($servers[$id]['rcon']))
      throw new Exception('Can\'t kick ' . addslashes($name) . '. No RCON password set!');
    
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
      'secure'     => $server_info['secure'],
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
    $version = @file_get_contents('http://www.sourcebans.net/public/versionchecker/?type=rel');
    
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

function ClearCache()
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER')))
      throw new Exception($phrases['access_denied']);
      
    Util::ClearCache();
    return array(
      'message' => 'Cache cleared.'
    );
  }
  catch(Exception $e)
  {
    return array(
      'error' => $e->getMessage()
    );
  }
}

function ClearLogs()
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'logs.php';
    
    $logs_writer = new LogsWriter();
    $logs_writer->clear();
    return array(
      'headline' => 'Logs cleared',
      'message' => 'The logs has been successfully cleared.',
      'redirect' => 'admin_settings.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'There was an error clearing the logs: ',
      'error' => $e->getMessage()
    );
  }
}

function ClearActions()
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    if(!$userbank->HasAccess(array('ADMIN_OWNER')))
      throw new Exception($phrases['access_denied']);
    
    require_once WRITERS_DIR . 'actions.php';
    
    $actions_writer = new ActionsWriter();
    $actions_writer->clear();
    return array(
      'headline' => 'Actions cleared',
      'message' => 'The actions has been successfully cleared.',
      'redirect' => 'admin_admins.php'
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'There was an error clearing the actions: ',
      'error' => $e->getMessage()
    );
  }
}
?>