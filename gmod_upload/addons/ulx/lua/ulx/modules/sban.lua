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
//  This file incorporates work covered by the following copyright(s): 
//
//   ULX Source Bans 0.2.3a
//   Copyright (C) 2015 FunDK and PatPeter
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>  
//
// *************************************************************************

local SBAN_PREFIX			= "sb_"					//Prefix dont change if you dont know what you are doing
local SBAN_WEBSITE			= "your.website.com"	//Source Bans Website

//ServerID in sercer.cfg file
CreateConVar( "sban_serverid", -1 )

if cvars.GetConVarCallbacks( "sban_serverid" ) == nil then //Checks if the Hook exists (FOr debugging only)
	cvars.AddChangeCallback( "sban_serverid", function( convar_name, old, new )
		if isnumber(tonumber(new)) then
			print( "[SBAN_ULX] ServerID: "..GetConVarNumber("sban_serverid") )
		else
			print( "[SBAN_ULX] Please only use numbers" )
		end
	end)
end

SBAN = SBAN or {}
/*
	Functions
*/
//Admin stuff
	//Admin Check
	function SBAN.Admin_IsAdmin( steamid, callback )
		local query = "SELECT count(*) as c FROM "..SBAN_PREFIX.."admins WHERE authid = '"..steamid.."'"
		SBAN_MYSQL.Query( query, function( status, data )
			callback( SBAN_MYSQL.ExistsCheck( data ) )
		end )
	end
	
	//AdminId
	function SBAN.Admin_GetID( steamid, callback )
		local query = "SELECT aid FROM "..SBAN_PREFIX.."admins WHERE authid = '"..steamid.."'"
		
		SBAN_MYSQL.Query( query, function( status, data )
			callback( data[1]["aid"] )
		end )
	end

	//Admin Info
	function SBAN.Admin_GetInfo( steamid, callback )
		local query = "SELECT aid, srv_group FROM "..SBAN_PREFIX.."admins WHERE authid = '"..steamid.."'"
		
		SBAN_MYSQL.Query( query, function( status, data )
			local id = nil
			local group = nil
			
			if status then
				if data[1] != nil then
					id = data[1]["aid"]
					
					if data[1]["srv_group"] != '' then
						group = data[1]["srv_group"]
					end
				end
			end
			
			callback( id, group )
		end )
	end
	
	//Admin Check Server access
	function SBAN.Admin_CheckServerID( aid, callback )
		local query = "SELECT Count(*) as c FROM "
		query = query..SBAN_PREFIX.."admins_servers_groups as adm_grp"
		query = query.." LEFT JOIN "..SBAN_PREFIX.."servers_groups as srv_grp ON adm_grp.srv_group_id = srv_grp.group_id"
		query = query.." WHERE"
		query = query.." adm_grp.admin_id = "..aid.." AND"
		query = query.." adm_grp.server_id = "..GetConVarNumber("sban_serverid").." OR"
		query = query.." adm_grp.admin_id = "..aid.." AND"
		query = query.." srv_grp.server_id = "..GetConVarNumber("sban_serverid")
		
		SBAN_MYSQL.Query( query, function( status, data )
			callback( SBAN_MYSQL.ExistsCheck( data ) )
		end )
	end
	
	//Update Admin
	function SBAN.Admin_Update( ply, group )
		SBAN.Admin_GetInfo( ply:SteamID(), function( aid, group )

			if aid != nil || group != nil then
			
				SBAN.Admin_CheckServerID( aid, function( hasaccess )
					local reset = true
					
					if hasaccess then
						if group != nil then
							reset = false
							if (ULib.ucl.getUserRegisteredID( ply ) == nil || ply:GetUserGroup() != group ) then
								
								ulx.adduserid( ply, ply:SteamID(), group )
							end
						end
					end
					
					-- if reset then SBAN.Admin_Reset( ply ) end //Reset admin
				end )
			else
				-- SBAN.Admin_Reset( ply )	//Reset admin
			end
		end )
	end
	
	//Admin group set
	function SBAN.Admin_Reset( ply )
		if( ULib.ucl.getUserRegisteredID( ply ) != nil ) then
			ulx.removeuserid( ply, ply:SteamID() )
		end
	end



