/**
 * =============================================================================
 * SourceBans Admins Plugin
 *
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: sourcebans.sp 178 2008-12-01 15:10:00Z tsunami $
 * =============================================================================
 */

#include <sourcemod>
#include <sourcebans>

//#define _DEBUG

public Plugin:myinfo =
{
	name        = "SourceBans: Admins",
	author      = "InterWave Studios",
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
new Handle:g_hDatabase;
new String:g_sDatabasePrefix[16];
new String:g_sServerIp[16];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("sqladmins.phrases");
}

#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
#else
public bool:AskPluginLoad(Handle:myself, bool:late, String:error[], err_max)
#endif
{
	CreateNative("SB_GetAdminId", Native_GetAdminId);
	RegPluginLibrary("sb_admins");
	#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
	return APLRes_Success;
	#else
	return true;
	#endif
}

public OnRebuildAdminCache(AdminCachePart:part)
{
	// Mark this part of the cache as being rebuilt.  This is used by the 
	// callback system to determine whether the results should still be 
	// used.
	new iSequence               = ++g_iSequence;
	g_iRebuildCachePart[_:part] = iSequence;
	
	// If we don't have a database connection, we can't do any lookups just yet.
	if(!g_hDatabase)
	{
		// Ask for a new connection if we need it.
		SB_Connect();
		return;
	}
	
	if(part      == AdminCache_Admins)
		SB_FetchAdmins();
	else if(part == AdminCache_Groups)
		SB_FetchGroups(iSequence);
	else if(part == AdminCache_Overrides)
		SB_FetchOverrides(iSequence);
}

public Action:OnLogAction(Handle:source, Identity:ident, client, target, const String:message[])
{
	if(!g_hDatabase)
		return Plugin_Continue;
	
	decl String:sAdminIp[16] = "", String:sAuth[20] = "", String:sEscapedMessage[256], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sIp[16] = "", String:sName[MAX_NAME_LENGTH + 1] = "", String:sQuery[512];
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
	
	SQL_EscapeString(g_hDatabase, message, sEscapedMessage, sizeof(sEscapedMessage));
	SQL_EscapeString(g_hDatabase, sName,   sEscapedName,    sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_actions (name, steam, ip, message, server_id, admin_id, admin_ip, time) \
																	VALUES      (NULLIF('%s', ''), NULLIF('%s', ''), NULLIF('%s', ''), '%s', %i, NULLIF(%i, 0), '%s', UNIX_TIMESTAMP())",
																	g_sDatabasePrefix, sEscapedName, sAuth, sIp, sEscapedMessage, g_iServerId, iAdminId, sAdminIp);
	SQL_TQuery(g_hDatabase, Query_ErrorCheck, sQuery);
	return Plugin_Handled;
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
	if(!g_hDatabase)
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
	g_iServerId = SB_GetSettingCell("ServerID");
	g_hDatabase = database;
	
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
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SB_GetSettingString("ServerIP",       g_sServerIp,       sizeof(g_sServerIp));
}


/**
 * Query Callbacks
 */
public OnReceiveAdmin(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[256];
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
		
		iAdmin   = CreateAdmin(sName);
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
		PrintToServer("Found SQL admin (%i,%s,%s,%s,%s):%i:%i", iAdminId, sType, sIdentity, sPassword, sName, iAdmin, iLookup[iAdmins - 1][2]);
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
	PrintToServer("Binding client (%i, %i) resulted in: (%i, %i, %i)", iClient, iSequence, iAdminId, iAdmin, iGroups);
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
	decl String:sQuery[256];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name \
																	FROM      %s_srvgroups         AS sg \
																	LEFT JOIN %s_admins_srvgroups  AS ag ON ag.group_id = sg.id \
																	LEFT JOIN %s_servers_srvgroups AS gs ON gs.group_id = sg.id \
																	WHERE     ag.admin_id  = %i \
																		AND     gs.server_id = %i",
																	g_sDatabasePrefix, g_sDatabasePrefix, g_sDatabasePrefix, iAdminId, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iClient);
	WritePackCell(pack,   iSequence);
	WritePackCell(pack,   _:iAdmin);
	WritePackString(pack, sQuery);
	
	SQL_TQuery(owner, OnReceiveAdminGroups, sQuery, pack, DBPrio_High);
}

