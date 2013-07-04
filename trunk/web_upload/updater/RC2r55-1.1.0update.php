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
require_once("config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC2 to RC3 -<br>";
$db = ADONewConnection("mysqli://".DB_USER.':'.DB_PASS.'@'.DB_HOST.':'.DB_PORT.'/'.DB_NAME);

echo "- Altering table -<br>";
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemovedBy` int(8) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemoveType` VARCHAR(3) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemovedOn` int(10) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` DROP INDEX `authid`");

if( $result == false )
{
	echo "Error altering table";
	die();
}

echo "- Converting old bans -<br>";
$res = $db->Execute("SELECT * FROM `" . DB_PREFIX . "_banhistory`;");

while (!$res->EOF)
{
	$db->Execute("INSERT INTO `" . DB_PREFIX . "_bans` ( `bid` , `ip` , `authid` , `name` , `created` , `ends` , `length` , `reason` , `aid` , `adminIp` , `sid` , `country`, `RemovedBy`, `RemoveType` )
				VALUES ( NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )",
				array( $res->fields['IP'], $res->fields['AuthId'], $res->fields['Name'], $res->fields['Created'], $res->fields['Ends'], $res->fields['Length'], $res->fields['Reason'], $res->fields['AdminId'], $res->fields['AdminIp'], $res->fields['SId'], $res->fields['country'], 0, "U" ) );
	
	$newID = (int)$GLOBALS['db']->Insert_ID();
	echo "> Updating ban for: <b>". $res->fields['Name'] . "</b><br />";
	
	
	$res2 = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_demos` SET 
								`demid` = ?,
								WHERE `demtype` = 'B' AND `demid` = ?;",
								array( $newID, $res->fields['HistId'] ));
	if( !empty($res2) )
		echo "	>> Updating demo: <b>". $res->fields['HistId'] . "</b> > " . $newID ."<br />";
	$res->MoveNext();
}



echo "Done updating. Please delete this file.<br>";
?>
