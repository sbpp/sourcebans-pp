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

echo "- Starting <b>SourceBans</b> database update from RC1d to RC2 -<br>";
$db = ADONewConnection("mysqli://" . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.dateformat', 'm-d-y H:i')");
$db->Execute("INSERT INTO `" . DB_PREFIX . "_settings` (`setting`, `value`) VALUES ('config.timezone', 'Europe/London')");

echo "Done updating. Please delete this file.<br>";