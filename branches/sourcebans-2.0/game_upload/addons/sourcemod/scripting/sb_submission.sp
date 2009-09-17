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
 * @version $Id: sourcebans.sp 178 2008-12-01 15:10:00Z tsunami $
 * =============================================================================
 */

/**
  Table structure for table 'sb_submissions'
--------------------------------------------------------
  {prefix}_submissions (
  id mediumint(8) unsigned NOT NULL auto_increment,
  name varchar(64) NOT NULL,
  steam varchar(32) default NULL,
  ip varchar(15) default NULL,
  reason varchar(255) NOT NULL,
  server_id smallint(5) unsigned NOT NULL,
  subname varchar(64) NOT NULL,
  subemail varchar(128) NOT NULL,
  subip varchar(15) NOT NULL,
  archived tinyint(1) NOT NULL default '0',
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY server_id (server_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
--------------------------------------------------------
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
new g_iServerId;
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
	g_iServerId = SB_GetSettingCell("ServerID");
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
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> [reason]");
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
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> [reason]");
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
	
	// If they have given us a reason prepare the submission
	if(args >= 2)
	{
		decl String:sReasonBuffer[128];
		GetCmdArg(2, sReasonBuffer, sizeof(sReasonBuffer));
		PrepareSubmittal(client, iTarget, sReasonBuffer);
	}
	// If not, display the reason menu
	else 
	{
		ReplyToCommand(client, "Usage: sm_submitban <#userid|name> [reason]");
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
		PrepareSubmittal(client, g_aPlayers[client][iSubmissionTarget], sText[iStart]);
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
			PrepareSubmittal(param1, g_aPlayers[param1][iSubmissionTarget], sInfo);
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

public Query_Submission(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Make sure the query worked
	new iClient = ReadPackCell(pack), iTarget = ReadPackCell(pack);
	if(error[0]) 
	{
		decl String:sQuery[256];
		ReadPackString(pack, sQuery, sizeof(sQuery));
		LogError("SQL error: %s", error);
		LogError("Query dump: %s", sQuery);
		if(IsClientInGame(iClient))
			PrintToChat(iClient, "[SM] %t", "Submission failed", g_sWebsite);
		// We're done with you now.
		CloseHandle(pack);
		return;
	}
	
	// We're done with you now.
	CloseHandle(pack);
	
	// Increment the submission array for the target.
	g_aPlayers[iTarget][iBansSubmitted] = 1;
	
	// Blank out the target for this client
	g_aPlayers[iClient][iSubmissionTarget] = -1;
	// Blank out the target's saved info
	//Format(g_sTargetsAuth[iTarget], sizeof(g_sTargetsAuth[iTarget]), " ");
	//Format(g_sTargetsName[iTarget], sizeof(g_sTargetsName[iTarget]), " ");
	//Format(g_sTargetsIP[iTarget], sizeof(g_sTargetsIP[iTarget]), " ");
	
	// Report the results
	if(!IsClientInGame(iClient))
		return;
	
	PrintToChat(iClient, "[SM] %t", "Submission succeeded");
	PrintToChat(iClient, "[SM] %t", "Upload demo", g_sWebsite);
}


/**
 * Stocks
 */
stock PrepareSubmittal(iClient, iTarget, const String:sReason[])
{
	// Connect to the database
	if(g_hDatabase == INVALID_HANDLE)
	{
		SB_Connect();
		PrintToChat(iClient, "[SM] %t", "DB Connect Fail");
		return;
	}
	
	// TODO: Match these sizes up with the database structure
	decl String:sClientIp[16], String:sClientName[MAX_NAME_LENGTH + 1], String:sQuery[768];
	decl String:sEscapedClientName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedTargetName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256];
	
	// Get the clients information
	GetClientIP(iClient,   sClientIp,   sizeof(sClientIp));
	GetClientName(iClient, sClientName, sizeof(sClientName));
	
	// SQL Escape all the information (prepares for query)
	SQL_EscapeString(g_hDatabase, sClientName, sEscapedClientName, sizeof(sEscapedClientName));
	SQL_EscapeString(g_hDatabase, g_sTargetsName[iTarget], sEscapedTargetName, sizeof(sEscapedTargetName));
	SQL_EscapeString(g_hDatabase, sReason,     sEscapedReason,     sizeof(sEscapedReason));
	
	// Format the query
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_submissions (name, steam, ip, reason, server_id, subname, subip) VALUES ('%s', '%s', '%s', '%s', %i, '%s', '%s')",
																	g_sDatabasePrefix, sEscapedTargetName, g_sTargetsAuth[iTarget], g_sTargetsIP[iTarget], sEscapedReason, g_iServerId, sEscapedClientName, sClientIp);
	
	// Send the query.
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, iClient);
	WritePackCell(hPack, iTarget);
	WritePackString(hPack, sQuery);
	SQL_TQuery(g_hDatabase, Query_Submission, sQuery, hPack);
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
	GetClientAuthString(target, g_sTargetsAuth[client], sizeof(g_sTargetsAuth));
	GetClientIP(target,         g_sTargetsIP[client],   sizeof(g_sTargetsIP));
	GetClientName(target,       g_sTargetsName[client], sizeof(g_sTargetsName));
}

/**
Could not get this to work
stock DisplayTargetMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_Target);
	Format(sTitle, sizeof(sTitle), "%T:", "Select player", client);
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	AddTargetsToMenu2(hMenu, client, COMMAND_FILTER_NO_BOTS|COMMAND_FILTER_CONNECTED);
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}
*/