public OnReceiveAdminGroups(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[256];
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
		PrintToServer("Binding admin group (%i, %i, %i, %s, %i)", iClient, iSequence, iAdmin, sName, iGroup);
		#endif
		
		AdminInheritGroup(iAdmin, iGroup);
	}
	
	// We're DONE! Omg.
	NotifyPostAdminCheck(iClient);
	CloseHandle(pack);
}

public OnReceiveGroups(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[256];
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
		SQL_FetchString(hndl, 0, sName, sizeof(sName));
		SQL_FetchString(hndl, 1, sFlags, sizeof(sFlags));
		iImmunity = SQL_FetchInt(hndl, 2);
		
		#if defined _DEBUG
		PrintToServer("Adding group (%i, %s, %s)", iImmunity, sFlags, sName);
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
	decl String:sQuery[384];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name, go.type, go.name, go.access \
																	FROM      %s_srvgroups_overrides AS go \
																	LEFT JOIN %s_srvgroups           AS sg ON go.group_id = sg.id \
																	LEFT JOIN %s_servers_srvgroups   AS gs ON gs.group_id = sg.id \
																	WHERE     gs.server_id = %i \
																	ORDER BY  sg.id DESC",
																	g_sDatabasePrefix, g_sDatabasePrefix, g_sDatabasePrefix, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iSequence);
	WritePackString(pack, sQuery);
	
	SQL_TQuery(owner, OnReceiveGroupOverrides, sQuery, pack, DBPrio_High);
}

public OnReceiveGroupOverrides(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[384];
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
		PrintToServer("AddAdmGroupCmdOverride(%i, %s, %i, %i)", iGroup, sCommand, iType, iRule);
		#endif
		
		AddAdmGroupCmdOverride(iGroup, sCommand, iType, iRule);
	}
	
	// It's time to get the group immunity list.
	decl String:sQuery[384];
	Format(sQuery, sizeof(sQuery), "SELECT    sg1.name, sg2.name \
																	FROM      %s_srvgroups_immunity AS gi \
																	LEFT JOIN %s_srvgroups          AS sg1 ON sg1.id      = gi.group_id \
																	LEFT JOIN %s_srvgroups          AS sg2 ON sg2.id      = gi.other_id \
																	LEFT JOIN %s_servers_srvgroups  AS gs  ON gs.group_id = gi.group_id \
																	WHERE     gs.server_id = %i",
																	g_sDatabasePrefix, g_sDatabasePrefix, g_sDatabasePrefix, g_sDatabasePrefix, g_iServerId);
	
	ResetPack(pack);
	WritePackCell(pack,   iSequence);
	WritePackString(pack, sQuery);
	
	SQL_TQuery(owner, OnReceiveGroupImmunity, sQuery, pack, DBPrio_High);
}

public OnReceiveGroupImmunity(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[384];
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
		decl String:sGroup1[33];
		decl String:sGroup2[33];
		new GroupId:iGroup1, GroupId:iGroup2;
		
		SQL_FetchString(hndl, 0, sGroup1, sizeof(sGroup1));
		SQL_FetchString(hndl, 1, sGroup2, sizeof(sGroup2));
		
		if(((iGroup1 = FindAdmGroup(sGroup1)) == INVALID_GROUP_ID) || (iGroup2 = FindAdmGroup(sGroup2)) == INVALID_GROUP_ID)
			continue;
		
		SetAdmGroupImmuneFrom(iGroup1, iGroup2);
		#if defined _DEBUG
		PrintToServer("SetAdmGroupImmuneFrom(%i, %i)", iGroup1, iGroup2);
		#endif
	}
	
	// Clear the sequence so another connect doesn't refetch
	g_iRebuildCachePart[_:AdminCache_Groups] = 0;
}

