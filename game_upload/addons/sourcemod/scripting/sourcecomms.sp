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
//  This file is based off work covered by the following copyright(s):  
//
//   SourceComms 0.9.266
//   Copyright (C) 2013-2014 Alexandr Duplishchev
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
//
// *************************************************************************

#pragma semicolon 1

#include <sourcemod>
#include <basecomm>
#include <sourcecomms>

#undef REQUIRE_PLUGIN
#include <adminmenu>

#define UNBLOCK_FLAG ADMFLAG_CHEATS
#define DATABASE "sourcebans"

// #define DEBUG
// #define LOG_QUERIES

// Do not edit below this line //
//-----------------------------//

#define PLUGIN_VERSION "(SB++) 1.5.4.6"
#define PREFIX "\x04[SourceComms]\x01 "

#define MAX_TIME_MULTI 30       // maximum mass-target punishment length

#define NOW 0
#define TYPE_TEMP_SHIFT 10

#define TYPE_MUTE 1
#define TYPE_GAG 2
#define TYPE_SILENCE 3
#define TYPE_UNMUTE 4
#define TYPE_UNGAG 5
#define TYPE_UNSILENCE 6
#define TYPE_TEMP_UNMUTE 14     // TYPE_TEMP_SHIFT + TYPE_UNMUTE
#define TYPE_TEMP_UNGAG 15      // TYPE_TEMP_SHIFT + TYPE_UNGAG
#define TYPE_TEMP_UNSILENCE 16  // TYPE_TEMP_SHIFT + TYPE_UNSILENCE

#define MAX_REASONS 32
#define DISPLAY_SIZE 64
#define REASON_SIZE 192

new iNumReasons;
new String:g_sReasonDisplays[MAX_REASONS][DISPLAY_SIZE], String:g_sReasonKey[MAX_REASONS][REASON_SIZE];

#define MAX_TIMES 32
new iNumTimes, g_iTimeMinutes[MAX_TIMES];
new String:g_sTimeDisplays[MAX_TIMES][DISPLAY_SIZE];

enum State/* ConfigState */
{
	ConfigStateNone = 0, 
	ConfigStateConfig, 
	ConfigStateReasons, 
	ConfigStateTimes, 
	ConfigStateServers, 
}
enum DatabaseState/* Database connection state */
{
	DatabaseState_None = 0, 
	DatabaseState_Wait, 
	DatabaseState_Connecting, 
	DatabaseState_Connected, 
}

new DatabaseState:g_DatabaseState;
new g_iConnectLock = 0;
new g_iSequence = 0;

new State:ConfigState;
new Handle:ConfigParser;

new Handle:hTopMenu = INVALID_HANDLE;

/* Cvar handle*/
new Handle:CvarHostIp;
new Handle:CvarPort;

new String:ServerIp[24];
new String:ServerPort[7];

/* Database handle */
new Handle:g_hDatabase;
new Handle:SQLiteDB;

new String:DatabasePrefix[10] = "sb";

/* Timer handles */
new Handle:g_hPlayerRecheck[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... };
new Handle:g_hGagExpireTimer[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... };
new Handle:g_hMuteExpireTimer[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... };


/* Log Stuff */
#if defined LOG_QUERIES
new String:logQuery[256];
#endif

new Float:RetryTime = 15.0;
new DefaultTime = 30;
new DisUBImCheck = 0;
new ConsoleImmunity = 0;
new ConfigMaxLength = 0;
new ConfigWhiteListOnly = 0;
new serverID = 0;

/* List menu */
enum PeskyPanels
{
	curTarget, 
	curIndex, 
	viewingMute, 
	viewingGag, 
	viewingList, 
}
new g_iPeskyPanels[MAXPLAYERS + 1][PeskyPanels];

new bool:g_bPlayerStatus[MAXPLAYERS + 1]; // Player block check status
new String:g_sName[MAXPLAYERS + 1][MAX_NAME_LENGTH];

new bType:g_MuteType[MAXPLAYERS + 1];
new g_iMuteTime[MAXPLAYERS + 1];
new g_iMuteLength[MAXPLAYERS + 1]; // in sec
new g_iMuteLevel[MAXPLAYERS + 1]; // immunity level of admin
new String:g_sMuteAdminName[MAXPLAYERS + 1][MAX_NAME_LENGTH];
new String:g_sMuteReason[MAXPLAYERS + 1][256];
new String:g_sMuteAdminAuth[MAXPLAYERS + 1][64];

new bType:g_GagType[MAXPLAYERS + 1];
new g_iGagTime[MAXPLAYERS + 1];
new g_iGagLength[MAXPLAYERS + 1]; // in sec
new g_iGagLevel[MAXPLAYERS + 1]; // immunity level of admin
new String:g_sGagAdminName[MAXPLAYERS + 1][MAX_NAME_LENGTH];
new String:g_sGagReason[MAXPLAYERS + 1][256];
new String:g_sGagAdminAuth[MAXPLAYERS + 1][64];

new Handle:g_hServersWhiteList = INVALID_HANDLE;

public Plugin:myinfo = 
{
	name = "SourceComms", 
	author = "Alex, Sarabveer(VEER™)", 
	description = "Advanced punishments management for the Source engine in SourceBans style", 
	version = PLUGIN_VERSION, 
	url = "https://sarabveer.github.io/SourceBans-Fork/"
};

public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
{
	CreateNative("SourceComms_SetClientMute", Native_SetClientMute);
	CreateNative("SourceComms_SetClientGag", Native_SetClientGag);
	CreateNative("SourceComms_GetClientMuteType", Native_GetClientMuteType);
	CreateNative("SourceComms_GetClientGagType", Native_GetClientGagType);
	MarkNativeAsOptional("SQL_SetCharset");
	RegPluginLibrary("sourcecomms");
	return APLRes_Success;
}

public OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("sourcecomms.phrases");
	
	new Handle:hTemp = INVALID_HANDLE;
	if (LibraryExists("adminmenu") && ((hTemp = GetAdminTopMenu()) != INVALID_HANDLE))
		OnAdminMenuReady(hTemp);
	
	CvarHostIp = FindConVar("hostip");
	CvarPort = FindConVar("hostport");
	g_hServersWhiteList = CreateArray();
	
	CreateConVar("sourcecomms_version", PLUGIN_VERSION, _, FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY);
	AddCommandListener(CommandCallback, "sm_gag");
	AddCommandListener(CommandCallback, "sm_mute");
	AddCommandListener(CommandCallback, "sm_silence");
	AddCommandListener(CommandCallback, "sm_ungag");
	AddCommandListener(CommandCallback, "sm_unmute");
	AddCommandListener(CommandCallback, "sm_unsilence");
	RegServerCmd("sc_fw_block", FWBlock, "Blocking player comms by command from sourceban web site");
	RegServerCmd("sc_fw_ungag", FWUngag, "Ungagging player by command from sourceban web site");
	RegServerCmd("sc_fw_unmute", FWUnmute, "Unmuting player by command from sourceban web site");
	RegConsoleCmd("sm_comms", CommandComms, "Shows current player communications status");
	
	HookEvent("player_changename", Event_OnPlayerName, EventHookMode_Post);
	
	#if defined LOG_QUERIES
	BuildPath(Path_SM, logQuery, sizeof(logQuery), "logs/sourcecomms-q.log");
	#endif
	
	#if defined DEBUG
	PrintToServer("Sourcecomms plugin loading. Version %s", PLUGIN_VERSION);
	#endif
	
	// Catch config error
	if (!SQL_CheckConfig(DATABASE))
	{
		SetFailState("Database failure: could not find database conf  %s", DATABASE);
		return;
	}
	DB_Connect();
	InitializeBackupDB();
	
	ServerInfo();
	
	for (new client = 1; client <= MaxClients; client++)
	{
		if (IsClientInGame(client) && IsClientAuthorized(client))
			OnClientPostAdminCheck(client);
	}
}

public OnLibraryRemoved(const String:name[])
{
	if (StrEqual(name, "adminmenu"))
		hTopMenu = INVALID_HANDLE;
}

public OnMapStart()
{
	ReadConfig();
}

public OnMapEnd()
{
	// Clean up on map end just so we can start a fresh connection when we need it later.
	// Also it is necessary for using SQL_SetCharset
	if (g_hDatabase)
		CloseHandle(g_hDatabase);
	
	g_hDatabase = INVALID_HANDLE;
}


// CLIENT CONNECTION FUNCTIONS //

public OnClientDisconnect(client)
{
	if (g_hPlayerRecheck[client] != INVALID_HANDLE && CloseHandle(g_hPlayerRecheck[client]))
		g_hPlayerRecheck[client] = INVALID_HANDLE;
	
	CloseMuteExpireTimer(client);
	CloseGagExpireTimer(client);
}

public bool:OnClientConnect(client, String:rejectmsg[], maxlen)
{
	g_bPlayerStatus[client] = false;
	return true;
}

public OnClientConnected(client)
{
	g_sName[client][0] = '\0';
	
	MarkClientAsUnMuted(client);
	MarkClientAsUnGagged(client);
}

public OnClientPostAdminCheck(client)
{
	decl String:clientAuth[64];
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	GetClientName(client, g_sName[client], sizeof(g_sName[]));
	
	/* Do not check bots or check player with lan steamid. */
	if (clientAuth[0] == 'B' || clientAuth[9] == 'L' || !DB_Connect())
	{
		g_bPlayerStatus[client] = true;
		return;
	}
	
	if (client > 0 && IsClientInGame(client) && !IsFakeClient(client))
	{
		// if plugin was late loaded
		if (BaseComm_IsClientMuted(client))
		{
			MarkClientAsMuted(client);
		}
		if (BaseComm_IsClientGagged(client))
		{
			MarkClientAsGagged(client);
		}
		
		decl String:sClAuthYZEscaped[sizeof(clientAuth) * 2 + 1];
		SQL_EscapeString(g_hDatabase, clientAuth[8], sClAuthYZEscaped, sizeof(sClAuthYZEscaped));
		
		decl String:Query[4096];
		FormatEx(Query, sizeof(Query), 
			"SELECT      (c.ends - UNIX_TIMESTAMP()) AS remaining, \
                        c.length, c.type, c.created, c.reason, a.user, \
                        IF (a.immunity>=g.immunity, a.immunity, IFNULL(g.immunity,0)) AS immunity, \
                        c.aid, c.sid, a.authid \
            FROM        %s_comms     AS c \
            LEFT JOIN   %s_admins    AS a  ON a.aid = c.aid \
            LEFT JOIN   %s_srvgroups AS g  ON g.name = a.srv_group \
            WHERE       RemoveType IS NULL \
                          AND c.authid REGEXP '^STEAM_[0-9]:%s$' \
                          AND (length = '0' OR ends > UNIX_TIMESTAMP())", 
			DatabasePrefix, DatabasePrefix, DatabasePrefix, sClAuthYZEscaped);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "OnClientPostAdminCheck for: %s. QUERY: %s", clientAuth, Query);
		#endif
		SQL_TQuery(g_hDatabase, Query_VerifyBlock, Query, GetClientUserId(client), DBPrio_High);
	}
}


// OTHER CLIENT CODE //

public Action:Event_OnPlayerName(Handle:event, const String:name[], bool:dontBroadcast)
{
	new client = GetClientOfUserId(GetEventInt(event, "userid"));
	if (client > 0 && IsClientInGame(client))
		GetEventString(event, "newname", g_sName[client], sizeof(g_sName[]));
}

public BaseComm_OnClientMute(client, bool:muteState)
{
	if (client > 0 && client <= MaxClients)
	{
		if (muteState)
		{
			if (g_MuteType[client] == bNot)
			{
				MarkClientAsMuted(client, _, _, _, _, _, "Muted through BaseComm natives");
				SavePunishment(_, client, TYPE_MUTE, _, "Muted through BaseComm natives");
			}
		}
		else
		{
			if (g_MuteType[client] > bNot)
			{
				MarkClientAsUnMuted(client);
			}
		}
	}
}

public BaseComm_OnClientGag(client, bool:gagState)
{
	if (client > 0 && client <= MaxClients)
	{
		if (gagState)
		{
			if (g_GagType[client] == bNot)
			{
				MarkClientAsGagged(client, _, _, _, _, _, "Gagged through BaseComm natives");
				SavePunishment(_, client, TYPE_GAG, _, "Gagged through BaseComm natives");
			}
		}
		else
		{
			if (g_GagType[client] > bNot)
			{
				MarkClientAsUnGagged(client);
			}
		}
	}
}

// COMMAND CODE //

public Action:CommandComms(client, args)
{
	if (!client)
	{
		ReplyToCommand(client, "%s%t", PREFIX, "CommandComms_na");
		return Plugin_Continue;
	}
	
	if (g_MuteType[client] > bNot || g_GagType[client] > bNot)
		AdminMenu_ListTarget(client, client, 0);
	else
		ReplyToCommand(client, "%s%t", PREFIX, "CommandComms_nb");
	
	return Plugin_Handled;
}

