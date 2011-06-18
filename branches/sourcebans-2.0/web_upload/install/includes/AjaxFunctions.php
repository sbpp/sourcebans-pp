<?php
/**
 * AJAX Callbacks
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage AJAX
 * @version    $Id$
 */
class AjaxCallbacks
{
  public static function ImportAmxBans($host, $port, $user, $pass, $name, $prefix)
  {
    try
    {
      SBInstall::importAmxBans($host, $port, $user, $pass, $name, $prefix);
    }
    catch(Exception $e)
    {
      return array(
        'error' => $e->getMessage()
      );
    }
  }
  
  public static function Install($host, $port, $user, $pass, $name, $prefix)
  {
    try
    {
      SBInstall::install($host, $port, $user, $pass, $name, $prefix);
    }
    catch(Exception $e)
    {
      return array(
        'error' => $e->getMessage()
      );
    }
  }
  
  public static function SetOwner($username, $password, $confirm_password, $email, $auth, $identity)
  {
    try
    {
      SBInstall::setOwner($username, $password, $confirm_password, $email, $auth, $identity);
    }
    catch(Exception $e)
    {
      return array(
        'error' => $e->getMessage()
      );
    }
  }
}