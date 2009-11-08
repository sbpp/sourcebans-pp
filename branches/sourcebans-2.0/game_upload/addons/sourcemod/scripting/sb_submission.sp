/**
 * =============================================================================
 * SourceBans Submission Plugin
 *
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */

#pragma semicolon 1

#include <sourcemod>
#include <sourcebans>

#undef REQUIRE_PLUGIN
#include <adminmenu>
#define REQUIRE_PLUGIN

public Plugin:myinfo =
{
	name        = "SourceBans: Submission",
	author      = "InterWave Studios",
	description = "Allows players to submit bans ingame",
	version     = SB_VERSION,
	url         = "http://www.sourcebans.net"
};


/**
 * Globals
 */
enum PlayerData
{
	iBansSubmitted,
	iSubmissionTarget,
	bool:bOwnReason
};

new g_aPlayers[MAXPLAYERS + 1][PlayerData];
new String:g_sTargetsAuth[MAXPLAYERS + 1][32];
new String:g_sTargetsName[MAXPLAYERS + 1][MAX_NAME_LENGTH + 1];
new String:g_sTargetsIP[MAXPLAYERS + 1][16];
new Handle:g_hDatabase;
new Handle:g_hReasonMenu;
new Handle:g_hHackingMenu;
new Handle:g_hTopMenu;
new String:g_sDatabasePrefix[16];
new String:g_sWebsite[256];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	RegConsoleCmd("sm_submitban", Command_SubmitBan, "sm_submitban <#userid|name> [reason]");
	RegConsoleCmd("say", Command_Say);
	RegConsoleCmd("say_team", Command_Say);
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("sb_submission.phrases");
	
	g_hReasonMenu = CreateMenu(MenuHandler_Reason);
	g_hHackingMenu = CreateMenu(MenuHandler_Reason);
}

public OnAdminMenuReady(Handle:topmenu)
{
	if(topmenu != g_hTopMenu)
		g_hTopMenu = topmenu;
}

public OnAllPluginsLoaded()
{
	new Handle:hTopMenu;
	if(LibraryExists("adminmenu") && (hTopMenu = GetAdminTopMenu()))
		OnAdminMenuReady(hTopMenu);
}

public OnLibraryRemoved(const String:name[])
{
	if(StrEqual(name, "adminmenu"))
		g_hTopMenu = INVALID_HANDLE;
}


/**
 * Client Forwards
 */
public OnClientPostAdminCheck(client)
{
	// If it's console or a fake client, or there is no database connection, we can bug out.
	if(!client || IsFakeClient(client) || !g_hDatabase)
		return;
	
	// Get the steamid and format the query.
	decl String:sAuth[20], String:sQuery[128];
	GetClientAuthString(client, sAuth, sizeof(sAuth));
	Format(sQuery, sizeof(sQuery), "SELECT steam FROM %s_submissions WHERE steam REGEXP '^STEAM_[0-9]:%s$'", g_sDatabasePrefix, sAuth[8]);
	
	// Send the query.
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, client);
	WritePackString(hPack, sQuery);
	SQL_TQuery(g_hDatabase, Query_RecieveSubmissions, sQuery, hPack, DBPrio_High);
}

public OnClientDisconnect(client)
{
	// Cleanup the client variables
	g_aPlayers[client][iBansSubmitted] = -1;
	g_aPlayers[client][iSubmissionTarget] = -1;
	// Not going to search to see of the target is currently in the process for a submission
	// This allows us to submit bans even if the person disconnects after the process is started
}


/**
 * SourceBans Forwards
 */
public SB_OnConnect(Handle:database)
{
	g_hDatabase = database;
}

public SB_OnReload()
{
	// Get settings from SourceBans config and store them locally
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SB_GetSettingString("Website",        g_sWebsite,        sizeof(g_sWebsite));
	
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
	CloseHandle(hBanReasons);
	CloseHandle(hHackingReasons);
}


/**
 * Commands
 */
public Action:Command_SubmitBan(client, args)
{
	// Make sure we have arguments, if not, display the player menu and bug out.
	if(!args) 
	{
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> <reason>");
		DisplayTargetMenu(client);
		return Plugin_Handled;
	}
	
	// We were at least sent a target, lets check him
	decl String:sTargetBuffer[128];
	GetCmdArg(1, sTargetBuffer, sizeof(sTargetBuffer));
	new iTarget = FindTarget(client, sTargetBuffer, false, false);
	
	// If it's not a valid target display the player menu and bug out.
	if(iTarget <= 0 || !IsClientInGame(iTarget)) 
	{
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> <reason>");
		DisplayTargetMenu(client);
		return Plugin_Handled;
	}
	
	// If it's a valid target but the player already has bans submitted, tell them and bug out.
	if(g_aPlayers[iTarget][iBansSubmitted])
	{
		decl String:sTargetName[64];
		GetClientName(iTarget, sTargetName, sizeof(sTargetName));
		ReplyToCommand(client, "[SM] %t", "Player already flagged", sTargetName);
		return Plugin_Handled;
	}
	
	// Set the target variables
	AssignTargetInfo(client, iTarget);
	
	// If they have given us a reason submit the ban
	if(args >= 2)
	{
		decl String:sReason[256];
		GetCmdArg(2, sReason, sizeof(sReason));
		SubmitBan(client, iTarget, sReason);
	}
	// If not, display the reason menu
	else 
	{
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> <reason>");
		DisplayMenu(g_hReasonMenu, client, MENU_TIME_FOREVER);
	}
	return Plugin_Handled;
}

