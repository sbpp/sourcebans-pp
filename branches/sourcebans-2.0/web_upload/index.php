<?php
/**
 * Single point of entry
 *
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
require_once 'bootstrap.php';


try
{
  // Router
  $registry->router = new Router();
  $registry->router->route(SBUri::parse());
}
catch(Exception $e)
{
  // Error
  $registry->template->error = $e->getMessage();
  $registry->template->display('error');
}