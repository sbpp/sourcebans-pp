/**
 * =============================================================================
 * SourceBans Bans Plugin
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

#undef REQUIRE_PLUGIN
#include <adminmenu>
#include <dbi>

#define STEAM_BAN_TYPE		0
#define IP_BAN_TYPE				1
#define DEFAULT_BAN_TYPE	STEAM_BAN_TYPE

//#define _DEBUG

public Plugin:myinfo =
{
	name        = "SourceBans: Bans",
	author      = "InterWave Studios",
	description = "Advanced ban management for the Source engine",
	version     = SB_VERSION,
	url         = "http://www.sourcebans.net"
};


/**
 * Globals
 */
new g_iBanTarget[MAXPLAYERS + 1];
new g_iBanTime[MAXPLAYERS + 1];
new g_iProcessQueueTime;
new g_iServerId;
new bool:g_bEnableAddBan;
new bool:g_bEnableUnban;
new bool:g_bPlayerStatus[MAXPLAYERS + 1];
new Float:g_fRetryTime;
new Handle:g_hDatabase;
new Handle:g_hBanTimes;
new Handle:g_hBanTimesFlags;
new Handle:g_hBanTimesLength;
new Handle:g_hHackingMenu;
new Handle:g_hPlayerRecheck[MAXPLAYERS + 1];
new Handle:g_hProcessQueue;
new Handle:g_hReasonMenu;
new Handle:g_hSQLiteDB;
new Handle:g_hTopMenu;
new String:g_sDatabasePrefix[16];
new String:g_sServerIp[16];
new String:g_sWebsite[256];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	RegAdminCmd("sm_ban",    Command_Ban,    ADMFLAG_BAN,   "sm_ban <#userid|name> <minutes|0> [reason]");
	RegAdminCmd("sm_unban",  Command_Unban,  ADMFLAG_UNBAN, "sm_unban <steamid|ip>");
	RegAdminCmd("sm_addban", Command_AddBan, ADMFLAG_RCON,  "sm_addban <time> <steamid> [reason]");
	RegAdminCmd("sm_banip",  Command_BanIp,  ADMFLAG_BAN,   "sm_banip <ip|#userid|name> <time> [reason]");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("basebans.phrases");
	
	g_hHackingMenu  = CreateMenu(MenuHandler_Reason);
	g_hReasonMenu   = CreateMenu(MenuHandler_Reason);
	
	// Account for late loading
	new Handle:hTopMenu;
	if(LibraryExists("adminmenu") && (hTopMenu = GetAdminTopMenu()))
		OnAdminMenuReady(hTopMenu);
	
	// Connect to local database
	decl String:sError[256];
	g_hSQLiteDB = SQLite_UseDatabase("sourcemod-local", sError, sizeof(sError));
	if(sError[0])
	{
		LogError("%T (%s)", "Could not connect to database", LANG_SERVER, sError);
		return;
	}
	
	// Create local bans table
	SQL_FastQuery(g_hSQLiteDB, "CREATE TABLE IF NOT EXISTS sb_bans (type INTEGER, steam TEXT PRIMARY KEY ON CONFLICT REPLACE, ip TEXT, name TEXT, created INTEGER, length INTEGER, reason TEXT, admin_id TEXT, admin_ip TEXT, queued BOOLEAN, time INTEGER)");
	
	// Process temporary bans every minute
	CreateTimer(60.0, Timer_ProcessTemp);
}

public OnAdminMenuReady(Handle:topmenu)
{
	// Block us from being called twice
	//if(topmenu == g_hTopMenu)
	//	return;
	
	// Save the handle
	g_hTopMenu = topmenu;
	
	// Find the "Player Commands" category
	new TopMenuObject:iPlayerCommands = FindTopMenuCategory(g_hTopMenu, ADMINMENU_PLAYERCOMMANDS);
	if(iPlayerCommands)
		AddToTopMenu(g_hTopMenu,
			"sm_ban",
			TopMenuObject_Item,
			MenuHandler_Ban,
			iPlayerCommands,
			"sm_ban",
			ADMFLAG_BAN);
}

public OnConfigsExecuted()
{
	decl String:sNewFile[PLATFORM_MAX_PATH + 1], String:sOldFile[PLATFORM_MAX_PATH + 1];
	BuildPath(Path_SM, sNewFile, sizeof(sNewFile), "plugins/disabled/basebans.smx");
	BuildPath(Path_SM, sOldFile, sizeof(sOldFile), "plugins/basebans.smx");
	
	// Check if plugins/basebans.smx exists, and if not, ignore
	if(!FileExists(sOldFile))
		return;
	
	// Check if plugins/disabled/basebans.smx already exists, and if so, delete it
	if(FileExists(sNewFile))
		DeleteFile(sNewFile);
	
	// Unload plugins/basebans.smx and move it to plugins/disabled/basebans.smx
	ServerCommand("sm plugins unload basebans");
	RenameFile(sNewFile, sOldFile);
	LogMessage("plugins/basebans.smx was unloaded and moved to plugins/disabled/basebans.smx");
}


/**
 * Client Forwards
 */