public Action:Command_Say(client, args)
{
	// If this client is not typing their own reason to ban someone, ignore
	if(!g_aPlayers[client][bOwnReason])
		return Plugin_Continue;
	
	g_aPlayers[client][bOwnReason] = false;
	
	decl String:sText[192];
	new iStart = 0;
	if(GetCmdArgString(sText, sizeof(sText)) < 1)
		return Plugin_Continue;
	
	if(sText[strlen(sText) - 1] == '"')
	{
		sText[strlen(sText) - 1] = '\0';
		iStart = 1;
	}
	if(StrEqual(sText[iStart], "!noreason"))
	{
		ReplyToCommand(client, "%s%t", SB_PREFIX, "Chat Reason Aborted");
		return Plugin_Handled;
	}
	if(g_aPlayers[client][iSubmissionTarget] != -1)
	{
		SubmitBan(client, g_aPlayers[client][iSubmissionTarget], sText[iStart]);
		return Plugin_Handled;
	}
	return Plugin_Continue;
}


/**
 * Menu Handlers
 */
public MenuHandler_Target(Handle:menu, MenuAction:action, param1, param2)
{
	if(action == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Select)
	{
		decl String:sTargetUserID[10];
		GetMenuItem(menu, param2, sTargetUserID, sizeof(sTargetUserID));
		// Set the target variables
		AssignTargetInfo(param1, GetClientOfUserId(StringToInt(sTargetUserID)));
		DisplayMenu(g_hReasonMenu, param1, MENU_TIME_FOREVER);
	}
}

public MenuHandler_Reason(Handle:menu, MenuAction:action, param1, param2)
{
	if(action == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_Select)
	{
		decl String:sInfo[64];
		GetMenuItem(menu, param2, sInfo, sizeof(sInfo));
		if(StrEqual(sInfo, "Hacking") && menu == g_hReasonMenu)
		{
			DisplayMenu(g_hHackingMenu, param1, MENU_TIME_FOREVER);
			return;
		}
		if(StrEqual(sInfo, "Own Reason"))
		{
			g_aPlayers[param1][bOwnReason] = true;
			PrintToChat(param1, "%s%t", SB_PREFIX, "Chat Reason");
			return;
		}
		if(g_aPlayers[param1][iSubmissionTarget] != -1)
		{
			SubmitBan(param1, g_aPlayers[param1][iSubmissionTarget], sInfo);
		}
	}
}


/**
 * Query Callbacks
 */
public Query_RecieveSubmissions(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// If the client is no longer connected we can bug out.
	new iClient = ReadPackCell(pack);
	if(!IsClientInGame(iClient))
	{
		CloseHandle(pack);
		return;
	}
	
	// Make sure we succeeded.
	if(error[0])
	{
		decl String:sQuery[256];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error: %s", error);
		LogError("Query dump: %s", sQuery);
		CloseHandle(pack);
		return;
	}
	
	// We're done with you now.
	CloseHandle(pack);
	
	// Set the number of submissions 
	g_aPlayers[iClient][iBansSubmitted] = SQL_GetRowCount(hndl);
}


/**
 * Stocks
 */
stock SubmitBan(client, target, const String:reason[])
{
	SB_SubmitBan(client, target, reason);
	
	// Increment the submission array for the target.
	g_aPlayers[target][iBansSubmitted] = 1;
	
	// Blank out the target for this client
	g_aPlayers[client][iSubmissionTarget] = -1;
}

stock DisplayTargetMenu(client)
{
	new Handle:hMenu = CreateMenu(MenuHandler_Target);
	AddTargetsToMenu(hMenu, 0, true, false);
	SetMenuTitle(hMenu, "Select A Player:");
	SetMenuExitBackButton(hMenu, true);
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

stock AssignTargetInfo(client, target)
{
	g_aPlayers[client][iSubmissionTarget] = target;
	GetClientAuthString(target,	g_sTargetsAuth[target],		sizeof(g_sTargetsAuth[]));
	GetClientIP(target,					g_sTargetsIP[target],	   	sizeof(g_sTargetsIP[]));
	GetClientName(target,				g_sTargetsName[target], 	sizeof(g_sTargetsName[]));
}