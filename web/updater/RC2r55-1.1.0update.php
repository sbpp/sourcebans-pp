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
require_once("config.php");
define('ROOT', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT . '/includes');
include_once(INCLUDES_PATH . "/adodb/adodb.inc.php");

echo "- Starting <b>SourceBans</b> database update from RC2 to RC3 -<br>";
$db = ADONewConnection("mysqli://" . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

echo "- Altering table -<br>";
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemovedBy` int(8) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemoveType` VARCHAR(3) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` ADD `RemovedOn` int(10) NULL;");
$result = $db->Execute("ALTER TABLE `" . DB_PREFIX . "_bans` DROP INDEX `authid`");

if ($result == false) {
    echo "Error altering table";
    die();
}

echo "- Converting old bans -<br>";
$res = $db->Execute("SELECT * FROM `" . DB_PREFIX . "_banhistory`;");

while (!$res->EOF) {
    $db->Execute("INSERT INTO `" . DB_PREFIX . "_bans` ( `bid` , `ip` , `authid` , `name` , `created` , `ends` , `length` , `reason` , `aid` , `adminIp` , `sid` , `country`, `RemovedBy`, `RemoveType` )
            VALUES ( NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )", array(
        $res->fields['IP'],
        $res->fields['AuthId'],
        $res->fields['Name'],
        $res->fields['Created'],
        $res->fields['Ends'],
        $res->fields['Length'],
        $res->fields['Reason'],
        $res->fields['AdminId'],
        $res->fields['AdminIp'],
        $res->fields['SId'],
        $res->fields['country'],
        0,
        "U"
    ));
    
    $newID = (int) $GLOBALS['db']->Insert_ID();
    echo "> Updating ban for: <b>" . $res->fields['Name'] . "</b><br />";
    
    
    $res2 = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_demos` SET 
            `demid` = ?,
            WHERE `demtype` = 'B' AND `demid` = ?;", array(
        $newID,
        $res->fields['HistId']
    ));
    if (!empty($res2))
        echo "	>> Updating demo: <b>" . $res->fields['HistId'] . "</b> > " . $newID . "<br />";
    $res->MoveNext();
}
echo "Done updating. Please delete this file.<br>";