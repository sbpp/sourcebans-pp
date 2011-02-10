<?php
require_once BASE_PATH . 'api.php';

class SB_KickIt extends SBPlugin
{
  public static function OnAddBan($id, $name, $type, $steam, $ip, $length, $reason)
  {
    foreach(SB_API::getServers() as $id => $server)
    {
      try
      {
        preg_match_all(STATUS_PARSE, SB_API::sendRCON('status', $id), $players);
        
        foreach($players[1] AS $userid)
        {
          if(($type == STEAM_BAN_TYPE && $steam == $players[3]) ||
             ($type == IP_BAN_TYPE    && $ip    == strtok($players[8][$i], ':')))
          {
            $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], 'plugins/kickit.php'));
            SB_API::sendRCON('kickid ' . $userid . ' You have been banned by this server, check http://' . $_SERVER['HTTP_HOST'] . $requri . ' for more info.', $id);
            // Player found & kicked!
            return;
          }
        }
        // Player not found.
      }
      catch(Exception $e)
      {
        // Write error line into box
        //UpdateProgress($id, $e->getMessage());
      }
    }
  }
}

new SB_KickIt('Kick-It', 'Peace-Maker', 'Kicks a player from the server when banned from the webpanel.', SB_VERSION, 'http://www.sourcebans.net');
?>