//Player Stuff
	//Check for bans
	function SBAN.Player_CheckBanned( steamid, callback )
		local query = "SELECT bid, reason, length FROM ("
		query = query.."SELECT * FROM sb_bans WHERE authid = '"..steamid.."' AND RemoveType IS NULL"
		query = query..") as bans WHERE length = 0 OR ends >= "..os.time()
		SBAN_MYSQL.Query( query, function( status, data )
			local bid, reason, length = nil, nil, nil
			
			if status then
				if data[1] != nil then
					bid = data[1]["bid"]
					reason = data[1]["reason"]
					length = data[1]["length"]
				end
			end
			
			callback( bid, reason, length )
		end )
	end
	
	//Add Block
	function SBAN.Player_AddBlock( ply, bid )
		local query = "INSERT INTO "..SBAN_PREFIX.."banlog (sid, time, name, bid) "
		query = query.." VALUES ("..GetConVarNumber("sban_serverid")..", "..os.time()..", '"..SBAN_MYSQL.StringFormat( ply:Nick() ).."', "..bid..")"
		
		SBAN_MYSQL.QueryInsert( query, function( status, id ) end )
	end
	
	//DoBan
	function SBAN.Player_DoBan( ip, steamid, name, length, reason, adminid )
		SBAN.Admin_GetID( adminid, function( aid )
			local time = os.time();
			local query = "INSERT INTO "..SBAN_PREFIX.."bans (ip, authid, name, created, ends, length, reason, aid, sid) "
			query = query.." VALUES ('"..ip.."', '"..steamid.."', '"..SBAN_MYSQL.StringFormat( name ).."',"..time..", "..(time + length)..", "..length..", '"..reason.."', "..aid..", "..GetConVarNumber("sban_serverid")..");"
			
			
			SBAN_MYSQL.QueryInsert( query, function( status, id )
				if status then
					print("[SBAN_ULX][Ban] "..name.." "..steamid.." BanID:"..id)
				else
					print("[SBAN_ULX][Ban] Error while banning "..name.." "..steamid)
				end
			end )
		end )
	end
	
	//DoBan
	function SBAN.Player_Ban( ply, length, reason, adminid )
		local ip_port = string.Split( ply:IPAddress(), ":" )
		local ip = ip_port[1]

		SBAN.Player_DoBan(ip , ply:SteamID(), ply:Nick(), length, reason, adminid )
		SBAN.Kick( ply, reason, length )
	end
	
	//Kick
	function SBAN.Kick( ply, reason, length )
		if(reason == nil) then
			ply:Kick("You have been banned for this server, please visit "..SBAN_WEBSITE)
		else
			ply:Kick("You have been banned for this server ("..reason.."), please visit "..SBAN_WEBSITE)
		end
	end



/*
	Hooks
*/
function SBAN.PlayerAuth( ply, steamid )
	//Check if banned
	SBAN.Player_CheckBanned( steamid, function( bid, reason, length )
		if bid != nil then
			//Kicks player and adds a block to SBAN
			SBAN.Kick( ply, reason, length )
			SBAN.Player_AddBlock( ply, bid )
		else
			SBAN.Admin_Update( ply )
		end
	end )
end
hook.Add( "PlayerAuthed", "sban_ulx_auth", SBAN.PlayerAuth)

/*
	Commands
*/
function SBAN.CMD_ReloadAdmins( ply, cmd, args, str )
	for k,v in pairs( player.GetAll() ) do
		SBAN.Admin_Update( v )
	end
end
concommand.Add( "sm_rehash", SBAN.CMD_ReloadAdmins)
concommand.Add( "sban_rehash", SBAN.CMD_ReloadAdmins)