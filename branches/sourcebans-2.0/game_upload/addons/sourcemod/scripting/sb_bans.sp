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

#include <sourcebans>

#undef REQUIRE_PLUGIN
#include <adminmenu>
#include <dbi>

#define STEAM_BAN_TYPE	0
#define IP_BAN_TYPE			1

public Plugin:myinfo =
{
	name        = "SourceBans: Bans",
	author      = "InterWave Studios",
	description = "Advanced ban management for the Source engine",
	version     = SB_VERSION,
	url         = "http://www.sourcebans.net"
};

new g_iBanTarget[MAXPLAYERS + 1];
new g_iBanTime[MAXPLAYERS + 1];
new g_iProcessQueueTime;
new g_iServerId;
new bool:g_bEnableAddBan;
new bool:g_bEnableUnban;
new bool:g_bLocalBackup;
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

public OnPluginStart()
{
	RegAdminCmd("sm_ban",    Command_Ban,    ADMFLAG_BAN,   "sm_ban <#userid|name> <minutes|0> [reason]");
	RegAdminCmd("sm_unban",  Command_Unban,  ADMFLAG_UNBAN, "sm_unban <steamid|ip>");
	RegAdminCmd("sm_addban", Command_AddBan, ADMFLAG_RCON,  "sm_addban <time> <steamid> [reason]");
	RegAdminCmd("sm_banip",  Command_BanIp,  ADMFLAG_BAN,   "sm_banip <ip|#userid|name> <time> [reason]");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("basebans.phrases");
	
	// Connect to SQLite database
	SQL_TConnect(OnConnect, "storage-local");
	
	g_hHackingMenu = CreateMenu(MenuHandler_Reason);
	g_hReasonMenu  = CreateMenu(MenuHandler_Reason);
	
	/* Account for late loading */
	new Handle:hTopMenu;
	if(LibraryExists("adminmenu") && (hTopMenu = GetAdminTopMenu()))
		OnAdminMenuReady(hTopMenu);
}

