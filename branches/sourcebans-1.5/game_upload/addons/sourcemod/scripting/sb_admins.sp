/**
 * SourceBans Admins Plugin
 *
 * @author GameConnect
 * @version 1.5.0
 * @copyright SourceBans (C)2007-2013 GameConnect.net.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id$
 */

#pragma semicolon 1

#include <sourcemod>
#include <sourcebans>

//#define _DEBUG

public Plugin:myinfo =
{
	name        = "SourceBans: Admins",
	author      = "GameConnect",
	description = "Advanced admin management for the Source engine",
	version     = SB_VERSION,
	url         = "http://www.sourcebans.net"
};


/**
 * Globals
 */
new g_iAdminId[MAXPLAYERS + 1];
new g_iPlayerSeq[MAXPLAYERS + 1];				// Player-specific sequence numbers
new g_iRebuildCachePart[3] = {0};				// Cache part sequence numbers
new g_iSequence            = 0;
new g_iServerId;
new bool:g_bPlayerAuth[MAXPLAYERS + 1];	// Whether a player has been "pre-authed"
new bool:g_bRequireSiteLogin;
new String:g_sServerIp[16];


/**
 * Plugin Forwards
 */
public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
{
	CreateNative("SB_GetAdminId",     Native_GetAdminId);
	CreateNative("SB_AddAdmin",       Native_AddAdmin);
	CreateNative("SB_DeleteAdmin",    Native_DeleteAdmin);
	CreateNative("SB_AddGroup",       Native_AddGroup);
	CreateNative("SB_DeleteGroup",    Native_DeleteGroup);
	CreateNative("SB_SetAdminGroups", Native_SetAdminGroups);
	RegPluginLibrary("sb_admins");
	
	return APLRes_Success;
}

public OnPluginStart()
{
	RegAdminCmd("sb_addadmin",       Command_AddAdmin,       ADMFLAG_ROOT, "Adds an admin to SourceBans");
	RegAdminCmd("sb_deladmin",       Command_DelAdmin,       ADMFLAG_ROOT, "Removes an admin from SourceBans");
	RegAdminCmd("sb_addgroup",       Command_AddGroup,       ADMFLAG_ROOT, "Adds a group to SourceBans");
	RegAdminCmd("sb_delgroup",       Command_DelGroup,       ADMFLAG_ROOT, "Removes a group from SourceBans");
	RegAdminCmd("sb_setadmingroups", Command_SetAdminGroups, ADMFLAG_ROOT, "Sets an admin's groups in SourceBans");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("sqladmins.phrases");
	
	// Account for late loading
	if(LibraryExists("sourcebans"))
		SB_Init();
}

public OnRebuildAdminCache(AdminCachePart:part)
{
	// Mark this part of the cache as being rebuilt.  This is used by the 
	// callback system to determine whether the results should still be 
	// used.
	new iSequence               = ++g_iSequence;
	g_iRebuildCachePart[_:part] = iSequence;
	
	// If we don't have a database connection, we can't do any lookups just yet.
	if(!SB_Connect())
		return;
	
	if(part      == AdminCache_Admins)
		SB_FetchAdmins();
	else if(part == AdminCache_Groups)
		SB_FetchGroups(iSequence);
	else if(part == AdminCache_Overrides)
		SB_FetchOverrides(iSequence);
}

/*
public Action:OnLogAction(Handle:source, Identity:ident, client, target, const String:message[])
{
	decl String:sAdminIp[16] = "", String:sAuth[20] = "", String:sEscapedMessage[256], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sIp[16] = "", String:sName[MAX_NAME_LENGTH + 1] = "", String:sQuery[1024];
	new iAdminId = SB_GetAdminId(client);
	if(target > 0 && IsClientInGame(target))
	{
		GetClientAuthString(target, sAuth, sizeof(sAuth));
		GetClientIP(target,         sIp,   sizeof(sIp));
		GetClientName(target,       sName, sizeof(sName));
	}
	if(client > 0 && IsClientInGame(client))
		GetClientIP(client, sAdminIp, sizeof(sAdminIp));
	else
		sAdminIp = g_sServerIp;
	
	SB_Escape(message, sEscapedMessage, sizeof(sEscapedMessage));
	SB_Escape(sName,   sEscapedName,    sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "INSERT INTO {{actions}} (name, steam, ip, message, server_id, admin_id, admin_ip, time) \
	                                VALUES      (NULLIF('%s', ''), NULLIF('%s', ''), NULLIF('%s', ''), '%s', %i, NULLIF(%i, 0), '%s', UNIX_TIMESTAMP())",
	                                sEscapedName, sAuth, sIp, sEscapedMessage, g_iServerId, iAdminId, sAdminIp);
	SB_Execute(sQuery);
	return Plugin_Handled;
}
*/

public OnConfigsExecuted()
{
	if(DisablePlugin("admin-sql-prefetch") | DisablePlugin("admin-sql-threaded") | DisablePlugin("sql-admin-manager"))
	{
		// Reload admins
		DumpAdminCache(AdminCache_Groups, true);
		DumpAdminCache(AdminCache_Overrides, true);
	}
}


/**
 * Client Forwards
 */
