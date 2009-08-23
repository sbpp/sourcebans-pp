#pragma semicolon 1

#include <sourcemod>
#include <sourcebans>

#undef REQUIRE_PLUGIN
#include <adminmenu>

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
new g_iPlayerBansSubmitted[MAXPLAYERS + 1];
new g_iServerId;
new Handle:g_hDatabase;
new Handle:g_hReasonMenu;
new Handle:g_hTopMenu;
new String:g_sDatabasePrefix[16];
new String:g_sWebsite[256];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	RegConsoleCmd("sb_submitban", Command_SubmitBan, "sb_submitban <#userid|name> [reason]");
	
	LoadTranslations("sb_submission.phrases");
	
	g_hReasonMenu = CreateMenu(MenuHandler_Reason);
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
	SQL_TQuery(g_hDatabase, OnRecieveSubmissions, sQuery, hPack, DBPrio_High);
}

public OnClientDisconnect(client)
{
	// Cleanup the client variables
	g_iPlayerBansSubmitted[client] = 0;
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
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SB_GetSettingString("Website",        g_sWebsite,        sizeof(g_sWebsite));
}


/**
 * Commands
 */
public Action:Command_SubmitBan(client, args)
{
	// Make sure we have arguments, if not, display the player menu and bug out.
	if(!args) 
	{
		ReplyToCommand(client, "Usage: sb_submitban <#userid|name> [reason]");
		DisplayTargetMenu(client);
		return Plugin_Handled;
	}
	
	// We were at least sent a target, lets check him
	decl String:sTargetBuffer[128];
	GetCmdArg(1, sTargetBuffer, sizeof(sTargetBuffer));
	new iTarget = FindTarget(client, sTargetBuffer, true); 
	
	// If it's not a valid target display the player menu and bug out.
	if(iTarget <= 0 || !IsClientInGame(iTarget)) 
	{
		ReplyToCommand(client, "Usage: sb_submitban <#userid|name> [reason]");
		DisplayTargetMenu(client);
		return Plugin_Handled;
	}
	
	// If it's a valid target but the player already has bans submitted, tell them and bug out.
	if(g_iPlayerBansSubmitted[iTarget])
	{
		decl String:sTargetName[64];
		GetClientName(iTarget, sTargetName, sizeof(sTargetName));
		ReplyToCommand(client, "[SM] %t", "Player already flagged", sTargetName);
		return Plugin_Handled;
	}
	
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
		// TODO: Deal with passing the target ID
		ReplyToCommand(client, "Usage: sb_submitban <#userid|name> [reason]");
		DisplayMenu(g_hReasonMenu, client, MENU_TIME_FOREVER);
	}
	return Plugin_Handled;
}


/**
 * Menu Handlers
 */
public MenuHandler_Target(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
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
		// TODO: Deal with passing the target ID
		DisplayMenu(g_hReasonMenu, param1, MENU_TIME_FOREVER);
	}
}

public MenuHandler_Reason(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action == MenuAction_Select)
	{
		decl String:sReason[64];
		GetMenuItem(menu, param2, sReason, sizeof(sReason));
	}
}


/**
 * Query Callbacks
 */
public OnRecieveSubmissions(Handle:owner, Handle:hndl, const String:error[], any:pack)
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
	g_iPlayerBansSubmitted[iClient] = SQL_GetRowCount(hndl);
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
		return;
	}
	
	// We're done with you now.
	CloseHandle(pack);
	
	// Increment the submission array for the target.
	g_iPlayerBansSubmitted[iTarget] = 1;
	
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
	if(!g_hDatabase)
	{
		SB_Connect();
		PrintToChat(iClient, "[SM] %t", "DB Connect Fail");
		return;
	}
	
	// TODO: Match these sizes up with the database structure
	decl String:sClientIp[16], String:sClientName[MAX_NAME_LENGTH + 1], String:sTargetAuth[32], String:sTargetIp[16], String:sTargetName[MAX_NAME_LENGTH + 1], String:sQuery[768];
	decl String:sEscapedClientName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedTargetName[MAX_NAME_LENGTH * 2 + 1], String:sEscapedReason[256];
	
	// Get the targets information
	GetClientAuthString(iTarget, sTargetAuth, sizeof(sTargetAuth));
	GetClientIP(iTarget,         sTargetIp,   sizeof(sTargetIp));
	GetClientName(iTarget,       sTargetName, sizeof(sTargetName));
	
	// Get the clients information
	GetClientIP(iClient,   sClientIp,   sizeof(sClientIp));
	GetClientName(iClient, sClientName, sizeof(sClientName));
	
	// SQL Escape all the information (prepares for query)
	SQL_EscapeString(g_hDatabase, sClientName, sEscapedClientName, sizeof(sEscapedClientName));
	SQL_EscapeString(g_hDatabase, sTargetName, sEscapedTargetName, sizeof(sEscapedTargetName));
	SQL_EscapeString(g_hDatabase, sReason,     sEscapedReason,     sizeof(sEscapedReason));
	
	// Format the query
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_submissions (name, steam, ip, reason, server_id, subname, subip) VALUES ('%s', '%s', '%s', '%s', %i, '%s', '%s')",
																	g_sDatabasePrefix, sEscapedTargetName, sTargetAuth, sTargetIp, sEscapedReason, g_iServerId, sEscapedClientName, sClientIp);
	
	// Send the query.
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, iClient);
	WritePackCell(hPack, iTarget);
	WritePackString(hPack, sQuery);
	SQL_TQuery(g_hDatabase, Query_Submission, sQuery, hPack);
}

stock Handle:DisplayTargetMenu(client)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_Target);
	Format(sTitle, sizeof(sTitle), "%t:", "Select player");
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	AddTargetsToMenu2(hMenu, client, COMMAND_FILTER_NO_BOTS|COMMAND_FILTER_CONNECTED);
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}