public Action:FWBlock(args)
{
	decl String:arg_string[256];
	new String:sArg[3][64];
	GetCmdArgString(arg_string, sizeof(arg_string));
	
	decl type, length;
	if (ExplodeString(arg_string, " ", sArg, 3, 64) != 3 || !StringToIntEx(sArg[0], type) || type < 1 || type > 3 || !StringToIntEx(sArg[1], length))
	{
		LogError("Wrong usage of sc_fw_block");
		return Plugin_Stop;
	}
	
	LogMessage("Received block command from web: steam %s, type %d, length %d", sArg[2], type, length);
	
	decl String:clientAuth[64];
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i))
		{
			GetClientAuthId(i, AuthId_Steam2, clientAuth, sizeof(clientAuth));
			if (strcmp(clientAuth, sArg[2], false) == 0)
			{
				#if defined DEBUG
				PrintToServer("Catched %s for blocking from web", clientAuth);
				#endif
				
				switch (type) {
					case TYPE_MUTE:setMute(i, length, clientAuth);
					case TYPE_GAG:setGag(i, length, clientAuth);
					case TYPE_SILENCE: { setMute(i, length, clientAuth); setGag(i, length, clientAuth); }
				}
				break;
			}
		}
	}
	
	return Plugin_Handled;
}

public Action:FWUngag(args)
{
	decl String:arg_string[256];
	new String:sArg[1][64];
	GetCmdArgString(arg_string, sizeof(arg_string));
	if (!ExplodeString(arg_string, " ", sArg, 1, 64))
	{
		LogError("Wrong usage of sc_fw_ungag");
		return Plugin_Stop;
	}
	
	LogMessage("Received ungag command from web: steam %s", sArg[0]);
	
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i))
		{
			decl String:clientAuth[64];
			GetClientAuthId(i, AuthId_Steam2, clientAuth, sizeof(clientAuth));
			if (strcmp(clientAuth, sArg[0], false) == 0)
			{
				#if defined DEBUG
				PrintToServer("Catched %s for ungagging from web", clientAuth);
				#endif
				
				if (g_GagType[i] > bNot)
				{
					PerformUnGag(i);
					PrintToChat(i, "%s%t", PREFIX, "FWUngag");
					LogMessage("%s is ungagged from web", clientAuth);
				}
				else
					LogError("Can't ungag %s from web, it isn't gagged", clientAuth);
				break;
			}
		}
	}
	return Plugin_Handled;
}

public Action:FWUnmute(args)
{
	decl String:arg_string[256];
	new String:sArg[1][64];
	GetCmdArgString(arg_string, sizeof(arg_string));
	if (!ExplodeString(arg_string, " ", sArg, 1, 64))
	{
		LogError("Wrong usage of sc_fw_ungag");
		return Plugin_Stop;
	}
	
	LogMessage("Received unmute command from web: steam %s", sArg[0]);
	
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i))
		{
			decl String:clientAuth[64];
			GetClientAuthId(i, AuthId_Steam2, clientAuth, sizeof(clientAuth));
			if (strcmp(clientAuth, sArg[0], false) == 0)
			{
				#if defined DEBUG
				PrintToServer("Catched %s for unmuting from web", clientAuth);
				#endif
				
				if (g_MuteType[i] > bNot)
				{
					PerformUnMute(i);
					PrintToChat(i, "%s%t", PREFIX, "FWUnmute");
					LogMessage("%s is unmuted from web", clientAuth);
				}
				else
					LogError("Can't unmute %s from web, it isn't muted", clientAuth);
				break;
			}
		}
	}
	return Plugin_Handled;
}


public Action:CommandCallback(client, const String:command[], args)
{
	if (client && !CheckCommandAccess(client, command, ADMFLAG_CHAT))
		return Plugin_Continue;
	
	new type;
	if (StrEqual(command, "sm_gag", false))
		type = TYPE_GAG;
	else if (StrEqual(command, "sm_mute", false))
		type = TYPE_MUTE;
	else if (StrEqual(command, "sm_ungag", false))
		type = TYPE_UNGAG;
	else if (StrEqual(command, "sm_unmute", false))
		type = TYPE_UNMUTE;
	else if (StrEqual(command, "sm_silence", false))
		type = TYPE_SILENCE;
	else if (StrEqual(command, "sm_unsilence", false))
		type = TYPE_UNSILENCE;
	else
		return Plugin_Stop;
	
	if (args < 1)
	{
		ReplyToCommand(client, "%sUsage: %s <#userid|name> %s", PREFIX, command, type <= TYPE_SILENCE ? "[time|0] [reason]" : "[reason]");
		if (type <= TYPE_SILENCE)
			ReplyToCommand(client, "%sUsage: %s <#userid|name> [reason]", PREFIX, command);
		return Plugin_Stop;
	}
	
	decl String:sBuffer[256];
	GetCmdArgString(sBuffer, sizeof(sBuffer));
	
	if (type <= TYPE_SILENCE)
		CreateBlock(client, _, _, type, _, sBuffer);
	else
		ProcessUnBlock(client, _, type, _, sBuffer);
	
	return Plugin_Stop;
}


// MENU CODE //

public OnAdminMenuReady(Handle:topmenu)
{
	/* Block us from being called twice */
	if (topmenu == hTopMenu)
		return;
	
	/* Save the Handle */
	hTopMenu = topmenu;
	
	new TopMenuObject:MenuObject = AddToTopMenu(hTopMenu, "sourcecomm_cmds", TopMenuObject_Category, Handle_Commands, INVALID_TOPMENUOBJECT);
	if (MenuObject == INVALID_TOPMENUOBJECT)
		return;
	
	AddToTopMenu(hTopMenu, "sourcecomm_gag", TopMenuObject_Item, Handle_MenuGag, MenuObject, "sm_gag", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_ungag", TopMenuObject_Item, Handle_MenuUnGag, MenuObject, "sm_ungag", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_mute", TopMenuObject_Item, Handle_MenuMute, MenuObject, "sm_mute", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_unmute", TopMenuObject_Item, Handle_MenuUnMute, MenuObject, "sm_unmute", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_silence", TopMenuObject_Item, Handle_MenuSilence, MenuObject, "sm_silence", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_unsilence", TopMenuObject_Item, Handle_MenuUnSilence, MenuObject, "sm_unsilence", ADMFLAG_CHAT);
	AddToTopMenu(hTopMenu, "sourcecomm_list", TopMenuObject_Item, Handle_MenuList, MenuObject, "sm_commlist", ADMFLAG_CHAT);
}

public Handle_Commands(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
		Format(buffer, maxlength, "%T", "AdminMenu_Main", param1);
		case TopMenuAction_DisplayTitle:
		Format(buffer, maxlength, "%T", "AdminMenu_Select_Main", param1);
	}
}

public Handle_MenuGag(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_Gag", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_GAG);
}

public Handle_MenuUnGag(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_UnGag", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_UNGAG);
}

public Handle_MenuMute(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_Mute", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_MUTE);
}

public Handle_MenuUnMute(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_UnMute", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_UNMUTE);
}

public Handle_MenuSilence(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_Silence", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_SILENCE);
}

public Handle_MenuUnSilence(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_UnSilence", param1);
	else if (action == TopMenuAction_SelectOption)
		AdminMenu_Target(param1, TYPE_UNSILENCE);
}

public Handle_MenuList(Handle:menu, TopMenuAction:action, TopMenuObject:object_id, param1, String:buffer[], maxlength)
{
	if (action == TopMenuAction_DisplayOption)
		Format(buffer, maxlength, "%T", "AdminMenu_List", param1);
	else if (action == TopMenuAction_SelectOption)
	{
		g_iPeskyPanels[param1][viewingList] = false;
		AdminMenu_List(param1, 0);
	}
}

AdminMenu_Target(client, type)
{
	decl String:Title[192], String:Option[32];
	switch (type)
	{
		case TYPE_GAG:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Gag", client);
		case TYPE_MUTE:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Mute", client);
		case TYPE_SILENCE:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Silence", client);
		case TYPE_UNGAG:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Ungag", client);
		case TYPE_UNMUTE:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Unmute", client);
		case TYPE_UNSILENCE:
		Format(Title, sizeof(Title), "%T", "AdminMenu_Select_Unsilence", client);
	}
	
	new Handle:hMenu = CreateMenu(MenuHandler_MenuTarget); // Common menu - players list. Almost full for blocking, and almost empty for unblocking
	SetMenuTitle(hMenu, Title);
	SetMenuExitBackButton(hMenu, true);
	
	new iClients;
	if (type <= 3) // Mute, gag, silence
	{
		for (new i = 1; i <= MaxClients; i++)
		{
			if (IsClientInGame(i) && !IsFakeClient(i))
			{
				switch (type)
				{
					case TYPE_MUTE:
					if (g_MuteType[i] > bNot)
						continue;
					case TYPE_GAG:
					if (g_GagType[i] > bNot)
						continue;
					case TYPE_SILENCE:
					if (g_MuteType[i] > bNot || g_GagType[i] > bNot)
						continue;
				}
				iClients++;
				strcopy(Title, sizeof(Title), g_sName[i]);
				AdminMenu_GetPunishPhrase(client, i, Title, sizeof(Title));
				Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
				AddMenuItem(hMenu, Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
			}
		}
	}
	else // UnMute, ungag, unsilence
	{
		for (new i = 1; i <= MaxClients; i++)
		{
			if (IsClientInGame(i) && !IsFakeClient(i))
			{
				switch (type)
				{
					case TYPE_UNMUTE:
					{
						if (g_MuteType[i] > bNot)
						{
							iClients++;
							strcopy(Title, sizeof(Title), g_sName[i]);
							Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
							AddMenuItem(hMenu, Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
						}
					}
					case TYPE_UNGAG:
					{
						if (g_GagType[i] > bNot)
						{
							iClients++;
							strcopy(Title, sizeof(Title), g_sName[i]);
							Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
							AddMenuItem(hMenu, Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
						}
					}
					case TYPE_UNSILENCE:
					{
						if (g_MuteType[i] > bNot && g_GagType[i] > bNot)
						{
							iClients++;
							strcopy(Title, sizeof(Title), g_sName[i]);
							Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
							AddMenuItem(hMenu, Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
						}
					}
				}
			}
		}
	}
	if (!iClients)
	{
		switch (type)
		{
			case TYPE_UNMUTE:
			Format(Title, sizeof(Title), "%T", "AdminMenu_Option_Mute_Empty", client);
			case TYPE_UNGAG:
			Format(Title, sizeof(Title), "%T", "AdminMenu_Option_Gag_Empty", client);
			case TYPE_UNSILENCE:
			Format(Title, sizeof(Title), "%T", "AdminMenu_Option_Silence_Empty", client);
			default:
			Format(Title, sizeof(Title), "%T", "AdminMenu_Option_Empty", client);
		}
		AddMenuItem(hMenu, "0", Title, ITEMDRAW_DISABLED);
	}
	
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

public MenuHandler_MenuTarget(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_End:
		CloseHandle(menu);
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			decl String:Option[32], String:Temp[2][8];
			GetMenuItem(menu, param2, Option, sizeof(Option));
			ExplodeString(Option, " ", Temp, 2, 8);
			new target = GetClientOfUserId(StringToInt(Temp[0]));
			
			if (Bool_ValidMenuTarget(param1, target))
			{
				new type = StringToInt(Temp[1]);
				if (type <= TYPE_SILENCE)
					AdminMenu_Duration(param1, target, type);
				else
					ProcessUnBlock(param1, target, type);
			}
		}
	}
}

AdminMenu_Duration(client, target, type)
{
	new Handle:hMenu = CreateMenu(MenuHandler_MenuDuration);
	decl String:sBuffer[192], String:sTemp[64];
	Format(sBuffer, sizeof(sBuffer), "%T", "AdminMenu_Title_Durations", client);
	SetMenuTitle(hMenu, sBuffer);
	SetMenuExitBackButton(hMenu, true);
	
	for (new i = 0; i <= iNumTimes; i++)
	{
		if (IsAllowedBlockLength(client, g_iTimeMinutes[i]))
		{
			Format(sTemp, sizeof(sTemp), "%d %d %d", GetClientUserId(target), type, i); // TargetID TYPE_BLOCK index_of_Time
			AddMenuItem(hMenu, sTemp, g_sTimeDisplays[i]);
		}
	}
	
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

public MenuHandler_MenuDuration(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_End:
		CloseHandle(menu);
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			decl String:sOption[32], String:sTemp[3][8];
			GetMenuItem(menu, param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 3, 8);
			// TargetID TYPE_BLOCK index_of_Time
			new target = GetClientOfUserId(StringToInt(sTemp[0]));
			
			if (Bool_ValidMenuTarget(param1, target))
			{
				new type = StringToInt(sTemp[1]);
				new lengthIndex = StringToInt(sTemp[2]);
				
				if (iNumReasons) // we have reasons to show
					AdminMenu_Reason(param1, target, type, lengthIndex);
				else
					CreateBlock(param1, target, g_iTimeMinutes[lengthIndex], type);
			}
		}
	}
}

AdminMenu_Reason(client, target, type, lengthIndex)
{
	new Handle:hMenu = CreateMenu(MenuHandler_MenuReason);
	decl String:sBuffer[192], String:sTemp[64];
	Format(sBuffer, sizeof(sBuffer), "%T", "AdminMenu_Title_Reasons", client);
	SetMenuTitle(hMenu, sBuffer);
	SetMenuExitBackButton(hMenu, true);
	
	for (new i = 0; i <= iNumReasons; i++)
	{
		Format(sTemp, sizeof(sTemp), "%d %d %d %d", GetClientUserId(target), type, i, lengthIndex); // TargetID TYPE_BLOCK ReasonIndex LenghtIndex
		AddMenuItem(hMenu, sTemp, g_sReasonDisplays[i]);
	}
	
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

public MenuHandler_MenuReason(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_End:
		CloseHandle(menu);
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			decl String:sOption[64], String:sTemp[4][8];
			GetMenuItem(menu, param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 4, 8);
			// TargetID TYPE_BLOCK ReasonIndex LenghtIndex
			new target = GetClientOfUserId(StringToInt(sTemp[0]));
			
			if (Bool_ValidMenuTarget(param1, target))
			{
				new type = StringToInt(sTemp[1]);
				new reasonIndex = StringToInt(sTemp[2]);
				new lengthIndex = StringToInt(sTemp[3]);
				new length;
				if (lengthIndex >= 0 && lengthIndex <= iNumTimes)
					length = g_iTimeMinutes[lengthIndex];
				else
				{
					length = DefaultTime;
					LogError("Wrong length index in menu - using default time");
				}
				
				CreateBlock(param1, target, length, type, g_sReasonKey[reasonIndex]);
			}
		}
	}
}

AdminMenu_List(client, index)
{
	decl String:sTitle[192], String:sOption[32];
	Format(sTitle, sizeof(sTitle), "%T", "AdminMenu_Select_List", client);
	new iClients, Handle:hMenu = CreateMenu(MenuHandler_MenuList);
	SetMenuTitle(hMenu, sTitle);
	if (!g_iPeskyPanels[client][viewingList])
		SetMenuExitBackButton(hMenu, true);
	
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && !IsFakeClient(i) && (g_MuteType[i] > bNot || g_GagType[i] > bNot))
		{
			iClients++;
			strcopy(sTitle, sizeof(sTitle), g_sName[i]);
			AdminMenu_GetPunishPhrase(client, i, sTitle, sizeof(sTitle));
			Format(sOption, sizeof(sOption), "%d", GetClientUserId(i));
			AddMenuItem(hMenu, sOption, sTitle);
		}
	}
	
	if (!iClients)
	{
		Format(sTitle, sizeof(sTitle), "%T", "ListMenu_Option_Empty", client);
		AddMenuItem(hMenu, "0", sTitle, ITEMDRAW_DISABLED);
	}
	
	DisplayMenuAtItem(hMenu, client, index, MENU_TIME_FOREVER);
}