public bool:OnClientConnect(client, String:rejectmsg[], maxlen)
{
	g_iAdminId[client]    = 0;
	g_iPlayerSeq[client]  = 0;
	g_bPlayerAuth[client] = false;
	return true;
}

public OnClientDisconnect(client)
{
	g_iAdminId[client]    = 0;
	g_iPlayerSeq[client]  = 0;
	g_bPlayerAuth[client] = false;
}

public Action:OnClientPreAdminCheck(client)
{
	g_bPlayerAuth[client] = true;
	
	// Play nice with other plugins.  If there's no database, don't delay the 
	// connection process.  Unfortunately, we can't attempt anything else and 
	// we just have to hope either the database is waiting or someone will type 
	// sm_reloadadmins.
	if(!SB_Connect())
		return Plugin_Continue;
	
	// Similarly, if the cache is in the process of being rebuilt, don't delay 
	// the client's normal connection flow.  The database will soon auth the client 
	// normally.
	if(g_iRebuildCachePart[_:AdminCache_Admins])
		return Plugin_Continue;
	
	// If someone has already assigned an admin ID (bad bad bad), don't 
	// bother waiting.
	if(GetUserAdmin(client) != INVALID_ADMIN_ID)
		return Plugin_Continue;
	
	SB_FetchAdmin(client);
	return Plugin_Handled;
}


/**
 * SourceBans Forwards
 */
public SB_OnConnect(Handle:database)
{
	g_iServerId = SB_GetConfigValue("ServerID");
	
	// See if we need to get any of the cache stuff now.
	new iSequence;
	if((iSequence = g_iRebuildCachePart[_:AdminCache_Admins]))
		SB_FetchAdmins();
	if((iSequence = g_iRebuildCachePart[_:AdminCache_Groups]))
		SB_FetchGroups(iSequence);
	if((iSequence = g_iRebuildCachePart[_:AdminCache_Overrides]))
		SB_FetchOverrides(iSequence);
}

public SB_OnReload()
{
	g_bRequireSiteLogin = bool:SB_GetConfigValue("RequireSiteLogin");
	
	SB_GetConfigString("ServerIP", g_sServerIp, sizeof(g_sServerIp));
}


/**
 * Commands
 */
public Action:Command_AddAdmin(client, args)
{
	if(args < 4)
	{
		ReplyToCommand(client, "%sUsage: sb_addadmin <name> <authtype> <identity> [password] [group1] ... [group N]", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!SB_Connect())
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Could not connect to database");
		return Plugin_Handled;
	}
	
	decl iLen, String:sArg[256], String:sIdentity[65], String:sName[MAX_NAME_LENGTH + 1], String:sPassword[65], String:sType[16];
	GetCmdArgString(sArg, sizeof(sArg));
	iLen  = BreakString(sArg,       sName,     sizeof(sName));
	iLen += BreakString(sArg[iLen], sType,     sizeof(sType));
	iLen += BreakString(sArg[iLen], sIdentity, sizeof(sIdentity));
	
	if(sArg[iLen])
		iLen        += BreakString(sArg[iLen], sPassword, sizeof(sPassword));
	else
		sPassword[0] = '\0';
	
	SB_AddAdmin(client, sName, sType, sIdentity, sPassword, sArg[iLen]);
	return Plugin_Handled;
}

public Action:Command_DelAdmin(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sb_deladmin <authtype> <identity>", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!SB_Connect())
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Could not connect to database");
		return Plugin_Handled;
	}
	
	decl String:sIdentity[65], String:sType[16];
	GetCmdArg(1, sType,     sizeof(sType));
	GetCmdArg(2, sIdentity, sizeof(sIdentity));
	
	SB_DeleteAdmin(client, sType, sIdentity);
	return Plugin_Handled;
}

public Action:Command_AddGroup(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sb_addgroup <name> <flags> [immunity]", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!SB_Connect())
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Could not connect to database");
		return Plugin_Handled;
	}
	
	decl String:sFlags[33], String:sName[MAX_NAME_LENGTH + 1];
	GetCmdArg(1, sName,  sizeof(sName));
	GetCmdArg(2, sFlags, sizeof(sFlags));
	
	new iImmunity;
	if(args >= 3)
	{
		decl String:sArg[32];
		GetCmdArg(3, sArg, sizeof(sArg));
		if(!StringToIntEx(sArg, iImmunity))
		{
			ReplyToCommand(client, "%s%t", SB_PREFIX, "Invalid immunity");
			return Plugin_Handled;
		}
	}
	
	SB_AddGroup(client, sName, sFlags, iImmunity);
	return Plugin_Handled;
}

public Action:Command_DelGroup(client, args)
{
	if(args < 1)
	{
		ReplyToCommand(client, "%sUsage: sb_delgroup <name>", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!SB_Connect())
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Could not connect to database");
		return Plugin_Handled;
	}
	
	decl String:sName[MAX_NAME_LENGTH + 1];
	new iStart = 0;
	GetCmdArgString(sName, sizeof(sName));
	
	// Strip quotes in case the user tries to use them
	if(sName[strlen(sName) - 1] == '"')
	{
		sName[strlen(sName) - 1] = '\0';
		iStart                   = 1;
	}
	
	SB_DeleteGroup(client, sName[iStart]);
	return Plugin_Handled;
}