public OnAdminMenuReady(Handle:topmenu)
{
	/* Block us from being called twice */
	if(topmenu == g_hTopMenu)
		return;
	
	/* Save the Handle */
	g_hTopMenu = topmenu;
	
	/* Find the "Player Commands" category */
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
	decl String:sNewFile[PLATFORM_MAX_PATH], String:sOldFile[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, sOldFile, sizeof(sOldFile), "plugins/basebans.smx");
	BuildPath(Path_SM, sNewFile, sizeof(sNewFile), "plugins/disabled/basebans.smx");
	
	// Check if plugins/basebans.smx exists, and if not, don't continue
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

public OnClientAuthorized(client, const String:auth[])
{
	if((!g_hDatabase && !g_bLocalBackup) || StrContains("BOT STEAM_ID_LAN", auth) != -1)
	{
		g_bPlayerStatus[client] = true;
		return;
	}
	
	decl String:sIp[16], String:sQuery[256];
	GetClientIP(client, sIp, sizeof(sIp));
	// Format client IP for range matching
	ReplaceString(sIp, sizeof(sIp), ".",  "\\.");
	ReplaceString(sIp, sizeof(sIp), ".0", "..{1,3}");
	
	if(g_hDatabase)
	{
		Format(sQuery, sizeof(sQuery), "SELECT steam, ip, name, reason, ends - created \
																		FROM   %s_bans \
																		WHERE  ((type = %i AND steam REGEXP '^STEAM_[0-9]:%s$') OR (type = %i AND ip REGEXP '^%s$')) \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, STEAM_BAN_TYPE, auth[8], IP_BAN_TYPE, sIp);
		SQL_TQuery(g_hDatabase, Query_BanVerify, sQuery, client, DBPrio_High);
	}
	else if(g_bLocalBackup)
	{
		Format(sQuery, sizeof(sQuery), "SELECT type, steam, ip \
																		FROM   sb_bans \
																		WHERE  ((type = %i AND steam REGEXP '^STEAM_[0-9]:%s$') OR (type = %i AND ip REGEXP '^%s$'))",
																		STEAM_BAN_TYPE, auth[8], IP_BAN_TYPE, sIp);
		SQL_TQuery(g_hSQLiteDB, Query_BanVerify, sQuery, client, DBPrio_High);
	}
}

public OnClientDisconnect(client)
{
	if(g_hPlayerRecheck[client])
	{
		KillTimer(g_hPlayerRecheck[client]);
		g_hPlayerRecheck[client] = INVALID_HANDLE;
	}
}

public bool:OnClientConnect(client, String:rejectmsg[], maxlen)
{
	g_bPlayerStatus[client] = false;
	return true;
}

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

public Action:OnBanClient(client, time, flags, const String:reason[], const String:kick_message[], const String:command[], any:admin)
{
	decl String:sAuth[20], String:sAdminAuth[20], String:sAdminIp[16], String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sQuery[512];
	new Handle:hPack = CreateDataPack();
	if(!admin)
	{
		strcopy(sAdminAuth, sizeof(sAdminAuth), "STEAM_ID_SERVER");
		strcopy(sAdminIp,   sizeof(sAdminIp),   g_sServerIp);
	}
	else
	{
		GetClientAuthString(admin, sAdminAuth, sizeof(sAdminAuth));
		GetClientIP(admin,         sAdminIp,   sizeof(sAdminIp));
	}
	
	GetClientAuthString(client, sAuth, sizeof(sAuth));
	GetClientIP(client,         sIp,   sizeof(sIp));
	GetClientName(client,       sName, sizeof(sName));
	
	WritePackCell(hPack,   admin);
	WritePackCell(hPack,   time);
	WritePackString(hPack, sAuth);
	WritePackString(hPack, sIp);
	WritePackString(hPack, sName);
	WritePackString(hPack, reason);
	WritePackString(hPack, sAdminAuth);
	WritePackString(hPack, sAdminIp);
	
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
	
	if(!g_hDatabase)
	{
		InsertTempBan(STEAM_BAN_TYPE, sAuth, sIp, sName, time, reason, sAdminAuth, sAdminIp);
		return Plugin_Handled;
	}
	
	SQL_EscapeString(g_hDatabase, sName,  sEscapedName,   sizeof(sEscapedName));
	SQL_EscapeString(g_hDatabase, reason, sEscapedReason, sizeof(sEscapedReason));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (steam, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      ('%s', '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', %i, (SELECT id FROM %s_admins WHERE identity REGEXP '^STEAM_[0-9]:%s$'), '%s')",
																	g_sDatabasePrefix, sAuth, sIp, sEscapedName, time * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, sAdminAuth[8], sAdminIp);
	SQL_TQuery(g_hDatabase, Query_BanInsert, sQuery, hPack, DBPrio_High);
	
	LogAction(admin,
						client,
						"\"%L\" banned \"%L\" (minutes \"%i\") (reason \"%s\")",
						admin,
						client,
						time,
						reason);
	
	return Plugin_Handled;
}

public Action:OnBanIdentity(const String:identity[], time, flags, const String:reason[], const String:command[], any:admin)
{
	decl String:sAdminAuth[20], String:sAdminIp[16], String:sQuery[140], String:sType[2];
	new bool:bSteam = (strncmp(identity, "STEAM_", 6) == 0), Handle:hPack = CreateDataPack();
	if(!admin)
	{
		strcopy(sAdminAuth, sizeof(sAdminAuth), "STEAM_ID_SERVER");
		strcopy(sAdminIp,   sizeof(sAdminIp),   g_sServerIp);
	}
	else
	{
		GetClientAuthString(admin, sAdminAuth, sizeof(sAdminAuth));
		GetClientIP(admin,         sAdminIp,   sizeof(sAdminIp));
	}
	
	WritePackCell(hPack,   admin);
	WritePackCell(hPack,   time);
	WritePackString(hPack, identity);
	WritePackString(hPack, reason);
	WritePackString(hPack, sAdminAuth);
	WritePackString(hPack, sAdminIp);
	
	if(flags      & BANFLAG_AUTHID || ((flags & BANFLAG_AUTO) && bSteam))
	{
		sType = "d";
		
		Format(sQuery, sizeof(sQuery), "SELECT id \
																		FROM   %s_bans \
																		WHERE  type   = %i \
																		  AND  steam REGEXP '^STEAM_[0-9]:%s$' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, STEAM_BAN_TYPE, identity[8]);
		SQL_TQuery(g_hDatabase, Query_AddBanSelect, sQuery, hPack, DBPrio_High);
	}
	else if(flags & BANFLAG_IP     || ((flags & BANFLAG_AUTO) && !bSteam))
	{
		sType = "p";
		
		Format(sQuery, sizeof(sQuery), "SELECT id \
																		FROM   %s_bans \
																		WHERE  type = %i \
																		  AND  ip   = '%s' \
																			AND  (ends - created = 0 OR ends > UNIX_TIMESTAMP()) \
																			AND  unban_admin_id IS NULL",
																		g_sDatabasePrefix, IP_BAN_TYPE, identity);
		SQL_TQuery(g_hDatabase, Query_BanIpSelect,  sQuery, hPack, DBPrio_High);
	}
	
	LogAction(admin,
						-1,
						"\"%L\" added ban (minutes \"%i\") (i%s \"%s\") (reason \"%s\")",
						admin,
						time,
						sType,
						identity,
						reason);
	
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
	
	LogAction(admin,
						-1,
						"\"%L\" removed ban (filter \"%s\")",
						admin,
						identity);
	
	return Plugin_Handled;
}

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
	SQL_TQuery(g_hSQLiteDB, Query_ProcessQueue, "SELECT type, steam, ip, name, created, length, reason, admin_id, admin_ip \
																							 FROM   sb_queue");
}

public MenuHandler_Ban(Handle:topmenu, TopMenuAction:action, TopMenuObject:object_id, param, String:buffer[], maxlength)
{
	if(action      == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "Ban player", param);
	else if(action == TopMenuAction_SelectOption)
		DisplayBanTargetMenu(param);
}

public MenuHandler_BanTarget(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
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

public MenuHandler_BanTime(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
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
	if(!strcmp("Hacking", sInfo) && menu == g_hReasonMenu)
	{
		DisplayMenu(g_hHackingMenu, param1, MENU_TIME_FOREVER);
		return;
	}
	if(g_iBanTarget[param1] != -1)
		BanClient(g_iBanTarget[param1], g_iBanTime[param1], BANFLAG_AUTO, sInfo, sKickMessage, "sm_ban", param1);
	
	g_iBanTarget[param1] = -1;
	g_iBanTime[param1]   = -1;
}

public Query_BanInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, iTime, String:sAuth[20], String:sAdminAuth[20], String:sAdminIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sIp[16], String:sReason[128];
	ResetPack(pack);
	iAdmin  = ReadPackCell(pack);
	iTime   = ReadPackCell(pack);
	ReadPackString(pack, sAuth,      sizeof(sAuth));
	ReadPackString(pack, sIp,        sizeof(sIp));
	ReadPackString(pack, sName,      sizeof(sName));
	ReadPackString(pack, sReason,    sizeof(sReason));
	ReadPackString(pack, sAdminAuth, sizeof(sAdminAuth));
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	
	if(error[0])
	{
		LogError("Failed to insert the ban into the database: %s", error);
		InsertTempBan(STEAM_BAN_TYPE, sAuth, sIp, sName, iTime, sReason, sAdminAuth, sAdminIp);
		
		if(iAdmin    && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.", SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",       SB_PREFIX, sAuth);
		return;
	}
	if(g_bLocalBackup)
		InsertLocalBan(STEAM_BAN_TYPE, sAuth, sIp);
}

public Query_BanIpSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, iTime, String:sAdminAuth[20], String:sAdminIp[16], String:sEscapedReason[256], String:sIp[16], String:sQuery[512], String:sReason[128];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
	iTime  = ReadPackCell(pack);
	ReadPackString(pack, sIp,        sizeof(sIp));
	ReadPackString(pack, sReason,    sizeof(sReason));
	ReadPackString(pack, sAdminAuth, sizeof(sAdminAuth));
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	SQL_EscapeString(g_hDatabase, sReason, sEscapedReason, sizeof(sEscapedReason));
	
	if(error[0])
	{
		LogError("Failed to retrieve the IP ban from the database: %s", error);
		
		if(iAdmin    && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",     SB_PREFIX, sIp);
		else
			PrintToServer("%sFailed to ban %s.",           SB_PREFIX, sIp);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		if(iAdmin    && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%s%s is already banned.", SB_PREFIX, sIp);
		else
			PrintToServer("%s%s is already banned.",       SB_PREFIX, sIp);
		return;
	}
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      (%i, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', %i, (SELECT id FROM %s_admins WHERE identity REGEXP '^STEAM_[0-9]:%s$'), '%s')",
																	g_sDatabasePrefix, IP_BAN_TYPE, sIp, iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, sAdminAuth[8], sAdminIp);
	SQL_TQuery(g_hDatabase, Query_BanIpInsert, sQuery, pack, DBPrio_High);
}

public Query_BanIpInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, iTime, String:sAdminAuth[30], String:sAdminIp[30], String:sIp[16], String:sReason[64];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
	iTime  = ReadPackCell(pack);
	ReadPackString(pack, sIp,        sizeof(sIp));
	ReadPackString(pack, sReason,    sizeof(sReason));
	ReadPackString(pack, sAdminAuth, sizeof(sAdminAuth));
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	
	if(error[0])
	{
		LogError("Failed to insert the IP ban into the database: %s", error);
		InsertTempBan(IP_BAN_TYPE, "", sIp, "", iTime, sReason, sAdminAuth, sAdminIp);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",       SB_PREFIX, sIp);
		else
			PrintToServer("%sFailed to ban %s.",             SB_PREFIX, sIp);
		return;
	}
	if(g_bLocalBackup)
		InsertLocalBan(IP_BAN_TYPE, "", sIp);
}

public Query_AddBanSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, iTime, String:sAdminAuth[20], String:sAdminIp[20], String:sAuth[20], String:sEscapedReason[256], String:sQuery[512], String:sReason[128];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
	iTime  = ReadPackCell(pack);
	ReadPackString(pack, sAuth,      sizeof(sAuth));
	ReadPackString(pack, sReason,    sizeof(sReason));
	ReadPackString(pack, sAdminAuth, sizeof(sAdminAuth));
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	SQL_EscapeString(g_hDatabase, sReason, sEscapedReason, sizeof(sEscapedReason));
	
	if(error[0])
	{
		LogError("Failed to retrieve the ID ban from the database: %s", error);
		
		if(iAdmin    && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",     SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",           SB_PREFIX, sAuth);
		return;
	}
	if(SQL_GetRowCount(hndl))
	{
		if(iAdmin    && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%s%s is already banned.", SB_PREFIX, sAuth);
		else
			PrintToServer("%s%s is already banned.",       SB_PREFIX, sAuth);
		return;
	}
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, steam, name, created, ends, reason, server_id, admin_id, admin_ip) \
																	VALUES      (%i, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, '%s', (SELECT id FROM %s_admins WHERE identity REGEXP '^STEAM_[0-9]:%s$'), '%s', %i, ' ')",
																	g_sDatabasePrefix, STEAM_BAN_TYPE, sAuth, iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, sAdminAuth[8], sAdminIp);
	SQL_TQuery(g_hDatabase, Query_AddBanInsert, sQuery, pack, DBPrio_High);
}

