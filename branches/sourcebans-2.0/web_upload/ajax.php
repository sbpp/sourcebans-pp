<?php
require_once 'api.php';


/**
 * AJAX functions
 * 
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage AJAX
 * @version    $Id$
 */
class AjaxFunctions
{
  private static $_registry;
  
  
  public static function init()
  {
    self::$_registry = Registry::getInstance();
  }
  
  
  public static function AddServerGroup($admins, $id)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'EDIT_ADMINS')))
        throw new Exception($language->access_denied);
      
      foreach($admins as $admin)
      {
        $admin->addServerGroup($id);
      }
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function ArchiveProtest($id)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($language->access_denied);
      
      $protest           = self::$_registry->protests[$id];
      $protest->archived = true;
      $protest->save();
      
      return array(
        'title'    => 'Protest archived',
        'message'  => 'The protest for "' . $protest->name . '" has been archived.',
        'redirect' => new SBUri('admin', 'bans') . '#protests',
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while archiving the protest for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function ArchiveSubmission($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($language->access_denied);
      
      $name = addslashes(htmlspecialchars($name));
      
      SB_API::archiveSubmission($id);
      
      return array(
        'title'    => 'Submission archived',
        'message'  => 'The submission for "' . $name . '" has been archived.',
        'redirect' => new SBUri('admin', 'bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while archiving the submission for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function BanExpires($id, $ends)
  {
    $language = self::$_registry->user->language;
    $user     = self::$_registry->user;
    
    return array(
      'id'      => $id,
      'expires' => $secs <= 0 ? $language->expired : Util::SecondsToString($secs)
    );
  }
  
  public static function ClearActions()
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER')))
        throw new Exception($language->access_denied);
      
      SB_API::clearActions();
      
      return array(
        'title'    => 'Actions cleared',
        'message'  => 'The actions have been successfully cleared.',
        'redirect' => new SBUri('admin', 'admins'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while clearing the actions: ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function ClearCache()
  {
    self::$_registry->cache->clear();
    self::$_registry->template->clear();
  }
  
  public static function ClearLogs()
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER')))
        throw new Exception($language->access_denied);
      
      SB_API::clearLog();
      
      return array(
        'title'    => 'Logs cleared',
        'message'  => 'The logs have been successfully cleared.',
        'redirect' => new SBUri('admin', 'settings'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while clearing the logs: ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteAdmin($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'DELETE_ADMINS')))
        throw new Exception($language->access_denied);
      
      SB_API::deleteAdmin($id);
      
      return array(
        'title'    => 'Admin deleted',
        'message'  => 'The admin "' . $name . '" has been deleted.',
        'redirect' => new SBUri('admin', 'admins'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the admin "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteBan($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'DELETE_BANS')))
        throw new Exception($language->access_denied);
      
      $ban      = SB_API::getBan($id);
      $name     = $ban->name;
      $identity = ($ban->type == self::$_registry->ip_ban_type ? $ban->ip : $ban->steam);
      
      $ban->delete();
      
      return array(
        'title'    => 'Ban deleted',
        'message'  => 'The ban for "' . $name . '" (' . $identity . ') has been deleted.',
        'redirect' => new SBUri('bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the ban for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteGroup($id, $name, $type)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'DELETE_GROUPS')))
        throw new Exception($language->access_denied);
      
      SB_API::deleteGroup($id, $type);
      
      return array(
        'title'    => 'Group deleted',
        'message'  => 'The group "' . $name . '" has been successfully deleted.',
        'redirect' => new SBUri('admin', 'groups'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the group "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteGame($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'DELETE_GAMES')))
        throw new Exception($language->access_denied);
      
      $name = addslashes(htmlspecialchars($name));
      
      SB_API::deleteGame($id);
      
      return array(
        'title'    => 'Game deleted',
        'message'  => 'The game "' . $name . '" has been successfully deleted.',
        'redirect' => new SBUri('admin', 'games'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'There was an error deleting the game "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteProtest($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_PROTESTS')))
        throw new Exception($language->access_denied);
      
      SB_API::deleteProtest($id);
      
      return array(
        'title'    => 'Protest deleted',
        'message'  => 'The protest for "' . $name . '" has been successfully deleted.',
        'redirect' => new SBUri('admin', 'bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the protest for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteServer($id)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'DELETE_SERVERS')))
        throw new Exception($language->access_denied);
      
      SB_API::deleteServer($id);
      
      return array(
        'title'    => 'Server deleted',
        'message'  => 'The server "' . $name . '" has been successfully deleted.',
        'redirect' => new SBUri('admin', 'servers'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the server "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function DeleteSubmission($id)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
        throw new Exception($language->access_denied);
      
      SB_API::deleteSubmission($id);
      
      return array(
        'title'    => 'Submission deleted',
        'message'  => 'The submission for "' . $name . '" has been successfully deleted.',
        'redirect' => new SBUri('admin', 'bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while deleting the submission for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function GetBans($type, $identity)
  {
    try
    {
      $bans = SB_API::getBans(false, 0, 1, null, null, $type == self::$_registry->ip_ban_type ? 'ip' : 'steam', $identity);
      
      return array(
        'count' => count($bans),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while retrieving the bans for "' . $identity . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function KickPlayer($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $uri      = self::$_registry->uri;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(self::$_registry->sm_root . self::$_registry->sm_kick))
        throw new Exception($language->access_denied);
      
      preg_match_all(self::$_registry->status_parse, SB_API::sendRCON('status', $id), $players);
      
      foreach($players[2] as $index => $player)
      {
        if($player != $name)
          continue;
        
        $server_rcon->execute('kickid ' . $players[3][$index] . ' You have been kicked by this server. Check http://' . $_SERVER['HTTP_HOST'] . $uri->base . ' for more info.');
        
        return array(
          'id'   => $id,
          'name' => $name,
        );
      }
      
      throw new Exception('Can\'t kick ' . addslashes($name) . '. Player not on the server anymore!');
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'name'  => $name,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function RestoreProtest($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_PROTEST')))
        throw new Exception($language->access_denied);
      
      SB_API::restoreProtest($id);
      
      return array(
        'title'    => 'Protest restored',
        'message'  => 'The protest for "' . $name . '" has been successfully restored.',
        'redirect' => new SBUri('admin', 'bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while restoring the protest for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function RestoreSubmission($id, $name)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'BAN_SUBMISSIONS')))
        throw new Exception($language->access_denied);
      
      SB_API::restoreSubmission($id);
      
      return array(
        'title'    => 'Submission restored',
        'message'  => 'The submission for "' . $name . '" has been successfully restored.',
        'redirect' => new SBUri('admin', 'bans'),
      );
    }
    catch(Exception $e)
    {
      return array(
        'message' => 'An error occured while restoring the submission for "' . $name . '": ',
        'error'   => $e->getMessage(),
      );
    }
  }
  
  public static function SelectTheme($theme)
  {
    try
    {
      return array(
        'theme' => new SBTheme($theme),
      );
    }
    catch(Exception $e)
    {
      return array(
        'theme' => $theme,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function ServerAdmins($id)
  {
    try
    {
      $admin_list    = array();
      $server_admins = array();
      
      foreach(SB_API::getAdmins() as $admin)
        $admin_list[$admin->identity] = $admin->id;
      
      $authids       = array_keys($admin_list);
      
      preg_match_all(self::$_registry->status_parse, SB_API::sendRCON('status', $id), $players);
      
      foreach($players[3] as $authid)
      {
        if(!Util::in_array($authid, $authids))
          continue;
        
        $server_admins[$admin_list[$authid]] = array(
          'name'  => $players[2][$i],
          'steam' => $players[3][$i],
          'ip'    => strtok($players[8][$i], ':'),
          'time'  => $players[4][$i],
          'ping'  => $players[5][$i]
        );
      }
      
      return array(
        'id'     => $id,
        'admins' => $server_admins,
      );
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function ServerInfo($id)
  {
    try
    {
      $uri          = new SBUri();
      $server       = self::$_registry->servers[$id];
      $server_query = new ServerQuery($server->ip, $server->port);
      $server_info  = $server_query->getInfo();
      
      if(empty($server_info))
        throw new Exception(snprintf($language->error_connecting, $server->ip, $server->port));
      if($server_info['hostname'] == "anned by server\n")
        throw new Exception(snprintf($language->banned_by_server, $server->ip, $server->port));
      
      $map_image = 'images/maps/' . $server->game->folder . '/' . $server_info['map'] . '.jpg';
      
      return array(
        'id'         => $id,
        'hostname'   => preg_replace('/[\x00-\x1F\x7F-\x9F]/', null, $server_info['hostname']),
        'numplayers' => $server_info['numplayers'],
        'maxplayers' => $server_info['maxplayers'],
        'map'        => $server_info['map'],
        'os'         => $server_info['os'],
        'secure'     => $server_info['secure'],
        'map_image'  => file_exists(self::$_registry->site_dir . $map_image) ? $uri->base . '/' . $map_image : null,
      );
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function ServerPlayers($id)
  {
    try
    {
      $players = SB_API::getServerPlayers($id);
      
      foreach($players as &$player)
      {
        $player['time'] = Util::SecondsToString($player['time']);
      }
      
      return array(
        'id'      => $id,
        'players' => $players,
      );
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function SetWebGroup($admins, $id)
  {
    try
    {
      $language = self::$_registry->user->language;
      $user     = self::$_registry->user;
      
      if(!$user->hasAccess(array('OWNER', 'EDIT_ADMINS')))
        throw new Exception($language->access_denied);
      
      foreach($admins as $admin_id)
      {
        $admin            = self::$_registry->admins[$admin_id];
        $admin->web_group = $id;
        $admin->save();
      }
    }
    catch(Exception $e)
    {
      return array(
        'id'    => $id,
        'name'  => $name,
        'error' => $e->getMessage(),
      );
    }
  }
  
  public static function Version()
  {
    try
    {
      $language = self::$_registry->user->language;
      $version  = @file_get_contents('http://www.sourcebans.net/public/versionchecker/?type=rel');
      
      if(empty($version) || strlen($version) > 8)
        throw new Exception('Error retrieving latest release.');
      
      return array(
        'version' => $version,
        'update'  => version_compare($version, self::$_registry->sb_version) > 0,
      );
    }
    catch(Exception $e)
    {
      return array(
        'error' => $e->getMessage(),
      );
    }
  }
}


AjaxFunctions::init();