public Action:Command_SetAdminGroups(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sb_setadmingroups <authtype> <identity> [group1] ... [group N]", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!SB_Connect())
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Could not connect to database");
		return Plugin_Handled;
	}
	
	decl iLen, String:sArg[256], String:sIdentity[65], String:sType[16];
	GetCmdArgString(sArg, sizeof(sArg));
	iLen  = BreakString(sArg,       sType,     sizeof(sType));
	iLen += BreakString(sArg[iLen], sIdentity, sizeof(sIdentity));
	
	if(!StrEqual(sType, AUTHMETHOD_STEAM) && !StrEqual(sType, AUTHMETHOD_IP) && !StrEqual(sType, AUTHMETHOD_NAME))
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Invalid authtype");
		return Plugin_Handled;
	}
	
	SB_SetAdminGroups(client, sType, sIdentity, sArg[iLen]);
	return Plugin_Handled;
}


/**
 * Query Callbacks
 */
public Query_AddAdmin(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	decl String:sGroups[512], String:sIdentity[65], String:sName[MAX_NAME_LENGTH + 1], String:sPassword[65], String:sType[16];
	new iAdmin = ReadPackCell(pack);
	ReadPackString(pack, sName,     sizeof(sName));
	ReadPackString(pack, sType,     sizeof(sType));
	ReadPackString(pack, sIdentity, sizeof(sIdentity));
	ReadPackString(pack, sPassword, sizeof(sPassword));
	ReadPackString(pack, sGroups,   sizeof(sGroups));
	CloseHandle(pack);
	
	if(error[0])
	{
		LogError("Failed to retrieve the admin from the database: %s", error);
		
		ReplyToCommand(iAdmin, "%sFailed to retrieve the admin.", SB_PREFIX);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin already exists");
		return;
	}
	
	decl String:sEscapedIdentity[129], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedPassword[129], String:sQuery[1024];
	SB_Escape(sIdentity, sEscapedIdentity, sizeof(sEscapedIdentity));
	SB_Escape(sName,     sEscapedName,     sizeof(sEscapedName));
	SB_Escape(sPassword, sEscapedPassword, sizeof(sEscapedPassword));
	Format(sQuery, sizeof(sQuery), "INSERT INTO {{admins}} (user, authid, srv_password) \
	                                VALUES      ('%s', '%s', NULLIF('%s', ''))",
	                                sEscapedName, sEscapedIdentity, sEscapedPassword);
	SB_Execute(sQuery);
	
	ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin added");
	SB_SetAdminGroups(iAdmin, sType, sIdentity, sGroups);
}

public Query_DelAdmin(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	new iAdmin = ReadPackCell(pack);
	CloseHandle(pack);
	
	if(error[0])
	{
		LogError("Failed to retrieve the admin from the database: %s", error);
		
		ReplyToCommand(iAdmin, "%sFailed to retrieve the admin.", SB_PREFIX);
		return;
	}
	if(!SQL_FetchRow(hndl))
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin not found");
		return;
	}
	
	decl String:sQuery[1024];
	new iAdminId = SQL_FetchInt(hndl, 0);
	
	// Delete group bindings
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{admins_servers_groups}} \
	                                WHERE       admin_id = %i",
	                                iAdminId);
	SB_Execute(sQuery);
	
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{admins}} \
	                                WHERE       aid = %i",
	                                iAdminId);
	SB_Execute(sQuery);
	
	ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin deleted");
}

public Query_AddGroup(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	decl String:sFlags[33], String:sName[MAX_NAME_LENGTH + 1];
	new iAdmin    = ReadPackCell(pack);
	ReadPackString(pack, sName,  sizeof(sName));
	ReadPackString(pack, sFlags, sizeof(sFlags));
	new iImmunity = ReadPackCell(pack);
	CloseHandle(pack);
	
	if(error[0])
	{
		LogError("Failed to retrieve the group from the database: %s", error);
		
		ReplyToCommand(iAdmin, "%sFailed to retrieve the group.", SB_PREFIX);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Group already exists");
		return;
	}
	
	decl String:sEscapedFlags[65], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sQuery[1024];
	SB_Escape(sFlags, sEscapedFlags, sizeof(sEscapedFlags));
	SB_Escape(sName,  sEscapedName,  sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "INSERT INTO {{srvgroups}} (name, flags, immunity) \
	                                VALUES ('%s', '%s', %i)",
	                                sEscapedName, sEscapedFlags, iImmunity);
	SB_Execute(sQuery);
	
	ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Group added");
}

public Query_DelGroup(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	decl String:sQuery[1024];
	new iAdmin = ReadPackCell(pack);
	CloseHandle(pack);
	
	if(error[0])
	{
		LogError("Failed to retrieve the group from the database: %s", error);
		
		ReplyToCommand(iAdmin, "%sFailed to retrieve the group.", SB_PREFIX);
		return;
	}
	if(!SQL_FetchRow(hndl))
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Group not found");
		return;
	}
	
	new iGroupId = SQL_FetchInt(hndl, 0);
	
	// Delete admin inheritance for this group
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{admins_servers_groups}} \
	                                WHERE       group_id = %i",
	                                iGroupId);
	SB_Execute(sQuery);
	
	// Delete group overrides
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{srvgroups_overrides}} \
	                                WHERE       group_id = %i",
	                                iGroupId);
	SB_Execute(sQuery);
	
	// Delete immunity
	/*
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{srvgroups_immunity}} \
	                                WHERE       group_id = %i \
	                                   OR       other_id = %i",
	                                iGroupId, iGroupId);
	SB_Execute(sQuery);
	*/
	
	// Finally delete the group
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{srvgroups}} \
	                                WHERE       id = %i",
	                                iGroupId);
	SB_Execute(sQuery);
	
	ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Group deleted");
}

