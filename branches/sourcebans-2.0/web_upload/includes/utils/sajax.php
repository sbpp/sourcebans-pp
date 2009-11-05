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
  sAJAX::register('GetBans');
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

function ArchiveProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::archiveProtest($id);
    
    return array(
      'title'    => 'Protest archived',
      'message'  => 'The protest for "' . $name . '" has been archived.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while archiving the protest for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function ArchiveSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::archiveSubmission($id);
    
    return array(
      'title'    => 'Submission archived',
      'message'  => 'The submission for "' . $name . '" has been archived.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while archiving the submission for "' . $name . '": ',
      'error'   => $e->getMessage()
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

function ClearActions()
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER')))
      throw new Exception($phrases['access_denied']);
    
    SB_API::clearActions();
    
    return array(
      'title'    => 'Actions cleared',
      'message'  => 'The actions have been successfully cleared.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_admins.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while clearing the actions: ',
      'error'   => $e->getMessage()
    );
  }
}

function ClearCache()
{
  Util::clearCache();
}

function ClearLogs()
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER')))
      throw new Exception($phrases['access_denied']);
    
    SB_API::clearLog();
    
    return array(
      'title'    => 'Logs cleared',
      'message'  => 'The logs have been successfully cleared.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_settings.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while clearing the logs: ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteAdmin($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'DELETE_ADMINS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteAdmin($id);
    
    return array(
      'title'    => 'Admin deleted',
      'message'  => 'The admin "' . $name . '" has been deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_admins.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the admin "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteBan($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'DELETE_BANS')))
      throw new Exception($phrases['access_denied']);
    
    $ban      = SB_API::getBan($id);
    
    $name     = addslashes(htmlspecialchars($name));
    $identity = ($ban['type'] == STEAM_BAN_TYPE ? $ban['steam'] : $ban['ip']);
    
    SB_API::deleteBan($id);
    
    return array(
      'title'    => 'Ban deleted',
      'message'  => 'The ban for "' . $name . '" (' . $identity . ') has been deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'banlist.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the ban for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteGroup($id, $name, $type)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'DELETE_GROUPS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteGroup($id, $type);
    
    return array(
      'title'    => 'Group deleted',
      'message'  => 'The group "' . $name . '" has been successfully deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_groups.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the group "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteMod($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'DELETE_MODS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteMod($id);
    
    return array(
      'title'    => 'Mod deleted',
      'message'  => 'The mod "' . $name . '" has been successfully deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_mods.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'There was an error deleting the mod "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteProtest($id);
    
    return array(
      'title'    => 'Protest deleted',
      'message'  => 'The protest for "' . $name . '" has been successfully deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the protest for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteServer($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'DELETE_SERVERS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteServer($id);
    
    return array(
      'title'    => 'Server deleted',
      'message'  => 'The server "' . $name . '" has been successfully deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_servers.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the server "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function DeleteSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::deleteSubmission($id);
    
    return array(
      'title'    => 'Submission deleted',
      'message'  => 'The submission for "' . $name . '" has been successfully deleted.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while deleting the submission for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function GetBans($type, $identity)
{
  try
  {
    $bans = SB_API::getBans(false, 0, 1, null, null, $type == IP_BAN_TYPE ? 'ip' : 'steam', $identity);
    
    return array(
      'count' => $bans['count']
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while retrieving the bans for "' . $identity . '": ',
      'error'   => $e->getMessage()
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
    
    preg_match_all(STATUS_PARSE, SB_API::sendRCON('status', $id), $players);
    
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

function RestoreProtest($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTEST')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::restoreProtest($id);
    
    return array(
      'title'    => 'Protest restored',
      'message'  => 'The protest for "' . $name . '" has been successfully restored.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while restoring the protest for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function RestoreSubmission($id, $name)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
      throw new Exception($phrases['access_denied']);
    
    $name = addslashes(htmlspecialchars($name));
    
    SB_API::restoreSubmission($id);
    
    return array(
      'title'    => 'Submission restored',
      'message'  => 'The submission for "' . $name . '" has been successfully restored.',
      'redirect' => Util::buildUrl(array(
        '_' => 'admin_bans.php'
      ))
    );
  }
  catch(Exception $e)
  {
    return array(
      'message' => 'An error occured while restoring the submission for "' . $name . '": ',
      'error'   => $e->getMessage()
    );
  }
}

function SelectTheme($theme)
{
  try
  {
    $phrases = Env::get('phrases');
    $file    = THEMES_DIR . $theme . '/theme.info';
    
    if(!file_exists($file))
      throw new Exception($phrases['invalid_theme']);
    
    return array_merge(array(
      'theme' => $theme
    ), parse_ini_file($file));
  }
  catch(Exception $e)
  {
    return array(
      'theme' => $theme,
      'error' => $e->getMessage()
    );
  }
}

function ServerAdmins($id)
{
  try
  {
    $admin_list    = array();
    $server_admins = array();
    
    foreach(SB_API::getAdmins() as $id => $admin)
      $admin_list[$admin['identity']] = $id;
    
    $authids       = array_keys($admin_list);
    
    preg_match_all(STATUS_PARSE, SB_API::sendRCON('status', $id), $players);
    
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
    $server      = SB_API::getServer($id);
    $server_info = SB_API::getServerInfo($id);
    
    if(empty($server_info))
      throw new Exception('Error connecting (' . $server['ip'] . ':' . $server['port'] . ')');
    if($server_info['hostname'] == "anned by server\n")
      throw new Exception('Banned by server (' . $server['ip'] . ':' . $server['port'] . ')');
    
    $map_image = 'images/maps/' . $server['mod_folder'] . '/' . $server_info['map'] . '.jpg';
    
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
    return array(
      'id'      => $id,
      'players' => SB_API::getServerPlayers($id)
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

function SetWebGroup($admins, $id)
{
  try
  {
    $phrases  = Env::get('phrases');
    $userbank = Env::get('userbank');
    
    if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
      throw new Exception($phrases['access_denied']);
    
    foreach($admins as $admin)
      SB_API::editAdmin($admin, null, null, null, null, null, null, null, $id);
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

function Version()
{
  try
  {
    $phrases = Env::get('phrases');
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
?>