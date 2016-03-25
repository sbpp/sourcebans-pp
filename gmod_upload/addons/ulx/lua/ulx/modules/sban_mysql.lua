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

require ("mysqloo")

//Config
local SBANDATABASE_HOSTNAME = "DB Hostname" 	//Database IP/Host
local SBANDATABASE_HOSTPORT = 3306				//Database Port
local SBANDATABASE_DATABASE = "DB Database"		//Database Database/Schema
local SBANDATABASE_USERNAME = "DB Username"		//Database Username
local SBANDATABASE_PASSWORD = "DB Password"		//Database Password

SBAN_MYSQL = SBAN_MYSQL or {}
/*
	Mysql Connection
*/	
	//Connect
	function SBAN_MYSQL.Connect( first )
		if first then
			print("[SBAN_ULX][MYSQL] Connecting Database")
			sban_db:connect()
			sban_db:wait()	//Forces the server to wait for the DB connection
		end

		local dbstatus = sban_db:status()
		if dbstatus != mysqloo.DATABASE_CONNECTED && dbstatus != mysqloo.DATABASE_CONNECTING then
			print("[SBAN_ULX][MYSQL] Connection was lost, Trying to reconnect")
			sban_db:connect()
		else
			if first then
				//Creates WatchDog for the database
				if(timer.Exists("sbanulx_database_check")) then timer.Destroy("sbanulx_database_check")end
				timer.Create("sbanulx_database_check", 1,0, function() SBAN_MYSQL.Connect(false) end)
			end
		end
	end
	
	//Checks if the connection is already made
	if(sban_db == nil) then
		sban_db = mysqloo.connect(SBANDATABASE_HOSTNAME, SBANDATABASE_USERNAME, SBANDATABASE_PASSWORD, SBANDATABASE_DATABASE, SBANDATABASE_HOSTPORT)
		SBAN_MYSQL.Connect(true)
	end

	//Connection Made
	function sban_db:onConnected()
		print("[SBAN_ULX][MYSQL] Connected")
	end

	//Connection Error
	function sban_db:onConnectionFailed( err )
		print("[SBAN_ULX][MYSQL] Connection to database failed")
		print("[SBAN_ULX][MYSQL] Error:"..err)
	end

	
	
/*
	Functions
*/
	//StringFormat
	function SBAN_MYSQL.StringFormat( str )
		return sban_db:escape( str )
	end
	
	//ExistsCheck
	function SBAN_MYSQL.ExistsCheck( result )
		if result[1] != nil then
			if tonumber(result[1]["c"]) >= 1 then
				return true
			else
				return false
			end
		end
	end
	
	//Check Nil
	function SBAN_MYSQL.NilCheck( var )
		
		if var == nil then
			return "null"
		else
			return var
		end
	end

	
	
/*
	Query Methods
*/
	/*
		Database.Query( string, callback )

		Return:
			status 	= If the query went well it's true
			data 	= Returns if the query went well
	*/
	function SBAN_MYSQL.Query( sqlquery, callback )
		local q = sban_db:query( sqlquery )
		local status, result

		function q:onSuccess( data )
			status = true
			result = data
			
			if callback != false && callback != nil then callback( status, result ) end
		end
		
		function q:onError( err, sql )
			print("[SBAN_ULX][MYSQL][Query][error] "..err)
			print("[SBAN_ULX][MYSQL][Query][error] "..sql)
			
			status = false
			result = nil
			
			if callback != false && callback != nil then callback( status, result ) end
		end
		
		q:start()
		
		if callback == nil || !callback then
			q:wait()
			return status, result
		end
	end	

	/*
		Database.QueryInsert( string, callback )

		Return:
			status 	= If the query went well it's true
			id 		= The insert id from the insert query
	*/
	function SBAN_MYSQL.QueryInsert( sqlquery, callback )
		local q = sban_db:query(sqlquery)
		local status, result

		function q:onSuccess( data )
			local id = q:lastInsert()
			
			
			status = true
			result = id
			
			if callback != false && callback != nil then callback( status, result ) end
		end
		
		function q:onError( err, sql )
			print("[SBAN_ULX][MYSQL][QueryInsert][error] "..err)
			print("[SBAN_ULX][MYSQL][QueryInsert][error] "..sql)
			
			status = false
			result = nil
			
			if callback != false && callback != nil then callback( status, result ) end
		end
		
		q:start()

		if callback == nil || !callback then
			q:wait()
			return status, result
		end
	end

	/*
		Database.QueryUpdate( string, callback )

		Return:
			status = true and the query went well
	*/
	function SBAN_MYSQL.QueryUpdate( sqlquery, callback )
		local q = sban_db:query(sqlquery)
		local status

		function q:onSuccess( data )
			local id = q:lastInsert()
			
			status = true
			
			if callback != false && callback != nil then callback( status ) end
		end
		
		function q:onError( err, sql )
			print("[SBAN_ULX][MYSQL][QueryUpdate][error] "..err)
			print("[SBAN_ULX][MYSQL][QueryUpdate][error] "..sql)
			
			status = false
			
			if callback != false && callback != nil then callback( status ) end
		end
		
		q:start()

		if callback == nil || !callback then
			q:wait()
			return status
		end
	end