public Query_SetAdminGroups(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	new iAdmin = ReadPackCell(pack);
	
	if(error[0])
	{
		LogError("Failed to retrieve the admin from the database: %s", error);
		
		ReplyToCommand(iAdmin, "%sFailed to retrieve the admin.", SB_PREFIX);
		CloseHandle(pack);
		return;
	}
	if(!SQL_FetchRow(hndl))
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin not found");
		CloseHandle(pack);
		return;
	}
	
	decl String:sQuery[1024];
	new iAdminId = SQL_FetchInt(hndl, 0);
	
	// First delete all of the user's existing groups.
	Format(sQuery, sizeof(sQuery), "DELETE FROM {{admins_servers_groups}} \
	                                WHERE       admin_id = %i",
	                                iAdmin);
	SB_Execute(sQuery);
	
	new iCount = ReadPackCell(pack);
	if(!iCount)
	{
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "SQL Admin groups reset");
		CloseHandle(pack);
		return;
	}
	
	decl String:sName[65];
	new iOrder = 0;
	while(iOrder++ < iCount)
	{
		ReadPackString(pack, sName, sizeof(sName));
		Format(sQuery, sizeof(sQuery), "INSERT INTO {{admins_servers_groups}} (admin_id, group_id) \
		                                VALUES      (%i, (SELECT id FROM {{srvgroups}} WHERE name = '%s'))",
		                                iAdminId, sName);
		SB_Execute(sQuery);
	}
	
	CloseHandle(pack);
	
	if(iOrder     == 1)
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "Added group to user");
	else if(iOrder > 1)
		ReplyToCommand(iAdmin, "%s%t", SB_PREFIX, "Added groups to user", iOrder);
}

public Query_SelectAdmin(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Check if this is the latest result request.
	new iClient = ReadPackCell(pack), iSequence = ReadPackCell(pack);
	if(g_iPlayerSeq[iClient] != iSequence)
	{
		// Discard everything, since we're out of sequence.
		CloseHandle(pack);
		return;
	}
	
	// If we need to use the results, make sure they succeeded.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error receiving admin: %s", error);
		LogError("Query dump: %s", sQuery);
		RunAdminCacheChecks(iClient);
		NotifyPostAdminCheck(iClient);
		CloseHandle(pack);
		return;
	}
	
	new iAccounts = SQL_GetRowCount(hndl);
	if(!iAccounts)
	{
		RunAdminCacheChecks(iClient);
		NotifyPostAdminCheck(iClient);
		CloseHandle(pack);
		return;
	}
	
	// Cache admin info -- [0] = db id, [1] = cache id, [2] = groups
	decl iLookup[iAccounts][3];
	new iAdmins = 0;
	
	decl iAdminId, AdminId:iAdmin, String:sIdentity[65],String:sName[MAX_NAME_LENGTH + 1], String:sPassword[65], String:sType[8];
	while(SQL_FetchRow(hndl))
	{
		iAdminId = SQL_FetchInt(hndl, 0);
		SQL_FetchString(hndl, 1, sName,     sizeof(sName));
		SQL_FetchString(hndl, 2, sType,     sizeof(sType));
		SQL_FetchString(hndl, 3, sIdentity, sizeof(sIdentity));
		SQL_FetchString(hndl, 4, sPassword, sizeof(sPassword));
		
		// For dynamic admins we clear anything already in the cache.
		if((iAdmin = FindAdminByIdentity(sType, sIdentity)) != INVALID_ADMIN_ID)
			RemoveAdmin(iAdmin);
		
		iAdmin = CreateAdmin(sName);
		if(!BindAdminIdentity(iAdmin, sType, sIdentity))
		{
			LogError("Could not bind prefetched SQL admin (authtype \"%s\") (identity \"%s\")", sType, sIdentity);
			continue;
		}
		
		iLookup[iAdmins][0] = iAdminId;
		iLookup[iAdmins][1] = _:iAdmin;
		iLookup[iAdmins][2] = SQL_FetchInt(hndl, 5);
		iAdmins++;
		
		#if defined _DEBUG
		PrintToServer("%sFound SQL admin (%i,%s,%s,%s,%s):%i:%i", SB_PREFIX, iAdminId, sType, sIdentity, sPassword, sName, iAdmin, iLookup[iAdmins - 1][2]);
		#endif
		
		// See if this admin wants a password
		if(sPassword[0])
			SetAdminPassword(iAdmin, sPassword);
	}
	
	// Try binding the admin.
	RunAdminCacheChecks(iClient);
	iAdmin      = GetUserAdmin(iClient);
	iAdminId    = 0;
	new iGroups = 0;
	
	for(new i = 0; i < iAdmins; i++)
	{
		if(iLookup[i][1] == _:iAdmin)
		{
			iAdminId = iLookup[i][0];
			iGroups  = iLookup[i][2];
			break;
		}
	}
	
	g_iAdminId[iClient] = iAdminId;
	
	#if defined _DEBUG
	PrintToServer("%sBinding client (%i, %i) resulted in: (%i, %i, %i)", SB_PREFIX, iClient, iSequence, iAdminId, iAdmin, iGroups);
	#endif
	
	// If we can't verify that we assigned a database admin, or the admin has no 
	// groups, don't bother doing anything.
	if(!iAdminId || !iGroups)
	{
		NotifyPostAdminCheck(iClient);
		CloseHandle(pack);
		return;
	}
	
	// The admin has groups -- we need to fetch them!
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name \
	                                FROM      {{srvgroups}}             AS sg \
	                                LEFT JOIN {{admins_servers_groups}} AS ag ON ag.group_id = sg.id \
	                                LEFT JOIN {{servers_groups}}        AS gs ON gs.group_id = ag.srv_group_id \
	                                WHERE     ag.admin_id  = %i \
	                                  AND     (ag.server_id = %i OR gs.server_id = %i) \
	                                GROUP BY  sg.id",
	                                iAdminId, g_iServerId, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iClient);
	WritePackCell(pack,   iSequence);
	WritePackCell(pack,   _:iAdmin);
	WritePackString(pack, sQuery);
	
	SB_Query(Query_SelectAdminGroups, sQuery, pack, DBPrio_High);
}