public Query_AddBanInsert(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, iTime, String:sAdminAuth[20], String:sAdminIp[20], String:sAuth[20], String:sReason[64];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
	iTime  = ReadPackCell(pack);
	ReadPackString(pack, sAuth,      sizeof(sAuth));
	ReadPackString(pack, sReason,    sizeof(sReason));
	ReadPackString(pack, sAdminAuth, sizeof(sAdminAuth));
	ReadPackString(pack, sAdminIp,   sizeof(sAdminIp));
	
	if(error[0])
	{
		LogError("Failed to insert the ID ban into the database: %s", error);
		InsertTempBan(STEAM_BAN_TYPE, sAuth, "", "", iTime, sReason, sAdminAuth, sAdminIp);
		
		if(iAdmin && IsClientInGame(iAdmin))
			PrintToChat(iAdmin, "%sFailed to ban %s.",       SB_PREFIX, sAuth);
		else
			PrintToServer("%sFailed to ban %s.",             SB_PREFIX, sAuth);
		return;
	}
	if(g_bLocalBackup)
		InsertLocalBan(STEAM_BAN_TYPE, sAuth, "");
}

public Query_UnbanSelect(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, String:sIdentity[20], String:sQuery[512];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
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
	
	if(g_bLocalBackup)
	{
		if(strncmp(sIdentity, "STEAM_", 6) == 0)
			Format(sQuery, sizeof(sQuery), "DELETE FROM sb_bans \
																			WHERE       type  = %i \
																				AND       steam = '%s'",
																			STEAM_BAN_TYPE, sIdentity);
		else
			Format(sQuery, sizeof(sQuery), "DELETE FROM sb_bans \
																			WHERE       type = %i \
																				AND       ip   = '%s'",
																			IP_BAN_TYPE, sIdentity);
		
		SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, sQuery, pack, DBPrio_High);
	}
}

