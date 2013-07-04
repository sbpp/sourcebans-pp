<?php
/**
 * =============================================================================
 * Update the database structure from RC1c -> RC2
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: rc1c-rc2_update.php 130 2008-08-25 15:09:44Z smithxxl $
 * =============================================================================
 */

define('IN_SB', true);
require_once("../config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC1c to RC1d -<br>";
$db = ADONewConnection("mysqli://".DB_USER.':'.DB_PASS.'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);

$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.dateformat', 'm-d-y H:i')");
$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.timezone', 'Europe/London')");

$alt = $db->Execute("ALTER TABLE ".DB_PREFIX."_admins MODIFY COLUMN `validate` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;");

$out = $db->GetRow("SELECT `immunity` FROM sb_admins");
if($out)
{
	echo "The table structure is already up-to-date. Please delete this file.";
	die();
}

$res = $db->Execute("ALTER TABLE `".DB_PREFIX."_admins` ADD `immunity` INT( 10 ) NOT NULL DEFAULT '0',
ADD `srv_group` VARCHAR( 128 ) NULL ,
ADD `srv_flags` VARCHAR( 64 ) NULL,
ADD `srv_password` VARCHAR( 128 ) NULL;");

if(!$res)
{
	echo "There was an error altering the table structure."; die();
}
else 
{
	echo "Table structure successfully altered...<br>";
}

$srvadmins = $db->GetAll("SELECT * FROM ".DB_PREFIX."_srvadmins");
echo "Found: ". count($srvadmins) . " admins...<br><br><br>";
$errors = 0;
foreach($srvadmins AS $sa)
{
	echo "Updating entry for: " . $sa['name'] . "... ";
	$res = $db->Execute("UPDATE ".DB_PREFIX."_admins SET 
						`immunity` = " . $sa['immunity'] . ",
						`srv_group` = '" . $sa['groups'] . "',
						`srv_flags` = '" . $sa['flags'] . "',
						`srv_password` = '" . $sa['password'] . "'
						WHERE authid = '" . $sa['identity'] . "';");
	echo $res ? "<b>Ok</b><br>" : "<b>Failed</b><br>";
	if(!$res)
		$errors++;	
}
if($errors == 0)
{
	echo "<br><br>Deleting old admins table...";
	$res = $db->Execute("DROP TABLE ".DB_PREFIX."_srvadmins");
	echo $res ? "<b>Ok</b><br>" : "<b>Failed</b><br>";
}
else 
	echo "<br><br>There were some failed admin imports. Old admins table will <b>not</b> be deleted.<br>";
echo "Done updating admin structure. Please delete this file.<br>";

?>
