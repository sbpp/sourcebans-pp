<?php
/**
 * Gravatar
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Plugin
 * @version    $Id$
 */
class GravatarPlugin extends SBPlugin
{
  protected $name        = 'Gravatar';
  protected $author      = 'Tsunami';
  protected $description = 'Adds Gravatar support.';
  protected $version     = '1.0';
  protected $url         = 'http://www.sourcebans.net';
  
  
  public function OnDisplayTemplate(&$template, $file)
  {
    $uri  = $this->_registry->uri;
    $user = $this->_registry->user;
    
    if($user->is_logged_in())
    {
      $template->addScript($uri->base . '/scripts/gravatar.php?id=' . md5(strtolower($user->email)));
    }
  }
}