public Query_UnbanUpdate(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl iAdmin, String:sIdentity[20];
	ResetPack(pack);
	iAdmin = ReadPackCell(pack);
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
	decl String:sAuth[20], String:sIp[16], String:sName[MAX_NAME_LENGTH + 1], String:sReason[128];
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
	
	GetClientAuthString(client, sAuth, sizeof(sAuth));
	GetClientIP(client,         sIp,   sizeof(sIp));
	GetClientName(client,       sName, sizeof(sName));
	// Format client IP for range matching
	ReplaceString(sIp, sizeof(sIp), ".",  "\\.");
	ReplaceString(sIp, sizeof(sIp), ".0", "..{1,3}");
	
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sLength[64], String:sQuery[512];
	SQL_EscapeString(g_hDatabase, sName, sEscapedName, sizeof(sEscapedName));
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_blocks (ban_id, name, server_id, time) \
																	VALUES      ((SELECT id FROM %s_bans WHERE ((type = %i AND steam REGEXP '^STEAM_[0-9]:%s$') OR (type = %i AND ip REGEXP '^%s$')) AND unban_admin_id IS NULL ORDER BY created LIMIT 1), '%s', %i, UNIX_TIMESTAMP())",
																	g_sDatabasePrefix, g_sDatabasePrefix, STEAM_BAN_TYPE, sAuth[8], IP_BAN_TYPE, sIp, sEscapedName, g_iServerId);
	SQL_TQuery(g_hDatabase, Query_ErrorCheck, sQuery, client, DBPrio_High);
	
	SQL_FetchString(hndl, 0, sAuth,   sizeof(sAuth));
	SQL_FetchString(hndl, 1, sIp,     sizeof(sIp));
	SQL_FetchString(hndl, 2, sName,   sizeof(sName));
	SQL_FetchString(hndl, 3, sReason, sizeof(sReason));
	SecondsToString(sLength, sizeof(sLength), SQL_FetchInt(hndl, 4));
	PrintToConsole(client, "===============================================");
	PrintToConsole(client, "%sYou are banned from this server.", SB_PREFIX);
	PrintToConsole(client, "%sYou have %s left on your ban.",    SB_PREFIX, sLength);
	PrintToConsole(client, "%sName:       %s",                   SB_PREFIX, sName);
	PrintToConsole(client, "%sSteam ID:   %s",                   SB_PREFIX, sAuth);
	PrintToConsole(client, "%sIP address: %s",                   SB_PREFIX, sIp);
	PrintToConsole(client, "%sReason:     %s",                   SB_PREFIX, sReason);
	PrintToConsole(client, "%sYou can protest your ban at %s.",  SB_PREFIX, g_sWebsite);
	PrintToConsole(client, "===============================================");
	
	KickClient(client, "%t", "Banned Check Site", g_sWebsite);
}