public OnReceiveOverrides(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
		decl String:sQuery[256];
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
		PrintToServer("Adding override (%s, %s, %s)", sType, sName, sFlags);
		#endif
		
		if(StrEqual(sType,      "command"))
			AddCommandOverride(sName, Override_Command,      ReadFlagString(sFlags));
		else if(StrEqual(sType, "group"))
			AddCommandOverride(sName, Override_CommandGroup, ReadFlagString(sFlags));
	}
	
	// Clear the sequence so another connect doesn't refetch
	g_iRebuildCachePart[_:AdminCache_Overrides] = 0;
}

public Query_ErrorCheck(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
}


/**
 * Natives
 */
public Native_GetAdminId(Handle:plugin, numParams)
{
	new iClient = GetNativeCell(1);
	return iClient && IsClientInGame(iClient) ? g_iAdminId[iClient] : 0;
}


/**
 * Stocks
 */
stock SB_FetchAdmin(iClient)
{
	decl String:sAuth[20], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sQuery[768];
	// Get authentication information from the client.
	GetClientName(iClient, sName, sizeof(sName));
	GetClientIP(iClient,   sIp,   sizeof(sIp));
	
	sAuth[0] = '\0';
	if(GetClientAuthString(iClient, sAuth, sizeof(sAuth)) && StrEqual(sAuth, "STEAM_ID_LAN"))
		sAuth[0] = '\0';
	
	// Construct the query using the information the client gave us.
	SQL_EscapeString(g_hDatabase, sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "SELECT    ad.id, ad.name, ad.auth, ad.identity, ad.srv_password, COUNT(ag.group_id) \
																	FROM      %s_admins            AS ad \
																	LEFT JOIN %s_admins_srvgroups  AS ag ON ag.admin_id = ad.id \
																	LEFT JOIN %s_servers_srvgroups AS gs ON gs.group_id = ag.group_id \
																	WHERE     ((ad.auth = '%s' AND ad.identity REGEXP '^STEAM_[0-9]:%s$') \
																		 OR      (ad.auth = '%s' AND '%s' REGEXP REPLACE(REPLACE(ad.identity, '.', '\\.') , '.0', '..{1,3}')) \
																		 OR      (ad.auth = '%s' AND ad.identity = '%s')) \
																		AND     gs.server_id = %i \
																	GROUP BY  ad.id",
																	g_sDatabasePrefix, g_sDatabasePrefix, g_sDatabasePrefix, AUTHMETHOD_STEAM, sAuth[8], AUTHMETHOD_IP, sIp, AUTHMETHOD_NAME, sEscapedName, g_iServerId);
	
	// Send the actual query.
	g_iPlayerSeq[iClient] = ++g_iSequence;
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iClient);
	WritePackCell(hPack,   g_iPlayerSeq[iClient]);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("Sending admin query: %s", sQuery);
	#endif
	
	SQL_TQuery(g_hDatabase, OnReceiveAdmin, sQuery, hPack, DBPrio_High);
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
	decl String:sQuery[192];
	Format(sQuery, sizeof(sQuery), "SELECT    sg.name, sg.flags, sg.immunity \
																	FROM      %s_srvgroups         AS sg \
																	LEFT JOIN %s_servers_srvgroups AS gs ON gs.group_id = sg.id \
																	WHERE     gs.server_id = %i",
																	g_sDatabasePrefix, g_sDatabasePrefix, g_iServerId);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iSequence);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("Sending groups query: %s", sQuery);
	#endif
	
	SQL_TQuery(g_hDatabase, OnReceiveGroups, sQuery, hPack, DBPrio_High);
}

stock SB_FetchOverrides(iSequence)
{
	decl String:sQuery[64];
	Format(sQuery, sizeof(sQuery), "SELECT type, name, flags \
																	FROM   %s_overrides",
																	g_sDatabasePrefix);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   iSequence);
	WritePackString(hPack, sQuery);
	
	#if defined _DEBUG
	PrintToServer("Sending overrides query: %s", sQuery);
	#endif
	
	SQL_TQuery(g_hDatabase, OnReceiveOverrides, sQuery, hPack, DBPrio_High);
}