public OnClientAuthorized(client, const String:auth[])
{
	if(!g_hDatabase || StrContains("BOT STEAM_ID_LAN", auth) != -1)
	{
		g_bPlayerStatus[client] = true;
		return;
	}
	
	decl String:sIp[16], String:sQuery[256];
	GetClientIP(client, sIp, sizeof(sIp));
	
	Format(sQuery, sizeof(sQuery), "SELECT type, steam, ip, name, created, ends - created, reason, admin_id, admin_ip \
																	FROM   %s_bans \
																	WHERE  ((type = %i AND steam REGEXP '^STEAM_[0-9]:%s$') OR (type = %i AND '%s' REGEXP REPLACE(REPLACE(ip, '.', '\\.') , '.0', '..{1,3}'))) \
																		AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																		AND  unban_admin_id IS NULL",
																	g_sDatabasePrefix, STEAM_BAN_TYPE, auth[8], IP_BAN_TYPE, sIp);
	SQL_TQuery(g_hDatabase, Query_BanVerify, sQuery, client, DBPrio_High);
}

public bool:OnClientConnect(client, String:rejectmsg[], maxlen)
{
	g_bPlayerStatus[client] = false;
	if(!g_hSQLiteDB)
		return true;
	
	decl String:sIp[16], String:sQuery[256];
	GetClientIP(client, sIp, sizeof(sIp));
	Format(sQuery, sizeof(sQuery), "SELECT steam, ip, name, reason, length \
																	FROM   sb_bans \
																	WHERE  '%s' REGEXP REPLACE(REPLACE(ip, '.', '\\.') , '.0', '..{1,3}') \
																		AND  time < %i",
																	sIp, GetTime());
	
	new Handle:hQuery = SQL_Query(g_hSQLiteDB, sQuery);
	if(!hQuery || !SQL_FetchRow(hQuery))
		return true;
	
	Format(sQuery, sizeof(sQuery), "UPDATE sb_bans \
																	SET    time = time + 5 \
																	WHERE  '%s' REGEXP REPLACE(REPLACE(ip, '.', '\\.') , '.0', '..{1,3}')",
																	sIp);
	SQL_FastQuery(g_hSQLiteDB, sQuery);
	
	decl String:sAuth[20], String:sName[MAX_NAME_LENGTH + 1], String:sReason[128];
	SQL_FetchString(hQuery, 0, sAuth,   sizeof(sAuth));
	SQL_FetchString(hQuery, 1, sIp,     sizeof(sIp));
	SQL_FetchString(hQuery, 2, sName,   sizeof(sName));
	SQL_FetchString(hQuery, 3, sReason, sizeof(sReason));
	PrintBanInformation(client, sAuth, sIp, sName, sReason, SQL_FetchInt(hQuery, 4));
	
	Format(rejectmsg, maxlen, "%t", "Banned Check Site", g_sWebsite);
	return false;
}

public OnClientDisconnect(client)
{
	if(g_hPlayerRecheck[client])
	{
		KillTimer(g_hPlayerRecheck[client]);
		g_hPlayerRecheck[client] = INVALID_HANDLE;
	}
}


/**
 * Ban Forwards
 */
public Action:OnBanClient(client, time, flags, const String:reason[], const String:kick_message[], const String:command[], any:admin)
{
	decl String:sAdminIp[16], String:sAuth[20], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1];
	new iAdminId = GetAdminId(admin);
	GetClientAuthString(client, sAuth, sizeof(sAuth));
	GetClientIP(client,         sIp,   sizeof(sIp));
	GetClientName(client,       sName, sizeof(sName));
	
	if(!admin)
		sAdminIp = g_sServerIp;
	else
		GetClientIP(admin, sAdminIp, sizeof(sAdminIp));
	if(!g_hDatabase)
	{
		InsertLocalBan(DEFAULT_BAN_TYPE, sAuth, sIp, sName, GetTime(), time, reason, iAdminId, sAdminIp, true);
		return Plugin_Handled;
	}
	if(!time)
	{
		if(reason[0])
			ShowActivity2(admin, SB_PREFIX, "%t", "Permabanned player reason", sName, reason);
		else
			ShowActivity2(admin, SB_PREFIX, "%t", "Permabanned player",        sName);
	}
	else
	{
		if(reason[0])
			ShowActivity2(admin, SB_PREFIX, "%t", "Banned player reason",      sName, time, reason);
		else
			ShowActivity2(admin, SB_PREFIX, "%t", "Banned player",             sName, time);
	}
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   admin);
	WritePackCell(hPack,   time);
	WritePackString(hPack, sAuth);
	WritePackString(hPack, sIp);
	WritePackString(hPack, sName);
	WritePackString(hPack, reason);
	WritePackCell(hPack,   iAdminId);
	WritePackString(hPack, sAdminIp);
	
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256], String:sQuery[512];
	SQL_EscapeString(g_hDatabase, sName,  sEscapedName,   sizeof(sEscapedName));
	SQL_EscapeString(g_hDatabase, reason, sEscapedReason, sizeof(sEscapedReason));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, steam, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      (%i, '%s', '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', %i, NULLIF(%i, 0), '%s')",
																	g_sDatabasePrefix, DEFAULT_BAN_TYPE, sAuth, sIp, sEscapedName, time * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, iAdminId, sAdminIp);
	SQL_TQuery(g_hDatabase, Query_BanInsert, sQuery, hPack, DBPrio_High);
	
	LogAction(admin, client, "\"%L\" banned \"%L\" (minutes \"%i\") (reason \"%s\")", admin, client, time, reason);
	return Plugin_Handled;
}

