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
require_once READERS_DIR . 'overrides.php';
require_once READERS_DIR . 'protests.php';
require_once READERS_DIR . 'quotes.php';
require_once READERS_DIR . 'server_query.php';
require_once READERS_DIR . 'servers.php';
require_once READERS_DIR . 'settings.php';
require_once READERS_DIR . 'submissions.php';
require_once READERS_DIR . 'translations.php';
require_once UTILS_DIR   . 'servers/server_rcon.php';
require_once WRITERS_DIR . 'actions.php';
require_once WRITERS_DIR . 'admins.php';
require_once WRITERS_DIR . 'bans.php';
require_once WRITERS_DIR . 'comments.php';
require_once WRITERS_DIR . 'groups.php';
require_once WRITERS_DIR . 'logs.php';
require_once WRITERS_DIR . 'mods.php';
require_once WRITERS_DIR . 'overrides.php';
require_once WRITERS_DIR . 'protests.php';
require_once WRITERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'settings.php';
require_once WRITERS_DIR . 'submissions.php';

class SB_API
{
  /**
   * Clears the actions
   *
   * @noreturn
   */
  public static function clearActions()
  {
    ActionsWriter::clear();
  }
  
  
  /**
   * Returns a list of actions
   *
   * @param  integer $limit The amount of actions to return per page, or 0 for all the actions
   * @param  integer $page  The page to return
   * @return array   A list of actions
   */
  public static function getActions($limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $actions_reader         = new ActionsReader();
    $actions_reader->limit  = $limit;
    $actions_reader->page   = $page;
    $actions_reader->search = $search;
    $actions_reader->type   = $type;
    
    if(!is_null($order))
      $actions_reader->order = $order;
    if(!is_null($sort))
      $actions_reader->sort  = $sort;
    
    return $actions_reader->executeCached(ONE_MINUTE * 5);
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
   * @return integer The id of the added admin
   */
  public static function addAdmin($name, $auth, $identity, $email = '', $password = '', $srv_password = false, $srv_groups = array(), $web_group = null)
  {
    return AdminsWriter::add($name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group);
  }
  
  
  /**
   * Deletes an admin
   *
   * @param integer $id The id of the admin to delete
   * @noreturn
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
   * @noreturn
   */
  public static function editAdmin($id, $name = null, $auth = null, $identity = null, $email = null, $password = null, $srv_password = null, $srv_groups = null, $web_group = null, $theme = null, $language = null)
  {
    AdminsWriter::edit($id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group, $theme, $language);
  }
  
  
  /**
   * Returns an admin
   *
   * @param  integer $id The id of the admin to return
   * @return array   The admin
   */
  public static function getAdmin($id)
  {
    $admins = self::getAdmins();
    
    if(!isset($admins['list'][$id]))
      throw new Exception('Invalid ID specified.');
    
    return $admins['list'][$id];
  }
  
  
  /**
   * Returns a list of admins
   *
   * @param  integer $limit The amount of admins to return per page, or 0 for all the admins
   * @param  integer $page  The page to return
   * @return array   A list of admins
   */
  public static function getAdmins($limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $admins_reader         = new AdminsReader();
    $admins_reader->limit  = $limit;
    $admins_reader->page   = $page;
    $admins_reader->search = $search;
    $admins_reader->type   = $type;
    
    if(!is_null($order))
      $admins_reader->order = $order;
    if(!is_null($sort))
      $admins_reader->sort  = $sort;
    
    return $admins_reader->executeCached(ONE_MINUTE * 5);
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
    AdminsWriter::import($file, $tmp_name);
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
   * @return integer The id of the added ban
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
   * @noreturn
   */
  public static function editBan($id, $type = null, $steam = null, $ip = null, $name = null, $reason = null, $length = null)
  {
    BansWriter::edit($id, $type, $steam, $ip, $name, $reason, $length);
  }
  
  
  /**
   * Returns a ban
   *
   * @param  integer $id The id of the ban to return
   * @return array   The ban
   */
  public static function getBan($id)
  {
    $bans = self::getBans();
    
    if(!isset($bans['list'][$id]))
      throw new Exception('Invalid ID specified.');
    
    return $bans[$id];
  }
  
  
  /**
   * Returns a list of bans
   *
   * @param  integer $limit The amount of bans to return per page, or 0 for all the bans
   * @param  integer $page  The page to return
   * @return array   A list of bans
   */
  public static function getBans($hideinactive = false, $limit = 0, $page = 1, $sort = null, $order = null, $search = null, $type = null)
  {
    $bans_reader               = new BansReader();
    $bans_reader->hideinactive = $hideinactive;
    $bans_reader->limit        = $limit;
    $bans_reader->page         = $page;
    $bans_reader->search       = $search;
    $bans_reader->type         = $type;
    
    if(!is_null($sort))
      $bans_reader->sort  = $sort;
    if(!is_null($order))
      $bans_reader->order = $order;
    
    return $bans_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Imports one or more bans
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   * @noreturn
   */
  public static function importBans($file, $tmp_name = '')
  {
    BansWriter::import($file, $tmp_name);
  }
  
  
  /**
   * Unbans a ban
   *
   * @param integer $id     The id of the ban to unban
   * @param string  $reason The reason for unbanning the ban
   * @noreturn
   */
  public static function unbanBan($id, $reason)
  {
    BansWriter::unban($id, $reason);
  }
  
  
  /**
   * Returns a list of blocks
   *
   * @param  integer $limit The amount of blocks to return per page, or 0 for all the blocks
   * @param  integer $page  The page to return
   * @return array   A list of blocks
   */
  public static function getBlocks($limit = 0, $page = 1)
  {
    $blocks_reader        = new BlocksReader();
    $blocks_reader->limit = $limit;
    $blocks_reader->page  = $page;
    
    return $blocks_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Clears the cache
   *
   * @noreturn
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
   * @return integer The id of the added comment
   */
  public static function addComment($ban_id, $type, $message)
  {
    return CommentsWriter::add($ban_id, $type, $message);
  }
  
  
  /**
   * Deletes a comment
   *
   * @param integer $id The id of the comment to delete
   * @noreturn
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
   * @noreturn
   */
  public static function editComment($id, $message)
  {
    CommentsWriter::edit($id, $message);
  }
  
  
  /**
   * Returns a comment
   *
   * @param  integer $id The id of the comment to return
   * @return array   The comment
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
   * @param  integer $ban_id The id of the ban/protest/submission to return the comments from
   * @param  integer $type   The type of the comments to return (BAN_TYPE, PROTEST_TYPE, SUBMISSION_TYPE)
   * @return array   A list of comments
   */
  public static function getComments($ban_id, $type)
  {
    $comments_reader         = new CommentsReader();
    $comments_reader->ban_id = $ban_id;
    $comments_reader->type   = $type;
    
    return $comments_reader->executeCached(ONE_DAY);
  }
  
  
  /**
   * Adds a group
   *
   * @param  integer $type      The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param  string  $name      The name of the group
   * @param  mixed   $flags     The access flags of the group
   * @param  integer $immunity  The immunity level of the group
   * @param  array   $overrides The overrides of the group
   * @return integer The id of the added group
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
   * @noreturn
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
   * @noreturn
   */
  public static function editGroup($id, $type, $name = null, $flags = null, $immunity = null, $overrides = null)
  {
    GroupsWriter::edit($id, $type, $name, $flags, $immunity, $overrides);
  }
  
  
  /**
   * Returns a group
   *
   * @param  integer $type The type of the group (SERVER_GROUPS, WEB_GROUPS)
   * @param  integer $id   The id of the group to return
   * @return array   The group
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
   * @param  integer $type The type of the groups (SERVER_GROUPS, WEB_GROUPS)
   * @return array   A list of groups
   */
  public static function getGroups($type)
  {
    $groups_reader       = new GroupsReader();
    $groups_reader->type = $type;
    
    return $groups_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Imports one or more groups
   *
   * @param string $file     The file to import from
   * @param string $tmp_name Optional temporary filename
   * @noreturn
   */
  public static function importGroups($file, $tmp_name = '')
  {
    GroupsWriter::import($file, $tmp_name);
  }
  
  
  /**
   * Adds a log
   *
   * @param  string  $type    The type of the log
   * @param  string  $title   The title of the log
   * @param  string  $message The message of the log
   * @return integer The id of the added log
   */
  public static function addLog($type, $title, $message)
  {
    return LogsWriter::add($type, $title, $message);
  }
  
  
  /**
   * Clears the logs
   *
   * @noreturn
   */
  public static function clearLogs()
  {
    LogsWriter::clear();
  }
  
  
  /**
   * Returns a log
   *a
   * @param  integer $id The id of the log to return
   * @return array   The log
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
   * @param  integer $limit The amount of logs to return per page, or 0 for all the logs
   * @param  integer $page  The page to return
   * @return array   A list of logs
   */
  public static function getLogs($limit = 0, $page = 1)
  {
    $logs_reader        = new LogsReader();
    $logs_reader->limit = $limit;
    $logs_reader->page  = $page;
    
    return $logs_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Adds a mod
   *
   * @param  string  $name    The name of the mod
   * @param  string  $folder  The folder of the mod
   * @param  string  $icon    The icon of the mod
   * @return integer The id of the added mod
   */
  public static function addMod($name, $folder, $icon)
  {
    return ModsWriter::add($name, $folder, $icon);
  }
  
  
  /**
   * Deletes a mod
   *
   * @param integer $id The id of the mod to delete
   * @noreturn
   */
  public static function deleteMod($id)
  {
    ModsWriter::delete($id);
  }
  
  
  /**
   * Edits a mod
   *
   * @param integer $id     The id of the mod to edit
   * @param string  $name   The name of the mod
   * @param string  $folder The folder of the mod
   * @param string  $icon   The icon of the mod
   * @noreturn
   */
  public static function editMod($id, $name = null, $folder = null, $icon = null)
  {
    ModsWriter::edit($id, $name, $folder, $icon);
  }
  
  
  /**
   * Returns a mod
   *
   * @param  integer $id The id of the mod to return
   * @return array   The mod
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
   *
   * @return array The list of mods
   */
  public static function getMods()
  {
    $mods_reader = new ModsReader();
    
    return $mods_reader->executeCached(ONE_DAY);
  }
  
  
  /**
   * Adds an override
   *
   * @param  string  $type  The type of the override
   * @param  string  $name  The name of the override
   * @param  string  $flags The flags of the override
   * @return integer The id of the added override
   */
  public static function addOverride($type, $name, $flags)
  {
    return OverridesWriter::add($type, $name, $flags);
  }
  
  
  /**
   * Clears the overrides
   *
   * @noreturn
   */
  public static function clearOverrides()
  {
    OverridesWriter::clear();
  }
  
  
  /**
   * Returns the list of overrides
   *
   * @return array The list of overrides
   */
  public static function getOverrides()
  {
    $overrides_reader = new OverridesReader();
    
    return $overrides_reader->executeCached(ONE_MINUTE * 5);
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
    return SBPlugins::call(func_get_args());
  }
  
  
  /**
   * Returns the list of plugins
   *
   * @return array The list of plugins
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
   * @return integer The id of the added protest
   */
  public static function addProtest($name, $type, $steam, $ip, $reason, $email)
  {
    return ProtestsWriter::add($name, $type, $steam, $ip, $reason, $email);
  }
  
  
  /**
   * Archives a protest
   *
   * @param integer $id The id of the protest to archive
   * @noreturn
   */
  public static function archiveProtest($id)
  {
    ProtestsWriter::archive($id);
  }
  
  
  /**
   * Deletes a protest
   *
   * @param integer $id The id of the protest to delete
   * @noreturn
   */
  public static function deleteProtest($id)
  {
    ProtestsWriter::delete($id);
  }
  
  
  /**
   * Returns a protest
   *
   * @param  integer $id The id of the protest to return
   * @return array   The protest
   */
  public static function getProtest($id)
  {
    $protests = self::getProtests();
    
    if(!isset($protests['list'][$id]))
      throw new Exception('Invalid ID specified.');
    
    return $protests['list'][$id];
  }
  
  
  /**
   * Returns a list of protests
   *
   * @param  integer $limit The amount of protests to return per page, or 0 for all the protests
   * @param  integer $page  The page to return
   * @return array   A list of protests
   */
  public static function getProtests($archive = false, $limit = 0, $page = 1, $sort = null, $order = null)
  {
    $protests_reader          = new ProtestsReader();
    $protests_reader->archive = $archive;
    $protests_reader->limit   = $limit;
    $protests_reader->page    = $page;
    
    if(!is_null($order))
      $protests_reader->order = $order;
    if(!is_null($sort))
      $protests_reader->sort  = $sort;
    
    return $protests_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Restores a protest from the archive
   *
   * @param integer $id The id of the protest to restore
   * @noreturn
   */
  public static function restoreProtest($id)
  {
    ProtestsWriter::restore($id);
  }
  
  
  /**
   * Returns the list of quotes
   *
   * @return array The list of quotes
   */
  public static function getQuotes()
  {
    $quotes_reader = new QuotesReader();
    
    return $quotes_reader->executeCached(ONE_DAY);
  }
  
  
  /**
   * Returns a random quote
   *
   * @return array A random quote
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
   * @return integer The id of the added server
   */
  public static function addServer($ip, $port, $rcon, $mod, $enabled = true, $groups = array())
  {
    return ServersWriter::add($ip, $port, $rcon, $mod, $enabled, $groups);
  }
  
  
  /**
   * Deletes a server
   *
   * @param integer $id The id of the server to delete
   * @noreturn
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
   * @noreturn
   */
  public static function editServer($id, $ip = null, $port = null, $rcon = null, $mod = null, $enabled = null, $groups = null)
  {
    ServersWriter::edit($id, $ip, $port, $rcon, $mod, $enabled, $groups);
  }
  
  
  /**
   * Returns a server
   *
   * @param  integer $id The id of the server to return
   * @return array   The server
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
   * @param  integer $id The id of the server to return the info from
   * @return array   The info from the server
   */
  public static function getServerInfo($id)
  {
    $servers = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_INFO;
    
    return $server_query_reader->executeCached(ONE_MINUTE);
  }
  
  
  /**
   * Returns the players from a server
   *
   * @param  integer $id The id of the server to return the players from
   * @return array   The players from the server
   */
  public static function getServerPlayers($id)
  {
    $servers = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_PLAYERS;
    
    return $server_query_reader->executeCached(ONE_MINUTE);
  }
  
  
  /**
   * Returns the rules from a server
   *
   * @param  integer $id The id of the server to return the rules from
   * @return array   The rules from the server
   */
  public static function getServerRules($id)
  {
    $servers = self::getServers();
    
    if(!isset($servers[$id]))
      throw new Exception('Invalid ID specified.');
    
    $server_query_reader       = new ServerQueryReader();
    $server_query_reader->ip   = $servers[$id]['ip'];
    $server_query_reader->port = $servers[$id]['port'];
    $server_query_reader->type = SERVER_RULES;
    
    return $server_query_reader->executeCached(ONE_MINUTE);
  }
  
  
  /**
   * Returns the list of servers
   *
   * @return array The list of servers
   */
  public static function getServers()
  {
    $servers_reader = new ServersReader();
    
    return $servers_reader->executeCached(ONE_MINUTE);
  }
  
  
  /**
   * Sends an RCON command to one or all servers
   *
   * @param  string  $command The RCON command to send to the server
   * @param  integer $id      The id of the server to send the command to, or 0 for all servers
   * @return string  The output of the RCON command
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
   * @param  string $name The name of the setting to return (banlist.bansperpage, banlist.hideadminname, config.dateformat, config.debug, config.defaultpage, config.enableprotest,
                                                             config.enablesubmit, config.exportpublic, config.language, config.password.minlength, config.summertime, config.theme,
                                                             config.timezone, config.version, dash.intro.text, dash.intro.title, dash.lognopopup, template.logo, template.title)
   * @return mixed  The setting
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
   *
   * @return array The list of settings
   */
  public static function getSettings()
  {
    $settings_reader = new SettingsReader();
    
    return $settings_reader->executeCached(ONE_DAY);
  }
  
  
  /**
   * Updates one or more settings
   *
   * @param array $settings The list of settings to update
   * @noreturn
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
   * @return integer The id of the added submission
   */
  public static function addSubmission($steam, $ip, $name, $reason, $subname, $subemail, $server = 0)
  {
    return SubmissionsWriter::add($steam, $ip, $name, $reason, $subname, $subemail, $server);
  }
  
  
  /**
   * Archives a submission
   *
   * @param integer $id The id of the submission to archive
   * @noreturn
   */
  public static function archiveSubmission($id)
  {
    SubmissionsWriter::archive($id);
  }
  
  
  /**
   * Bans a submission
   *
   * @param  integer $id The id of the submission to ban
   * @return integer The id of the added ban
   */
  public static function banSubmission($id)
  {
    return SubmissionsWriter::ban($id);
  }
  
  
  /**
   * Deletes a submission
   *
   * @param integer $id The id of the submission to delete
   * @noreturn
   */
  public static function deleteSubmission($id)
  {
    SubmissionsWriter::delete($id);
  }
  
  
  /**
   * Returns a submission
   *
   * @param  integer $id The id of the submission to return
   * @return array   The submission
   */
  public static function getSubmission($id)
  {
    $submissions = self::getSubmissions();
    
    if(!isset($submissions['list'][$id]))
      throw new Exception('Invalid ID specified.');
    
    return $submissions['list'][$id];
  }
  
  
  /**
   * Returns a list of submissions
   *
   * @param  integer $limit The amount of submissions to return per page, or 0 for all the submissions
   * @param  integer $page  The page to return
   * @return array   A list of submissions
   */
  public static function getSubmissions($archive = false, $limit = 0, $page = 1, $sort = null, $order = null)
  {
    $submissions_reader          = new SubmissionsReader();
    $submissions_reader->archive = $archive;
    $submissions_reader->limit   = $limit;
    $submissions_reader->page    = $page;
    
    if(!is_null($order))
      $submissions_reader->order = $order;
    if(!is_null($sort))
      $submissions_reader->sort  = $sort;
    
    return $submissions_reader->executeCached(ONE_MINUTE * 5);
  }
  
  
  /**
   * Restores a submission from the archive
   *
   * @param integer $id The id of the submission to restore
   * @noreturn
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
   * @noreturn
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
   * Returns a translation
   *
   * @param  string $name The name of the translation to return
   * @param  string $lang The language of the translation to return
   * @return string The translation
   */
  public static function getTranslation($name, $lang = 'en')
  {
    $translations = self::getTranslations($lang);
    
    if(!isset($translations['phrases'][$name]))
      throw new Exception('Invalid name specified.');
    
    return $translations['phrases'][$name];
  }
  
  
  /**
   * Returns a list of translations
   *
   * @param  string $lang The language of the translations to return
   * @return array  A list of translations
   */
  public static function getTranslations($lang = 'en')
  {
    $translations_reader           = new TranslationsReader();
    $translations_reader->language = $lang;
    
    return $translations_reader->executeCached(ONE_DAY);
  }
  
  
  /**
   * Loads translations into memory
   *
   * @param string $lang The language of the translations to return
   * @noreturn
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