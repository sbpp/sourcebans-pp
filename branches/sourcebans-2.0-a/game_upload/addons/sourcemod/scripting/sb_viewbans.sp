/**
 * =============================================================================
 * SourceBans View Previous Bans Plugin
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

public Plugin:myinfo =
{
	name     	= "SourceBans: View Bans",
	author   	= "InterWave Studios",
	description	= "Allows admins to view bans ingame",
	version   	= SB_VERSION,
	url      	= "http://www.sourcebans.net"
};


/**
 * Globals
 */
new g_iPlayerBans[MAXPLAYERS + 1] = { -1, ... };
new Handle:g_hDatabase;
new Handle:g_hPlayerResults[MAXPLAYERS + 1] = { INVALID_HANDLE, ... };
new Handle:g_hTopMenu;
new String:g_sDatabasePrefix[16];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	RegAdminCmd("sb_viewbans", Command_ViewBans, ADMFLAG_KICK, "Usage: sm_viewbans <#userid|name>");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("sb_viewbans.phrases");
}

public OnAdminMenuReady(Handle:topmenu)
{
	// Block us from being called twice
	if(topmenu == g_hTopMenu)
		return;
	
	// Save the handle
	g_hTopMenu = topmenu;
	
	// Find the "Player Commands" category
	new TopMenuObject:iPlayerCommands = FindTopMenuCategory(g_hTopMenu, ADMINMENU_PLAYERCOMMANDS);
	if(iPlayerCommands)
		AddToTopMenu(g_hTopMenu,
			"sb_viewbans",
			TopMenuObject_Item,
			MenuHandler_AdminMenu_ViewBans,
			iPlayerCommands,
			"sb_viewbans",
			ADMFLAG_KICK);
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
	// If its a fake client or 0 we can bug out.
	if(!client || IsFakeClient(client))
		return;
	
	// Request the ban information
	RequestBanInformation(client, true);	
}

public OnClientDisconnect(client)
{
	// Cleanup the client variables
	if(g_hPlayerResults[client] != INVALID_HANDLE)
	{
		CloseHandle(g_hPlayerResults[client]);
		g_hPlayerResults[client] = INVALID_HANDLE;
	}
	g_iPlayerBans[client] = -1;
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
	SB_GetSettingString("DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
}


/**
 * Commands
 */
public Action:Command_ViewBans(client, args)
{
	// Make sure we have arguments, if not, display the player menu and bug out.
	if(args < 1) 
	{
		ReplyToCommand(client, "Usage: sm_viewbans <#userid|name>");
		DisplayMenu(BuildPlayerMenu(client), client, MENU_TIME_FOREVER);
		return Plugin_Handled;
	}
	
	// We were at least sent a target, let's check him
	decl String:sTargetBuffer[128];
	GetCmdArg(1, sTargetBuffer, sizeof(sTargetBuffer));
	new iTarget = FindTarget(client, sTargetBuffer, true); 

	// If it's not a valid target display the player menu and bug out.
	if(iTarget <= 0 || !IsClientInGame(iTarget)) 
	{
		ReplyToCommand(client, "Usage: sm_viewbans <#userid|name>");
		DisplayMenu(BuildPlayerMenu(client), client, MENU_TIME_FOREVER);
		return Plugin_Handled;
	}
	
	// We have a valid target start to process the request.
	switch (g_iPlayerBans[iTarget])
	{
		case -1:
		{
			// This player has not been checked against the database
			// Format and send the query to see if we can get it now
			RequestBanInformation(iTarget, false, client);
			ReplyToCommand(client, "[SM] %t", "Processing client");
		}
		case 0:
		{
			// This player has no bans, print that
			PrintBans(client, iTarget);
		}
		default:
		{
			// If it's not -1 or 0 then we have bans.  Build the players ban information menu
			DisplayMenu(BuildPlayerBanListMenu(iTarget), client, MENU_TIME_FOREVER);
		}
	}
	return Plugin_Handled;
}


/**
 * Menu Handlers
 */
public MenuHandler_AdminMenu_ViewBans(Handle:topmenu, TopMenuAction:action, TopMenuObject:object_id, param, String:buffer[], maxlength)
{
	if(action      == TopMenuAction_DisplayOption)
	{
		Format(buffer, maxlength, "%T", "View bans", param);
	}
	else if(action == TopMenuAction_SelectOption)
	{
		DisplayMenu(BuildPlayerMenu(param), param, MENU_TIME_FOREVER);
	}
}
 
public MenuHandler_SelectPlayer(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action != MenuAction_Select) 
		return;
	
	// Get the selection.
	decl String:sTargetUserID[10];
	GetMenuItem(menu, param2, sTargetUserID, sizeof(sTargetUserID));
	new iTarget = GetClientOfUserId(StringToInt(sTargetUserID));
	
	// If the target is no longer connected we can bug out.
	if(!iTarget || !IsClientInGame(iTarget))
		return;
	
	// We have a valid target start to process the request.
	switch(g_iPlayerBans[iTarget])
	{
		case -1:
		{
			// This player has not been checked against the database
			// Format and send the query to see if we can get it now
			RequestBanInformation(iTarget, false, param1);
			PrintToChat(param1, "[SM] %t", "Processing client");
		}
		case 0:
		{
			// This player has no bans, print that
			PrintBans(param1, iTarget);
		}
		default:
		{
			// If it's not -1 or 0 then we have bans.  Build the players ban information menu
			DisplayMenu(BuildPlayerBanListMenu(iTarget), param1, MENU_TIME_FOREVER);
		}
	}
}