public Query_SelectAdminGroups(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Make sure it's the same client.
	new iClient = ReadPackCell(pack), iSequence = ReadPackCell(pack);
	if(g_iPlayerSeq[iClient] != iSequence)
	{
		CloseHandle(pack);
		return;
	}
	
	// Someone could have sneakily changed the admin id while we waited.
	new AdminId:iAdmin = AdminId:ReadPackCell(pack);
	if(GetUserAdmin(iClient) != iAdmin)
	{
		NotifyPostAdminCheck(iClient);
		CloseHandle(pack);
		return;
	}
	
	// See if we got results.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error receiving admin: %s", error);
		LogError("Query dump: %s", sQuery);
		NotifyPostAdminCheck(iClient);
		CloseHandle(pack);
		return;
	}
	
	decl GroupId:iGroup, String:sName[33];
	while(SQL_FetchRow(hndl))
	{
		SQL_FetchString(hndl, 0, sName, sizeof(sName));
		
		if((iGroup = FindAdmGroup(sName)) == INVALID_GROUP_ID)
			continue;
		
		#if defined _DEBUG
		PrintToServer("%sBinding admin group (%i, %i, %i, %s, %i)", SB_PREFIX, iClient, iSequence, iAdmin, sName, iGroup);
		#endif
		
		AdminInheritGroup(iAdmin, iGroup);
	}
	
	// We're DONE! Omg.
	NotifyPostAdminCheck(iClient);
	CloseHandle(pack);
}

public Query_SelectGroups(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Check if this is the latest result request.
	new iSequence = ReadPackCell(pack);
	if(g_iRebuildCachePart[_:AdminCache_Groups] != iSequence)
	{
		// Discard everything, since we're out of sequence.
		CloseHandle(pack);
		return;
	}
	
	// If we need to use the results, make sure they succeeded.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error receiving groups: %s", error);
		LogError("Query dump: %s", sQuery);
		CloseHandle(pack);
		return;
	}
	
	// Now start fetching groups.
	decl iImmunity, String:sFlags[33], String:sName[33];
	while(SQL_FetchRow(hndl))
	{
		SQL_FetchString(hndl, 0, sName,  sizeof(sName));
		SQL_FetchString(hndl, 1, sFlags, sizeof(sFlags));
		iImmunity = SQL_FetchInt(hndl, 2);
		
		#if defined _DEBUG
		PrintToServer("%sAdding group (%i, %s, %s)", SB_PREFIX, iImmunity, sFlags, sName);
		#endif
		
		// Find or create the group
		new GroupId:iGroup;
		if((iGroup = FindAdmGroup(sName)) == INVALID_GROUP_ID)
			iGroup = CreateAdmGroup(sName);
		
		// Add flags from the database to the group
		decl AdminFlag:iFlag;
		for(new i = 0, iLen = strlen(sFlags); i < iLen; i++)
		{
			if(FindFlagByChar(sFlags[i], iFlag))
				SetAdmGroupAddFlag(iGroup, iFlag, true);
		}
		
		SetAdmGroupImmunityLevel(iGroup, iImmunity);
	}
	
	// It's time to get the group override list.
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name, go.type, go.name, go.access \
	                                FROM      {{srvgroups_overrides}}   AS go \
	                                LEFT JOIN {{srvgroups}}             AS sg ON go.group_id = sg.id \
	                                LEFT JOIN {{admins_servers_groups}} AS ag ON ag.group_id = sg.id \
	                                LEFT JOIN {{servers_groups}}        AS gs ON gs.group_id = ag.srv_group_id \
	                                WHERE     (ag.server_id = %i OR gs.server_id = %i) \
	                                ORDER BY  sg.id DESC",
	                                g_iServerId, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iSequence);
	WritePackString(pack, sQuery);
	
	SB_Query(Query_SelectGroupOverrides, sQuery, pack, DBPrio_High);
}