public Query_ProcessQueue(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl iAdmin, iStart, iTime, iType, String:sAuth[20], String:sReason[128], String:sName[MAX_NAME_LENGTH + 1], String:sIp[16], String:sAdminIp[16], String:sQuery[768];
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256];
	while(hndl && SQL_FetchRow(hndl))
	{
		// SELECT type, steam, ip, name, created, length, reason, admin_id, admin_ip FROM sb_queue
		iType  = SQL_FetchInt(hndl, 0);
		SQL_FetchString(hndl,  1, sAuth, sizeof(sAuth));
		SQL_FetchString(hndl,  2, sIp,        sizeof(sIp));
		SQL_FetchString(hndl,  3, sName,      sizeof(sName));
		iStart = SQL_FetchInt(hndl, 4);
		iTime  = SQL_FetchInt(hndl, 5);
		SQL_FetchString(hndl,  6, sReason,    sizeof(sReason));
		iAdmin = SQL_FetchInt(hndl, 7);
		SQL_FetchString(hndl,  8, sAdminIp,   sizeof(sAdminIp));
		SQL_EscapeString(hndl, sName,   sEscapedName,   sizeof(sEscapedName));
		SQL_EscapeString(hndl, sReason, sEscapedReason, sizeof(sEscapedReason));
		if(iStart + iTime * 60 > GetTime())
		{
			Format(sQuery, sizeof(sQuery), "INSERT INTO %s_bans (type, steam, ip, name, created, ends, reason, server_id, admin_id, admin_ip) \
																			VALUES      (%i, '%s', '%s', '%s', %i, %i, '%s', %i, '%s', %i)",
																			g_sDatabasePrefix, iType, sAuth, sIp, sEscapedName, iStart, iStart + iTime * 60, sEscapedReason, g_iServerId, g_sDatabasePrefix, iAdmin, sAdminIp);
			
			new Handle:hPack = CreateDataPack();
			WritePackString(hPack, sAuth);
			
			SQL_TQuery(g_hDatabase, Query_AddedFromSQLite, sQuery, hPack);
		}
		else
		{
			Format(sQuery, sizeof(sQuery), "DELETE FROM sb_queue \
																			WHERE       steam = '%s'",
																			sAuth);
			SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, sQuery);
		}
	}
}