public MenuHandler_MenuList(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_End:
		CloseHandle(menu);
		case MenuAction_Cancel:
		{
			if (!g_iPeskyPanels[param1][viewingList])
				if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			decl String:sOption[32];
			GetMenuItem(menu, param2, sOption, sizeof(sOption));
			new target = GetClientOfUserId(StringToInt(sOption));
			
			if (Bool_ValidMenuTarget(param1, target))
				AdminMenu_ListTarget(param1, target, GetMenuSelectionPosition());
			else
				AdminMenu_List(param1, GetMenuSelectionPosition());
		}
	}
}

AdminMenu_ListTarget(client, target, index, viewMute = 0, viewGag = 0)
{
	new userid = GetClientUserId(target), Handle:hMenu = CreateMenu(MenuHandler_MenuListTarget);
	decl String:sBuffer[192], String:sOption[32];
	SetMenuTitle(hMenu, g_sName[target]);
	SetMenuPagination(hMenu, MENU_NO_PAGINATION);
	SetMenuExitButton(hMenu, true);
	SetMenuExitBackButton(hMenu, false);
	
	if (g_MuteType[target] > bNot)
	{
		Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Mute", client);
		Format(sOption, sizeof(sOption), "0 %d %d %b %b", userid, index, viewMute, viewGag);
		AddMenuItem(hMenu, sOption, sBuffer);
		
		if (viewMute)
		{
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Admin", client, g_sMuteAdminName[target]);
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			decl String:sMuteTemp[192], String:_sMuteTime[192];
			Format(sMuteTemp, sizeof(sMuteTemp), "%T", "ListMenu_Option_Duration", client);
			switch (g_MuteType[target])
			{
				case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Perm", client);
				case bTime:Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Time", client, g_iMuteLength[target]);
				case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Temp", client);
				default:Format(sBuffer, sizeof(sBuffer), "error");
			}
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			FormatTime(_sMuteTime, sizeof(_sMuteTime), NULL_STRING, g_iMuteTime[target]);
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Issue", client, _sMuteTime);
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			Format(sMuteTemp, sizeof(sMuteTemp), "%T", "ListMenu_Option_Expire", client);
			switch (g_MuteType[target])
			{
				case bTime:
				{
					FormatTime(_sMuteTime, sizeof(_sMuteTime), NULL_STRING, (g_iMuteTime[target] + g_iMuteLength[target] * 60));
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Time", client, _sMuteTime);
				}
				case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Perm", client);
				case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Temp_Reconnect", client);
				default:Format(sBuffer, sizeof(sBuffer), "error");
			}
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			if (strlen(g_sMuteReason[target]) > 0)
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason", client);
				Format(sOption, sizeof(sOption), "1 %d %d %b %b", userid, index, viewMute, viewGag);
				AddMenuItem(hMenu, sOption, sBuffer);
			}
			else
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason_None", client);
				AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			}
		}
	}
	
	if (g_GagType[target] > bNot)
	{
		Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Gag", client);
		Format(sOption, sizeof(sOption), "2 %d %d %b %b", userid, index, viewMute, viewGag);
		AddMenuItem(hMenu, sOption, sBuffer);
		
		if (viewGag)
		{
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Admin", client, g_sGagAdminName[target]);
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			decl String:sGagTemp[192], String:_sGagTime[192];
			Format(sGagTemp, sizeof(sGagTemp), "%T", "ListMenu_Option_Duration", client);
			
			switch (g_GagType[target])
			{
				case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Perm", client);
				case bTime:Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Time", client, g_iGagLength[target]);
				case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Temp", client);
				default:Format(sBuffer, sizeof(sBuffer), "error");
			}
			
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			FormatTime(_sGagTime, sizeof(_sGagTime), NULL_STRING, g_iGagTime[target]);
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Issue", client, _sGagTime);
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			Format(sGagTemp, sizeof(sGagTemp), "%T", "ListMenu_Option_Expire", client);
			
			switch (g_GagType[target])
			{
				case bTime:
				{
					FormatTime(_sGagTime, sizeof(_sGagTime), NULL_STRING, (g_iGagTime[target] + g_iGagLength[target] * 60));
					Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Expire_Time", client, _sGagTime);
				}
				case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Expire_Perm", client);
				case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Expire_Temp_Reconnect", client);
				default:Format(sBuffer, sizeof(sBuffer), "error");
			}
			
			AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			
			if (strlen(g_sGagReason[target]) > 0)
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason", client);
				Format(sOption, sizeof(sOption), "3 %d %d %b %b", userid, index, viewMute, viewGag);
				AddMenuItem(hMenu, sOption, sBuffer);
			}
			else
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason_None", client);
				AddMenuItem(hMenu, "", sBuffer, ITEMDRAW_DISABLED);
			}
		}
	}
	
	g_iPeskyPanels[client][curIndex] = index;
	g_iPeskyPanels[client][curTarget] = target;
	g_iPeskyPanels[client][viewingGag] = viewGag;
	g_iPeskyPanels[client][viewingMute] = viewMute;
	DisplayMenu(hMenu, client, MENU_TIME_FOREVER);
}

public MenuHandler_MenuListTarget(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_End:
		CloseHandle(menu);
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack)
				AdminMenu_List(param1, g_iPeskyPanels[param1][curIndex]);
		}
		case MenuAction_Select:
		{
			decl String:sOption[64], String:sTemp[5][8];
			GetMenuItem(menu, param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 5, 8);
			
			new target = GetClientOfUserId(StringToInt(sTemp[1]));
			if (param1 == target || Bool_ValidMenuTarget(param1, target))
			{
				switch (StringToInt(sTemp[0]))
				{
					case 0:
					AdminMenu_ListTarget(param1, target, StringToInt(sTemp[2]), !(StringToInt(sTemp[3])), 0);
					case 1, 3:
					AdminMenu_ListTargetReason(param1, target, g_iPeskyPanels[param1][viewingMute], g_iPeskyPanels[param1][viewingGag]);
					case 2:
					AdminMenu_ListTarget(param1, target, StringToInt(sTemp[2]), 0, !(StringToInt(sTemp[4])));
				}
			}
			else
				AdminMenu_List(param1, StringToInt(sTemp[2]));
			
		}
	}
}

AdminMenu_ListTargetReason(client, target, showMute, showGag)
{
	decl String:sTemp[192], String:sBuffer[192];
	new Handle:hPanel = CreatePanel();
	SetPanelTitle(hPanel, g_sName[target]);
	DrawPanelItem(hPanel, " ", ITEMDRAW_SPACER | ITEMDRAW_RAWLINE);
	
	if (showMute)
	{
		Format(sTemp, sizeof(sTemp), "%T", "ReasonPanel_Punishment_Mute", client);
		switch (g_MuteType[target])
		{
			case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Perm", client);
			case bTime:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Time", client, g_iMuteLength[target]);
			case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Temp", client);
			default:Format(sBuffer, sizeof(sBuffer), "error");
		}
		DrawPanelText(hPanel, sBuffer);
		
		Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Reason", client, g_sMuteReason[target]);
		DrawPanelText(hPanel, sBuffer);
	}
	else if (showGag)
	{
		Format(sTemp, sizeof(sTemp), "%T", "ReasonPanel_Punishment_Gag", client);
		switch (g_GagType[target])
		{
			case bPerm:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Perm", client);
			case bTime:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Time", client, g_iGagLength[target]);
			case bSess:Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Temp", client);
			default:Format(sBuffer, sizeof(sBuffer), "error");
		}
		DrawPanelText(hPanel, sBuffer);
		
		Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Reason", client, g_sGagReason[target]);
		DrawPanelText(hPanel, sBuffer);
	}
	
	DrawPanelItem(hPanel, " ", ITEMDRAW_SPACER | ITEMDRAW_RAWLINE);
	SetPanelCurrentKey(hPanel, 10);
	Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Back", client);
	DrawPanelItem(hPanel, sBuffer);
	SendPanelToClient(hPanel, client, PanelHandler_ListTargetReason, MENU_TIME_FOREVER);
	CloseHandle(hPanel);
}

public PanelHandler_ListTargetReason(Handle:menu, MenuAction:action, param1, param2)
{
	if (action == MenuAction_Select)
	{
		AdminMenu_ListTarget(param1, g_iPeskyPanels[param1][curTarget], 
			g_iPeskyPanels[param1][curIndex], 
			g_iPeskyPanels[param1][viewingMute], 
			g_iPeskyPanels[param1][viewingGag]);
	}
}


// SQL CALLBACKS //

public GotDatabase(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	#if defined DEBUG
	PrintToServer("GotDatabase(data: %d, lock: %d, g_h: %d, hndl: %d)", data, g_iConnectLock, g_hDatabase, hndl);
	#endif
	
	// If this happens to be an old connection request, ignore it.
	if (data != g_iConnectLock || g_hDatabase)
	{
		if (hndl)
			CloseHandle(hndl);
		return;
	}
	
	g_iConnectLock = 0;
	g_DatabaseState = DatabaseState_Connected;
	g_hDatabase = hndl;
	
	// See if the connection is valid.  If not, don't un-mark the caches
	// as needing rebuilding, in case the next connection request works.
	if (!g_hDatabase)
	{
		LogError("Connecting to database failed: %s", error);
		return;
	}
	
	// Set character set to UTF-8 in the database
	if (GetFeatureStatus(FeatureType_Native, "SQL_SetCharset") == FeatureStatus_Available)
	{
		SQL_SetCharset(g_hDatabase, "utf8");
	}
	else
	{
		decl String:query[128];
		FormatEx(query, sizeof(query), "SET NAMES 'UTF8'");
		#if defined LOG_QUERIES
		LogToFile(logQuery, "Set encoding. QUERY: %s", query);
		#endif
		SQL_TQuery(g_hDatabase, Query_ErrorCheck, query);
	}
	
	// Process queue
	SQL_TQuery(SQLiteDB, Query_ProcessQueue, 
		"SELECT  id, steam_id, time, start_time, reason, name, admin_id, admin_ip, type \
        FROM    queue2");
	
	// Force recheck players
	ForcePlayersRecheck();
}

public Query_AddBlockInsert(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (DB_Conn_Lost(hndl) || error[0])
	{
		LogError("Query_AddBlockInsert failed: %s", error);
		
		ResetPack(data);
		new length = ReadPackCell(data);
		new type = ReadPackCell(data);
		decl String:name[MAX_NAME_LENGTH], String:auth[64], String:adminAuth[32], String:adminIp[20];
		new String:reason[256];
		ReadPackString(data, name, sizeof(name));
		ReadPackString(data, auth, sizeof(auth));
		ReadPackString(data, reason, sizeof(reason));
		ReadPackString(data, adminAuth, sizeof(adminAuth));
		ReadPackString(data, adminIp, sizeof(adminIp));
		
		InsertTempBlock(length, type, name, auth, reason, adminAuth, adminIp);
	}
	CloseHandle(data);
}