public Query_SelectGroupOverrides(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Check if this is the latest result request.
	new iSequence = ReadPackCell(pack);
	if(g_iRebuildCachePart[_:AdminCache_Groups] != iSequence)
	{
		// Discard everything, since we're out of sequence.
		CloseHandle(pack);
		return;
	}
	
	// If we need to use the results, make sure they succeeded.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));		
		LogError("SQL error receiving group overrides: %s", error);
		LogError("Query dump: %s", sQuery);
		CloseHandle(pack);	
		return;
	}
	
	// Fetch the overrides.
	decl GroupId:iGroup, OverrideRule:iRule, OverrideType:iType, String:sAccess[16], String:sCommand[64], String:sName[80], String:sType[16];
	while(SQL_FetchRow(hndl))
	{
		SQL_FetchString(hndl, 0, sName,    sizeof(sName));
		SQL_FetchString(hndl, 1, sType,    sizeof(sType));
		SQL_FetchString(hndl, 2, sCommand, sizeof(sCommand));
		SQL_FetchString(hndl, 3, sAccess,  sizeof(sAccess));
		
		// Find the group. This is actually faster than doing the ID lookup.
		if((iGroup = FindAdmGroup(sName)) == INVALID_GROUP_ID)
		{
			// Oh well, just ignore it.
			continue;
		}
		
		iRule = StrEqual(sAccess, "allow") ? Command_Allow         : Command_Deny;
		iType = StrEqual(sType,   "group") ? Override_CommandGroup : Override_Command;
		
		#if defined _DEBUG
		PrintToServer("%sAddAdmGroupCmdOverride(%i, %s, %i, %i)", SB_PREFIX, iGroup, sCommand, iType, iRule);
		#endif
		
		AddAdmGroupCmdOverride(iGroup, sCommand, iType, iRule);
	}
	
	// Remove this, if the group immunity gets implemented
	CloseHandle(pack);
	
	// It's time to get the group immunity list.
	/*
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT    sg1.name, sg2.name \
	                                FROM      {{srvgroups_immunity}}    AS gi \
	                                LEFT JOIN {{srvgroups}}             AS sg1 ON sg1.id      = gi.group_id \
	                                LEFT JOIN {{srvgroups}}             AS sg2 ON sg2.id      = gi.other_id \
	                                LEFT JOIN {{admins_servers_groups}} AS ag  ON ag.group_id = gi.group_id \
	                                LEFT JOIN {{servers_groups}}        AS gs  ON gs.group_id = ag.srv_group_id \
	                                WHERE     (ag.server_id = %i OR gs.server_id = %i)",
	                                g_iServerId, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iSequence);
	WritePackString(pack, sQuery);
	
	SB_Query(Query_SelectGroupImmunity, sQuery, pack, DBPrio_High);
	*/
}

/*
public Query_SelectGroupImmunity(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Check if this is the latest result request.
	new iSequence = ReadPackCell(pack);
	if(g_iRebuildCachePart[_:AdminCache_Groups] != iSequence)
	{
		// Discard everything, since we're out of sequence.
		CloseHandle(pack);
		return;
	}
	
	// If we need to use the results, make sure they succeeded.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));		
		LogError("SQL error receiving group immunity: %s", error);
		LogError("Query dump: %s", sQuery);
		CloseHandle(pack);	
		return;
	}
	
	// We're done with the pack forever.
	CloseHandle(pack);
	
	while(SQL_FetchRow(hndl))
	{
		decl String:sGroup1[33], String:sGroup2[33];
		new GroupId:iGroup1, GroupId:iGroup2;
		
		SQL_FetchString(hndl, 0, sGroup1, sizeof(sGroup1));
		SQL_FetchString(hndl, 1, sGroup2, sizeof(sGroup2));
		
		if((iGroup1 = FindAdmGroup(sGroup1)) == INVALID_GROUP_ID || (iGroup2 = FindAdmGroup(sGroup2)) == INVALID_GROUP_ID)
			continue;
		
		SetAdmGroupImmuneFrom(iGroup1, iGroup2);
		#if defined _DEBUG
		PrintToServer("%sSetAdmGroupImmuneFrom(%i, %i)", SB_PREFIX, iGroup1, iGroup2);
		#endif
	}
	
	// Clear the sequence so another connect doesn't refetch
	g_iRebuildCachePart[_:AdminCache_Groups] = 0;
}
*/

public Query_SelectOverrides(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Check if this is the latest result request.
	new iSequence = ReadPackCell(pack);
	if(g_iRebuildCachePart[_:AdminCache_Overrides] != iSequence)
	{
		// Discard everything, since we're out of sequence.
		CloseHandle(pack);
		return;
	}
	
	// If we need to use the results, make sure they succeeded.
	if(error[0])
	{
		decl String:sQuery[1024];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error receiving overrides: %s", error);
		LogError("Query dump: %s", sQuery);
		CloseHandle(pack);
		return;
	}
	
	// We're done with you, now.
	CloseHandle(pack);
	
	decl String:sFlags[32], String:sName[64], String:sType[64];
	while(SQL_FetchRow(hndl))
	{
		SQL_FetchString(hndl, 0, sType, sizeof(sType));
		SQL_FetchString(hndl, 1, sName, sizeof(sName));
		SQL_FetchString(hndl, 2, sFlags, sizeof(sFlags));
		
		#if defined _DEBUG
		PrintToServer("%sAdding override (%s, %s, %s)", SB_PREFIX, sType, sName, sFlags);
		#endif
		
		if(StrEqual(sType,      "command"))
			AddCommandOverride(sName, Override_Command,      ReadFlagString(sFlags));
		else if(StrEqual(sType, "group"))
			AddCommandOverride(sName, Override_CommandGroup, ReadFlagString(sFlags));
	}
	
	// Clear the sequence so another connect doesn't refetch
	g_iRebuildCachePart[_:AdminCache_Overrides] = 0;
}