public Action:OnBanIdentity(const String:identity[], time, flags, const String:reason[], const String:command[], any:admin)
{
	decl String:sAdminIp[16], String:sQuery[140];
	new iAdminId    = GetAdminId(admin),
			bool:bSteam = strncmp(identity, "STEAM_", 6) == 0;
	
	if(!admin)
		sAdminIp = g_sServerIp;
	else
		GetClientIP(admin, sAdminIp, sizeof(sAdminIp));
	if(!g_hDatabase)
	{
		if(bSteam)
			InsertLocalBan(STEAM_BAN_TYPE, identity, "", "", GetTime(), time, reason, iAdminId, sAdminIp, true);
		else
			InsertLocalBan(IP_BAN_TYPE,    "", identity, "", GetTime(), time, reason, iAdminId, sAdminIp, true);
		return Plugin_Handled;
	}
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   admin);
	WritePackCell(hPack,   time);
	WritePackString(hPack, identity);
	WritePackString(hPack, reason);
	WritePackCell(hPack,   iAdminId);
	WritePackString(hPack, sAdminIp);
	
	if(flags      & BANFLAG_AUTHID || ((flags & BANFLAG_AUTO) && bSteam))
	{
		Format(sQuery, sizeof(sQuery), "SELECT id \
																		FROM   %s_bans \
																		WHERE  type  = %i \
																		  AND  steam REGEXP '^STEAM_[0-9]:%s$' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, STEAM_BAN_TYPE, identity[8]);
		SQL_TQuery(g_hDatabase, Query_AddBanSelect, sQuery, hPack, DBPrio_High);
		
		LogAction(admin, -1, "\"%L\" added ban (minutes \"%i\") (id \"%s\") (reason \"%s\")", admin, time, identity, reason);
	}
	else if(flags & BANFLAG_IP     || ((flags & BANFLAG_AUTO) && !bSteam))
	{
		Format(sQuery, sizeof(sQuery), "SELECT id \
																		FROM   %s_bans \
																		WHERE  type = %i \
																		  AND  ip   = '%s' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, IP_BAN_TYPE, identity);
		SQL_TQuery(g_hDatabase, Query_BanIpSelect,  sQuery, hPack, DBPrio_High);
		
		LogAction(admin, -1, "\"%L\" added ban (minutes \"%i\") (ip \"%s\") (reason \"%s\")", admin, time, identity, reason);
	}
	return Plugin_Handled;
}

public Action:OnRemoveBan(const String:identity[], flags, const String:command[], any:admin)
{
	decl String:sQuery[256];
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack,   admin);
	WritePackString(hPack, identity);
	
	if(flags      & BANFLAG_AUTHID)
		Format(sQuery, sizeof(sQuery), "SELECT admin_id \
																		FROM   %s_bans \
																		WHERE  type  = %i \
																			AND  steam REGEXP '^STEAM_[0-9]:%s$' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, STEAM_BAN_TYPE, identity[8]);
	else if(flags & BANFLAG_IP)
		Format(sQuery, sizeof(sQuery), "SELECT admin_id \
																		FROM   %s_bans \
																		WHERE  type = %i \
																			AND  ip   = '%s' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, IP_BAN_TYPE, identity);
	SQL_TQuery(g_hDatabase, Query_UnbanSelect, sQuery, hPack);
	
	LogAction(admin, -1, "\"%L\" removed ban (filter \"%s\")", admin, identity);
	return Plugin_Handled;
}


/**
 * SourceBans Forwards
 */
public SB_OnConnect(Handle:database)
{
	g_iServerId = SB_GetSettingCell("ServerID");
	g_hDatabase = database;
}

public SB_OnReload()
{
	// Get settings from SourceBans config and store them locally
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SB_GetSettingString("ServerIP",       g_sServerIp,       sizeof(g_sServerIp));
	SB_GetSettingString("Website",        g_sWebsite,        sizeof(g_sWebsite));
	g_bEnableAddBan     = SB_GetSettingCell("Addban") == 1;
	g_bEnableUnban      = SB_GetSettingCell("Unban")  == 1;
	g_iProcessQueueTime = SB_GetSettingCell("ProcessQueueTime");
	g_fRetryTime        = float(SB_GetSettingCell("RetryTime"));
	g_hBanTimes         = Handle:SB_GetSettingCell("BanTimes");
	g_hBanTimesFlags    = Handle:SB_GetSettingCell("BanTimesFlags");
	g_hBanTimesLength   = Handle:SB_GetSettingCell("BanTimesLength");
	
	// Get reasons from SourceBans config and store them locally
	decl String:sReason[128];
	new Handle:hBanReasons     = Handle:SB_GetSettingCell("BanReasons");
	new Handle:hHackingReasons = Handle:SB_GetSettingCell("HackingReasons");
	
	// Empty reason menus
	RemoveAllMenuItems(g_hReasonMenu);
	RemoveAllMenuItems(g_hHackingMenu);
	
	// Add reasons from SourceBans config to reason menus
	for(new i = 0, iSize = GetArraySize(hBanReasons);     i < iSize; i++)
	{
		GetArrayString(hBanReasons,     i, sReason, sizeof(sReason));
		AddMenuItem(g_hReasonMenu,  sReason, sReason);
	}
	for(new i = 0, iSize = GetArraySize(hHackingReasons); i < iSize; i++)
	{
		GetArrayString(hHackingReasons, i, sReason, sizeof(sReason));
		AddMenuItem(g_hHackingMenu, sReason, sReason);
	}
	
	// Restart process queue timer
	if(g_hProcessQueue)
		KillTimer(g_hProcessQueue);
	
	g_hProcessQueue = CreateTimer(g_iProcessQueueTime * 60.0, Timer_ProcessQueue, _, TIMER_REPEAT);
}


/**
 * Commands
 */
public Action:Command_Ban(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_ban <#userid|name> <time|0> [reason]", SB_PREFIX);
		return Plugin_Handled;
	}
	
	decl iLen, String:sArg[256], String:sKickMessage[128], String:sTarget[64], String:sTime[12];
	GetCmdArgString(sArg, sizeof(sArg));
	iLen  = BreakString(sArg,       sTarget, sizeof(sTarget));
	iLen += BreakString(sArg[iLen], sTime,   sizeof(sTime));
	
	new iTarget = FindTarget(client, sTarget, true), iTime = StringToInt(sTime);
	if(iTarget == -1)
		return Plugin_Handled;
	if(!g_bPlayerStatus[iTarget])
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Ban Not Verified");
		return Plugin_Handled;
	}
	if(!iTime && !(GetUserFlagBits(client) & (ADMFLAG_UNBAN|ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "%sYou do not have Perm Ban Permission", SB_PREFIX);
		return Plugin_Handled;
	}
	
	Format(sKickMessage, sizeof(sKickMessage), "%t", "Banned Check Site", g_sWebsite);
	BanClient(iTarget, iTime, BANFLAG_AUTO, sArg[iLen], sKickMessage, "sm_ban", client);
	return Plugin_Handled;
}

public Action:Command_BanIp(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_banip <ip|#userid|name> <time> [reason]", SB_PREFIX);
		return Plugin_Handled;
	}
	
	decl iLen, iTargets[1], bool:tn_is_ml, String:sArg[256], String:sIp[16], String:sTargets[MAX_TARGET_LENGTH], String:sTime[12];
	GetCmdArgString(sArg, sizeof(sArg));
	iLen  = BreakString(sArg,       sIp,   sizeof(sIp));
	iLen += BreakString(sArg[iLen], sTime, sizeof(sTime));
	
	new iTarget = -1, iTime = StringToInt(sTime);
	if(!iTime && !(GetUserFlagBits(client) & (ADMFLAG_UNBAN|ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "%sYou do not have Perm Ban Permission", SB_PREFIX);
		return Plugin_Handled;
	}
	if(ProcessTargetString(sIp,
		client,
		iTargets,
		1,
		COMMAND_FILTER_CONNECTED|COMMAND_FILTER_NO_MULTI,
		sTargets,
		sizeof(sTargets),
		tn_is_ml) > 0)
	{
		iTarget = iTargets[0];
		if(!IsFakeClient(iTarget) && CanUserTarget(client, iTarget))
			GetClientIP(iTarget, sIp, sizeof(sIp));
	}
	
	BanIdentity(sIp, iTime, BANFLAG_IP, sArg[iLen], "sm_banip",  client);
	return Plugin_Handled;
}

public Action:Command_AddBan(client, args)
{
	if(args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_addban <time> <steamid> [reason]", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!g_bEnableAddBan)
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Can Not Add Ban", g_sWebsite);
		return Plugin_Handled;
	}
	
	decl iLen, iTargets[1], bool:tn_is_ml, String:sArg[256], String:sAuth[20], String:sTargets[MAX_TARGET_LENGTH], String:sTime[20];
	GetCmdArgString(sArg, sizeof(sArg));
	iLen  = BreakString(sArg,       sTime, sizeof(sTime));
	iLen += BreakString(sArg[iLen], sAuth, sizeof(sAuth));
	
	new iTarget = -1, iTime = StringToInt(sTime);
	if(!iTime && !(GetUserFlagBits(client) & (ADMFLAG_UNBAN|ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "%sYou do not have Perm Ban Permission", SB_PREFIX);
		return Plugin_Handled;
	}
	if(ProcessTargetString(sAuth,
		client,
		iTargets,
		1,
		COMMAND_FILTER_CONNECTED|COMMAND_FILTER_NO_MULTI,
		sTargets,
		sizeof(sTargets),
		tn_is_ml) > 0)
	{
		iTarget = iTargets[0];
		
		if(!IsFakeClient(iTarget) && CanUserTarget(client, iTarget))
			GetClientAuthString(iTarget, sAuth, sizeof(sAuth));
	}
	
	BanIdentity(sAuth, iTime, BANFLAG_AUTHID, sArg[iLen], "sm_addban", client);
	return Plugin_Handled;
}

public Action:Command_Unban(client, args)
{
	if(args < 1)
	{
		ReplyToCommand(client, "%sUsage: sm_unban <steamid|ip>", SB_PREFIX);
		return Plugin_Handled;
	}
	if(!(GetUserFlagBits(client) & (ADMFLAG_UNBAN|ADMFLAG_ROOT)) || !g_bEnableUnban)
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Can Not Unban", g_sWebsite);
		return Plugin_Handled;
	}
	
	decl String:sArg[24];
	GetCmdArgString(sArg, sizeof(sArg));
	ReplaceString(sArg,   sizeof(sArg), "\"", "");
	
	RemoveBan(sArg, strncmp(sArg, "STEAM_", 6) == 0 ? BANFLAG_AUTHID : BANFLAG_IP, "sm_unban", client);
	return Plugin_Handled;
}


/**
 * Timers
 */
public Action:Timer_ClientRecheck(Handle:timer, any:client)
{
	if(!g_bPlayerStatus[client] && IsClientConnected(client))
	{
		decl String:sAuth[20];
		GetClientAuthString(client, sAuth, sizeof(sAuth));
		OnClientAuthorized(client,  sAuth);
	}
	
	g_hPlayerRecheck[client] = INVALID_HANDLE;
	return Plugin_Stop;
}

public Action:Timer_ProcessQueue(Handle:timer, any:data)
{
	new Handle:hQuery = SQL_Query(g_hSQLiteDB, "SELECT type, steam, ip, name, created, length, reason, admin_id, admin_ip \
																							FROM   sb_bans \
																							WHERE  queued = 1");
	if(!hQuery)
		return;
	
	decl iAdmin, iStart, iTime, iType, String:sAdminIp[16], String:sAuth[20], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1],
			 String:sEscapedReason[256], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sQuery[768], String:sReason[128];
	while(SQL_FetchRow(hQuery))
	{
		iType  = SQL_FetchInt(hQuery, 0);
		SQL_FetchString(hQuery,  1, sAuth,    sizeof(sAuth));
		SQL_FetchString(hQuery,  2, sIp,      sizeof(sIp));
		SQL_FetchString(hQuery,  3, sName,    sizeof(sName));
		iStart = SQL_FetchInt(hQuery, 4);
		iTime  = SQL_FetchInt(hQuery, 5);
		SQL_FetchString(hQuery,  6, sReason,  sizeof(sReason));
		iAdmin = SQL_FetchInt(hQuery, 7);
		SQL_FetchString(hQuery,  8, sAdminIp, sizeof(sAdminIp));
		SQL_EscapeString(g_hSQLiteDB, sName,   sEscapedName,   sizeof(sEscapedName));
		SQL_EscapeString(g_hSQLiteDB, sReason, sEscapedReason, sizeof(sEscapedReason));
		if(iStart + iTime * 60 <= GetTime())
		{
			DeleteLocalBan(iType == STEAM_BAN_TYPE ? sAuth : sIp);
			continue;
		}
		
		new Handle:hPack = CreateDataPack();
		WritePackString(hPack, iType == STEAM_BAN_TYPE ? sAuth : sIp);
		
		Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, steam, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																		VALUES      (%i, '%s', '%s', '%s', %i, %i, '%s', %i, '%s', %i)",
																		g_sDatabasePrefix, iType, sAuth, sIp, sEscapedName, iStart, iStart + iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, iAdmin, sAdminIp);
		SQL_TQuery(g_hDatabase, Query_AddedFromQueue, sQuery, hPack);
	}
}

