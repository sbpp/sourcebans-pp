<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2019 SourceBans++ Dev Team <https://github.com/sbpp>
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
require_once('../includes/Database.php');


function convertAmxbans($oldDB, $newDB)
{
    set_time_limit(0); //Never time out
    ob_start();
    if (!$oldDB) {
        die("Failed to connect to AMX Bans database");
    }

    echo "Converting ".$oldDB->getPrefix()."_bans... ";
    ob_flush();
    flush();
    $oldDB->query('SELECT `player_ip`, `player_id`, `player_nick`, `ban_created`, `ban_length`, `ban_reason`, `admin_ip` FROM `:prefix_bans`');
    $data = $oldDB->resultset();
    $oldDB->query('SELECT UNIX_TIMESTAMP() AS time FROM :prefix_bans');
    $time = $oldDB->single();

    if (!$newDB) {
        die("Failed to connect to SourceBans database");
    }

    $newDB->query('INSERT INTO `:prefix_bans` (ip, authid, name, created, ends, length, reason, adminIp, aid) VALUES (:ip, :authid, :name, :created, :ends, :length, :reason, :adminIp, :aid)');

    foreach ($data as $value) {
        $newDB->bind(':ip', $value['player_ip']);
        $newDB->bind(':authid', $value['player_id']);
        $newDB->bind(':name', $value['player_nick']);
        $newDB->bind(':created', $value['ban_created']);
        $newDB->bind(':ends', $value['ban_length'] == 0 ? 0 : $value['ban_created']+$value['ban_length']);
        $newDB->bind(':length', $value['ban_length']);
        $newDB->bind(':reason', $value['ban_reason']);
        $newDB->bind(':adminIp', $value['admin_ip']);
        $newDB->bind(':aid', 0);

        $newDB->execute();
    }
    echo "OK<br>";
}
