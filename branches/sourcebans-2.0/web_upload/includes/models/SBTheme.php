<?php
/**
 * SourceBans theme model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Themes
 * @version    $Id$
 */
class SBTheme extends Theme
{
  function __construct($theme = null)
  {
    parent::__construct($theme);
    
    if($this->version == 'SB_VERSION')
    {
      $this->version = $this->_registry->sb_version;
    }
  }
}