public Query_UnBlockSelect(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl String:adminAuth[30], String:targetAuth[30];
	new String:reason[256];
	
	ResetPack(data);
	new adminUserID = ReadPackCell(data);
	new targetUserID = ReadPackCell(data);
	new type = ReadPackCell(data); // not in use unless DEBUG
	ReadPackString(data, adminAuth, sizeof(adminAuth));
	ReadPackString(data, targetAuth, sizeof(targetAuth));
	ReadPackString(data, reason, sizeof(reason));
	
	new admin = GetClientOfUserId(adminUserID);
	new target = GetClientOfUserId(targetUserID);
	
	#if defined DEBUG
	PrintToServer("Query_UnBlockSelect(adminUID: %d/%d, targetUID: %d/%d, type: %d, adminAuth: %s, targetAuth: %s, reason: %s)", 
		adminUserID, admin, targetUserID, target, type, adminAuth, targetAuth, reason);
	#endif
	
	decl String:targetName[MAX_NAME_LENGTH];
	strcopy(targetName, MAX_NAME_LENGTH, target && IsClientInGame(target) ? g_sName[target] : targetAuth); //FIXME
	
	new bool:hasErrors = false;
	// If error is not an empty string the query failed
	if (DB_Conn_Lost(hndl) || error[0] != '\0')
	{
		LogError("Query_UnBlockSelect failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%s%T", PREFIX, "Unblock Select Failed", admin, targetAuth);
			PrintToConsole(admin, "%s%T", PREFIX, "Unblock Select Failed", admin, targetAuth);
		}
		else
		{
			PrintToServer("%s%T", PREFIX, "Unblock Select Failed", LANG_SERVER, targetAuth);
		}
		hasErrors = true;
	}
	
	// If there was no results then a ban does not exist for that id
	if (!DB_Conn_Lost(hndl) && !SQL_GetRowCount(hndl))
	{
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%s%t", PREFIX, "No blocks found", targetAuth);
			PrintToConsole(admin, "%s%t", PREFIX, "No blocks found", targetAuth);
		}
		else
		{
			PrintToServer("%s%T", PREFIX, "No blocks found", LANG_SERVER, targetAuth);
		}
		hasErrors = true;
	}
	
	if (hasErrors)
	{
		#if defined DEBUG
		PrintToServer("Calling TempUnBlock from Query_UnBlockSelect");
		#endif
		
		TempUnBlock(data); // Datapack closed inside.
		return;
	}
	else
	{
		new bool:b_success = false;
		// Get the values from the founded blocks.
		while (SQL_MoreRows(hndl))
		{
			// Oh noes! What happened?!
			if (!SQL_FetchRow(hndl))
				continue;
			
			new bid = SQL_FetchInt(hndl, 0);
			new iAID = SQL_FetchInt(hndl, 1);
			new cAID = SQL_FetchInt(hndl, 2);
			new cImmunity = SQL_FetchInt(hndl, 3);
			new cType = SQL_FetchInt(hndl, 4);
			
			#if defined DEBUG
			PrintToServer("Fetched from DB: bid %d, iAID: %d, cAID: %d, cImmunity: %d, cType: %d", bid, iAID, cAID, cImmunity, cType);
			// WHO WE ARE?
			PrintToServer("WHO WE ARE CHECKING!");
			if (iAID == cAID)
				PrintToServer("we are block author");
			if (!admin)
				PrintToServer("we are console (possibly)");
			if (AdmHasFlag(admin))
				PrintToServer("we have special flag");
			if (GetAdmImmunity(admin) > cImmunity)
				PrintToServer("we have %d immunity and block has %d. we cool", GetAdmImmunity(admin), cImmunity);
			#endif
			
			// Checking - has we access to unblock?
			if (iAID == cAID || (!admin && StrEqual(adminAuth, "STEAM_ID_SERVER")) || AdmHasFlag(admin) || (DisUBImCheck == 0 && (GetAdmImmunity(admin) > cImmunity)))
			{
				// Ok! we have rights to unblock
				b_success = true;
				// UnMute/UnGag, Show & log activity
				if (target && IsClientInGame(target))
				{
					switch (cType)
					{
						case TYPE_MUTE:
						{
							PerformUnMute(target);
							LogAction(admin, target, "\"%L\" unmuted \"%L\" (reason \"%s\")", admin, target, reason);
						}
						//-------------------------------------------------------------------------------------------------
						case TYPE_GAG:
						{
							PerformUnGag(target);
							LogAction(admin, target, "\"%L\" ungagged \"%L\" (reason \"%s\")", admin, target, reason);
						}
					}
				}
				
				new Handle:dataPack = CreateDataPack();
				WritePackCell(dataPack, adminUserID);
				WritePackCell(dataPack, cType);
				WritePackString(dataPack, g_sName[target]);
				WritePackString(dataPack, targetAuth);
				
				decl String:unbanReason[sizeof(reason) * 2 + 1];
				SQL_EscapeString(g_hDatabase, reason, unbanReason, sizeof(unbanReason));
				
				decl String:query[2048];
				Format(query, sizeof(query), 
					"UPDATE  %s_comms \
                    SET     RemovedBy = %d, \
                            RemoveType = 'U', \
                            RemovedOn = UNIX_TIMESTAMP(), \
                            ureason = '%s' \
                    WHERE   bid = %d", 
					DatabasePrefix, iAID, unbanReason, bid);
				#if defined LOG_QUERIES
				LogToFile(logQuery, "Query_UnBlockSelect. QUERY: %s", query);
				#endif
				SQL_TQuery(g_hDatabase, Query_UnBlockUpdate, query, dataPack);
			}
			else
			{
				// sorry, we don't have permission to unblock!
				#if defined DEBUG
				PrintToServer("No permissions to unblock in Query_UnBlockSelect");
				#endif
				switch (cType)
				{
					case TYPE_MUTE:
					{
						if (admin && IsClientInGame(admin))
						{
							PrintToChat(admin, "%s%t", PREFIX, "No permission unmute", targetName);
							PrintToConsole(admin, "%s%t", PREFIX, "No permission unmute", targetName);
						}
						LogAction(admin, target, "\"%L\" tried (and didn't have permission) to unmute %s (reason \"%s\")", admin, targetAuth, reason);
					}
					//-------------------------------------------------------------------------------------------------
					case TYPE_GAG:
					{
						if (admin && IsClientInGame(admin))
						{
							PrintToChat(admin, "%s%t", PREFIX, "No permission ungag", targetName);
							PrintToConsole(admin, "%s%t", PREFIX, "No permission ungag", targetName);
						}
						LogAction(admin, target, "\"%L\" tried (and didn't have permission) to ungag %s (reason \"%s\")", admin, targetAuth, reason);
					}
				}
			}
		}
		
		if (b_success && target && IsClientInGame(target))
		{
			#if defined DEBUG
			PrintToServer("Showing activity to server in Query_UnBlockSelect");
			#endif
			ShowActivityToServer(admin, type, _, _, g_sName[target], _);
			
			if (type == TYPE_UNSILENCE)
			{
				// check result for possible combination with temp and time punishments (temp was skipped in code above)
				SetPackPosition(data, 16);
				if (g_MuteType[target] > bNot)
				{
					WritePackCell(data, TYPE_UNMUTE);
					TempUnBlock(data);
					data = INVALID_HANDLE;
				}
				else if (g_GagType[target] > bNot)
				{
					WritePackCell(data, TYPE_UNGAG);
					TempUnBlock(data);
					data = INVALID_HANDLE;
				}
			}
		}
	}
	if (data != INVALID_HANDLE)
		CloseHandle(data);
}

public Query_UnBlockUpdate(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	new admin, type;
	decl String:targetName[MAX_NAME_LENGTH], String:targetAuth[30];
	
	ResetPack(data);
	admin = GetClientOfUserId(ReadPackCell(data));
	type = ReadPackCell(data);
	ReadPackString(data, targetName, sizeof(targetName));
	ReadPackString(data, targetAuth, sizeof(targetAuth));
	CloseHandle(data);
	
	if (DB_Conn_Lost(hndl) || error[0] != '\0')
	{
		LogError("Query_UnBlockUpdate failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%s%t", PREFIX, "Unblock insert failed");
			PrintToConsole(admin, "%s%t", PREFIX, "Unblock insert failed");
		}
		return;
	}
	
	switch (type)
	{
		case TYPE_MUTE:
		{
			LogAction(admin, -1, "\"%L\" removed mute for %s from DB", admin, targetAuth);
			if (admin && IsClientInGame(admin))
			{
				PrintToChat(admin, "%s%t", PREFIX, "successfully unmuted", targetName);
				PrintToConsole(admin, "%s%t", PREFIX, "successfully unmuted", targetName);
			}
			else
			{
				PrintToServer("%s%T", PREFIX, "successfully unmuted", LANG_SERVER, targetName);
			}
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_GAG:
		{
			LogAction(admin, -1, "\"%L\" removed gag for %s from DB", admin, targetAuth);
			if (admin && IsClientInGame(admin)) {
				PrintToChat(admin, "%s%t", PREFIX, "successfully ungagged", targetName);
				PrintToConsole(admin, "%s%t", PREFIX, "successfully ungagged", targetName);
			}
			else
			{
				PrintToServer("%s%T", PREFIX, "successfully ungagged", LANG_SERVER, targetName);
			}
		}
	}
}

// ProcessQueueCallback is called as the result of selecting all the rows from the queue table
public Query_ProcessQueue(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE || error[0])
	{
		LogError("Query_ProcessQueue failed: %s", error);
		return;
	}
	
	decl String:auth[64];
	decl String:name[MAX_NAME_LENGTH];
	new String:reason[256];
	decl String:adminAuth[64], String:adminIp[20];
	decl String:query[4096];
	
	while (SQL_MoreRows(hndl))
	{
		// Oh noes! What happened?!
		if (!SQL_FetchRow(hndl))
			continue;
		
		decl String:sAuthEscaped[sizeof(auth) * 2 + 1];
		decl String:banName[MAX_NAME_LENGTH * 2 + 1];
		decl String:banReason[sizeof(reason) * 2 + 1];
		decl String:sAdmAuthEscaped[sizeof(adminAuth) * 2 + 1];
		decl String:sAdmAuthYZEscaped[sizeof(adminAuth) * 2 + 1];
		
		// if we get to here then there are rows in the queue pending processing
		//steam_id TEXT, time INTEGER, start_time INTEGER, reason TEXT, name TEXT, admin_id TEXT, admin_ip TEXT, type INTEGER
		new id = SQL_FetchInt(hndl, 0);
		SQL_FetchString(hndl, 1, auth, sizeof(auth));
		new time = SQL_FetchInt(hndl, 2);
		new startTime = SQL_FetchInt(hndl, 3);
		SQL_FetchString(hndl, 4, reason, sizeof(reason));
		SQL_FetchString(hndl, 5, name, sizeof(name));
		SQL_FetchString(hndl, 6, adminAuth, sizeof(adminAuth));
		SQL_FetchString(hndl, 7, adminIp, sizeof(adminIp));
		new type = SQL_FetchInt(hndl, 8);
		
		if (DB_Connect()) {
			SQL_EscapeString(g_hDatabase, auth, sAuthEscaped, sizeof(sAuthEscaped));
			SQL_EscapeString(g_hDatabase, name, banName, sizeof(banName));
			SQL_EscapeString(g_hDatabase, reason, banReason, sizeof(banReason));
			SQL_EscapeString(g_hDatabase, adminAuth, sAdmAuthEscaped, sizeof(sAdmAuthEscaped));
			SQL_EscapeString(g_hDatabase, adminAuth[8], sAdmAuthYZEscaped, sizeof(sAdmAuthYZEscaped));
		}
		else
			continue;
		// all blocks should be entered into db!
		
		FormatEx(query, sizeof(query), 
			"INSERT INTO     %s_comms (authid, name, created, ends, length, reason, aid, adminIp, sid, type) \
                VALUES         ('%s', '%s', %d, %d, %d, '%s', \
                                IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '0'), \
                                '%s', %d, %d)", 
			DatabasePrefix, sAuthEscaped, banName, startTime, (startTime + (time * 60)), (time * 60), banReason, DatabasePrefix, sAdmAuthEscaped, sAdmAuthYZEscaped, adminIp, serverID, type);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "Query_ProcessQueue. QUERY: %s", query);
		#endif
		SQL_TQuery(g_hDatabase, Query_AddBlockFromQueue, query, id);
	}
}

public Query_AddBlockFromQueue(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl String:query[512];
	if (error[0] == '\0')
	{
		// The insert was successful so delete the record from the queue
		FormatEx(query, sizeof(query), 
			"DELETE FROM queue2 \
            WHERE       id = %d", 
			data);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "Query_AddBlockFromQueue. QUERY: %s", query);
		#endif
		SQL_TQuery(SQLiteDB, Query_ErrorCheck, query);
	}
}

public Query_ErrorCheck(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (DB_Conn_Lost(hndl) || error[0])
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
}