/**
 * Natives
 */
public Native_GetAdminId(Handle:plugin, numParams)
{
	new iClient = GetNativeCell(1);
	return iClient && IsClientInGame(iClient) ? g_iAdminId[iClient] : 0;
}

public Native_AddAdmin(Handle:plugin, numParams)
{
	// order = client, name, authtype, identity, password, groups
	
	decl String:sGroups[512], String:sIdentity[65], String:sName[33], String:sPassword[65], String:sType[16];
	new iClient   = GetNativeCell(1);
	GetNativeString(2, sName,     sizeof(sName));
	GetNativeString(3, sType,     sizeof(sType));
	GetNativeString(4, sIdentity, sizeof(sIdentity));
	GetNativeString(5, sPassword, sizeof(sPassword));
	GetNativeString(6, sGroups,   sizeof(sGroups));
	
	if(!StrEqual(sType, AUTHMETHOD_STEAM) && !StrEqual(sType, AUTHMETHOD_IP) && !StrEqual(sType, AUTHMETHOD_NAME))
		return ThrowNativeError(SP_ERROR_NATIVE, "%s%T", SB_PREFIX, "Invalid authtype", iClient);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackString(hPack, sName);
	WritePackString(hPack, sType);
	WritePackString(hPack, sIdentity);
	WritePackString(hPack, sPassword);
	WritePackString(hPack, sGroups);
	
	decl String:sEscapedIdentity[129], String:sQuery[1024];
	SB_Escape(sIdentity, sEscapedIdentity, sizeof(sEscapedIdentity));
	Format(sQuery, sizeof(sQuery), "SELECT 1 \
	                                FROM   {{admins}} \
	                                WHERE  authid = '%s'",
	                                sEscapedIdentity);
	SB_Query(Query_AddAdmin, sQuery, hPack);
	
	return SP_ERROR_NONE;
}

public Native_DeleteAdmin(Handle:plugin, numParams)
{
	// order = client, authtype, identity
	
	decl String:sIdentity[65], String:sType[16];
	new iClient = GetNativeCell(1);
	GetNativeString(2, sType,     sizeof(sType));
	GetNativeString(3, sIdentity, sizeof(sIdentity));
	
	if(!StrEqual(sType, AUTHMETHOD_STEAM) && !StrEqual(sType, AUTHMETHOD_IP) && !StrEqual(sType, AUTHMETHOD_NAME))
		return ThrowNativeError(SP_ERROR_NATIVE, "%s%T", SB_PREFIX, "Invalid authtype", iClient);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackString(hPack, sType);
	WritePackString(hPack, sIdentity);
	
	decl String:sEscapedIdentity[129], String:sQuery[1024];
	SB_Escape(sIdentity, sEscapedIdentity, sizeof(sEscapedIdentity));
	Format(sQuery, sizeof(sQuery), "SELECT aid \
	                                FROM   {{admins}} \
	                                WHERE  authid = '%s'",
	                                sEscapedIdentity);
	SB_Query(Query_DelAdmin, sQuery, hPack);
	
	return SP_ERROR_NONE;
}

public Native_AddGroup(Handle:plugin, numParams)
{
	// order = client, name, flags, immunity
	
	decl String:sFlags[33], String:sName[33];
	new iClient   = GetNativeCell(1);
	GetNativeString(2, sName,  sizeof(sName));
	GetNativeString(3, sFlags, sizeof(sFlags));
	new iImmunity = GetNativeCell(4);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackString(hPack, sName);
	WritePackString(hPack, sFlags);
	WritePackCell(hPack,   iImmunity);
	
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sQuery[1024];
	SB_Escape(sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "SELECT 1 \
	                                FROM   {{srvgroups}} \
	                                WHERE  name = '%s'",
	                                sEscapedName);
	SB_Query(Query_AddGroup, sQuery, hPack);
}

public Native_DeleteGroup(Handle:plugin, numParams)
{
	// order = client, name
	
	decl String:sName[33];
	new iClient = GetNativeCell(1);
	GetNativeString(2, sName, sizeof(sName));
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackString(hPack, sName);
	
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sQuery[1024];
	SB_Escape(sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "SELECT id \
	                                FROM   {{srvgroups}} \
	                                WHERE  name = '%s'",
	                                sEscapedName);
	SB_Query(Query_DelGroup, sQuery, hPack);
}