public Query_AddedFromSQLite(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	decl String:sAuth[20], String:sQuery[64];
	ResetPack(pack);
	ReadPackString(pack, sAuth, sizeof(sAuth));
	if(error[0])
		ServerCommand("banid %i %s", g_iProcessQueueTime, sAuth);
	else
	{
		ServerCommand("removeid %s", sAuth);
		Format(sQuery, sizeof(sQuery), "DELETE FROM sb_queue \
																		WHERE       steam = '%s'",
																		sAuth);
		SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, sQuery);
	}
	CloseHandle(pack);
}

public Query_ErrorCheck(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
		LogError("%T: %s", "Failed to query database", error);
}

public OnConnect(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	g_hSQLiteDB = hndl;
	
	if(error[0])
	{
		LogError("%T (%s)", "Could not connect to database", LANG_SERVER, error);
		return;
	}
	
	SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, "CREATE TABLE IF NOT EXISTS sb_bans (type INTEGER, steam TEXT PRIMARY KEY ON CONFLICT REPLACE, ip TEXT)");
	SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, "CREATE TABLE IF NOT EXISTS sb_queue (type INTEGER, steam TEXT PRIMARY KEY ON CONFLICT REPLACE, ip TEXT, name TEXT, created INTEGER, length INTEGER, reason TEXT, admin_id TEXT, admin_ip TEXT)");
}

public SB_OnReload()
{
	// Get settings from SourceBans config and store them locally
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SB_GetSettingString("ServerIP",       g_sServerIp,       sizeof(g_sServerIp));
	SB_GetSettingString("Website",        g_sWebsite,        sizeof(g_sWebsite));
	g_bEnableAddBan     = SB_GetSettingCell("Addban")      == 1;
	g_bEnableUnban      = SB_GetSettingCell("Unban")       == 1;
	g_bLocalBackup      = SB_GetSettingCell("LocalBackup") == 1;
	g_iProcessQueueTime = SB_GetSettingCell("ProcessQueueTime");
	g_iServerId         = SB_GetSettingCell("ServerID");
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

DisplayBanTargetMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_BanTarget);
	Format(sTitle, sizeof(sTitle), "%T:", "Ban player", client);
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	AddTargetsToMenu2(hMenu, client, COMMAND_FILTER_NO_BOTS|COMMAND_FILTER_CONNECTED);
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

DisplayBanTimeMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_BanTime);
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

stock InsertLocalBan(iType, const String:sAuth[], const String:sIp[])
{
	decl String:sQuery[128];
	Format(sQuery, sizeof(sQuery), "INSERT INTO sb_bans VALUES (%i, '%s', '%s')", 
																	iType, sAuth, sIp);
	SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, sQuery);
}

stock InsertTempBan(iType, const String:sAuth[], const String:sIp[], const String:sName[], iTime, const String:sReason[], const String:sAdminAuth[], const String:sAdminIp[])
{
	decl String:sEscapedName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256], String:sQuery[512];
	ServerCommand("banid %i %s", g_iProcessQueueTime, sAuth);
	SQL_EscapeString(g_hSQLiteDB, sName,   sEscapedName,   sizeof(sEscapedName));
	SQL_EscapeString(g_hSQLiteDB, sReason, sEscapedReason, sizeof(sEscapedReason));
	Format(sQuery, sizeof(sQuery), "INSERT INTO sb_queue VALUES (%i, '%s', %i, %i, '%s', '%s', '%s', '%s', '%s')", 
																	iType, sAuth, sIp, sEscapedName, GetTime(), iTime, sEscapedReason, sAdminAuth, sAdminIp);
	SQL_TQuery(g_hSQLiteDB, Query_ErrorCheck, sQuery);
}

stock SecondsToString(String:sBuffer[], iLength, iSecs, bool:bTextual = true)
{
	if(bTextual)
	{
		decl String:sDesc[6][8] = {"months", "weeks", "days", "hours", "minutes", "seconds"};
		new  iCount, iDiv[6]    = {60 * 60 * 24 * 30, 60 * 60 * 24 * 7, 60 * 60 * 24, 60 * 60, 60, 1};
		
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