public Query_VerifyBlock(Handle:owner, Handle:hndl, const String:error[], any:userid)
{
	decl String:clientAuth[64];
	new client = GetClientOfUserId(userid);
	
	#if defined DEBUG
	PrintToServer("Query_VerifyBlock(userid: %d, client: %d)", userid, client);
	#endif
	
	if (!client)
		return;
	
	/* Failure happen. Do retry with delay */
	if (DB_Conn_Lost(hndl))
	{
		LogError("Query_VerifyBlock failed: %s", error);
		if (g_hPlayerRecheck[client] == INVALID_HANDLE)
			g_hPlayerRecheck[client] = CreateTimer(RetryTime, ClientRecheck, userid);
		return;
	}
	
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	
	//SELECT (c.ends - UNIX_TIMESTAMP()) as remaining, c.length, c.type, c.created, c.reason, a.user,
	//IF (a.immunity>=g.immunity, a.immunity, IFNULL(g.immunity,0)) as immunity, c.aid, c.sid, c.authid
	//FROM %s_comms c LEFT JOIN %s_admins a ON a.aid=c.aid LEFT JOIN %s_srvgroups g ON g.name = a.srv_group
	//WHERE c.authid REGEXP '^STEAM_[0-9]:%s$' AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL",
	if (SQL_GetRowCount(hndl) > 0)
	{
		while (SQL_FetchRow(hndl))
		{
			if (NotApplyToThisServer(SQL_FetchInt(hndl, 8)))
				continue;
			
			decl String:sAdmName[MAX_NAME_LENGTH], String:sAdmAuth[64];
			new String:sReason[256];
			new remaining_time = SQL_FetchInt(hndl, 0);
			new length = SQL_FetchInt(hndl, 1);
			new type = SQL_FetchInt(hndl, 2);
			new time = SQL_FetchInt(hndl, 3);
			SQL_FetchString(hndl, 4, sReason, sizeof(sReason));
			SQL_FetchString(hndl, 5, sAdmName, sizeof(sAdmName));
			new immunity = SQL_FetchInt(hndl, 6);
			new aid = SQL_FetchInt(hndl, 7);
			SQL_FetchString(hndl, 9, sAdmAuth, sizeof(sAdmAuth));
			
			// Block from CONSOLE (aid=0) and we have `console immunity` value in config
			if (!aid && ConsoleImmunity > immunity)
				immunity = ConsoleImmunity;
			
			#if defined DEBUG
			PrintToServer("Fetched from DB: remaining %d, length %d, type %d", remaining_time, length, type);
			#endif
			
			switch (type)
			{
				case TYPE_MUTE:
				{
					if (g_MuteType[client] < bTime)
					{
						PerformMute(client, time, length / 60, sAdmName, sAdmAuth, immunity, sReason, remaining_time);
						PrintToChat(client, "%s%t", PREFIX, "Muted on connect");
					}
				}
				case TYPE_GAG:
				{
					if (g_GagType[client] < bTime)
					{
						PerformGag(client, time, length / 60, sAdmName, sAdmAuth, immunity, sReason, remaining_time);
						PrintToChat(client, "%s%t", PREFIX, "Gagged on connect");
					}
				}
			}
		}
	}
	
	g_bPlayerStatus[client] = true;
}


// TIMER CALL BACKS //

public Action:ClientRecheck(Handle:timer, any:userid)
{
	#if defined DEBUG
	PrintToServer("ClientRecheck(userid: %d)", userid);
	#endif
	
	new client = GetClientOfUserId(userid);
	if (!client)
		return;
	
	if (IsClientConnected(client))
		OnClientPostAdminCheck(client);
	
	g_hPlayerRecheck[client] = INVALID_HANDLE;
}

public Action:Timer_MuteExpire(Handle:timer, any:userid)
{
	new client = GetClientOfUserId(userid);
	if (!client)
		return;
	
	#if defined DEBUG
	decl String:clientAuth[64];
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	PrintToServer("Mute expired for %s", clientAuth);
	#endif
	
	PrintToChat(client, "%s%t", PREFIX, "Mute expired");
	
	g_hMuteExpireTimer[client] = INVALID_HANDLE;
	MarkClientAsUnMuted(client);
	if (IsClientInGame(client))
		BaseComm_SetClientMute(client, false);
}

public Action:Timer_GagExpire(Handle:timer, any:userid)
{
	new client = GetClientOfUserId(userid);
	if (!client)
		return;
	
	#if defined DEBUG
	decl String:clientAuth[64];
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	PrintToServer("Gag expired for %s", clientAuth);
	#endif
	
	PrintToChat(client, "%s%t", PREFIX, "Gag expired");
	
	g_hGagExpireTimer[client] = INVALID_HANDLE;
	MarkClientAsUnGagged(client);
	if (IsClientInGame(client))
		BaseComm_SetClientGag(client, false);
}

public Action:Timer_StopWait(Handle:timer, any:data)
{
	g_DatabaseState = DatabaseState_None;
	DB_Connect();
}

// PARSER //

static InitializeConfigParser()
{
	if (ConfigParser == INVALID_HANDLE)
	{
		ConfigParser = SMC_CreateParser();
		SMC_SetReaders(ConfigParser, ReadConfig_NewSection, ReadConfig_KeyValue, ReadConfig_EndSection);
	}
}

static InternalReadConfig(const String:path[])
{
	ConfigState = ConfigStateNone;
	
	new SMCError:err = SMC_ParseFile(ConfigParser, path);
	
	if (err != SMCError_Okay)
	{
		decl String:buffer[64];
		PrintToServer("%s", SMC_GetErrorString(err, buffer, sizeof(buffer)) ? buffer : "Fatal parse error");
	}
}