public Action:Timer_ProcessTemp(Handle:timer)
{
	// Delete temporary bans that were added over 5 minutes ago
	decl String:sQuery[128];
	Format(sQuery, sizeof(sQuery), "DELETE FROM sb_bans \
																	WHERE       time + 300 <= %i \
																		AND       queued      = 0",
																	GetTime());
	SQL_FastQuery(g_hSQLiteDB, sQuery);
}


/**
 * Menu Handlers
 */
public MenuHandler_Ban(Handle:topmenu, TopMenuAction:action, TopMenuObject:object_id, param, String:buffer[], maxlength)
{
	if(action      == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "Ban player", param);
	else if(action == TopMenuAction_SelectOption)
		DisplayBanTargetMenu(param);
}

public MenuHandler_Target(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Select)
	{
		decl iTarget, String:sInfo[32];
		GetMenuItem(menu, param2, sInfo, sizeof(sInfo));
		if((iTarget = GetClientOfUserId(StringToInt(sInfo))) == 0)
			PrintToChat(param1, "%s%t", "Player no longer available", SB_PREFIX);
		else if(!CanUserTarget(param1, iTarget))
			PrintToChat(param1, "%s%t", "Unable to target",           SB_PREFIX);
		else
		{
			g_iBanTarget[param1] = iTarget;
			DisplayBanTimeMenu(param1);
		}
	}
}