public Native_SetAdminGroups(Handle:plugin, numParams)
{
	// order = client, authtype, identity, groups
	
	decl String:sGroups[256], String:sIdentity[65], String:sType[16];
	new iClient = GetNativeCell(1);
	GetNativeString(2, sType,     sizeof(sType));
	GetNativeString(3, sIdentity, sizeof(sIdentity));
	GetNativeString(4, sGroups,   sizeof(sGroups));
	TrimString(sGroups);
	
	if(!StrEqual(sType, AUTHMETHOD_STEAM) && !StrEqual(sType, AUTHMETHOD_IP) && !StrEqual(sType, AUTHMETHOD_NAME))
		return ThrowNativeError(SP_ERROR_NATIVE, "%s%T", SB_PREFIX, "Invalid authtype", iClient);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, iClient);
	
	// If groups were passed
	if(sGroups[0])
	{
		/**
		 * Get the total number of groups.
		 * We have to do this first because the query needs to know
		 * the amount before it starts to read the group names.
		 */
		decl String:sName[33];
		new iIndex  = 0, Handle:hGroups = CreateArray(33);
		while(iIndex != -1)
		{
			iIndex = BreakString(sGroups[iIndex], sName, sizeof(sName));
			PushArrayString(hGroups, sName);
		}
		
		// Store amount of passed groups
		new iGroups = GetArraySize(hGroups);
		WritePackCell(hPack, iGroups);
		
		// Store group names
		for(new i = 0; i < iGroups; i++)
		{
			GetArrayString(hGroups, i, sName, sizeof(sName));
			WritePackString(hPack, sName);
		}
	}
	else
		WritePackCell(hPack, 0);
	
	decl String:sEscapedIdentity[129], String:sQuery[1024];
	SB_Escape(sIdentity, sEscapedIdentity, sizeof(sEscapedIdentity));
	Format(sQuery, sizeof(sQuery), "SELECT aid \
	                                FROM   {{admins}} \
	                                WHERE  authid = '%s'",
	                                sEscapedIdentity);
	SB_Query(Query_SetAdminGroups, sQuery, hPack);
	
	return SP_ERROR_NONE;
}


/**
 * Stocks
 */
stock SB_FetchAdmin(iClient)
{
	decl String:sAuth[20], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1];
	// Get authentication information from the client.
	GetClientName(iClient, sName, sizeof(sName));
	GetClientIP(iClient,   sIp,   sizeof(sIp));
	
	if(!GetClientAuthString(iClient, sAuth, sizeof(sAuth)) || StrContains("BOT STEAM_ID_LAN", sAuth) != -1)
		sAuth[8] = '\0';
	
	// Construct the query using the information the client gave us.
	decl String:sCondition[1024] = "", String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sQuery[1024];
	if(g_bRequireSiteLogin)
		StrCat(sCondition, sizeof(sCondition), " AND ad.lastvisit IS NOT NULL");
	
	SB_Escape(sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "SELECT    ad.aid, ad.user, 'steam', ad.authid, ad.srv_password, COUNT(ag.group_id) \
	                                FROM      {{admins}}                AS ad \
	                                LEFT JOIN {{admins_servers_groups}} AS ag ON ag.admin_id = ad.aid \
	                                LEFT JOIN {{servers_groups}}        AS gs ON gs.group_id = ag.srv_group_id \
	                                WHERE     ((ad.authid REGEXP '^STEAM_[0-9]:%s$') \
	                                   OR      ('%s' REGEXP REPLACE(REPLACE(ad.authid, '.', '\\.') , '.0', '..{1,3}')) \
	                                   OR      (ad.authid = '%s')) \
	                                  AND     (ag.server_id = %i OR gs.server_id = %i)%s \
	                                GROUP BY  ad.aid",
	                                sAuth[8], sIp, sEscapedName, g_iServerId, g_iServerId, sCondition);
	
	// Send the actual query.
	g_iPlayerSeq[iClient] = ++g_iSequence;
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackCell(hPack,   g_iPlayerSeq[iClient]);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("%sSending admin query: %s", SB_PREFIX, sQuery);
	#endif
	
	SB_Query(Query_SelectAdmin, sQuery, hPack, DBPrio_High);
}

stock SB_FetchAdmins()
{
	for(new i = 1; i <= MaxClients; i++)
	{
		if(g_bPlayerAuth[i] && GetUserAdmin(i) == INVALID_ADMIN_ID)
			SB_FetchAdmin(i);
	}
	
	// This round of updates is done.  Go in peace.
	g_iRebuildCachePart[_:AdminCache_Admins] = 0;
}

stock SB_FetchGroups(iSequence)
{
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name, sg.flags, sg.immunity \
	                                FROM      {{srvgroups}}             AS sg \
	                                LEFT JOIN {{admins_servers_groups}} AS ag ON ag.group_id = sg.id \
	                                LEFT JOIN {{servers_groups}}        AS gs ON gs.group_id = ag.srv_group_id \
	                                WHERE     (ag.server_id = %i OR gs.server_id = %i) \
	                                GROUP BY  sg.id",
	                                g_iServerId, g_iServerId);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iSequence);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("%sSending groups query: %s", SB_PREFIX, sQuery);
	#endif
	
	SB_Query(Query_SelectGroups, sQuery, hPack, DBPrio_High);
}

stock SB_FetchOverrides(iSequence)
{
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT type, name, flags \
	                                FROM   {{overrides}}");
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iSequence);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("%sSending overrides query: %s", SB_PREFIX, sQuery);
	#endif
	
	SB_Query(Query_SelectOverrides, sQuery, hPack, DBPrio_High);
}