public MenuHandler_BanList(Handle:menu, MenuAction:action, param1, param2)
{
	if(action      == MenuAction_Cancel)
	{
		if(param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
			DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
	}
	else if(action == MenuAction_End)
		CloseHandle(menu);
	else if(action != MenuAction_Select)
		return;
	
	// Get the selection and display the ban.
	decl String:sBanID[10];
	GetMenuItem(menu, param2, sBanID, sizeof(sBanID));
	// TODO: Deal with passing the target id
	new iTarget;
	new Handle:hPanel = BuildPlayerBanInfoPanel(iTarget, StringToInt(sBanID));
	SendPanelToClient(hPanel, param1, PanelHandler_BanInfo, MENU_TIME_FOREVER);
	CloseHandle(hPanel);
}

public PanelHandler_BanInfo(Handle:menu, MenuAction:action, param1, param2)
{
	if(action == MenuAction_Cancel && param2 == MenuCancel_ExitBack && g_hTopMenu && GetUserFlagBits(param1) & ADMFLAG_GENERIC)
		DisplayTopMenu(g_hTopMenu, param1, TopMenuPosition_LastCategory);
}


/**
 * Query Callbacks
 */
public OnRecieveBans(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	
	// Unload the datapack and load up some variables
	decl String:sQuery[256];
	new iClient = ReadPackCell(pack),
			iTarget = ReadPackCell(pack),
			iOnConnect = ReadPackCell(pack);
	ReadPackString(pack, sQuery, sizeof(sQuery));
	
	// We're done with you now.
	CloseHandle(pack);
	
	// If the target is no longer connected we can bug out.
	if(!IsClientInGame(iTarget))
		return;
	
	// Make sure we succeeded.
	if(error[0])
	{
		LogError("SQL error: %s", error);
		LogError("Query dump: %s", sQuery);
		return;
	}
	
	// Store the number bans.
	g_iPlayerBans[iTarget] = SQL_GetRowCount(hndl);
	
	// If we have bans, clone the handle.
	if(g_iPlayerBans[iTarget])
	{
		CloneHandle(g_hPlayerResults[iTarget], hndl);
		
		// If we the query was from a client connection announce bans to admins.
		if(iOnConnect)
		{
			SendChatToAdmins(iTarget);
			return;
		}
		
		// This query was sent by the sm_viewbans command.
		// Let's tell the client we succeeded. 
		if(IsClientInGame(iClient))
		{
			PrintToChat(iClient, "[SM] %t", "Processed client");
			PrintBans(iClient, iTarget);
		}
	}
}


/**
 * Stocks
 */
stock Handle:BuildPlayerMenu(iClient)
{
	decl String:sTitle[128];
	new Handle:hMenu = CreateMenu(MenuHandler_SelectPlayer);
	Format(sTitle, sizeof(sTitle), "%t:", "Select player");
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	AddTargetsToMenu2(hMenu, iClient, COMMAND_FILTER_NO_BOTS|COMMAND_FILTER_CONNECTED);
	return hMenu;
}

stock Handle:BuildPlayerBanListMenu(iTarget)
{
	// Create the menu and set the menu options.
	new Handle:hMenu = CreateMenu(MenuHandler_BanList);
	decl String:sTargetName[64], String:sTitle[128];
	GetClientName(iTarget, sTargetName, sizeof(sTargetName));
	Format(sTitle, sizeof(sTitle), "%t:", "Player ban list", sTargetName);
	SetMenuTitle(hMenu, sTitle);
	SetMenuExitBackButton(hMenu, true);
	
	// Add the bans to the menu.
	decl String:sReason[128], String:sBanID[10];
	SQL_Rewind(g_hPlayerResults[iTarget]);
	while (SQL_FetchRow(g_hPlayerResults[iTarget]))
	{
		SQL_FetchString(g_hPlayerResults[iTarget], 3, sReason, sizeof(sReason));
		SQL_FetchString(g_hPlayerResults[iTarget], 0, sBanID, sizeof(sBanID));
		AddMenuItem(hMenu, sBanID, sReason);
	}
	return hMenu;
}

stock Handle:BuildPlayerBanInfoPanel(iTarget, iBanID)
{
	// Create the panel and set the panel options.
	new Handle:hPanel = CreatePanel();
	decl String:sTargetName[64], String:sTitle[128];
	GetClientName(iTarget, sTargetName, sizeof(sTargetName));
	Format(sTitle, sizeof(sTitle), "%t:", "Player ban info", sTargetName);
	SetPanelTitle(hPanel, sTitle);

	// Create all the string variables we will need
	decl String:sBanID[10], String:sAuthID[64], String:sBanName[64], String:sCreated[15], String:sEnds[15], String:sLength[15], String:sReason[128], String:sAdminID[10], String:sRemovedType[10], String:sRemovedBy[10], String:sRemovedReason[15];
	
	decl String:sPanelAuthID[64], String:sPanelBanName[64], String:sPanelCreated[15], String:sPanelEnds[15], String:sPanelLength[15], String:sPanelReason[128], String:sPanelAdminID[10], String:sPanelRemovedBy[10];
	
	// Rewind the results
	SQL_Rewind(g_hPlayerResults[iTarget]);
	
	// Start searching for the ban
	while(SQL_FetchRow(g_hPlayerResults[iTarget]))
	{
		// If we have find the ban, start to get the ban information
		if(SQL_FetchInt(g_hPlayerResults[iTarget], 0) == iBanID)
		{
			SQL_FetchString(g_hPlayerResults[iTarget], 1, sAuthID,      sizeof(sAuthID));
			SQL_FetchString(g_hPlayerResults[iTarget], 2, sBanName,     sizeof(sBanName));
			SQL_FetchString(g_hPlayerResults[iTarget], 5, sLength,      sizeof(sLength));
			SQL_FetchString(g_hPlayerResults[iTarget], 6, sReason,      sizeof(sReason));
			SQL_FetchString(g_hPlayerResults[iTarget], 7, sAdminID,     sizeof(sAdminID));
			SQL_FetchString(g_hPlayerResults[iTarget], 8, sRemovedBy,   sizeof(sRemovedBy));
			SQL_FetchString(g_hPlayerResults[iTarget], 9, sRemovedType, sizeof(sRemovedType));
			
			if(!strcmp(sRemovedType,      "E"))
				Format(sRemovedReason, sizeof(sRemovedReason), "Removed Reason: Expired");
			else if(!strcmp(sRemovedType, "U"))
				Format(sRemovedReason, sizeof(sRemovedReason), "Removed Reason: Unbanned");
			
			FormatTime(sCreated, sizeof(sCreated), "%x %X", SQL_FetchInt(g_hPlayerResults[iTarget], 3));
			FormatTime(sEnds,    sizeof(sEnds),    "%x %X", SQL_FetchInt(g_hPlayerResults[iTarget], 4));
			
			// Format the strings for the panel
			Format(sBanID, sizeof(sBanID), "Ban ID: %i", iBanID);
			Format(sPanelBanName, sizeof(sPanelBanName), "Player: %s", sBanName);
			Format(sPanelAuthID, sizeof(sPanelAuthID), "Steam ID: %s", sAuthID);
			Format(sPanelCreated, sizeof(sPanelCreated), "Invoked on: %s", sCreated);
			Format(sPanelLength, sizeof(sPanelLength), "Banlength: %s", sLength);
			Format(sPanelEnds, sizeof(sPanelEnds), "Expired on: %s", sEnds);
			Format(sPanelReason, sizeof(sPanelReason), "Reason: %s", sReason);
			Format(sPanelAdminID, sizeof(sPanelAdminID), "Banned by: %s", sAdminID);
			
			// Add the ban information to the panel.
			DrawPanelItem(hPanel, sBanID);
			DrawPanelText(hPanel, sPanelBanName);
			DrawPanelText(hPanel, sPanelAuthID);
			DrawPanelText(hPanel, sPanelCreated);
			DrawPanelText(hPanel, sPanelLength);
			DrawPanelText(hPanel, sPanelEnds);
			DrawPanelText(hPanel, sPanelReason);
			DrawPanelText(hPanel, sPanelAdminID);
			DrawPanelText(hPanel, sRemovedReason);
			if(!SQL_IsFieldNull(g_hPlayerResults[iTarget], 8))
			{
				Format(sPanelRemovedBy, sizeof(sPanelRemovedBy), "Removed by: %s", sRemovedBy);
				DrawPanelText(hPanel, sPanelRemovedBy);
			}
		}
	}
	return hPanel;
}

stock RequestBanInformation(iTarget, bool:bOnConnect, iClient = 0)
{
	if(!g_hDatabase)
		return;
	
	// Get the steamid and format the query.
	decl String:sAuth[20], String:sQuery[256];
	GetClientAuthString(iTarget, sAuth, sizeof(sAuth));
	Format(sQuery, sizeof(sQuery), "SELECT id, steam, name, reason, length, admin_id, unban_admin_id, time \
																	FROM   %s_bans \
																	WHERE  steam REGEXP '^STEAM_[0-9]:%s$'",
																	g_sDatabasePrefix, sAuth[8]);
	
	// Send the query.
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, iClient);
	WritePackCell(hPack, iTarget);
	WritePackCell(hPack, bOnConnect);
	WritePackString(hPack, sQuery);
	SQL_TQuery(g_hDatabase, OnRecieveBans, sQuery, hPack);
}

stock SendChatToAdmins(iTarget)
{
	for(new i = 1; i <= MaxClients; i++)
	{
		if(IsClientInGame(i) && CheckCommandAccess(i, "sm_chat", ADMFLAG_CHAT))
			PrintBans(i, iTarget);
	}
}

stock PrintBans(iClient, iTarget)
{
	decl String:sAuth[64], String:sTargetName[64], String:sReplyBuffer[256];
	GetClientAuthString(iTarget, sAuth, sizeof(sAuth));
	GetClientName(iTarget, sTargetName, sizeof(sTargetName));
	Format(sReplyBuffer, sizeof(sReplyBuffer), "%t", "Player bans", sTargetName, sAuth, g_iPlayerBans[iTarget]);
	PrintToChat(iClient, "[SM] %s", sReplyBuffer);
}