public MenuHandler_Time(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Select)
	{
		decl String:sInfo[32];
		GetMenuItem(menu, param2, sInfo, sizeof(sInfo));
		g_iBanTime[param1] = StringToInt(sInfo);
		DisplayMenu(g_hReasonMenu, param1, MENU_TIME_FOREVER);
	}
}

public MenuHandler_Reason(Handle:menu, MenuAction:action, param1, param2)
{
	if(action != MenuAction_Select)
		return;
	
	decl String:sInfo[64], String:sKickMessage[128];
	Format(sKickMessage, sizeof(sKickMessage), "%t", "Banned Check Site", g_sWebsite);
	GetMenuItem(menu, param2, sInfo, sizeof(sInfo));
	if(StrEqual(sInfo, "Hacking") && menu == g_hReasonMenu)
	{
		DisplayMenu(g_hHackingMenu, param1, MENU_TIME_FOREVER);
		return;
	}
	if(g_iBanTarget[param1] != -1)
		BanClient(g_iBanTarget[param1], g_iBanTime[param1], BANFLAG_AUTO, sInfo, sKickMessage, "sm_ban", param1);
	
	g_iBanTarget[param1] = -1;
	g_iBanTime[param1]   = -1;
}


/**
 * Query Callbacks
 */
public Query_BanInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAdminIp[16], String:sAuth[20], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sReason[128];
	ResetPack(pack);
	new iAdmin   = ReadPackCell(pack);
	new iTime    = ReadPackCell(pack);
	ReadPackString(pack, sAuth,      sizeof(sAuth));
	ReadPackString(pack, sIp,        sizeof(sIp));
	ReadPackString(pack, sName,      sizeof(sName));
	ReadPackString(pack, sReason,    sizeof(sReason));
	new iAdminId = ReadPackCell(pack);
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	
	InsertLocalBan(STEAM_BAN_TYPE, sAuth, sIp, sName, GetTime(), iTime, sReason, iAdminId, sAdminIp, !!error[0]);
	if(error[0])
	{
		LogError("Failed to insert the ban into the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.", SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",       SB_PREFIX, sAuth);
		return;
	}
}

