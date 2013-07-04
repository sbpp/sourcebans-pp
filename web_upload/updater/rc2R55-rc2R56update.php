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
 * @version $Id$
 * =============================================================================
 */

define('IN_SB', true);
require_once("../config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC1d to RC2 -<br>";
$db = ADONewConnection("mysqli://".DB_USER.':'.DB_PASS.'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);

$db->Execute("ALTER TABLE `" . DB_PREFIX . "_admins` ADD `lastvisit` DATETIME NULL;");
echo "Done updating. Please delete this file.<br>";
?>
