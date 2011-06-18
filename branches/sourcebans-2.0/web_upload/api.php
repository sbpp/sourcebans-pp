<?php
require_once 'bootstrap.php';


/**
 * SourceBans API
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage API
 * @version    $Id$
 */
class SB_API
{
  private static $_registry;
  
  
  public static function init()
  {
    self::$_registry = Registry::getInstance();
  }
  
  
  /**
   * Clears the actions
   */
  public static function clearActions()
  {
    self::$_registry->actions->truncate();
  }
  
  
  /**
   * Returns a list of actions
   *
   * @param  integer $limit The amount of actions to return per page, or 0 for all the actions
   * @param  integer $page  The page to return
   * @return array          A list of actions
   */
  public static function getActions($limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $actions         = self::$_registry->actions;
    $actions->limit  = $limit;
    $actions->page   = $page;
    $actions->search = $search;
    $actions->type   = $type;
    
    if(!is_null($order))
    {
      $actions->order = $order;
    }
    if(!is_null($sort))
    {
      $actions->sort  = $sort;
    }
    
    return $actions;
  }
  
  
  /**
   * Creates an admin
   *
   * @return SBAdmin The created admin
   */
  public static function createAdmin()
  {
    return new SBAdmin();
  }
  
  
  /**
   * Deletes an admin
   *
   * @param integer $id The id of the admin to delete
   */
  public static function deleteAdmin($id)
  {
    $admin = self::getAdmin($id);
    $admin->delete();
  }
  
  
  /**
   * Edits an admin
   *
   * @param integer $id    The id of the admin to edit
   * @param string  $key   The key of the value to edit
   * @param mixed   $value The new value
   */
  public static function editAdmin($id, $key, $value)
  {
    $admin        = self::getAdmin($id);
    $admin->$$key = $value;
    $admin->save();
  }
  
  
  /**
   * Returns an admin
   *
   * @param  integer $id The id of the admin to return
   * @return SBAdmin     The admin
   */
  public static function getAdmin($id)
  {
    $admins = self::getAdmins();
    
    return $admins[$id];
  }
  
  
  /**
   * Returns a list of admins
   *
   * @param  integer $limit The amount of admins to return per page, or 0 for all the admins
   * @param  integer $page  The page to return
   * @return array          A list of admins
   */
  public static function getAdmins($limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $admins         = self::$_registry->admins;
    $admins->limit  = $limit;
    $admins->page   = $page;
    $admins->search = $search;
    $admins->type   = $type;
    
    if(!is_null($order))
    {
      $admins->order = $order;
    }
    if(!is_null($sort))
    {
      $admins->sort  = $sort;
    }
    
    return $admins;
  }
  
  
  /**
   * Imports one or more admins
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   * @noreturn
   */
  public static function importAdmins($file, $tmp_name = '')
  {
    if(!file_exists($tmp_name))
      $tmp_name = $file;
    if(!file_exists($tmp_name))
      throw new Exception($language->file_does_not_exist);
    
    switch(basename($file))
    {
      // SourceMod
      case 'admins_simple.ini':
      case 'admins.cfg':
        $admins        = self::$_registry->admins;
        $server_groups = self::$_registry->server_groups;
        
        foreach($server_groups as $group)
        {
          $group_list[$group->name] = $group->id;
        }
        
        // Detailed
        if(basename($file) == 'admins.cfg')
        {
          $kv = new KeyValues();
          $kv->load($tmp_name);
          
          foreach($kv as $name => $data)
          {
            $admin               = self::createAdmin();
            $admin->name         = $name;
            $admin->auth         = $data['auth'];
            $admin->identity     = $data['identity'];
            $admin->password     = isset($data['password']) ? $admins->encrypt_password($data['password']) : null;
            $admin->srv_password = isset($data['password']) ? $data['password']                            : null;
            $admin->save();
            
            $groups = array();
            if(isset($data['group']))
            {
              if(is_array($data['group']))
              {
                foreach($data['group'] as $group)
                  $groups[] = $group_list[$group];
              }
              else
              {
                $groups[] = $group_list[$data['group']];
              }
              
              $admin->setServerGroups($groups);
            }
          }
        }
        // Simple
        else
        {
          preg_match_all('~"(.+?)"[ \t]*"(.+?)"([ \t]*"(.+?)")?~', file_get_contents($tmp_file), $admins);
          
          for($i = 0; $i < count($admins[0]); $i++)
          {
            list($identity, $flags, $password) = array($admins[1][$i], $admins[2][$i], $admins[4][$i]);
            
            // Parse authentication type depending on identity
            if(preg_match(self::$_registry->steam_format, $identity))
              $auth = self::$_registry->steam_auth_type;
            else if($identity{0} == '!' && preg_match(self::$_registry->ip_format, $identity))
              $auth = self::$_registry->ip_auth_type;
            else
              $auth = self::$_registry->name_auth_type;
            
            // Parse flags
            if($flags{0} == '@')
            {
              $group = substr($flags, 1);
            }
            else if(strpos($flags, ':') !== false)
            {
              list($immunity, $flags) = explode(':', $flags);
            }
            
            $admin               = self::createAdmin();
            $admin->auth         = $auth;
            $admin->identity     = $identity;
            $admin->name         = $identity;
            $admin->password     = isset($password) ? $admins->encrypt_password($password) : null;
            $admin->srv_password = isset($password) ? $password                            : null;
            $admin->save();
            
            if(isset($group))
            {
              $admin->setServerGroups(array($group_list[$group]));
            }
          }
        }
        
        break;
      // Mani Admin Plugin
      case 'clients.txt':
        $kv = new KeyValues();
        $kv->load($tmp_name);
        
        foreach($kv['players'] as $name => $player)
        {
          $admin           = self::createAdmin();
          $admin->auth     = self::$_registry->steam_auth_type;
          $admin->name     = $name;
          $admin->identity = $player['steam'];
          $admin->save();
        }
        
        break;
      default:
        throw new Exception($language->unsupported_format);
    }
  }
  
  
  /**
   * Creates a ban
   *
   * @return SBBan The created ban
   */
  public static function createBan()
  {
    return new SBBan();
  }
  
  
  /**
   * Deletes a ban
   *
   * @param integer $id The id of the ban to delete
   */
  public static function deleteBan($id)
  {
    $ban = self::getBan($id);
    $ban->delete();
  }
  
  
  /**
   * Edits a ban
   *
   * @param integer $id    The id of the ban to edit
   * @param string  $key   The key of the value to edit
   * @param mixed   $value The new value
   */
  public static function editBan($id, $key, $value)
  {
    $ban        = self::getBan($id);
    $ban->$$key = $value;
    $ban->save();
  }
  
  
  /**
   * Returns a ban
   *
   * @param  integer $id The id of the ban to return
   * @return SBBan       The ban
   */
  public static function getBan($id)
  {
    $bans = self::getBans();
    
    return $bans[$id];
  }
  
  
  /**
   * Returns a list of bans
   *
   * @param  integer $limit The amount of bans to return per page, or 0 for all the bans
   * @param  integer $page  The page to return
   * @return array          A list of bans
   */
  public static function getBans($hideinactive = false, $limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $bans               = self::$_registry->bans;
    $bans->hideinactive = $hideinactive;
    $bans->limit        = $limit;
    $bans->page         = $page;
    $bans->search       = $search;
    $bans->type         = $type;
    
    if(!is_null($sort))
    {
      $bans->sort  = $sort;
    }
    if(!is_null($order))
    {
      $bans->order = $order;
    }
    
    return $bans;
  }
  
  
  /**
   * Imports one or more bans
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function importBans($file, $tmp_name = '')
  {
    self::$_registry->bans->import($file, $tmp_name);
  }
  
  
  /**
   * Unbans a ban
   *
   * @param integer $id     The id of the ban to unban
   * @param string  $reason The reason for unbanning the ban
   */
  public static function unbanBan($id, $reason)
  {
    $ban = self::getBan($id);
    $ban->unban($reason);
  }
  
  
  /**
   * Returns a list of blocks
   *
   * @param  integer $limit The amount of blocks to return per page, or 0 for all the blocks
   * @param  integer $page  The page to return
   * @return array          A list of blocks
   */
  public static function getBlocks($limit = 0, $page = 1)
  {
    $blocks        = self::$_registry->blocks;
    $blocks->limit = $limit;
    $blocks->page  = $page;
    
    return $blocks;
  }
  
  
  /**
   * Clears the cache
   */
  public static function clearCache()
  {
    // Clear data cache
    self::$_registry->cache->clear();
    
    // Clear template cache
    self::$_registry->template->clear();
  }
  
  
  /**
   * Adds a comment
   *
   * @param  integer $ban_id  The id of the ban/protest/submission to comment to
   * @param  integer $type    The type of the comment (BAN_TYPE, PROTEST_TYPE, SUBMISSION_TYPE)
   * @param  string  $message The message of the comment
   * @return integer          The id of the added comment
   */
  public static function createComment()
  {
    return new SBComment();
  }
  
  
  /**
   * Deletes a comment
   *
   * @param integer $id The id of the comment to delete
   */
  public static function deleteComment($id)
  {
    $comment = self::getComment($id);
    $comment->delete();
  }
  
  
  /**
   * Edits a comment
   *
   * @param integer $id      The id of the comment to edit
   * @param string  $message The message of the comment
   */
  public static function editComment($id, $message)
  {
    CommentsWriter::edit($id, $message);
  }
  
  
  /**
   * Returns a comment
   *
   * @param  integer $id The id of the comment to return
   * @return array       The comment
   */
  public static function getComment($id)
  {
    $comments = self::getComments();
    $language = self::$_registry->user->language;
    
    if(!isset($comments[$id]))
      throw new SBException($language->invalid_id);
    
    return $comments[$id];
  }
  
  
  /**
   * Returns a list of comments
   *
   * @param  integer $ban_id The id of the ban/protest/submission to return the comments from
   * @param  integer $type   The type of the comments to return (BAN_TYPE, PROTEST_TYPE, SUBMISSION_TYPE)
   * @return array           A list of comments
   */
  public static function getComments($ban_id, $type)
  {
    $comments         = self::$_registry->comments;
    $comments->ban_id = $ban_id;
    $comments->type   = $type;
    
    return $comments;
  }
  
  
  /**
   * Creates a game
   *
   * @return SBGame The created game
   */
  public static function createGame()
  {
    return new SBGame();
  }
  
  
  /**
   * Deletes a game
   *
   * @param integer $id The id of the game to delete
   */
  public static function deleteGame($id)
  {
    $game = self::getGame($id);
    $game->delete();
  }
  
  
  /**
   * Edits a game
   *
   * @param integer $id     The id of the game to edit
   * @param string  $name   The name of the game
   * @param string  $folder The folder of the game
   * @param string  $icon   The icon of the game
   */
  public static function editGame($id, $name = null, $folder = null, $icon = null)
  {
    GamesWriter::edit($id, $name, $folder, $icon);
  }
  
  
  /**
   * Returns a game
   *
   * @param  integer $id The id of the game to return
   * @return SBGame  The game
   */
  public static function getGame($id)
  {
    $games = self::getGames();
    
    return $games[$id];
  }
  
  
  /**
   * Returns the list of games
   *
   * @return array The list of games
   */
  public static function getGames()
  {
    return self::$_registry->games;
  }
  
  
  /**
   * Creates a group
   *
   * @return SBServerGroup The created server group
   */
  public static function createServerGroup()
  {
    return new SBServerGroup();
  }
  
  
  /**
   * Deletes a server group
   *
   * @param integer $id   The id of the group to delete
   * @param integer $type The type of the group to delete (SERVER_GROUPS, WEB_GROUPS)
   */
  public static function deleteServerGroup($id)
  {
    $group = self::getServerGroup($id);
    $group->delete();
  }
  
  
  /**
   * Edits a group
   *
   * @param integer $id        The id of the group to edit
   * @param integer $type      The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param string  $name      The name of the group
   * @param mixed   $flags     The access flags of the group
   * @param integer $immunity  The immunity level of the group
   * @param array   $overrides The overrides of the group
   */
  public static function editGroup($id, $type, $name = null, $flags = null, $immunity = null, $overrides = null)
  {
    GroupsWriter::edit($id, $type, $name, $flags, $immunity, $overrides);
  }
  
  
  /**
   * Returns a server group
   *
   * @param  integer       $id The id of the server group to return
   * @return SBServerGroup     The server group
   */
  public static function getServerGroup($id)
  {
    $groups = self::getServerGroups();
    
    return $group[$id];
  }
  
  
  /**
   * Returns a list of server groups
   *
   * @return array A list of server groups
   */
  public static function getServerGroups()
  {
    return self::$_registry->server_groups;
  }
  
  
  /**
   * Imports one or more server groups
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function importServerGroups($file, $tmp_name = '')
  {
    self::$_registry->server_groups->import($file, $tmp_name);
  }
  
  
  /**
   * Creates a log
   *
   * @return SBLog The created log
   */
  public static function createLog()
  {
    return new SBLog();
  }
  
  
  /**
   * Clears the logs
   */
  public static function clearLogs()
  {
    self::$_registry->logs->truncate();
  }
  
  
  /**
   * Returns a log
   *a
   * @param  integer $id The id of the log to return
   * @return SBLog       The log
   */
  public static function getLog($id)
  {
    $logs = self::getLogs();
    
    return new $logs[$id];
  }
  
  
  /**
   * Returns a list of logs
   *
   * @param  integer $limit The amount of logs to return per page, or 0 for all the logs
   * @param  integer $page  The page to return
   * @return array          A list of logs
   */
  public static function getLogs($limit = 0, $page = 1)
  {
    $logs        = self::$_registry->logs;
    $logs->limit = $limit;
    $logs->page  = $page;
    
    return $logs;
  }
  
  
  /**
   * Creates an override
   *
   * @return SBOverride The created override
   */
  public static function createOverride()
  {
    return new SBOverride();
  }
  
  
  /**
   * Clears the overrides
   */
  public static function clearOverrides()
  {
    self::$_registry->overrides->clear();
  }
  
  
  /**
   * Returns the list of overrides
   *
   * @return array The list of overrides
   */
  public static function getOverrides()
  {
    return self::$_registry->overrides;
  }
  
  
  /**
   * Calls a hook on the enabled plugins
   *
   * @param  string $hook     The hook to call
   * @param  mixed  $args[]   The arguments to pass to the hook
   * @return array  The referenced arguments to pass back to the calling function
   */
  public static function callHook()
  {
    return self::$_registry->plugins->call(func_get_args());
  }
  
  
  /**
   * Returns the list of plugins
   *
   * @return array The list of plugins
   */
  public static function getPlugins()
  {
    return self::$_registry->plugins;
  }
  
  
  /**
   * Creates a protest
   *
   * @return SBProtest The created protest
   */
  public static function createProtest()
  {
    return new SBProtest();
  }
  
  
  /**
   * Archives a protest
   *
   * @param integer $id The id of the protest to archive
   */
  public static function archiveProtest($id)
  {
    $protest = self::getProtest($id);
    $protest->archive();
  }
  
  
  /**
   * Deletes a protest
   *
   * @param integer $id The id of the protest to delete
   */
  public static function deleteProtest($id)
  {
    $protest = self::getProtest($id);
    $protest->delete();
  }
  
  
  /**
   * Returns a protest
   *
   * @param  integer $id The id of the protest to return
   * @return array       The protest
   */
  public static function getProtest($id)
  {
    $protests = self::getProtests();
    
    return $protests[$id];
  }
  
  
  /**
   * Returns a list of protests
   *
   * @param  integer $limit The amount of protests to return per page, or 0 for all the protests
   * @param  integer $page  The page to return
   * @return array          A list of protests
   */
  public static function getProtests($archive = false, $limit = 0, $page = 1, $sort = null, $order = null)
  {
    $protests          = self::$_registry->protests;
    $protests->archive = $archive;
    $protests->limit   = $limit;
    $protests->page    = $page;
    
    if(!is_null($order))
    {
      $protests->order = $order;
    }
    if(!is_null($sort))
    {
      $protests->sort  = $sort;
    }
    
    return $protests;
  }
  
  
  /**
   * Restores a protest from the archive
   *
   * @param integer $id The id of the protest to restore
   */
  public static function restoreProtest($id)
  {
    $protest = self::getProtest($id);
    $protest->restore();
  }
  
  
  /**
   * Returns the list of quotes
   *
   * @return array The list of quotes
   */
  public static function getQuotes()
  {
    return self::$_registry->quotes;
  }
  
  
  /**
   * Returns a random quote
   *
   * @return array A random quote
   */
  public static function getRandomQuote()
  {
    $quotes = self::getQuotes();
    
    return $quotes->getRandom();
  }
  
  
  /**
   * Adds a server
   *
   * @param  string  $ip      The IP address of the server
   * @param  integer $port    The port number of the server
   * @param  string  $rcon    The RCON password of the server
   * @param  integer $game    The id of the server game
   * @param  bool    $enabled Whether or not the server is enabled
   * @param  array   $groups  The list of server groups to add the server to
   * @return integer          The id of the added server
   */
  public static function createServer()
  {
    return new SBServer();
  }
  
  
  /**
   * Deletes a server
   *
   * @param integer $id The id of the server to delete
   */
  public static function deleteServer($id)
  {
    $server = self::getServer($id);
    $server->delete();
  }
  
  
  /**
   * Edits a server
   *
   * @param integer $id      The id of the server to edit
   * @param string  $ip      The IP address of the server
   * @param integer $port    The port number of the server
   * @param string  $rcon    The RCON password of the server
   * @param integer $game    The id of the server game
   * @param bool    $enabled Whether or not the server is enabled
   * @param array   $groups  The list of servers groups to add the server to
   */
  public static function editServer($id, $ip = null, $port = null, $rcon = null, $game = null, $enabled = null, $groups = null)
  {
    ServersWriter::edit($id, $ip, $port, $rcon, $game, $enabled, $groups);
  }
  
  
  /**
   * Returns a server
   *
   * @param  integer  $id The id of the server to return
   * @return SBServer     The server
   */
  public static function getServer($id)
  {
    $servers = self::getServers();
    
    return $servers[$id];
  }
  
  
  /**
   * Returns the info from a server
   *
   * @param  integer $id The id of the server to return the info from
   * @return array       The info from the server
   */
  public static function getServerInfo($id)
  {
    $server       = self::getServer($id);
    $server_query = new ServerQuery($server->ip, $server->port);
    
    return $server_query->getInfo();
  }
  
  
  /**
   * Returns the players from a server
   *
   * @param  integer $id The id of the server to return the players from
   * @return array       The players from the server
   */
  public static function getServerPlayers($id)
  {
    $server       = self::getServer($id);
    $server_query = new ServerQuery($server->ip, $server->port);
    
    return $server_query->getPlayers();
  }
  
  
  /**
   * Returns the rules from a server
   *
   * @param  integer $id The id of the server to return the rules from
   * @return array       The rules from the server
   */
  public static function getServerRules($id)
  {
    $server       = self::getServer($id);
    $server_query = new ServerQuery($server->ip, $server->port);
    
    return $server_query->getRules();
  }
  
  
  /**
   * Returns the list of servers
   *
   * @return array The list of servers
   */
  public static function getServers()
  {
    return self::$_registry->servers;
  }
  
  
  /**
   * Sends an RCON command to one or all servers
   *
   * @param  string  $command The RCON command to send to the server
   * @param  integer $id      The id of the server to send the command to, or null for all servers (default: null)
   * @return string           The output of the RCON command
   */
  public static function sendRCON($command, $id = null)
  {
    // One server
    if(is_numeric($id))
    {
      $server      = self::getServer($id);
      $server_rcon = new ServerRcon($server->ip, $server->port, $server->rcon);
      
      if(!$server_rcon->auth())
        throw new SBException('Invalid RCON password.');
      
      return $server_rcon->execute($command);
    }
    // All servers
    else
    {
      foreach(self::getServers() as $server)
      {
        $server_rcon = new ServerRcon($server->ip, $server->port, $server->rcon);
        
        if($server_rcon->auth())
        {
          $server_rcon->execute($command);
        }
      }
    }
  }
  
  
  /**
   * Returns a setting
   *
   * @param  string $name The name of the setting to return
   *                      (bans_hide_admin, bans_hide_ip, bans_public_export, dashboard_text, dashboard_title, date_format,
   *                       default_page, disable_log_popup, enable_debug, enable_protest, enable_submit, items_per_page,
   *                       language, password_min_length, summer_time, theme, timezone, version)
   * @return mixed        The setting
   */
  public static function getSetting($name)
  {
    $settings = self::getSettings();
    
    if(!isset($settings->$name))
      throw new SBException('Invalid name specified.');
    
    return $settings->$name;
  }
  
  
  /**
   * Returns the list of settings
   *
   * @return array The list of settings
   */
  public static function getSettings()
  {
    return self::$_registry->settings;
  }
  
  
  /**
   * Creates a submission
   *
   * @return SBSubmission The created submission
   */
  public static function createSubmission()
  {
    return new SBSubmission();
  }
  
  
  /**
   * Archives a submission
   *
   * @param integer $id The id of the submission to archive
   */
  public static function archiveSubmission($id)
  {
    $submission = self::getSubmission($id);
    $submission->archive();
  }
  
  
  /**
   * Bans a submission
   *
   * @param  integer $id The id of the submission to ban
   * @return SBBan       The added ban
   */
  public static function banSubmission($id)
  {
    $submission = self::getSubmission($id);
    return $submission->ban();
  }
  
  
  /**
   * Deletes a submission
   *
   * @param integer $id The id of the submission to delete
   */
  public static function deleteSubmission($id)
  {
    $submission = self::getSubmission($id);
    $submission->delete();
  }
  
  
  /**
   * Returns a submission
   *
   * @param  integer      $id The id of the submission to return
   * @return SBSubmission     The submission
   */
  public static function getSubmission($id)
  {
    $submissions = self::getSubmissions();
    
    return $submissions[$id];
  }
  
  
  /**
   * Returns a list of submissions
   *
   * @param  integer $limit The amount of submissions to return per page, or 0 for all the submissions
   * @param  integer $page  The page to return
   * @return array          A list of submissions
   */
  public static function getSubmissions($archive = false, $limit = 0, $page = 1, $sort = null, $order = null)
  {
    $submissions          = self::$_registry->submissions;
    $submissions->archive = $archive;
    $submissions->limit   = $limit;
    $submissions->page    = $page;
    
    if(!is_null($order))
    {
      $submissions->order = $order;
    }
    if(!is_null($sort))
    {
      $submissions->sort  = $sort;
    }
    
    return $submissions;
  }
  
  
  /**
   * Restores a submission from the archive
   *
   * @param integer $id The id of the submission to restore
   */
  public static function restoreSubmission($id)
  {
    $submission = self::getSubmission($id);
    $submission->restore();
  }
  
  
  /**
   * Adds a tab to the main menu
   *
   * @param string $url  the url to link to
   * @param string $name the name of the tab
   * @param string $desc the description of the tab
   */
  public static function addTab($url, $name, $desc)
  {
    Tabs::add($url, $name, $desc);
  }
  
  
  /**
   * Returns the list of main menu tabs
   *
   * @return array The list of main menu tabs
   */
  public static function getTabs()
  {
    return Tabs::getTabs();
  }
  
  
  /**
   * Returns a language phrase
   *
   * @param  string $name The name of the phrase to return
   * @param  string $code The code of the language to return
   * @return string       The translation
   */
  public static function getLanguagePhrase($name, $code = 'en')
  {
    $language = self::getLanguage($code);
    
    if(!isset($language->$name))
      throw new SBException('Invalid name specified.');
    
    return $language->$name;
  }
  
  
  /**
   * Returns a list of translations
   *
   * @param  string $lang The language of the translations to return
   * @return array        A list of translations
   */
  public static function getLanguage($code = 'en')
  {
    return new SBLanguage($code);
  }
  
  
  /**
   * Loads language phrases into memory
   *
   * @param string $file The file to load the language phrases from
   */
  public static function loadLanguagePhrases($file)
  {
    $language = self::$_registry->user->language;
    
    if(!file_exists($file))
      throw new SBException($language->file_does_not_exist);
    
    foreach(Util::parse_ini_file($file) as $name => $value)
    {
      $language->$name = $value;
    }
  }
  
  
  /**
   * Sends an e-mail using PHPMailer
   *
   * @param mixed  $to      The e-mail address(es) to send the e-mail to. A string to specify a single address, or an array for multiple addresses
   * @param string $from    The e-mail address to send the e-mail from
   * @param string $subject The e-mail subject
   * @param string $message The e-mail message
   */
  public static function mail($to, $from, $subject, $message)
  {
    $settings = self::$_registry->settings;
    $mail     = new PHPMailer(true);
    
    // If SMTP is enabled
    if($settings->enable_smtp)
    {
      $mail->IsSMTP();
      $mail->Host = $settings->smtp_host;
      $mail->Port = $settings->smtp_port;
      
      // If SMTP username and password have been filled in
      if(!empty($settings->smtp_username) && !empty($settings->smtp_password))
      {
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings->smtp_username;
        $mail->Password   = $settings->smtp_password;
      }
      // If SMTP secure option has been chosen
      if(!empty($settings->smtp_secure))
      {
        $mail->SMTPSecure = $settings->smtp_secure;
      }
    }
    // Add all addresses
    foreach((array)$to as $address)
    {
      $mail->AddAddress($address);
    }
    
    $mail->Subject = $subject;
    $mail->MsgHTML($message);
    $mail->SetFrom($from);
    if($mail->Send())
      return true;
    
    return $mail->ErrorInfo;
  }
}


SB_API::init();