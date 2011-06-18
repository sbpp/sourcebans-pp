<?php
/**
 * SourceBans language model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Languages
 * @version    $Id$
 */
class SBLanguage extends Language
{
  function __construct($language = null)
  {
    parent::__construct($language);
    
    // TODO: OnGetLanguage
    //list(, $this->_data) = $this->_registry->plugins->OnGetLanguage($this->_data, $language);
  }
}