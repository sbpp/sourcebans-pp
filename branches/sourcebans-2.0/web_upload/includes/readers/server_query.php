<?php
require_once READER;
require_once UTILS_DIR . 'servers/server_query.php';

define('SERVER_INFO',    0);
define('SERVER_PLAYERS', 1);
define('SERVER_RULES',   2);

class ServerQueryReader extends SBReader
{
  public $ip;
  public $port;
  public $type = SERVER_INFO;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $server_query = new CServerQuery($this->ip, $this->port);
    
    // Fetch server info, players or rules
    switch($this->type)
    {
      case SERVER_INFO:
        return $server_query->GetInfo();
      case SERVER_PLAYERS:
        return $server_query->GetPlayers();
      case SERVER_RULES:
        return $server_query->GetRules();
      default:
        throw new Exception('Invalid server query type specified.');
    }
  }
}
?>