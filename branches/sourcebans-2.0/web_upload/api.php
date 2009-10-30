<?php
require_once 'init.php';
require_once READERS_DIR . 'actions.php';
require_once READERS_DIR . 'admins.php';
require_once READERS_DIR . 'bans.php';
require_once READERS_DIR . 'blocks.php';
require_once READERS_DIR . 'comments.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'logs.php';
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'protests.php';
require_once READERS_DIR . 'quotes.php';
require_once READERS_DIR . 'server_query.php';
require_once READERS_DIR . 'servers.php';
require_once READERS_DIR . 'settings.php';
require_once READERS_DIR . 'submissions.php';
require_once READERS_DIR . 'translations.php';
require_once UTILS_DIR   . 'servers/server_rcon.php';
require_once WRITERS_DIR . 'admins.php';
require_once WRITERS_DIR . 'bans.php';
require_once WRITERS_DIR . 'comments.php';
require_once WRITERS_DIR . 'groups.php';
require_once WRITERS_DIR . 'logs.php';
require_once WRITERS_DIR . 'mods.php';
require_once WRITERS_DIR . 'protests.php';
require_once WRITERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'settings.php';
require_once WRITERS_DIR . 'submissions.php';

class SB_API
{
  /**
   * Returns a list of actions
   *
   * @param integer $limit The amount of actions to return per page, or 0 for all the actions
   * @param integer $page  The page to return
   */
  public static function getActions($limit = 0, $page = 1)
  {
    $actions_reader        = new ActionsReader();
    $actions_reader->limit = $limit;
    $actions_reader->page  = $page;
    $actions               = $actions_reader->executeCached(ONE_MINUTE * 5);
    
    return $actions;
  }
  
  
  /**
   * Adds an admin
   *
   * @param  string  $name         The name of the admin
   * @param  string  $auth         The authentication type of the admin (STEAM_AUTH_TYPE, IP_AUTH_TYPE, NAME_AUTH_TYPE)
   * @param  string  $identity     The identity of the admin
   * @param  string  $email        The e-mail address of the admin
   * @param  string  $password     The password of the admin
   * @param  bool    $srv_password Whether or not the password should be used as server password
   * @param  array   $srv_groups   The list of server admin groups of the admin
   * @param  integer $web_group    The web admin group of the admin
   * @return The id of the added admin
   */
  public static function addAdmin($name, $auth, $identity, $email = '', $password = '', $srv_password = false, $srv_groups = array(), $web_group = null)
  {
    return AdminsWriter::add($name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group);
  }
  
  
  /**
   * Deletes an admin
   *
   * @param integer $id The id of the admin to delete
   */
  public static function deleteAdmin($id)
  {
    AdminsWriter::delete($id);
  }
  
  
  /**
   * Edits an admin
   *
   * @param integer $id           The id of the admin to edit
   * @param string  $name         The name of the admin
   * @param string  $auth         The authentication type of the admin (STEAM_AUTH_TYPE, IP_AUTH_TYPE, NAME_AUTH_TYPE)
   * @param string  $identity     The identity of the admin
   * @param string  $email        The e-mail address of the admin
   * @param string  $password     The password of the admin
   * @param bool    $srv_password Whether or not the password should be used as server password
   * @param array   $srv_groups   The list of server admin groups of the admin
   * @param integer $web_group    The web admin group of the admin
   * @param string  $theme        The theme setting of the admin
   * @param string  $language     The language setting of the admin
   */
  public static function editAdmin($id, $name = null, $auth = null, $identity = null, $email = null, $password = null, $srv_password = null, $srv_groups = null, $web_group = null, $theme = null, $language = null)
  {
    AdminsWriter::edit($id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group, $theme, $language);
  }
  
  
  /**
   * Returns an admin
   *
   * @param integer $id The id of the admin to return
   */
  public static function getAdmin($id)
  {
    $admins = self::getAdmins();
    
    if(!isset($admins[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $admins[$id];
  }
  
  
  /**
   * Returns a list of admins
   *
   * @param integer $limit The amount of admins to return per page, or 0 for all the admins
   * @param integer $page  The page to return
   */
  public static function getAdmins($limit = 0, $page = 1)
  {
    $admins_reader        = new AdminsReader();
    $admins_reader->limit = $limit;
    $admins_reader->page  = $page;
    $admins               = $admins_reader->executeCached(ONE_MINUTE * 5);
    
    return $admins;
  }
  
  
  /**
   * Returns a list of server admins
   *
   * @param integer $server_id The id of the server to get the admins from
   * @param integer $limit     The amount of admins to return per page, or 0 for all the admins
   * @param integer $page      The page to return
   */
  public static function getServerAdmins($server_id, $limit = 0, $page = 1)
  {
    $admins_reader         = new AdminsReader();
    $admins_reader->limit  = $limit;
    $admins_reader->page   = $page;
    $admins_reader->search = $server_id;
    $admins_reader->type   = 'server';
    $admins                = $admins_reader->executeCached(ONE_MINUTE * 5);
    
    return $admins;
  }
  
  
  /**
   * Imports one or more admins
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function importAdmins($file, $tmp_name = '')
  {
    AdminsWriter::import($file, $tmp_name);
  }
  
  
  /**
   * Returns a filtered list of admins
   *
   * @param string  $search The string to search for
   * @param string  $type   The type of search to perform
   * @param integer $limit  The amount of admins to return per page, or 0 for all the admins
   * @param integer $page   The page to return
   */
  public static function searchAdmins($search, $type, $limit = 0, $page = 1)
  {
    $admins_reader         = new AdminsReader();
    $admins_reader->limit  = $limit;
    $admins_reader->page   = $page;
    $admins_reader->search = $search;
    $admins_reader->type   = $type;
    $admins                = $admins_reader->executeCached(ONE_MINUTE * 5);
    
    return $admins;
  }
  
  
  /**
   * Adds a ban
   *
   * @param  integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param  string  $steam  The Steam ID of the banned player
   * @param  string  $ip     The IP address of the banned player
   * @param  string  $name   The name of the banned player
   * @param  string  $reason The reason of the ban
   * @param  integer $length The length of the ban in minutes
   * @param  integer $server The server id on which the ban was performed, or 0 for a web ban
   * @return The id of the added ban
   */
  public static function addBan($type, $steam, $ip, $name, $reason, $length, $server = 0)
  {
    return BansWriter::add($type, $steam, $ip, $name, $reason, $length, $server);
  }
  
  
  /**
   * Deletes a ban
   *
   * @param integer $id The id of the ban to delete
   */
  public static function deleteBan($id)
  {
    BansWriter::delete($id);
  }
  
  
  /**
   * Edits a ban
   *
   * @param integer $id     The id of the ban to edit
   * @param integer $type   The type of the ban
   * @param string  $steam  The Steam ID of the banned player
   * @param string  $ip     The IP address of the banned player
   * @param string  $name   The name of the banned player
   * @param string  $reason The reason of the ban
   * @param integer $length The length of the ban in minutes
   */
  public static function editBan($id, $type = null, $steam = null, $ip = null, $name = null, $reason = null, $length = null)
  {
    BansWriter::edit($id, $type, $steam, $ip, $name, $reason, $length);
  }
  
  
  /**
   * Returns a ban
   *
   * @param integer $id The id of the ban to return
   */
  public static function getBan($id)
  {
    $bans = self::getBans();
    
    if(!isset($bans[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $bans[$id];
  }
  
  
  /**
   * Returns a list of bans
   *
   * @param integer $limit The amount of bans to return per page, or 0 for all the bans
   * @param integer $page  The page to return
   */
  public static function getBans($limit = 0, $page = 1)
  {
    $bans_reader        = new BansReader();
    $bans_reader->limit = $limit;
    $bans_reader->page  = $page;
    $bans               = $bans_reader->executeCached(ONE_MINUTE * 5);
    
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
    BansWriter::import($file, $tmp_name);
  }
  
  
  public static function searchBans($search, $type, $limit = 0, $page = 1)
  {
    $bans_reader         = new BansReader();
    $bans_reader->limit  = $limit;
    $bans_reader->page   = $page;
    $bans_reader->search = $search;
    $bans_reader->type   = $type;
    $bans                = $bans_reader->executeCached(ONE_MINUTE * 5);
    
    return $bans;
  }
  
  
  /**
   * Unbans a ban
   *
   * @param integer $id     The id of the ban to unban
   * @param string  $reason The reason for unbanning the ban
   */
  public static function unbanBan($id, $reason)
  {
    BansWriter::unban($id, $reason);
  }
  
  
  /**
   * Returns a list of blocks
   *
   * @param integer $limit The amount of blocks to return per page, or 0 for all the blocks
   * @param integer $page  The page to return
   */
  public static function getBlocks($limit = 0, $page = 1)
  {
    $blocks_reader        = new BlocksReader();
    $blocks_reader->limit = $limit;
    $blocks_reader->page  = $page;
    $blocks               = $blocks_reader->executeCached(ONE_MINUTE * 5);
    
    return $blocks;
  }
  
  
  /**
   * Clears the cache
   */
  public static function clearCache()
  {
    Util::clearCache();
  }
  
  
  /**
   * Adds a comment
   *
   * @param  integer $ban_id  The id of the ban/protest/submission to comment to
   * @param  integer $type    The type of the comment (BAN_TYPE, PROTEST_TYPE, SUBMISSION_TYPE)
   * @param  string  $message The message of the comment
   * @return The id of the added comment
   */
  public static function addComment($ban_id, $type, $message)
  {
    return CommentsWriter::add($ban_id, $type, $message);
  }
  
  
  /**
   * Deletes a comment
   *
   * @param integer $id The id of the comment to delete
   */
  public static function deleteComment($id)
  {
    CommentsWriter::delete($id);
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
   * @param integer $id The id of the comment to return
   */
  public static function getComment($id)
  {
    $comments = self::getComments();
    
    if(!isset($comments[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $comments[$id];
  }
  
  
  /**
   * Returns a list of comments
   *
   * @param integer $ban_id The id of the ban/protest/submission to return the comments from
   * @param integer $type   The type of the comments to return (BAN_TYPE, PROTEST_TYPE, SUBMISSION_TYPE)
   */
  public static function getComments($ban_id, $type)
  {
    $comments_reader       = new CommentsReader();
    $comments_reader->bid  = $ban_id;
    $comments_reader->type = $type;
    $comments              = $comments_reader->executeCached(ONE_DAY);
    
    return $comments;
  }
  
  
  /**
   * Adds a group
   *
   * @param  integer $type      The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param  string  $name      The name of the group
   * @param  mixed   $flags     The access flags of the group
   * @param  integer $immunity  The immunity level of the group
   * @param  array   $overrides The overrides of the group
   * @return The id of the added group
   */
  public static function addGroup($type, $name, $flags, $immunity = 0, $overrides = array())
  {
    return GroupsWriter::add($type, $name, $flags, $immunity, $overrides);
  }
  
  
  /**
   * Deletes a group
   *
   * @param integer $id   The id of the group to delete
   * @param integer $type The type of the group to delete (SERVER_GROUPS, WEB_GROUPS)
   */
  public static function deleteGroup($id, $type)
  {
    GroupsWriter::delete($id, $type);
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
   * Returns a group
   *
   * @param integer $type The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param integer $id   The id of the group to return
   */
  public static function getGroup($type, $id)
  {
    $groups = self::getGroups($type);
    
    if(!isset($groups[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $groups[$id];
  }
  
  
  /**
   * Returns a list of groups
   *
   * @param integer $type The type of the groups (SERVER_GROUPS, WEB_GROUPS)
   */
  public static function getGroups($type)
  {
    $groups_reader       = new GroupsReader();
    $groups_reader->type = $type;
    $groups              = $groups_reader->executeCached(ONE_MINUTE * 5);
    
    return $groups;
  }
  
  
  /**
   * Imports one or more groups
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   */
  public static function importGroups($file, $tmp_name = '')
  {
    GroupsWriter::import($file, $tmp_name);
  }
  
  
  /**
   * Adds a log
   *
   * @param  string $type    The type of the log
   * @param  string $title   The title of the log
   * @param  string $message The message of the log
   * @return The id of the added log
   */
  public static function addLog($type, $title, $message)
  {
    LogsWriter::add($type, $title, $message);
  }
  
  
  /**
   * Clears the logs
   */
  public static function clearLogs()
  {
    LogsWriter::clear();
  }
  
  
  /**
   * Returns a log
   *
   * @param integer $id The id of the log to return
   */
  public static function getLog($id)
  {
    $logs = self::getLogs();
    
    if(!isset($logs[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $logs[$id];
  }
  
  
  /**
   * Returns a list of logs
   *
   * @param integer $limit The amount of logs to return per page, or 0 for all the logs
   * @param integer $page  The page to return
   */
  public static function getLogs($limit = 0, $page = 1)
  {
    $logs_reader        = new LogsReader();
    $logs_reader->limit = $limit;
    $logs_reader->page  = $page;
    $logs               = $logs_reader->executeCached(ONE_MINUTE * 5);
    
    return $logs;
  }
  
  
  /**
   * Adds a mod
   *
   * @param  string $name    The name of the mod
   * @param  string $folder  The folder of the mod
   * @param  string $icon    The icon of the mod
   * @return The id of the added mod
   */
  public static function addMod($name, $folder, $icon)
  {
    return ModsWriter::add($name, $folder, $icon);
  }
  
  
  /**
   * Deletes a mod
   *
   * @param integer $id The id of the mod to delete
   */
  public static function deleteMod($id)
  {
    ModsWriter::delete($id);
  }
  
  
  /**
   * Edits a mod
   *
   * @param integer $id      The id of the mod to edit
   * @param string  $name    The name of the mod
   * @param string  $folder  The folder of the mod
   * @param string  $icon    The icon of the mod
   */
  public static function editMod($id, $name = null, $folder = null, $icon = null)
  {
    ModsWriter::edit($id, $name, $folder, $icon);
  }
  
  
  /**
   * Returns a mod
   *
   * @param integer $id The id of the mod to return
   */
  public static function getMod($id)
  {
    $mods = self::getMods();
    
    if(!isset($mods[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $mods[$id];
  }
  
  
  /**
   * Returns the list of mods
   */
  public static function getMods()
  {
    $mods_reader = new ModsReader();
    $mods        = $mods_reader->executeCached(ONE_DAY);
    
    return $mods;
  }
  
  
  /**
   * Calls a hook on the enabled plugins
   *
   * @param  string $hook     The hook to call
   * @param  mixed  $args[]   The arguments to pass to the hook
   * @return array  $ref_args The referenced arguments to pass back to the calling function
   */
  public static function callHook()
  {
    return SBPlugins::call(func_get_args());
  }
  
  /**
   * Returns the list of plugins
   */
  public static function getPlugins()
  {
    return SBPlugins::getPlugins();
  }
  
  
  /**
   * Adds a protest
   *
   * @param  string  $name   The name of the banned player
   * @param  integer $type   The type of the ban (STEAM_BAN_TYPE, IP_BAN_TYPE)
   * @param  string  $steam  The Steam ID of the banned player
   * @param  string  $ip     The IP address of the banned player
   * @param  string  $reason The reason of the protest
   * @param  string  $email  The e-mail address of the protester
   * @return The id of the added protest
   */
  public static function addProtest($name, $type, $steam, $ip, $reason, $email)
  {
    return ProtestsWriter::add($name, $type, $steam, $ip, $reason, $email);
  }
  
  
  /**
   * Archives a protest
   *
   * @param integer $id The id of the protest to archive
   */
  public static function archiveProtest($id)
  {
    ProtestsWriter::archive($id);
  }
  
  
  /**
   * Deletes a protest
   *
   * @param integer $id The id of the protest to delete
   */
  public static function deleteProtest($id)
  {
    ProtestsWriter::delete($id);
  }
  
  
  /**
   * Returns a protest
   *
   * @param integer $id The id of the protest to return
   */
  public static function getProtest($id)
  {
    $protests = self::getProtests();
    
    if(!isset($protests[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $protests[$id];
  }
  
  
  /**
   * Returns a list of protests
   *
   * @param integer $limit The amount of protests to return per page, or 0 for all the protests
   * @param integer $page  The page to return
   */
  public static function getProtests($limit = 0, $page = 1)
  {
    $protests_reader        = new ProtestsReader();
    $protests_reader->limit = $limit;
    $protests_reader->page  = $page;
    $protests               = $protests_reader->executeCached(ONE_MINUTE * 5);
    
    return $protests;
  }
  
  
  /**
   * Restores a protest from the archive
   *
   * @param integer $id The id of the protest to restore
   */
  public static function restoreProtest($id)
  {
    ProtestsWriter::restore($id);
  }
  
  
  /**
   * Returns a list of quotes
   */
  public static function getQuotes()
  {
    $quotes_reader = new QuotesReader();
    $quotes        = $quotes_reader->executeCached(ONE_DAY);
    
    return $quotes;
  }
  
  
  /**
   * Returns a random quote
   */
  public static function getRandomQuote()
  {
    $quotes = self::getQuotes();
    
    return $quotes[array_rand($quotes)];
  }
  
  
  /**
   * Adds a server
   *
   * @param  string  $ip      The IP address of the server
   * @param  integer $port    The port number of the server
   * @param  string  $rcon    The RCON password of the server
   * @param  integer $mod     The id of the server mod
   * @param  bool    $enabled Whether or not the server is enabled
   * @param  array   $groups  The list of server groups to add the server to
   * @return The id of the added server
   */
  public static function addServer($ip, $port, $rcon, $mod, $enabled = true, $groups = array())
  {
    return ServersWriter::add($ip, $port, $rcon, $mod, $enabled, $groups);
  }
  
  
  /**
   * Deletes a server
   *
   * @param integer $id The id of the server to delete
   */
  public static function deleteServer($id)
  {
    ServersWriter::delete($id);
  }
  
  
  /**
   * Edits a server
   *
   * @param integer $id      The id of the server to edit
   * @param string  $ip      The IP address of the server
   * @param integer $port    The port number of the server
   * @param string  $rcon    The RCON password of the server
   * @param integer $mod     The id of the server mod
   * @param bool    $enabled Whether or not the server is enabled
   * @param array   $groups  The list of servers groups to add the server to
   */
  public static function editServer($id, $ip = null, $port = null, $rcon = null, $mod = null, $enabled = null, $groups = null)
  {
    ServersWriter::edit($id, $ip, $port, $rcon, $mod, $enabled, $groups);
  }
  
  
  /**
   * Returns a server
   *
   * @param integer $id The id of the server to return
   */
  public static function getServer($id)
  {
    $servers = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $servers[$id];
  }
  
  
  /**
   * Returns the info from a server
   *
   * @param integer $id The id of the server to return the info from
   */
  public static function getServerInfo($id)
  {
    $servers     = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_INFO;
    $server_info               = $server_query_reader->executeCached(ONE_MINUTE);
    
    return $server_info;
  }
  
  
  /**
   * Returns the players from a server
   *
   * @param integer $id The id of the server to return the players from
   */
  public static function getServerPlayers($id)
  {
    $servers        = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_PLAYERS;
    $server_players            = $server_query_reader->executeCached(ONE_MINUTE);
    
    return $server_players;
  }
  
  
  /**
   * Returns the rules from a server
   *
   * @param integer $id The id of the server to return the rules from
   */
  public static function getServerRules($id)
  {
    $servers        = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_RULES;
    $server_rules              = $server_query_reader->executeCached(ONE_MINUTE);
    
    return $server_rules;
  }
  
  
  /**
   * Returns the list of servers
   */
  public static function getServers()
  {
    $servers_reader = new ServersReader();
    $servers        = $servers_reader->executeCached(ONE_MINUTE);
    
    return $servers;
  }
  
  
  /**
   * Sends an RCON command to one or all servers
   *
   * @param string  $command The RCON command to send to the server
   * @param integer $id      The id of the server to send the command to, or 0 for all servers
   */
  public static function sendRCON($command, $id = 0)
  {
    $servers = self::getServers();
    
    if($id)
    {
      if(!isset($servers[$id]))
        throw new Exception('Invalid ID specified.');
      
      $server_rcon = new CServerRcon($servers[$id]['ip'], $servers[$id]['port'], $servers[$id]['rcon']);
      
      if(!$server_rcon->Auth())
        throw new Exception('Invalid RCON password.');
      
      return $server_rcon->rconCommand($command);
    }
    else
    {
      foreach($servers as $server)
      {
        $server_rcon = new CServerRcon($server['ip'], $server['port'], $server['rcon']);
        
        if($server_rcon->Auth())
          $server_rcon->rconCommand($command);
      }
    }
  }
  
  
  /**
   * Returns a setting
   *
   * @param string $name The name of the setting to return (banlist.bansperpage, banlist.hideadminname, config.dateformat, config.debug, config.defaultpage, config.enableprotest,
                                                            config.enablesubmit, config.exportpublic, config.language, config.password.minlength, config.summertime, config.theme,
                                                            config.timezone, config.version, dash.intro.text, dash.intro.title, dash.lognopopup, template.logo, template.title)
   */
  public static function getSetting($name)
  {
    $settings = self::getSettings();
    
    if(!isset($settings[$name]))
      throw new Exception('Invalid name specified.');
    
    return $settings[$name];
  }
  
  
  /**
   * Returns the list of settings
   */
  public static function getSettings()
  {
    $settings_reader = new SettingsReader();
    $settings        = $settings_reader->executeCached(ONE_DAY);
    
    return $settings;
  }
  
  
  /**
   * Updates one or more settings
   *
   * @param array $settings The list of settings to update
   */
  public static function updateSettings($settings)
  {
    SettingsWriter::update($settings);
  }
  
  
  /**
   * Adds a submission
   *
   * @param  string  $steam    The Steam ID of the player to ban
   * @param  string  $ip       The IP address of the player to ban
   * @param  string  $name     The name of the player to ban
   * @param  string  $reason   The reason of the submission
   * @param  string  $subname  The name of the submitter
   * @param  string  $subemail The e-mail address of the submitter
   * @param  integer $server   The server id on which the player was playing
   * @return The id of the added submission
   */
  public static function addSubmission($steam, $ip, $name, $reason, $subname, $subemail, $server = 0)
  {
    return SubmissionsWriter::add($steam, $ip, $name, $reason, $subname, $subemail, $server);
  }
  
  
  /**
   * Archives a submission
   *
   * @param integer $id The id of the submission to archive
   */
  public static function archiveSubmission($id)
  {
    SubmissionsWriter::archive($id);
  }
  
  
  /**
   * Bans a submission
   *
   * @param  integer $id The id of the submission to ban
   * @return The id of the added ban
   */
  public static function banSubmission($id)
  {
    return SubmissionsWriter::ban($id);
  }
  
  
  /**
   * Deletes a submission
   *
   * @param integer $id The id of the submission to delete
   */
  public static function deleteSubmission($id)
  {
    SubmissionsWriter::delete($id);
  }
  
  
  /**
   * Returns a submission
   *
   * @param integer $id The id of the submission to return
   */
  public static function getSubmission($id)
  {
    $submissions = self::getSubmissions();
    
    if(!isset($submissions[$id]))
      throw new Exception('Invalid ID specified.');
    
    return $submissions[$id];
  }
  
  
  /**
   * Returns a list of submissions
   *
   * @param integer $limit The amount of submissions to return per page, or 0 for all the submissions
   * @param integer $page  The page to return
   */
  public static function getSubmissions($limit = 0, $page = 1)
  {
    $submissions_reader        = new SubmissionsReader();
    $submissions_reader->limit = $limit;
    $submissions_reader->page  = $page;
    $submissions               = $submissions_reader->executeCached(ONE_MINUTE * 5);
    
    return $submissions;
  }
  
  
  /**
   * Restores a submission from the archive
   *
   * @param integer $id The id of the submission to restore
   */
  public static function restoreSubmission($id)
  {
    SubmissionsWriter::restore($id);
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
   */
  public static function getTabs()
  {
    return Tabs::getTabs();
  }
  
  
  /**
   * Returns a translation
   *
   * @param string $name The name of the translation to return
   * @param string $lang The language of the translation to return
   */
  public static function getTranslation($name, $lang = 'en')
  {
    $translations = self::getTranslations($lang);
    
    if(!isset($translations[$name]))
      throw new Exception('Invalid name specified.');
    
    return $translations[$name];
  }
  
  
  /**
   * Returns a list of translations
   *
   * @param string $lang The language of the translations to return
   */
  public static function getTranslations($lang = 'en')
  {
    $translations_reader           = new TranslationsReader();
    $translations_reader->language = $lang;
    $translations                  = $translations_reader->executeCached(ONE_DAY);
    
    return $translations;
  }
  
  
  /**
   * Loads translations into memory
   *
   * @param string $lang The language of the translations to return
   */
  public static function loadTranslations($file)
  {
    $phrases      = Env::get('phrases');
    
    if(!file_exists($file))
      throw new Exception($phrases['file_does_not_exist']);
    
    $translations = Util::parse_ini_file($file);
    Env::set('phrases', array_merge($phrases, $translations['phrases']));
  }
}
?>