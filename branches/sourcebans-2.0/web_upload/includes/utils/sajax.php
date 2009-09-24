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
  sAJAX::register('AddServerGroup', function($admins, $id) {
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
  });
  
  sAJAX::register('ArchiveProtest', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'protests.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      ProtestsWriter::archive($id);
      
      return array(
        'headline' => 'Protest archived',
        'message' => "The protest for '$name' has been moved to the archive.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error moving the protest for '$name' to the archive: ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('ArchiveSubmission', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'submissions.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      SubmissionsWriter::archive($id);
      
      return array(
        'headline' => 'Submission archived',
        'message' => "The submission for '$name' has been moved to the archive.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error moving the submission for '$name' to the archive: ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('ClearActions', function() {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'actions.php';
      
      ActionsWriter::clear();
      
      return array(
        'headline' => 'Actions cleared',
        'message' => 'The actions has been successfully cleared.',
        'redirect' => 'admins.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'There was an error clearing the actions: ',
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('ClearCache', function() {
    Util::clearCache();
  });
  
  sAJAX::register('ClearLogs', function() {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'logs.php';
      
      LogsWriter::clear();
      
      return array(
        'headline' => 'Logs cleared',
        'message' => 'The logs has been successfully cleared.',
        'redirect' => 'settings.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'There was an error clearing the logs: ',
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('DeleteAdmin', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'DELETE_ADMINS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'admins.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      AdminsWriter::delete($id);
      
      return array(
        'headline' => 'Admin deleted',
        'message' => "The admin '$name' has been deleted.",
        'redirect' => 'admins.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the admin '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('DeleteBan', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'DELETE_BANS')))
        throw new Exception($phrases['access_denied']);
      
      require_once READERS_DIR . 'bans.php';
      require_once WRITERS_DIR . 'bans.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      $bans_reader = new BansReader();
      $bans        = $bans_reader->executeCached(ONE_MINUTE * 5);
      
      $identity = ($bans['list'][$id]['type'] == STEAM_BAN_TYPE ? $bans['list'][$id]['steam'] : $bans['list'][$id]['ip']);
      
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
  });
  
  sAJAX::register('DeleteGroup', function($id, $name, $type) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'DELETE_GROUPS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'groups.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      GroupsWriter::delete($id, $type);
      
      return array(
        'headline' => 'Group deleted',
        'message' => "The group '$name' has been successfully deleted.",
        'redirect' => 'groups.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the group '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('DeleteMod', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'DELETE_MODS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'mods.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      ModsWriter::delete($id);
      
      return array(
        'headline' => 'Mod deleted',
        'message' => "The mod '$name' has been successfully deleted.",
        'redirect' => 'mods.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the mod '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  sAJAX::register('DeleteProtest', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'protests.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      ProtestsWriter::delete($id);
      
      return array(
        'headline' => 'Protest deleted',
        'message' => "The protest for '$name' has been successfully deleted.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the protest for '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('DeleteServer', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'DELETE_SERVERS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'servers.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      ServersWriter::delete($id);
      
      return array(
        'headline' => 'Server deleted',
        'message' => "The server '$name' has been successfully deleted.",
        'redirect' => 'servers.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the server '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('DeleteSubmission', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'submissions.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      SubmissionsWriter::delete($id);
      
      return array(
        'headline' => 'Submission deleted',
        'message' => "The submission for '$name' has been successfully deleted.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error deleting the submission for '$name': ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('KickPlayer', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(SM_ROOT . SM_KICK))
        throw new Exception($phrases['access_denied']);
      
      require_once READERS_DIR . 'servers.php';
      require_once UTILS_DIR   . 'servers/server_rcon.php';
      
      $servers_reader = new ServersReader();
      
      $servers        = $servers_reader->executeCached(ONE_MINUTE);
      
      if(!isset($servers[$id]))
        throw new Exception('Invalid ID specified.');
      if(empty($servers[$id]['rcon']))
        throw new Exception('Can\'t kick ' . addslashes($name) . '. No RCON password set!');
      
      $server_rcon    = new CServerRcon($servers[$id]['ip'], $servers[$id]['port'], $servers[$id]['rcon']);
      if(!$server_rcon->Auth())
      {
        require_once WRITERS_DIR . 'servers.php';
        
        ServersWriter::edit($id, null, null, '');
        
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
  });
  
  sAJAX::register('RestoreProtest', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_PROTEST')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'protests.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      ProtestsWriter::restore($id);
      
      return array(
        'headline' => 'Protest restored',
        'message' => "The protest for '$name' has been successfully restored from the archive.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error restoring the protest for '$name' from the archive: ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('RestoreSubmission', function($id, $name) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'submissions.php';
      
      $name = addslashes(htmlspecialchars($name));
      
      SubmissionsWriter::restore($id);
      
      return array(
        'headline' => 'Submission restored',
        'message' => "The submission for '$name' has been successfully restored from the archive.",
        'redirect' => 'bans.php'
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => "There was an error restoring the submission for '$name' from the archive: ",
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('SelectTheme', function($theme) {
    try
    {
      $file = THEMES_DIR . $theme . '/theme.info';
      if(!file_exists($file))
        throw new Exception('Invalid theme name specified.');
      
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
  });
  
  sAJAX::register('ServerAdmins', function($id) {
    try
    {
      require_once READERS_DIR . 'admins.php';
      require_once READERS_DIR . 'servers.php';
      require_once UTILS_DIR   . 'servers/server_rcon.php';
      
      $admins_reader  = new AdminsReader();
      $servers_reader = new ServersReader();
      
      $admin_list     = array();
      $server_admins  = array();
      $admins         = $admins_reader->executeCached(ONE_MINUTE * 5);
      $servers        = $servers_reader->executeCached(ONE_MINUTE);
      
      foreach($admins['list'] as $id => $admin)
        $admin_list[$admin['identity']] = $id;
      
      $authids        = array_keys($admin_list);
      
      if(!isset($servers[$id]))
        throw new Exception('Invalid ID specified.');
      
      $server_rcon    = new CServerRcon($servers[$id]['ip'], $servers[$id]['port'], $servers[$id]['rcon']);
      
      if(!$server_rcon->Auth())
      {
        require_once WRITERS_DIR . 'servers.php';
        
        ServersWriter::edit($id, null, null, '');
        
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
  });
  
  sAJAX::register('SetWebGroup', function($admins, $id) {
    try
    {
      $phrases  = Env::get('phrases');
      $userbank = Env::get('userbank');
      
      if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
        throw new Exception($phrases['access_denied']);
      
      require_once WRITERS_DIR . 'admins.php';
      
      foreach($admins as $admin)
        AdminsWriter::edit($admin, null, null, null, null, null, null, null, $id);
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'name'  => $name,
        'error' => $e->getMessage()
      );
    }
  });
  
  sAJAX::register('Version', function() {
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
  });
}

sAJAX::register('BanExpires', function($id, $ends) {
  $phrases = Env::get('phrases');
  $secs    = $ends - time();
  
  return array(
    'id'      => $id,
    'expires' => $secs <= 0 ? $phrases['expired'] : Util::SecondsToString($secs)
  );
});

sAJAX::register('ServerInfo', function($id) {
  try
  {
    require_once READERS_DIR . 'server_query.php';
    require_once READERS_DIR . 'servers.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE);
    
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
});

sAJAX::register('ServerPlayers', function($id) {
  try
  {
    require_once READERS_DIR . 'server_query.php';
    require_once READERS_DIR . 'servers.php';
    
    $servers_reader = new ServersReader();
    
    $servers        = $servers_reader->executeCached(ONE_MINUTE);
    
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
});
?>