public SMCResult:ReadConfig_NewSection(Handle:smc, const String:name[], bool:opt_quotes)
{
	if (name[0])
	{
		if (strcmp("Config", name, false) == 0)
		{
			ConfigState = ConfigStateConfig;
		}
		else if (strcmp("CommsReasons", name, false) == 0)
		{
			ConfigState = ConfigStateReasons;
		}
		else if (strcmp("CommsTimes", name, false) == 0)
		{
			ConfigState = ConfigStateTimes;
		}
		else if (strcmp("ServersWhiteList", name, false) == 0)
		{
			ConfigState = ConfigStateServers;
		}
	}
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_KeyValue(Handle:smc, const String:key[], const String:value[], bool:key_quotes, bool:value_quotes)
{
	if (!key[0])
		return SMCParse_Continue;
	
	switch (ConfigState)
	{
		case ConfigStateConfig:
		{
			if (strcmp("DatabasePrefix", key, false) == 0)
			{
				strcopy(DatabasePrefix, sizeof(DatabasePrefix), value);
				
				if (DatabasePrefix[0] == '\0')
				{
					DatabasePrefix = "sb";
				}
			}
			else if (strcmp("RetryTime", key, false) == 0)
			{
				RetryTime = StringToFloat(value);
				if (RetryTime < 15.0)
				{
					RetryTime = 15.0;
				}
				else if (RetryTime > 60.0)
				{
					RetryTime = 60.0;
				}
			}
			else if (strcmp("ServerID", key, false) == 0)
			{
				if (!StringToIntEx(value, serverID) || serverID < 1)
				{
					serverID = 0;
				}
			}
			else if (strcmp("DefaultTime", key, false) == 0)
			{
				DefaultTime = StringToInt(value);
				if (DefaultTime < 0)
				{
					DefaultTime = -1;
				}
				if (DefaultTime == 0)
				{
					DefaultTime = 30;
				}
			}
			else if (strcmp("DisableUnblockImmunityCheck", key, false) == 0)
			{
				DisUBImCheck = StringToInt(value);
				if (DisUBImCheck != 1)
				{
					DisUBImCheck = 0;
				}
			}
			else if (strcmp("ConsoleImmunity", key, false) == 0)
			{
				ConsoleImmunity = StringToInt(value);
				if (ConsoleImmunity < 0 || ConsoleImmunity > 100)
				{
					ConsoleImmunity = 0;
				}
			}
			else if (strcmp("MaxLength", key, false) == 0)
			{
				ConfigMaxLength = StringToInt(value);
			}
			else if (strcmp("OnlyWhiteListServers", key, false) == 0)
			{
				ConfigWhiteListOnly = StringToInt(value);
				if (ConfigWhiteListOnly != 1)
				{
					ConfigWhiteListOnly = 0;
				}
			}
		}
		case ConfigStateReasons:
		{
			Format(g_sReasonKey[iNumReasons], REASON_SIZE, "%s", key);
			Format(g_sReasonDisplays[iNumReasons], DISPLAY_SIZE, "%s", value);
			#if defined DEBUG
			PrintToServer("Loaded reason. index %d, key \"%s\", display_text \"%s\"", iNumReasons, g_sReasonKey[iNumReasons], g_sReasonDisplays[iNumReasons]);
			#endif
			iNumReasons++;
		}
		case ConfigStateTimes:
		{
			Format(g_sTimeDisplays[iNumTimes], DISPLAY_SIZE, "%s", value);
			g_iTimeMinutes[iNumTimes] = StringToInt(key);
			#if defined DEBUG
			PrintToServer("Loaded time. index %d, time %d minutes, display_text \"%s\"", iNumTimes, g_iTimeMinutes[iNumTimes], g_sTimeDisplays[iNumTimes]);
			#endif
			iNumTimes++;
		}
		case ConfigStateServers:
		{
			if (strcmp("id", key, false) == 0)
			{
				new srvID = StringToInt(value);
				if (srvID >= 0)
				{
					PushArrayCell(g_hServersWhiteList, srvID);
					#if defined DEBUG
					PrintToServer("Loaded white list server id %d", srvID);
					#endif
				}
			}
		}
	}
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_EndSection(Handle:smc)
{
	return SMCParse_Continue;
}

// STOCK FUNCTIONS //
stock setGag(client, length, const String:clientAuth[])
{
	if (g_GagType[client] == bNot)
	{
		PerformGag(client, _, length / 60, _, _, _, _);
		PrintToChat(client, "%s%t", PREFIX, "Gagged on connect");
		LogMessage("%s is gagged from web", clientAuth);
	}
}

stock setMute(client, length, const String:clientAuth[])
{
	if (g_MuteType[client] == bNot)
	{
		PerformMute(client, _, length / 60, _, _, _, _);
		PrintToChat(client, "%s%t", PREFIX, "Muted on connect");
		LogMessage("%s is muted from web", clientAuth);
	}
}

stock bool:DB_Connect()
{
	#if defined DEBUG
	PrintToServer("DB_Connect(handle %d, state %d, lock %d)", g_hDatabase, g_DatabaseState, g_iConnectLock);
	#endif
	
	if (g_hDatabase)
	{
		return true;
	}
	
	if (g_DatabaseState == DatabaseState_Wait) // 100500 connections in a minute is bad idea..
	{
		return false;
	}
	
	if (g_DatabaseState != DatabaseState_Connecting)
	{
		g_DatabaseState = DatabaseState_Connecting;
		g_iConnectLock = ++g_iSequence;
		// Connect using the "sourcebans" section, or the "default" section if "sourcebans" does not exist
		SQL_TConnect(GotDatabase, DATABASE, g_iConnectLock);
	}
	
	return false;
}

stock bool:DB_Conn_Lost(Handle:hndl)
{
	if (hndl == INVALID_HANDLE)
	{
		if (g_hDatabase != INVALID_HANDLE)
		{
			LogError("Lost connection to DB. Reconnect after delay.");
			CloseHandle(g_hDatabase);
			g_hDatabase = INVALID_HANDLE;
		}
		if (g_DatabaseState != DatabaseState_Wait)
		{
			g_DatabaseState = DatabaseState_Wait;
			CreateTimer(RetryTime, Timer_StopWait, _, TIMER_FLAG_NO_MAPCHANGE);
		}
		return true;
	}
	
	return false;
}

stock InitializeBackupDB()
{
	decl String:error[255];
	SQLiteDB = SQLite_UseDatabase("sourcecomms-queue", error, sizeof(error));
	if (SQLiteDB == INVALID_HANDLE)
	{
		SetFailState(error);
	}
	
	SQL_TQuery(SQLiteDB, Query_ErrorCheck, 
		"CREATE TABLE IF NOT EXISTS queue2 ( \
            id INTEGER PRIMARY KEY, \
            steam_id TEXT, \
            time INTEGER, \
            start_time INTEGER, \
            reason TEXT, \
            name TEXT, \
            admin_id TEXT, \
            admin_ip TEXT, \
            type INTEGER)");
}

stock CreateBlock(client, targetId = 0, length = -1, type, const String:sReason[] = "", const String:sArgs[] = "")
{
	#if defined DEBUG
	PrintToServer("CreateBlock(admin: %d, target: %d, length: %d, type: %d, reason: %s, args: %s)", client, targetId, length, type, sReason, sArgs);
	#endif
	
	decl target_list[MAXPLAYERS], target_count, bool:tn_is_ml, String:target_name[MAX_NAME_LENGTH];
	new String:reason[256];
	new bool:skipped = false;
	
	// checking args
	if (targetId)
	{
		target_list[0] = targetId;
		target_count = 1;
		tn_is_ml = false;
		strcopy(target_name, sizeof(target_name), g_sName[targetId]);
		strcopy(reason, sizeof(reason), sReason);
	}
	else if (strlen(sArgs))
	{
		new String:sArg[3][192];
		
		if (ExplodeString(sArgs, "\"", sArg, 3, 192, true) == 3 && strlen(sArg[0]) == 0) // exploding by quotes
		{
			decl String:sTempArg[2][192];
			TrimString(sArg[2]);
			sArg[0] = sArg[1]; // target name
			ExplodeString(sArg[2], " ", sTempArg, 2, 192, true); // get length and reason
			sArg[1] = sTempArg[0]; // lenght
			sArg[2] = sTempArg[1]; // reason
		}
		else
		{
			ExplodeString(sArgs, " ", sArg, 3, 192, true); // exploding by spaces
		}
		
		// Get the target, find target returns a message on failure so we do not
		if ((target_count = ProcessTargetString(
					sArg[0], 
					client, 
					target_list, 
					MAXPLAYERS, 
					COMMAND_FILTER_NO_BOTS, 
					target_name, 
					sizeof(target_name), 
					tn_is_ml)) <= 0)
		{
			ReplyToTargetError(client, target_count);
			return;
		}
		
		// Get the block length
		if (!StringToIntEx(sArg[1], length)) // not valid number in second argument
		{
			length = DefaultTime;
			Format(reason, sizeof(reason), "%s %s", sArg[1], sArg[2]);
		}
		else
		{
			strcopy(reason, sizeof(reason), sArg[2]);
		}
		
		// Strip spaces and quotes from reason
		TrimString(reason);
		StripQuotes(reason);
		
		if (!IsAllowedBlockLength(client, length, target_count))
		{
			ReplyToCommand(client, "%s%t", PREFIX, "no access");
			return;
		}
	}
	else
	{
		return;
	}
	
	new admImmunity = GetAdmImmunity(client);
	decl String:adminAuth[64];
	
	if (client && IsClientInGame(client))
	{
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}
	else
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
	}
	
	for (new i = 0; i < target_count; i++)
	{
		new target = target_list[i];
		
		#if defined DEBUG
		decl String:auth[64];
		GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth));
		PrintToServer("Processing block for %s", auth);
		#endif
		
		if (!g_bPlayerStatus[target])
		{
			// The target has not been blocks verify. It must be completed before you can block anyone.
			ReplyToCommand(client, "%s%t", PREFIX, "Player Comms Not Verified");
			skipped = true;
			continue; // skip
		}
		
		switch (type)
		{
			case TYPE_MUTE:
			{
				if (!BaseComm_IsClientMuted(target))
				{
					#if defined DEBUG
					PrintToServer("%s not muted. Mute him, creating unmute timer and add record to DB", auth);
					#endif
					
					PerformMute(target, _, length, g_sName[client], adminAuth, admImmunity, reason);
					
					LogAction(client, target, "\"%L\" muted \"%L\" (minutes \"%d\") (reason \"%s\")", client, target, length, reason);
				}
				else
				{
					#if defined DEBUG
					PrintToServer("%s already muted", auth);
					#endif
					
					ReplyToCommand(client, "%s%t", PREFIX, "Player already muted", g_sName[target]);
					
					skipped = true;
					continue;
				}
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_GAG:
			{
				if (!BaseComm_IsClientGagged(target))
				{
					#if defined DEBUG
					PrintToServer("%s not gagged. Gag him, creating ungag timer and add record to DB", auth);
					#endif
					
					PerformGag(target, _, length, g_sName[client], adminAuth, admImmunity, reason);
					
					LogAction(client, target, "\"%L\" gagged \"%L\" (minutes \"%d\") (reason \"%s\")", client, target, length, reason);
				}
				else
				{
					#if defined DEBUG
					PrintToServer("%s already gagged", auth);
					#endif
					
					ReplyToCommand(client, "%s%t", PREFIX, "Player already gagged", g_sName[target]);
					
					skipped = true;
					continue;
				}
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_SILENCE:
			{
				if (!BaseComm_IsClientGagged(target) && !BaseComm_IsClientMuted(target))
				{
					#if defined DEBUG
					PrintToServer("%s not silenced. Silence him, creating ungag & unmute timers and add records to DB", auth);
					#endif
					
					PerformMute(target, _, length, g_sName[client], adminAuth, admImmunity, reason);
					PerformGag(target, _, length, g_sName[client], adminAuth, admImmunity, reason);
					
					LogAction(client, target, "\"%L\" silenced \"%L\" (minutes \"%d\") (reason \"%s\")", client, target, length, reason);
				}
				else
				{
					#if defined DEBUG
					PrintToServer("%s already gagged or/and muted", auth);
					#endif
					
					ReplyToCommand(client, "%s%t", PREFIX, "Player already silenced", g_sName[target]);
					
					skipped = true;
					continue;
				}
			}
		}
	}
	if (target_count == 1 && !skipped)
		SavePunishment(client, target_list[0], type, length, reason);
	if (target_count > 1 || !skipped)
		ShowActivityToServer(client, type, length, reason, target_name, tn_is_ml);
	
	return;
}

stock ProcessUnBlock(client, targetId = 0, type, String:sReason[] = "", const String:sArgs[] = "")
{
	#if defined DEBUG
	PrintToServer("ProcessUnBlock(admin: %d, target: %d, type: %d, reason: %s, args: %s)", client, targetId, type, sReason, sArgs);
	#endif
	
	decl target_list[MAXPLAYERS], target_count, bool:tn_is_ml, String:target_name[MAX_NAME_LENGTH];
	new String:reason[256];
	
	if (targetId)
	{
		target_list[0] = targetId;
		target_count = 1;
		tn_is_ml = false;
		strcopy(target_name, sizeof(target_name), g_sName[targetId]);
		strcopy(reason, sizeof(reason), sReason);
	}
	else
	{
		decl String:sBuffer[256];
		new String:sArg[3][192];
		GetCmdArgString(sBuffer, sizeof(sBuffer));
		
		if (ExplodeString(sBuffer, "\"", sArg, 3, 192, true) == 3 && strlen(sArg[0]) == 0)
		{
			TrimString(sArg[2]);
			sArg[0] = sArg[1]; // target name
			sArg[1] = sArg[2]; // reason; sArg[2] - not in use
		}
		else
		{
			ExplodeString(sBuffer, " ", sArg, 2, 192, true);
		}
		strcopy(reason, sizeof(reason), sArg[1]);
		// Strip spaces and quotes from reason
		TrimString(reason);
		StripQuotes(reason);
		
		// Get the target, find target returns a message on failure so we do not
		if ((target_count = ProcessTargetString(
					sArg[0], 
					client, 
					target_list, 
					MAXPLAYERS, 
					COMMAND_FILTER_NO_BOTS, 
					target_name, 
					sizeof(target_name), 
					tn_is_ml)) <= 0)
		{
			ReplyToTargetError(client, target_count);
			return;
		}
	}
	
	decl String:adminAuth[64];
	decl String:targetAuth[64];
	
	if (client && IsClientInGame(client))
	{
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}
	else
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
	}
	
	if (target_count > 1)
	{
		#if defined DEBUG
		PrintToServer("ProcessUnBlock - targets_count > 1");
		#endif
		
		for (new i = 0; i < target_count; i++)
		{
			new target = target_list[i];
			
			if (IsClientInGame(target))
				GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth));
			else
				continue;
			
			switch (type)
			{
				case TYPE_UNMUTE:
				{
					if (g_MuteType[target] == bTime || g_MuteType[target] == bPerm)
						continue;
				}
				case TYPE_UNGAG:
				{
					if (g_GagType[target] == bTime || g_GagType[target] == bPerm)
						continue;
				}
				case TYPE_UNSILENCE:
				{
					if ((g_MuteType[target] == bTime || g_MuteType[target] == bPerm) && 
						(g_GagType[target] == bTime || g_GagType[target] == bPerm))
					continue;
				}
			}
			
			new Handle:dataPack = CreateDataPack();
			WritePackCell(dataPack, GetClientUserId2(client));
			WritePackCell(dataPack, GetClientUserId(target));
			WritePackCell(dataPack, type);
			WritePackString(dataPack, adminAuth);
			WritePackString(dataPack, targetAuth); // not in use in this case
			WritePackString(dataPack, reason);
			
			TempUnBlock(dataPack);
		}
		
		#if defined DEBUG
		PrintToServer("Showing activity to server in ProcessUnBlock for targets_count > 1");
		#endif
		ShowActivityToServer(client, type + TYPE_TEMP_SHIFT, _, _, target_name, tn_is_ml);
	}
	else
	{
		decl String:typeWHERE[100];
		new bool:dontCheckDB = false;
		new target = target_list[0];
		
		if (IsClientInGame(target))
		{
			GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth));
		}
		else
		{
			return;
		}
		
		switch (type)
		{
			case TYPE_UNMUTE:
			{
				if (!BaseComm_IsClientMuted(target))
				{
					ReplyToCommand(client, "%s%t", PREFIX, "Player not muted");
					return;
				}
				else
				{
					FormatEx(typeWHERE, sizeof(typeWHERE), "c.type = '%d'", TYPE_MUTE);
					if (g_MuteType[target] == bSess)
						dontCheckDB = true;
				}
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_UNGAG:
			{
				if (!BaseComm_IsClientGagged(target))
				{
					ReplyToCommand(client, "%s%t", PREFIX, "Player not gagged");
					return;
				}
				else
				{
					FormatEx(typeWHERE, sizeof(typeWHERE), "c.type = '%d'", TYPE_GAG);
					if (g_GagType[target] == bSess)
						dontCheckDB = true;
				}
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_UNSILENCE:
			{
				if (!BaseComm_IsClientMuted(target) || !BaseComm_IsClientGagged(target))
				{
					ReplyToCommand(client, "%s%t", PREFIX, "Player not silenced");
					return;
				}
				else
				{
					FormatEx(typeWHERE, sizeof(typeWHERE), "(c.type = '%d' OR c.type = '%d')", TYPE_MUTE, TYPE_GAG);
					if (g_MuteType[target] == bSess && g_GagType[target] == bSess)
						dontCheckDB = true;
				}
			}
		}
		
		// Pack everything into a data pack so we can retain it
		new Handle:dataPack = CreateDataPack();
		WritePackCell(dataPack, GetClientUserId2(client));
		WritePackCell(dataPack, GetClientUserId(target));
		WritePackCell(dataPack, type);
		WritePackString(dataPack, adminAuth);
		WritePackString(dataPack, targetAuth);
		WritePackString(dataPack, reason);
		
		// Check current player status. If player has temporary punishment - don't get info from DB
		if (!dontCheckDB && DB_Connect())
		{
			decl String:sAdminAuthEscaped[sizeof(adminAuth) * 2 + 1];
			decl String:sAdminAuthYZEscaped[sizeof(adminAuth) * 2 + 1];
			decl String:sTargetAuthEscaped[sizeof(targetAuth) * 2 + 1];
			decl String:sTargetAuthYZEscaped[sizeof(targetAuth) * 2 + 1];
			
			SQL_EscapeString(g_hDatabase, adminAuth, sAdminAuthEscaped, sizeof(sAdminAuthEscaped));
			SQL_EscapeString(g_hDatabase, adminAuth[8], sAdminAuthYZEscaped, sizeof(sAdminAuthYZEscaped));
			SQL_EscapeString(g_hDatabase, targetAuth, sTargetAuthEscaped, sizeof(sTargetAuthEscaped));
			SQL_EscapeString(g_hDatabase, targetAuth[8], sTargetAuthYZEscaped, sizeof(sTargetAuthYZEscaped));
			
			decl String:query[4096];
			Format(query, sizeof(query), 
				"SELECT      c.bid, \
                            IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '0') as iaid, \
                            c.aid, \
                            IF (a.immunity>=g.immunity, a.immunity, IFNULL(g.immunity,0)) as immunity, \
                            c.type \
                FROM        %s_comms     AS c \
                LEFT JOIN   %s_admins    AS a ON a.aid = c.aid \
                LEFT JOIN   %s_srvgroups AS g ON g.name = a.srv_group \
                WHERE       RemoveType IS NULL \
                              AND (c.authid = '%s' OR c.authid REGEXP '^STEAM_[0-9]:%s$') \
                              AND (length = '0' OR ends > UNIX_TIMESTAMP()) \
                              AND %s", 
				DatabasePrefix, sAdminAuthEscaped, sAdminAuthYZEscaped, DatabasePrefix, DatabasePrefix, DatabasePrefix, sTargetAuthEscaped, sTargetAuthYZEscaped, typeWHERE);
			
			#if defined LOG_QUERIES
			LogToFile(logQuery, "ProcessUnBlock. QUERY: %s", query);
			#endif
			
			SQL_TQuery(g_hDatabase, Query_UnBlockSelect, query, dataPack);
		}
		else
		{
			#if defined DEBUG
			PrintToServer("Calling TempUnBlock from ProcessUnBlock");
			#endif
			
			if (TempUnBlock(dataPack))
				ShowActivityToServer(client, type + TYPE_TEMP_SHIFT, _, _, g_sName[target], _);
		}
	}
}

