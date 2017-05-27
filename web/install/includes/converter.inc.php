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
        /*
    	echo "Converting ".$fromprefix."_banhistory... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT player_ip, player_id, player_nick, ban_created, ban_length, ban_reason, admin_ip, admin_id, admin_nick
    				,server_ip, server_name, unban_created FROM ".$fromprefix."_banhistory");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_banhistory(Type,ip,authid,name,created,ends,length,reason,adminIp,Adminid,RemovedOn,RemovedBy) VALUES ('U',?,?,?,?,?,?,?,?,?,?,?)");
    	while (!$res->EOF)
    	{
        	$vals = array($res->fields[0],$res->fields[1],$res->fields[2],$res->fields[3],($res->fields[4] == 0 ? 0 : $res->fields[3]+$res->fields[4])
    			,$res->fields[4],$res->fields[5],$res->fields[6],$res->fields[7],$res->fields[8],$res->fields[7]);

    		foreach ($vals as $ind=>$cur)
    		{
        		if (is_null($cur))
        		{
            		$vals[$ind] = '';
        		}
    		}
    		$newdb->Execute($ins,$vals);
    		$res->MoveNext();
    	}
        echo "OK<br>";

   	echo "Converting ".$fromprefix."_levels... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT level, bans_add, bans_edit, bans_delete, bans_unban, bans_import, bans_export, amxadmins_view, amxadmins_edit
    	            , webadmins_view, webadmins_edit, permissions_edit, servers_edit FROM ".$fromprefix."_levels");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_groups(type,name,flags) VALUES (1,?,?)");
    	$levelconvert = array();
    	while (!$res->EOF)
    	{
        	$acc = 0;
        	if ($res->fields[1] == 'yes' || $res->fields[2] == 'yes' || $res->fields[3] == 'yes' || $res->fields[4] == 'yes')
        	{
            	$acc |= ADMIN_WEB_BANS;
        	}
        	// amxadmins_view is ignored
        	if ($res->fields[6] == 'yes')
        	{
            	$acc |= ADMIN_SERVER_ADMINS;
        	}
        	// webadmins_view is ignored
        	if ($res->fields[8] == 'yes')
        	{
            	$acc |= ADMIN_WEB_AGROUPS;
        	}
        	if ($res->fields[9] == 'yes')
        	{
            	$acc |= ADMIN_WEB_AGROUPS | ADMIN_SERVER_AGROUPS;
        	}
        	if ($res->fields[10] == 'yes')
        	{
            	$acc |= ADMIN_SERVER_ADD | ADMIN_SERVER_REMOVE | ADMIN_SERVER_GROUPS;
        	}
        	if ($res->fields[0] == '1')
        	{
            	$acc |= ADMIN_OWNER;
        	}
          	$newdb->Execute($ins,array("AMXBANS_".$res->fields[0],$acc));
            $levelconvert[$res->fields[0]] = $newdb->Insert_ID();
        	$res->MoveNext();
    	}
    	echo "OK<br>";

    	echo "Converting ".$fromprefix."_admins... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT username, level FROM ".$fromprefix."_webadmins");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_admins(user,name,gid) VALUES (?,?,?)");
    	while (!$res->EOF)
    	{
        	$newdb->Execute($ins,array($res->fields[0],$res->fields[0],$levelconvert[$res->fields[1]]));
        	$res->MoveNext();
    	}
    	echo "OK<br>"; */
    }
