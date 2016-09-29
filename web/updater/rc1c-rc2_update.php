<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

define('IN_SB', true);
require_once("../config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC1c to RC1d -<br>";
$db = ADONewConnection("mysqli://" . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.dateformat', 'm-d-y H:i')");
$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.timezone', 'Europe/London')");

$alt = $db->Execute("ALTER TABLE " . DB_PREFIX . "_admins MODIFY COLUMN `validate` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;");

$out = $db->GetRow("SELECT `immunity` FROM sb_admins");
if ($out) {
    echo "The table structure is already up-to-date. Please delete this file.";
    die();
}

$res = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_admins` ADD `immunity` INT( 10 ) NOT NULL DEFAULT '0',
ADD `srv_group` VARCHAR( 128 ) NULL ,
ADD `srv_flags` VARCHAR( 64 ) NULL,
ADD `srv_password` VARCHAR( 128 ) NULL;");

if (!$res) {
    echo "There was an error altering the table structure.";
    die();
} else {
    echo "Table structure successfully altered...<br>";
}

$srvadmins = $db->GetAll("SELECT * FROM " . DB_PREFIX . "_srvadmins");
echo "Found: " . count($srvadmins) . " admins...<br><br><br>";
$errors = 0;
foreach ($srvadmins AS $sa) {
    echo "Updating entry for: " . $sa['name'] . "... ";
    $res = $db->Execute("UPDATE " . DB_PREFIX . "_admins SET 
						`immunity` = " . $sa['immunity'] . ",
						`srv_group` = '" . $sa['groups'] . "',
						`srv_flags` = '" . $sa['flags'] . "',
						`srv_password` = '" . $sa['password'] . "'
						WHERE authid = '" . $sa['identity'] . "';");
    echo $res ? "<b>Ok</b><br>" : "<b>Failed</b><br>";
    if (!$res)
        $errors++;
}
if ($errors == 0) {
    echo "<br><br>Deleting old admins table...";
    $res = $db->Execute("DROP TABLE " . DB_PREFIX . "_srvadmins");
    echo $res ? "<b>Ok</b><br>" : "<b>Failed</b><br>";
} else
    echo "<br><br>There were some failed admin imports. Old admins table will <b>not</b> be deleted.<br>";
echo "Done updating admin structure. Please delete this file.<br>";

;