stock bool:TempUnBlock(Handle:data)
{
	decl String:adminAuth[30], String:targetAuth[30];
	new String:reason[256];
	ResetPack(data);
	new adminUserID = ReadPackCell(data);
	new targetUserID = ReadPackCell(data);
	new type = ReadPackCell(data);
	ReadPackString(data, adminAuth, sizeof(adminAuth));
	ReadPackString(data, targetAuth, sizeof(targetAuth));
	ReadPackString(data, reason, sizeof(reason));
	CloseHandle(data); // Need to close datapack
	
	#if defined DEBUG
	PrintToServer("TempUnBlock(adminUID: %d, targetUID: %d, type: %d, adminAuth: %s, targetAuth: %s, reason: %s)", adminUserID, targetUserID, type, adminAuth, targetAuth, reason);
	#endif
	
	new admin = GetClientOfUserId(adminUserID);
	new target = GetClientOfUserId(targetUserID);
	if (!target)
		return false; // target has gone away
	
	new AdmImmunity = GetAdmImmunity(admin);
	new bool:AdmImCheck = (DisUBImCheck == 0
		 && ((type == TYPE_UNMUTE && AdmImmunity >= g_iMuteLevel[target])
			 || (type == TYPE_UNGAG && AdmImmunity >= g_iGagLevel[target])
			 || (type == TYPE_UNSILENCE && AdmImmunity >= g_iMuteLevel[target]
				 && AdmImmunity >= g_iGagLevel[target])
			)
		);
	
	#if defined DEBUG
	PrintToServer("WHO WE ARE CHECKING!");
	if (!admin)
		PrintToServer("we are console (possibly)");
	if (AdmHasFlag(admin))
		PrintToServer("we have special flag");
	#endif
	
	// Check access for unblock without db changes (temporary unblock)
	new bool:bHasPermission = (!admin && StrEqual(adminAuth, "STEAM_ID_SERVER")) || AdmHasFlag(admin) || AdmImCheck;
	// can, if we are console or have special flag. else - deep checking by issuer authid
	if (!bHasPermission) {
		switch (type)
		{
			case TYPE_UNMUTE:
			{
				bHasPermission = StrEqual(adminAuth, g_sMuteAdminAuth[target]);
			}
			case TYPE_UNGAG:
			{
				bHasPermission = StrEqual(adminAuth, g_sGagAdminAuth[target]);
			}
			case TYPE_UNSILENCE:
			{
				bHasPermission = StrEqual(adminAuth, g_sMuteAdminAuth[target]) && StrEqual(adminAuth, g_sGagAdminAuth[target]);
			}
		}
	}
	
	if (bHasPermission)
	{
		switch (type)
		{
			case TYPE_UNMUTE:
			{
				PerformUnMute(target);
				LogAction(admin, target, "\"%L\" temporary unmuted \"%L\" (reason \"%s\")", admin, target, reason);
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_UNGAG:
			{
				PerformUnGag(target);
				LogAction(admin, target, "\"%L\" temporary ungagged \"%L\" (reason \"%s\")", admin, target, reason);
			}
			//-------------------------------------------------------------------------------------------------
			case TYPE_UNSILENCE:
			{
				PerformUnMute(target);
				PerformUnGag(target);
				LogAction(admin, target, "\"%L\" temporary unsilenced \"%L\" (reason \"%s\")", admin, target, reason);
			}
			default:
			{
				return false;
			}
		}
		return true;
	}
	else
	{
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%s%t", PREFIX, "No db error unlock perm");
			PrintToConsole(admin, "%s%t", PREFIX, "No db error unlock perm");
		}
		return false;
	}
}

stock InsertTempBlock(length, type, const String:name[], const String:auth[], const String:reason[], const String:adminAuth[], const String:adminIp[])
{
	LogMessage("Saving punishment for %s into queue", auth);
	
	decl String:banName[MAX_NAME_LENGTH * 2 + 1];
	decl String:banReason[256 * 2 + 1];
	decl String:sAuthEscaped[64 * 2 + 1];
	decl String:sAdminAuthEscaped[64 * 2 + 1];
	decl String:sQuery[4096], String:sQueryVal[2048];
	new String:sQueryMute[2048], String:sQueryGag[2048];
	
	// escaping everything
	SQL_EscapeString(SQLiteDB, name, banName, sizeof(banName));
	SQL_EscapeString(SQLiteDB, reason, banReason, sizeof(banReason));
	SQL_EscapeString(SQLiteDB, auth, sAuthEscaped, sizeof(sAuthEscaped));
	SQL_EscapeString(SQLiteDB, adminAuth, sAdminAuthEscaped, sizeof(sAdminAuthEscaped));
	
	// steam_id time start_time reason name admin_id admin_ip
	FormatEx(sQueryVal, sizeof(sQueryVal), 
		"'%s', %d, %d, '%s', '%s', '%s', '%s'", 
		sAuthEscaped, length, GetTime(), banReason, banName, sAdminAuthEscaped, adminIp);
	
	switch (type)
	{
		case TYPE_MUTE:FormatEx(sQueryMute, sizeof(sQueryMute), "(%s, %d)", sQueryVal, type);
		case TYPE_GAG:FormatEx(sQueryGag, sizeof(sQueryGag), "(%s, %d)", sQueryVal, type);
		case TYPE_SILENCE:
		{
			FormatEx(sQueryMute, sizeof(sQueryMute), "(%s, %d)", sQueryVal, TYPE_MUTE);
			FormatEx(sQueryGag, sizeof(sQueryGag), "(%s, %d)", sQueryVal, TYPE_GAG);
		}
	}
	
	FormatEx(sQuery, sizeof(sQuery), 
		"INSERT INTO queue2 (steam_id, time, start_time, reason, name, admin_id, admin_ip, type) VALUES %s%s%s", 
		sQueryMute, type == TYPE_SILENCE ? ", " : "", sQueryGag);
	
	#if defined LOG_QUERIES
	LogToFile(logQuery, "InsertTempBlock. QUERY: %s", sQuery);
	#endif
	
	SQL_TQuery(SQLiteDB, Query_ErrorCheck, sQuery);
}

stock ServerInfo()
{
	decl pieces[4];
	new longip = GetConVarInt(CvarHostIp);
	pieces[0] = (longip >> 24) & 0x000000FF;
	pieces[1] = (longip >> 16) & 0x000000FF;
	pieces[2] = (longip >> 8) & 0x000000FF;
	pieces[3] = longip & 0x000000FF;
	FormatEx(ServerIp, sizeof(ServerIp), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);
	GetConVarString(CvarPort, ServerPort, sizeof(ServerPort));
}

stock ReadConfig()
{
	InitializeConfigParser();
	
	if (ConfigParser == INVALID_HANDLE)
	{
		return;
	}
	
	decl String:ConfigFile1[PLATFORM_MAX_PATH], String:ConfigFile2[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, ConfigFile1, sizeof(ConfigFile1), "configs/sourcebans/sourcebans.cfg");
	BuildPath(Path_SM, ConfigFile2, sizeof(ConfigFile2), "configs/sourcebans/sourcecomms.cfg");
	
	if (FileExists(ConfigFile1))
	{
		PrintToServer("%sLoading configs/sourcebans/sourcebans.cfg config file", PREFIX);
		InternalReadConfig(ConfigFile1);
	}
	else
	{
		SetFailState("FATAL *** ERROR *** can't find %s", ConfigFile1);
	}
	if (FileExists(ConfigFile2))
	{
		PrintToServer("%sLoading configs/sourcecomms.cfg config file", PREFIX);
		iNumReasons = 0;
		iNumTimes = 0;
		InternalReadConfig(ConfigFile2);
		if (iNumReasons)
			iNumReasons--;
		if (iNumTimes)
			iNumTimes--;
		if (serverID == 0)
		{
			LogError("You must set valid `ServerID` value in sourcebans.cfg!");
			if (ConfigWhiteListOnly)
			{
				LogError("ServersWhiteList feature disabled!");
				ConfigWhiteListOnly = 0;
			}
		}
	}
	else
	{
		SetFailState("FATAL *** ERROR *** can't find %s", ConfigFile2);
	}
	#if defined DEBUG
	PrintToServer("Loaded DefaultTime value: %d", DefaultTime);
	PrintToServer("Loaded DisableUnblockImmunityCheck value: %d", DisUBImCheck);
	#endif
}


// some more

AdminMenu_GetPunishPhrase(client, target, String:name[], length)
{
	decl String:Buffer[192];
	if (g_MuteType[target] > bNot && g_GagType[target] > bNot)
		Format(Buffer, sizeof(Buffer), "%T", "AdminMenu_Display_Silenced", client, name);
	else if (g_MuteType[target] > bNot)
		Format(Buffer, sizeof(Buffer), "%T", "AdminMenu_Display_Muted", client, name);
	else if (g_GagType[target] > bNot)
		Format(Buffer, sizeof(Buffer), "%T", "AdminMenu_Display_Gagged", client, name);
	else
		Format(Buffer, sizeof(Buffer), "%T", "AdminMenu_Display_None", client, name);
	
	strcopy(name, length, Buffer);
}

bool:Bool_ValidMenuTarget(client, target)
{
	if (target <= 0)
	{
		if (client)
			PrintToChat(client, "%s%t", PREFIX, "AdminMenu_Not_Available");
		else
			ReplyToCommand(client, "%s%t", PREFIX, "AdminMenu_Not_Available");
		
		return false;
	}
	else if (!CanUserTarget(client, target))
	{
		if (client)
			PrintToChat(client, "%s%t", PREFIX, "Command_Target_Not_Targetable");
		else
			ReplyToCommand(client, "%s%t", PREFIX, "Command_Target_Not_Targetable");
		
		return false;
	}
	
	return true;
}

stock bool:IsAllowedBlockLength(admin, length, target_count = 1)
{
	if (target_count == 1)
	{
		// Restriction disabled, all allowed for console, all allowed for admins with special flag
		if (!ConfigMaxLength || !admin || AdmHasFlag(admin))
			return true;
		
		//return false if one of these statements evaluates to true; otherwise, return true
		return !(!length || length > ConfigMaxLength);
	}
	else
	{
		if (length < 0) //'session punishments allowed for mass-targeting'
			return true;
		
		//return false if one of these statements evaluates to true; otherwise, return true
		return !(!length || length > MAX_TIME_MULTI || length > DefaultTime);
	}
}

stock bool:AdmHasFlag(admin)
{
	return admin && CheckCommandAccess(admin, "", UNBLOCK_FLAG, true);
}

stock _:GetAdmImmunity(admin)
{
	return admin > 0 && GetUserAdmin(admin) != INVALID_ADMIN_ID ? 
	GetAdminImmunityLevel(GetUserAdmin(admin)) : 0;
}

stock _:GetClientUserId2(client)
{
	return client ? GetClientUserId(client) : 0; // 0 is for CONSOLE
}

stock ForcePlayersRecheck()
{
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i) && g_hPlayerRecheck[i] == INVALID_HANDLE)
		{
			#if defined DEBUG
			{
				decl String:clientAuth[64];
				GetClientAuthId(i, AuthId_Steam2, clientAuth, sizeof(clientAuth));
				PrintToServer("Creating Recheck timer for %s", clientAuth);
			}
			#endif
			g_hPlayerRecheck[i] = CreateTimer(float(i), ClientRecheck, GetClientUserId(i));
		}
	}
}

stock bool:NotApplyToThisServer(srvID)
{
	return ConfigWhiteListOnly && FindValueInArray(g_hServersWhiteList, srvID) == -1;
}

stock MarkClientAsUnMuted(target)
{
	g_MuteType[target] = bNot;
	g_iMuteTime[target] = 0;
	g_iMuteLength[target] = 0;
	g_iMuteLevel[target] = -1;
	g_sMuteAdminName[target][0] = '\0';
	g_sMuteReason[target][0] = '\0';
	g_sMuteAdminAuth[target][0] = '\0';
}

stock MarkClientAsUnGagged(target)
{
	g_GagType[target] = bNot;
	g_iGagTime[target] = 0;
	g_iGagLength[target] = 0;
	g_iGagLevel[target] = -1;
	g_sGagAdminName[target][0] = '\0';
	g_sGagReason[target][0] = '\0';
	g_sGagAdminAuth[target][0] = '\0';
}

stock MarkClientAsMuted(target, time = NOW, length = -1, const String:adminName[] = "CONSOLE", const String:adminAuth[] = "STEAM_ID_SERVER", adminImmunity = 0, const String:reason[] = "")
{
	if (time)
		g_iMuteTime[target] = time;
	else
		g_iMuteTime[target] = GetTime();
	
	g_iMuteLength[target] = length;
	g_iMuteLevel[target] = adminImmunity ? adminImmunity : ConsoleImmunity;
	strcopy(g_sMuteAdminName[target], sizeof(g_sMuteAdminName[]), adminName);
	strcopy(g_sMuteReason[target], sizeof(g_sMuteReason[]), reason);
	strcopy(g_sMuteAdminAuth[target], sizeof(g_sMuteAdminAuth[]), adminAuth);
	
	if (length > 0)
		g_MuteType[target] = bTime;
	else if (length == 0)
		g_MuteType[target] = bPerm;
	else
		g_MuteType[target] = bSess;
}