public Query_BanIpSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAdminIp[16], String:sEscapedReason[256], String:sIp[16], String:sQuery[512], String:sReason[128];
	ResetPack(pack);
	new iAdmin   = ReadPackCell(pack);
	new iTime    = ReadPackCell(pack);
	ReadPackString(pack, sIp,      sizeof(sIp));
	ReadPackString(pack, sReason,  sizeof(sReason));
	new iAdminId = ReadPackCell(pack);
	ReadPackString(pack, sAdminIp, sizeof(sAdminIp));
	
	if(error[0])
	{
		LogError("Failed to retrieve the IP ban from the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",     SB_PREFIX, sIp);
		else
			PrintToServer("%sFailed to ban %s.",           SB_PREFIX, sIp);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%s%s is already banned.", SB_PREFIX, sIp);
		else
			PrintToServer("%s%s is already banned.",       SB_PREFIX, sIp);
		return;
	}
	
	SQL_EscapeString(g_hDatabase, sReason, sEscapedReason, sizeof(sEscapedReason));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      (%i, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', %i, %i, '%s')",
																	g_sDatabasePrefix, IP_BAN_TYPE, sIp, iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, iAdminId, sAdminIp);
	SQL_TQuery(g_hDatabase, Query_BanIpInsert, sQuery, pack, DBPrio_High);
}

public Query_BanIpInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAdminIp[30], String:sIp[16], String:sReason[128];
	ResetPack(pack);
	new iAdmin   = ReadPackCell(pack);
	new iTime    = ReadPackCell(pack);
	ReadPackString(pack, sIp,      sizeof(sIp));
	ReadPackString(pack, sReason,  sizeof(sReason));
	new iAdminId = ReadPackCell(pack);
	ReadPackString(pack, sAdminIp, sizeof(sAdminIp));
	
	InsertLocalBan(IP_BAN_TYPE, "", sIp, "", GetTime(), iTime, sReason, iAdminId, sAdminIp, !!error[0]);
	if(error[0])
	{
		LogError("Failed to insert the IP ban into the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",       SB_PREFIX, sIp);
		else
			PrintToServer("%sFailed to ban %s.",             SB_PREFIX, sIp);
		return;
	}
}

public Query_AddBanSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAdminIp[20], String:sAuth[20], String:sEscapedReason[256], String:sQuery[512], String:sReason[128];
	ResetPack(pack);
	new iAdmin   = ReadPackCell(pack);
	new iTime    = ReadPackCell(pack);
	ReadPackString(pack, sAuth,      sizeof(sAuth));
	ReadPackString(pack, sReason,    sizeof(sReason));
	new iAdminId = ReadPackCell(pack);
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	
	if(error[0])
	{
		LogError("Failed to retrieve the ID ban from the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",     SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",           SB_PREFIX, sAuth);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%s%s is already banned.", SB_PREFIX, sAuth);
		else
			PrintToServer("%s%s is already banned.",       SB_PREFIX, sAuth);
		return;
	}
	
	SQL_EscapeString(g_hDatabase, sReason, sEscapedReason, sizeof(sEscapedReason));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, steam, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      (%i, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', NULLIF(%i, 0), '%s', %i, ' ')",
																	g_sDatabasePrefix, STEAM_BAN_TYPE, sAuth, iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, iAdminId, sAdminIp);
	SQL_TQuery(g_hDatabase, Query_AddBanInsert, sQuery, pack, DBPrio_High);
}

public Query_AddBanInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAdminIp[20], String:sAuth[20], String:sReason[128];
	ResetPack(pack);
	new iAdmin   = ReadPackCell(pack);
	new iTime    = ReadPackCell(pack);
	ReadPackString(pack, sAuth,    sizeof(sAuth));
	ReadPackString(pack, sReason,  sizeof(sReason));
	new iAdminId = ReadPackCell(pack);
	ReadPackString(pack, sAdminIp, sizeof(sAdminIp));
	
	InsertLocalBan(STEAM_BAN_TYPE, sAuth, "", "", GetTime(), iTime, sReason, iAdminId, sAdminIp, !!error[0]);
	if(error[0])
	{
		LogError("Failed to insert the ID ban into the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.", SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",       SB_PREFIX, sAuth);
		return;
	}
}

