<?php 
/**
 * =============================================================================
 * Main loader file
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: index.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

include_once 'init.php';
include_once(INCLUDES_PATH . "/user-functions.php");
include_once(INCLUDES_PATH . "/system-functions.php");
include_once('config.php');
include_once(INCLUDES_PATH . "/sb-callback.php");
$xajax->processRequests();
session_start();
include_once(INCLUDES_PATH . "/page-builder.php");










//Yarr!

?>