stock MarkClientAsGagged(target, time = NOW, length = -1, const String:adminName[] = "CONSOLE", const String:adminAuth[] = "STEAM_ID_SERVER", adminImmunity = 0, const String:reason[] = "")
{
	if (time)
		g_iGagTime[target] = time;
	else
		g_iGagTime[target] = GetTime();
	
	g_iGagLength[target] = length;
	g_iGagLevel[target] = adminImmunity ? adminImmunity : ConsoleImmunity;
	strcopy(g_sGagAdminName[target], sizeof(g_sGagAdminName[]), adminName);
	strcopy(g_sGagReason[target], sizeof(g_sGagReason[]), reason);
	strcopy(g_sGagAdminAuth[target], sizeof(g_sGagAdminAuth[]), adminAuth);
	
	if (length > 0)
		g_GagType[target] = bTime;
	else if (length == 0)
		g_GagType[target] = bPerm;
	else
		g_GagType[target] = bSess;
}

stock CloseMuteExpireTimer(target)
{
	if (g_hMuteExpireTimer[target] != INVALID_HANDLE && CloseHandle(g_hMuteExpireTimer[target]))
		g_hMuteExpireTimer[target] = INVALID_HANDLE;
}

stock CloseGagExpireTimer(target)
{
	if (g_hGagExpireTimer[target] != INVALID_HANDLE && CloseHandle(g_hGagExpireTimer[target]))
		g_hGagExpireTimer[target] = INVALID_HANDLE;
}

stock CreateMuteExpireTimer(target, remainingTime = 0)
{
	if (g_iMuteLength[target] > 0)
	{
		if (remainingTime)
			g_hMuteExpireTimer[target] = CreateTimer(float(remainingTime), Timer_MuteExpire, GetClientUserId(target), TIMER_FLAG_NO_MAPCHANGE);
		else
			g_hMuteExpireTimer[target] = CreateTimer(float(g_iMuteLength[target] * 60), Timer_MuteExpire, GetClientUserId(target), TIMER_FLAG_NO_MAPCHANGE);
	}
}

stock CreateGagExpireTimer(target, remainingTime = 0)
{
	if (g_iGagLength[target] > 0)
	{
		if (remainingTime)
			g_hGagExpireTimer[target] = CreateTimer(float(remainingTime), Timer_GagExpire, GetClientUserId(target), TIMER_FLAG_NO_MAPCHANGE);
		else
			g_hGagExpireTimer[target] = CreateTimer(float(g_iGagLength[target] * 60), Timer_GagExpire, GetClientUserId(target), TIMER_FLAG_NO_MAPCHANGE);
	}
}

stock PerformUnMute(target)
{
	MarkClientAsUnMuted(target);
	BaseComm_SetClientMute(target, false);
	CloseMuteExpireTimer(target);
}

stock PerformUnGag(target)
{
	MarkClientAsUnGagged(target);
	BaseComm_SetClientGag(target, false);
	CloseGagExpireTimer(target);
}

stock PerformMute(target, time = NOW, length = -1, const String:adminName[] = "CONSOLE", const String:adminAuth[] = "STEAM_ID_SERVER", adminImmunity = 0, const String:reason[] = "", remaining_time = 0)
{
	MarkClientAsMuted(target, time, length, adminName, adminAuth, adminImmunity, reason);
	BaseComm_SetClientMute(target, true);
	CreateMuteExpireTimer(target, remaining_time);
}

stock PerformGag(target, time = NOW, length = -1, const String:adminName[] = "CONSOLE", const String:adminAuth[] = "STEAM_ID_SERVER", adminImmunity = 0, const String:reason[] = "", remaining_time = 0)
{
	MarkClientAsGagged(target, time, length, adminName, adminAuth, adminImmunity, reason);
	BaseComm_SetClientGag(target, true);
	CreateGagExpireTimer(target, remaining_time);
}

stock SavePunishment(admin = 0, target, type, length = -1, const String:reason[] = "")
{
	if (type < TYPE_MUTE || type > TYPE_SILENCE)
		return;
	
	// target information
	decl String:targetAuth[64];
	if (IsClientInGame(target))
	{
		GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth));
	}
	else
	{
		return;
	}
	
	decl String:adminIp[24];
	decl String:adminAuth[64];
	if (admin && IsClientInGame(admin))
	{
		GetClientIP(admin, adminIp, sizeof(adminIp));
		GetClientAuthId(admin, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}
	else
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
		strcopy(adminIp, sizeof(adminIp), ServerIp);
	}
	
	decl String:sName[MAX_NAME_LENGTH];
	strcopy(sName, sizeof(sName), g_sName[target]);
	
	if (DB_Connect())
	{
		// Accepts length in minutes, writes to db in seconds! In all over places in plugin - length is in minutes.
		decl String:banName[MAX_NAME_LENGTH * 2 + 1];
		decl String:banReason[256 * 2 + 1];
		decl String:sAuthidEscaped[64 * 2 + 1];
		decl String:sAdminAuthIdEscaped[64 * 2 + 1];
		decl String:sAdminAuthIdYZEscaped[64 * 2 + 1];
		decl String:sQuery[4096], String:sQueryAdm[512], String:sQueryVal[1024];
		new String:sQueryMute[1024], String:sQueryGag[1024];
		
		// escaping everything
		SQL_EscapeString(g_hDatabase, sName, banName, sizeof(banName));
		SQL_EscapeString(g_hDatabase, reason, banReason, sizeof(banReason));
		SQL_EscapeString(g_hDatabase, targetAuth, sAuthidEscaped, sizeof(sAuthidEscaped));
		SQL_EscapeString(g_hDatabase, adminAuth, sAdminAuthIdEscaped, sizeof(sAdminAuthIdEscaped));
		SQL_EscapeString(g_hDatabase, adminAuth[8], sAdminAuthIdYZEscaped, sizeof(sAdminAuthIdYZEscaped));
		
		// bid    authid    name    created ends lenght reason aid adminip    sid    removedBy removedType removedon type ureason
		FormatEx(sQueryAdm, sizeof(sQueryAdm), 
			"IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), 0)", 
			DatabasePrefix, sAdminAuthIdEscaped, sAdminAuthIdYZEscaped);
		
		// authid name, created, ends, length, reason, aid, adminIp, sid
		FormatEx(sQueryVal, sizeof(sQueryVal), 
			"'%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', %s, '%s', %d", 
			sAuthidEscaped, banName, length * 60, length * 60, banReason, sQueryAdm, adminIp, serverID);
		
		switch (type)
		{
			case TYPE_GAG:FormatEx(sQueryGag, sizeof(sQueryGag), "(%s, %d)", sQueryVal, type);
			case TYPE_MUTE:FormatEx(sQueryMute, sizeof(sQueryMute), "(%s, %d)", sQueryVal, type);
			case TYPE_SILENCE:
			{
				FormatEx(sQueryMute, sizeof(sQueryMute), "(%s, %d)", sQueryVal, TYPE_MUTE);
				FormatEx(sQueryGag, sizeof(sQueryGag), "(%s, %d)", sQueryVal, TYPE_GAG);
			}
		}
		
		// litle magic - one query for all actions (mute, gag or silence)
		FormatEx(sQuery, sizeof(sQuery), 
			"INSERT INTO %s_comms (authid, name, created, ends, length, reason, aid, adminIp, sid, type) VALUES %s%s%s", 
			DatabasePrefix, sQueryMute, type == TYPE_SILENCE ? ", " : "", sQueryGag);
		
		#if defined LOG_QUERIES
		LogToFile(logQuery, "SavePunishment. QUERY: %s", sQuery);
		#endif
		
		// all data cached before calling asynchronous functions
		new Handle:dataPack = CreateDataPack();
		WritePackCell(dataPack, length);
		WritePackCell(dataPack, type);
		WritePackString(dataPack, sName);
		WritePackString(dataPack, targetAuth);
		WritePackString(dataPack, reason);
		WritePackString(dataPack, adminAuth);
		WritePackString(dataPack, adminIp);
		
		SQL_TQuery(g_hDatabase, Query_AddBlockInsert, sQuery, dataPack, DBPrio_High);
	}
	else
		InsertTempBlock(length, type, sName, targetAuth, reason, adminAuth, adminIp);
}

stock ShowActivityToServer(admin, type, length = 0, String:reason[] = "", String:targetName[], bool:ml = false)
{
	#if defined DEBUG
	PrintToServer("ShowActivityToServer(admin: %d, type: %d, length: %d, reason: %s, name: %s, ml: %b", 
		admin, type, length, reason, targetName, ml);
	#endif
	
	decl String:actionName[32], String:translationName[64];
	switch (type)
	{
		case TYPE_MUTE:
		{
			if (length > 0)
				strcopy(actionName, sizeof(actionName), "Muted");
			else if (length == 0)
				strcopy(actionName, sizeof(actionName), "Permamuted");
			else // temp block
				strcopy(actionName, sizeof(actionName), "Temp muted");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_GAG:
		{
			if (length > 0)
				strcopy(actionName, sizeof(actionName), "Gagged");
			else if (length == 0)
				strcopy(actionName, sizeof(actionName), "Permagagged");
			else //temp block
				strcopy(actionName, sizeof(actionName), "Temp gagged");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_SILENCE:
		{
			if (length > 0)
				strcopy(actionName, sizeof(actionName), "Silenced");
			else if (length == 0)
				strcopy(actionName, sizeof(actionName), "Permasilenced");
			else //temp block
				strcopy(actionName, sizeof(actionName), "Temp silenced");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_UNMUTE:
		{
			strcopy(actionName, sizeof(actionName), "Unmuted");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_UNGAG:
		{
			strcopy(actionName, sizeof(actionName), "Ungagged");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_TEMP_UNMUTE:
		{
			strcopy(actionName, sizeof(actionName), "Temp unmuted");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_TEMP_UNGAG:
		{
			strcopy(actionName, sizeof(actionName), "Temp ungagged");
		}
		//-------------------------------------------------------------------------------------------------
		case TYPE_TEMP_UNSILENCE:
		{
			strcopy(actionName, sizeof(actionName), "Temp unsilenced");
		}
		//-------------------------------------------------------------------------------------------------
		default:
		{
			return;
		}
	}
	
	Format(translationName, sizeof(translationName), "%s %s", actionName, reason[0] == '\0' ? "player" : "player reason");
	#if defined DEBUG
	PrintToServer("translation name: %s", translationName);
	#endif
	
	if (length > 0)
	{
		if (ml)
			ShowActivity2(admin, PREFIX, "%t", translationName, targetName, length, reason);
		else
			ShowActivity2(admin, PREFIX, "%t", translationName, "_s", targetName, length, reason);
	}
	else
	{
		if (ml)
			ShowActivity2(admin, PREFIX, "%t", translationName, targetName, reason);
		else
			ShowActivity2(admin, PREFIX, "%t", translationName, "_s", targetName, reason);
	}
}

// Natives //
public Native_SetClientMute(Handle:hPlugin, numParams)
{
	new target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
	}
	
	if (!IsClientInGame(target))
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
	}
	
	new bool:muteState = GetNativeCell(2);
	new muteLength = GetNativeCell(3);
	if (muteState && muteLength == 0)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Permanent mute is not allowed!");
	}
	
	new bool:bSaveToDB = GetNativeCell(4);
	if (!muteState && bSaveToDB)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Removing punishments from DB is not allowed!");
	}
	
	new String:sReason[256];
	GetNativeString(5, sReason, sizeof(sReason));
	
	if (muteState)
	{
		if (g_MuteType[target] > bNot)
		{
			return false;
		}
		
		PerformMute(target, _, muteLength, _, _, _, sReason);
		
		if (bSaveToDB)
			SavePunishment(_, target, TYPE_MUTE, muteLength, sReason);
	}
	else
	{
		if (g_MuteType[target] == bNot)
		{
			return false;
		}
		
		PerformUnMute(target);
	}
	
	return true;
}

public Native_SetClientGag(Handle:hPlugin, numParams)
{
	new target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
	}
	
	if (!IsClientInGame(target))
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
	}
	
	new bool:gagState = GetNativeCell(2);
	new gagLength = GetNativeCell(3);
	if (gagState && gagLength == 0)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Permanent gag is not allowed!");
	}
	
	new bool:bSaveToDB = GetNativeCell(4);
	if (!gagState && bSaveToDB)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Removing punishments from DB is not allowed!");
	}
	
	new String:sReason[256];
	GetNativeString(5, sReason, sizeof(sReason));
	
	if (gagState)
	{
		if (g_GagType[target] > bNot)
		{
			return false;
		}
		
		PerformGag(target, _, gagLength, _, _, _, sReason);
		
		if (bSaveToDB)
			SavePunishment(_, target, TYPE_GAG, gagLength, sReason);
	}
	else
	{
		if (g_GagType[target] == bNot)
		{
			return false;
		}
		
		PerformUnGag(target);
	}
	
	return true;
}

public Native_GetClientMuteType(Handle:hPlugin, numParams)
{
	new target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
	}
	
	if (!IsClientInGame(target))
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
	}
	
	return bType:g_MuteType[target];
}

public Native_GetClientGagType(Handle:hPlugin, numParams)
{
	new target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
	}
	
	if (!IsClientInGame(target))
	{
		return ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
	}
	
	return bType:g_GagType[target];
}

//Yarr!