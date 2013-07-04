<?php
/**
 * =============================================================================
 * Update the database structure from RC1d -> RC2
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: rc1d-rc2_update.php 130 2008-08-25 15:09:44Z smithxxl $
 * =============================================================================
 */

define('IN_SB', true);
require_once("../config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC1d to RC2 -<br>";
$db = ADONewConnection("mysqli://".DB_USER.':'.DB_PASS.'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);

$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.dateformat', 'm-d-y H:i')");
$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.timezone', 'Europe/London')");
$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.theme', 'default')");


echo "Done updating admin structure. Please delete this file.<br>";
?>
