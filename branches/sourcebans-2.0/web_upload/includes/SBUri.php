<?php
/**
 * SourceBans URI
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Uri
 * @version    $Id$
 */
class SBUri extends Uri
{
  public function __toString()
  {
    list($uri) =
      $this->_registry->plugins->OnBuildUri($this->_controller, $this->_action, $this->_data);
    
    // If no plugin returned data, build URI as default
    if(empty($uri))
      return parent::__toString();
    
    return $uri;
  }
  
  
  public static function parse($uri = null)
  {
    $registry                               = Registry::getInstance();
    list(list($controller, $action, $data)) = $registry->plugins->OnParseUri($uri);
    
    // If no plugin returned data, parse URI as default
    if(empty($controller))
      return parent::parse($uri);
    
    return new self($controller, $action, $data);
  }
}