public Query_UnbanSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sIdentity[20], String:sQuery[512];
	ResetPack(pack);
	new iAdmin = ReadPackCell(pack);
	ReadPackString(pack, sIdentity, sizeof(sIdentity));
	
	if(error[0])
	{
		LogError("Failed to retrieve the ban from the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to unban %s.",          SB_PREFIX, sIdentity);
		else
			PrintToServer("%sFailed to unban %s.",                SB_PREFIX, sIdentity);
		return;
	}
	if(!SQL_GetRowCount(hndl))
	{
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sNo active bans found for %s.", SB_PREFIX, sIdentity);
		else
			PrintToServer("%sNo active bans found for %s.",       SB_PREFIX, sIdentity);
		return;
	}
	
	iAdmin = SQL_FetchInt(hndl, 0);
	if(strncmp(sIdentity, "STEAM_", 6) == 0)
		Format(sQuery, sizeof(sQuery), "UPDATE   %s_bans \
																		SET      unban_admin_id = %i, \
																						 unban_time     = UNIX_TIMESTAMP() \
																		WHERE    type           = %i \
																			AND    steam          REGEXP '^STEAM_[0-9]:%s$' \
																		ORDER BY created DESC \
																		LIMIT    1",
																		g_sDatabasePrefix, iAdmin, STEAM_BAN_TYPE, sIdentity[8]);
	else
		Format(sQuery, sizeof(sQuery), "UPDATE   %s_bans \
																		SET      unban_admin_id = %i, \
																						 unban_time     = UNIX_TIMESTAMP() \
																		WHERE    type           = %i \
																			AND    ip             = '%s' \
																		ORDER BY created DESC \
																		LIMIT    1",
																		g_sDatabasePrefix, iAdmin, IP_BAN_TYPE, sIdentity);
	
	SQL_TQuery(g_hDatabase, Query_UnbanUpdate, sQuery, pack, DBPrio_High);
	
	DeleteLocalBan(sIdentity);
}

public Query_UnbanUpdate(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sIdentity[20];
	ResetPack(pack);
	new iAdmin = ReadPackCell(pack);
	ReadPackString(pack, sIdentity, sizeof(sIdentity));
	
	if(error[0])
	{
		LogError("Failed to unban the ban from the database: %s", error);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to unban %s.",       SB_PREFIX, sIdentity);
		else
			PrintToServer("%sFailed to unban %s.",             SB_PREFIX, sIdentity);
	}
}

public Query_BanVerify(Handle:owner, Handle:hndl, const String:error[], any:client)
{
	if(!client || !IsClientInGame(client))
		return;
	if(error[0])
	{
		LogError("Failed to verify the ban: %s", error);
		g_hPlayerRecheck[client] = CreateTimer(g_fRetryTime, Timer_ClientRecheck, client);
		return;
	}
	if(!SQL_GetRowCount(hndl))
	{
		g_bPlayerStatus[client] = true;
		return;
	}
	
	decl String:sAdminIp[16], String:sAuth[20], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sQuery[512], String:sReason[128];
	GetClientAuthString(client, sAuth, sizeof(sAuth));
	GetClientIP(client,         sIp,   sizeof(sIp));
	GetClientName(client,       sName, sizeof(sName));
	
	SQL_EscapeString(g_hDatabase, sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_blocks (ban_id, name, server_id, time) \
																	VALUES      ((SELECT id FROM %s_bans WHERE ((type = %i AND steam REGEXP '^STEAM_[0-9]:%s$') OR (type = %i AND '%s' REGEXP REPLACE(REPLACE(ip, '.', '\\.') , '.0', '..{1,3}'))) AND unban_admin_id IS NULL ORDER BY created LIMIT 1), '%s', %i, UNIX_TIMESTAMP())",
																	g_sDatabasePrefix, g_sDatabasePrefix, STEAM_BAN_TYPE, sAuth[8], IP_BAN_TYPE, sIp, sEscapedName, g_iServerId);
	SQL_TQuery(g_hDatabase, Query_ErrorCheck, sQuery, client, DBPrio_High);
	
	new iType    = SQL_FetchInt(hndl,  0);
	SQL_FetchString(hndl, 1, sAuth,    sizeof(sAuth));
	SQL_FetchString(hndl, 2, sIp,      sizeof(sIp));
	SQL_FetchString(hndl, 3, sName,    sizeof(sName));
	new iCreated = SQL_FetchInt(hndl,  4);
	new iLength  = SQL_FetchInt(hndl,  5);
	SQL_FetchString(hndl, 6, sReason,  sizeof(sReason));
	new iAdminId = SQL_FetchInt(hndl,  7);
	SQL_FetchString(hndl, 8, sAdminIp, sizeof(sAdminIp));
	PrintBanInformation(client, sAuth, sIp, sName, sReason, iLength);
	
	InsertLocalBan(iType, sAuth, sIp, sName, iCreated, iLength, sReason, iAdminId, sAdminIp);
	KickClient(client, "%t", "Banned Check Site", g_sWebsite);
}

public Query_AddedFromQueue(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	if(error[0])
		return;
	
	decl String:sIdentity[20];
	ResetPack(pack);
	ReadPackString(pack, sIdentity, sizeof(sIdentity));
	
	DeleteLocalBan(sIdentity);
}

public Query_ErrorCheck(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
		LogError("%T (%s)", "Failed to query database", error);
}


/**
 * Stocks
 */
DeleteLocalBan(const String:sIdentity[])
{
	if(!g_hSQLiteDB)
		return;
	
	decl String:sQuery[64];
	Format(sQuery, sizeof(sQuery), "DELETE FROM sb_bans \
																	WHERE       steam REGEXP '^STEAM_[0-9]:%s$' \
																		 OR       '%s'  REGEXP REPLACE(REPLACE(ip, '.', '\\.') , '.0', '..{1,3}')",
																	sIdentity[8], sIdentity);
	SQL_FastQuery(g_hSQLiteDB, sQuery);
}

DisplayBanTargetMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_Target);
	Format(sTitle, sizeof(sTitle), "%T:", "Ban player", client);
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	AddTargetsToMenu2(hMenu, client, COMMAND_FILTER_NO_BOTS|COMMAND_FILTER_CONNECTED);
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

DisplayBanTimeMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_Time);
	Format(sTitle, sizeof(sTitle), "%T:", "Ban player", client);
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	
	decl iFlags, String:sFlags[32], String:sLength[16], String:sName[32];
	for(new i = 0, iSize = GetArraySize(g_hBanTimes); i < iSize; i++)
	{
		GetArrayString(g_hBanTimes,       i, sName,   sizeof(sName));
		GetArrayString(g_hBanTimesFlags,  i, sFlags,  sizeof(sFlags));
		GetArrayString(g_hBanTimesLength, i, sLength, sizeof(sLength));
		iFlags = ReadFlagString(sFlags);
		
		if((GetUserFlagBits(client) & iFlags) == iFlags)
			AddMenuItem(hMenu, sLength, sName);
	}
	
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

GetAdminId(client)
{
	// If admins are enabled, return their admin id, otherwise return 0
	return SB_GetSettingCell("EnableAdmins") ? SB_GetAdminId(client) : 0;
}

InsertLocalBan(iType, const String:sAuth[], const String:sIp[], const String:sName[], iCreated, iLength, const String:sReason[], iAdminId, const String:sAdminIp[], bool:bQueued = false)
{
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256], String:sQuery[512];
	SQL_EscapeString(g_hSQLiteDB, sName,   sEscapedName,   sizeof(sEscapedName));
	SQL_EscapeString(g_hSQLiteDB, sReason, sEscapedReason, sizeof(sEscapedReason));
	
	Format(sQuery, sizeof(sQuery), "INSERT INTO sb_bans (type, steam, ip, name, created, length, reason, admin_id, admin_ip, queued, time) \
																	VALUES      (%i, '%s', '%s', '%s', %i, %i, '%s', '%s', '%s', %i, %i)", 
																	iType, sAuth, sIp, sEscapedName, iCreated, iLength, sEscapedReason, iAdminId, sAdminIp, bQueued ? 1 : 0, GetTime());
	SQL_FastQuery(g_hSQLiteDB, sQuery);
}

PrintBanInformation(iClient, const String:sAuth[], const String:sIp[], const String:sName[], const String:sReason[], iLength)
{
	decl String:sLength[64];
	SecondsToString(sLength, sizeof(sLength), iLength);
	PrintToConsole(iClient, "===============================================");
	PrintToConsole(iClient, "%sYou are banned from this server.", SB_PREFIX);
	PrintToConsole(iClient, "%sYou have %s left on your ban.",    SB_PREFIX, sLength);
	PrintToConsole(iClient, "%sName:       %s",                   SB_PREFIX, sName);
	PrintToConsole(iClient, "%sSteam ID:   %s",                   SB_PREFIX, sAuth);
	PrintToConsole(iClient, "%sIP address: %s",                   SB_PREFIX, sIp);
	PrintToConsole(iClient, "%sReason:     %s",                   SB_PREFIX, sReason);
	PrintToConsole(iClient, "%sYou can protest your ban at %s.",  SB_PREFIX, g_sWebsite);
	PrintToConsole(iClient, "===============================================");
}

SecondsToString(String:sBuffer[], iLength, iSecs, bool:bTextual = true)
{
	if(bTextual)
	{
		decl String:sDesc[6][8] = {"mo",              "wk",             "d",          "hr",    "min", "sec"};
		new  iCount, iDiv[6]    = {60 * 60 * 24 * 30, 60 * 60 * 24 * 7, 60 * 60 * 24, 60 * 60, 60,    1};
		
		for(new i = 0; i < sizeof(iDiv); i++)
		{
			if((iCount = iSecs / iDiv[i]) > 0)
			{
				Format(sBuffer, iLength, "%s%i %s, ", sBuffer, iCount, sDesc[i]);
				iSecs %= iDiv[i];
			}
		}
		strcopy(sBuffer, strlen(sBuffer) - 2, sBuffer);
	}
	else
	{
		new iHours = iSecs  / 60 / 60;
		iSecs     -= iHours * 60 * 60;
		new iMins  = iSecs  / 60;
		iSecs     %= 60;
		Format(sBuffer, iLength, "%i:%i:%i", iHours, iMins, iSecs);
	}
}