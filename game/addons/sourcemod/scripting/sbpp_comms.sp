// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2023 SourceBans++ Dev Team <https://github.com/sbpp>
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
//  This file is based off work(s) covered by the following copyright(s):
//
//   SourceComms 0.9.266
//   Copyright (C) 2013-2014 Alexandr Duplishchev
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
//
// *************************************************************************

#pragma semicolon 1
#pragma newdecls required

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

#define PLUGIN_VERSION "1.8.0"
#define PREFIX "\x04[SourceComms++]\x01 "

#define MAX_TIME_MULTI 30 // maximum mass-target punishment length
// session mute will expire after this if it hasn't already (fallback)
#define SESSION_MUTE_FALLBACK 120 * 60

#define NOW 0
#define TYPE_TEMP_SHIFT 10

#define MAX_REASONS 32
#define DISPLAY_SIZE 64
#define REASON_SIZE 192

int iNumReasons;
char g_sReasonDisplays[MAX_REASONS][DISPLAY_SIZE], g_sReasonKey[MAX_REASONS][REASON_SIZE];

#define MAX_TIMES 32
int iNumTimes, g_iTimeMinutes[MAX_TIMES];
char g_sTimeDisplays[MAX_TIMES][DISPLAY_SIZE];

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

DatabaseState g_DatabaseState;
int g_iConnectLock = 0;
int g_iSequence = 0;

State ConfigState;
SMCParser ConfigParser;

TopMenu hTopMenu = null;

/* Cvar handle*/
ConVar CvarHostIp;
ConVar CvarPort;

char ServerIp[24];
char ServerPort[7];

/* Database handle */
Database g_hDatabase;
Database SQLiteDB;

char DatabasePrefix[10] = "sb";

/* Timer handles */
Handle g_hPlayerRecheck[MAXPLAYERS + 1] = { null, ... };
Handle g_hGagExpireTimer[MAXPLAYERS + 1] = { null, ... };
Handle g_hMuteExpireTimer[MAXPLAYERS + 1] = { null, ... };


/* Log Stuff */
#if defined LOG_QUERIES
char logQuery[256];
#endif

float RetryTime = 15.0;
int DefaultTime = 30;
int DisUBImCheck = 0;
int ConsoleImmunity = 0;
int ConfigMaxLength = 0;
int ConfigWhiteListOnly = 0;
int serverID = 0;

/* List menu */
enum
{
	curTarget,
	curIndex,
	viewingMute,
	viewingGag,
	viewingList,
	PeskyPanels,
};
int g_iPeskyPanels[MAXPLAYERS + 1][PeskyPanels];

bool g_bPlayerStatus[MAXPLAYERS + 1]; // Player block check status
char g_sName[MAXPLAYERS + 1][MAX_NAME_LENGTH];

bType g_MuteType[MAXPLAYERS + 1];
int g_iMuteTime[MAXPLAYERS + 1];
int g_iMuteLength[MAXPLAYERS + 1]; // in sec
int g_iMuteLevel[MAXPLAYERS + 1]; // immunity level of admin
char g_sMuteAdminName[MAXPLAYERS + 1][MAX_NAME_LENGTH];
char g_sMuteReason[MAXPLAYERS + 1][256];
char g_sMuteAdminAuth[MAXPLAYERS + 1][64];

bType g_GagType[MAXPLAYERS + 1];
int g_iGagTime[MAXPLAYERS + 1];
int g_iGagLength[MAXPLAYERS + 1]; // in sec
int g_iGagLevel[MAXPLAYERS + 1]; // immunity level of admin
char g_sGagAdminName[MAXPLAYERS + 1][MAX_NAME_LENGTH];
char g_sGagReason[MAXPLAYERS + 1][256];
char g_sGagAdminAuth[MAXPLAYERS + 1][64];

ArrayList g_hServersWhiteList = null;

// Forward
Handle g_hFwd_OnPlayerPunished;

public Plugin myinfo =
{
	name = "SourceBans++: SourceComms",
	author = "Alex, SourceBans++ Dev Team",
	description = "Advanced punishments management for the Source engine in SourceBans style",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	CreateNative("SourceComms_SetClientMute", Native_SetClientMute);
	CreateNative("SourceComms_SetClientGag", Native_SetClientGag);
	CreateNative("SourceComms_GetClientMuteType", Native_GetClientMuteType);
	CreateNative("SourceComms_GetClientGagType", Native_GetClientGagType);

	g_hFwd_OnPlayerPunished = CreateGlobalForward("SourceComms_OnBlockAdded", ET_Ignore, Param_Cell, Param_Cell, Param_Cell, Param_Cell, Param_String);

	RegPluginLibrary("sourcecomms++");
	return APLRes_Success;
}

public void OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("sbpp_comms.phrases");

	TopMenu hTemp = null;
	if (LibraryExists("adminmenu") && ((hTemp = GetAdminTopMenu()) != INVALID_HANDLE))
		OnAdminMenuReady(hTemp);

	CvarHostIp = FindConVar("hostip");
	CvarPort = FindConVar("hostport");
	g_hServersWhiteList = new ArrayList();

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
		SetFailState("Database failure: could not find database config: %s", DATABASE);
		return;
	}
	DB_Connect();
	InitializeBackupDB();

	ServerInfo();

	for (int client = 1; client <= MaxClients; client++)
	{
		if (IsClientInGame(client) && IsClientAuthorized(client))
			OnClientPostAdminCheck(client);
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if (StrEqual(name, "adminmenu"))
		hTopMenu = null;
}

public void OnConfigsExecuted()
{
	ReadConfig();
}

public void OnMapStart()
{
	ReadConfig();
}

public void OnMapEnd()
{
	// Clean up on map end just so we can start a fresh connection when we need it later.
	// Also it is necessary for using SQL_SetCharset
	if (g_hDatabase)
		delete g_hDatabase;

	g_hDatabase = null;
}


// CLIENT CONNECTION FUNCTIONS //

public void OnClientDisconnect(int client)
{
	if (g_hPlayerRecheck[client] != null)
		delete g_hPlayerRecheck[client];

	CloseMuteExpireTimer(client);
	CloseGagExpireTimer(client);
}

public bool OnClientConnect(int client, char[] rejectmsg, int maxlen)
{
	g_bPlayerStatus[client] = false;
	return true;
}

public void OnClientConnected(int client)
{
	g_sName[client][0] = '\0';

	MarkClientAsUnMuted(client);
	MarkClientAsUnGagged(client);
}

public void OnClientPostAdminCheck(int client)
{
	char clientAuth[64];
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

		char sClAuthYZEscaped[sizeof(clientAuth) * 2 + 1];
		g_hDatabase.Escape(clientAuth[8], sClAuthYZEscaped, sizeof(sClAuthYZEscaped));

		char Query[4096];
		FormatEx(Query, sizeof(Query),
			"SELECT		(c.ends - UNIX_TIMESTAMP()) AS remaining, \
						c.length, c.type, c.created, c.reason, a.user, \
						IF (a.immunity>=g.immunity, a.immunity, IFNULL(g.immunity,0)) AS immunity, \
						c.aid, c.sid, a.authid \
			FROM		%s_comms	AS c \
			LEFT JOIN	%s_admins	AS a  ON a.aid = c.aid \
			LEFT JOIN	%s_srvgroups AS g  ON g.name = a.srv_group \
			WHERE		RemoveType IS NULL \
							AND c.authid REGEXP '^STEAM_[0-9]:%s$' \
							AND (length = '0' OR ends > UNIX_TIMESTAMP())",
			DatabasePrefix, DatabasePrefix, DatabasePrefix, sClAuthYZEscaped);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "OnClientPostAdminCheck for: %s. QUERY: %s", clientAuth, Query);
		#endif
		g_hDatabase.Query(Query_VerifyBlock, Query, GetClientUserId(client), DBPrio_High);
	}
}


// OTHER CLIENT CODE //

public Action Event_OnPlayerName(Handle event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	if (client > 0 && IsClientInGame(client))
		GetEventString(event, "newname", g_sName[client], sizeof(g_sName[]));
	return Plugin_Continue;
}

public void BaseComm_OnClientMute(int client, bool muteState)
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

public void BaseComm_OnClientGag(int client, bool gagState)
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

public Action CommandComms(int client, int args)
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

public Action FWBlock(int args)
{
	char arg_string[256];
	char sArg[3][64];
	GetCmdArgString(arg_string, sizeof(arg_string));

	int type, length;
	if (ExplodeString(arg_string, " ", sArg, 3, 64) != 3 || !StringToIntEx(sArg[0], type) || type < 1 || type > 3 || !StringToIntEx(sArg[1], length))
	{
		LogError("Wrong usage of sc_fw_block");
		return Plugin_Stop;
	}

	LogMessage("Received block command from web: steam %s, type %d, length %d", sArg[2], type, length);

	char clientAuth[64];
	for (int i = 1; i <= MaxClients; i++)
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

public Action FWUngag(int args)
{
	char arg_string[256];
	char sArg[1][64];
	GetCmdArgString(arg_string, sizeof(arg_string));
	if (!ExplodeString(arg_string, " ", sArg, 1, 64))
	{
		LogError("Wrong usage of sc_fw_ungag");
		return Plugin_Stop;
	}

	LogMessage("Received ungag command from web: steam %s", sArg[0]);

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i))
		{
			char clientAuth[64];
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

public Action FWUnmute(int args)
{
	char arg_string[256];
	char sArg[1][64];
	GetCmdArgString(arg_string, sizeof(arg_string));
	if (!ExplodeString(arg_string, " ", sArg, 1, 64))
	{
		LogError("Wrong usage of sc_fw_ungag");
		return Plugin_Stop;
	}

	LogMessage("Received unmute command from web: steam %s", sArg[0]);

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i))
		{
			char clientAuth[64];
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


public Action CommandCallback(int client, const char[] command, int args)
{
	if (client && !CheckCommandAccess(client, command, ADMFLAG_CHAT))
		return Plugin_Continue;

	int type;
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

	if ( !args && client )
	{
		AdminMenu_Target( client, type );
		return Plugin_Stop;
	}
	else if ( args < 1 )
	{
		ReplyToCommand( client, "%sUsage: %s <#userid|name> %s", PREFIX, command, type > TYPE_SILENCE ? "[reason]" : "[time|0] [reason]" );
		return Plugin_Stop;
	}

	char sBuffer[256];
	GetCmdArgString(sBuffer, sizeof(sBuffer));

	if (type <= TYPE_SILENCE)
		CreateBlock(client, _, _, type, _, sBuffer);
	else
		ProcessUnBlock(client, _, type, _, sBuffer);

	return Plugin_Stop;
}


// MENU CODE //

public void OnAdminMenuReady(Handle hTemp)
{
	TopMenu topmenu = view_as<TopMenu>(hTemp);

	/* Block us from being called twice */
	if (topmenu == hTopMenu)
		return;

	/* Save the Handle */
	hTopMenu = topmenu;

	TopMenuObject MenuObject = hTopMenu.AddCategory("sourcecomm_cmds", Handle_Commands);

	if (MenuObject == INVALID_TOPMENUOBJECT)
		return;

	hTopMenu.AddItem("sourcecomm_gag", Handle_MenuGag, MenuObject, "sm_gag", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_ungag", Handle_MenuUnGag, MenuObject, "sm_ungag", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_mute", Handle_MenuMute, MenuObject, "sm_mute", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_unmute", Handle_MenuUnMute, MenuObject, "sm_unmute", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_silence", Handle_MenuSilence, MenuObject, "sm_silence", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_unsilence", Handle_MenuUnSilence, MenuObject, "sm_unsilence", ADMFLAG_CHAT);
	hTopMenu.AddItem("sourcecomm_list", Handle_MenuList, MenuObject, "sm_commlist", ADMFLAG_CHAT);
}

public int Handle_Commands(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_Main", param1);
		case TopMenuAction_DisplayTitle:
			Format(buffer, maxlength, "%T", "AdminMenu_Select_Main", param1);
	}
	return 0;
}

public int Handle_MenuGag(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_Gag", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_GAG);
	}
	return 0;
}

public int Handle_MenuUnGag(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_UnGag", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_UNGAG);
	}
	return 0;
}

public int Handle_MenuMute(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_Mute", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_MUTE);
	}
	return 0;
}

public int Handle_MenuUnMute(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_UnMute", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_UNMUTE);
	}
	return 0;
}

public int Handle_MenuSilence(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_Silence", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_SILENCE);
	}
	return 0;
}

public int Handle_MenuUnSilence(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_UnSilence", param1);
		case TopMenuAction_SelectOption:
			AdminMenu_Target(param1, TYPE_UNSILENCE);
	}
	return 0;
}

public int Handle_MenuList(TopMenu menu, TopMenuAction action, TopMenuObject object_id, int param1, char[] buffer, int maxlength)
{
	switch (action)
	{
		case TopMenuAction_DisplayOption:
			Format(buffer, maxlength, "%T", "AdminMenu_List", param1);
		case TopMenuAction_SelectOption:
		{
			g_iPeskyPanels[param1][viewingList] = false;
			AdminMenu_List(param1, 0);
		}
	}
	return 0;
}

void AdminMenu_Target(int client, int type)
{
	char Title[192], Option[32];
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

	Menu hMenu = new Menu(MenuHandler_MenuTarget); // Common menu - players list. Almost full for blocking, and almost empty for unblocking
	hMenu.SetTitle(Title);
	hMenu.ExitBackButton = true;

	int iClients;
	if (type <= 3) // Mute, gag, silence
	{
		for (int i = 1; i <= MaxClients; i++)
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
				hMenu.AddItem(Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
			}
		}
	}
	else // UnMute, ungag, unsilence
	{
		for (int i = 1; i <= MaxClients; i++)
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
							hMenu.AddItem(Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
						}
					}
					case TYPE_UNGAG:
					{
						if (g_GagType[i] > bNot)
						{
							iClients++;
							strcopy(Title, sizeof(Title), g_sName[i]);
							Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
							hMenu.AddItem(Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
						}
					}
					case TYPE_UNSILENCE:
					{
						if (g_MuteType[i] > bNot && g_GagType[i] > bNot)
						{
							iClients++;
							strcopy(Title, sizeof(Title), g_sName[i]);
							Format(Option, sizeof(Option), "%d %d", GetClientUserId(i), type);
							hMenu.AddItem(Option, Title, (CanUserTarget(client, i) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED));
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

	hMenu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_MenuTarget(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_End:
			delete menu;
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != null)
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			char Option[32], Temp[2][8];
			menu.GetItem(param2, Option, sizeof(Option));
			ExplodeString(Option, " ", Temp, 2, 8);
			int target = GetClientOfUserId(StringToInt(Temp[0]));

			if (Bool_ValidMenuTarget(param1, target))
			{
				int type = StringToInt(Temp[1]);
				if (type <= TYPE_SILENCE)
					AdminMenu_Duration(param1, target, type);
				else
					ProcessUnBlock(param1, target, type);
			}
		}
	}
	return 0;
}

void AdminMenu_Duration(int client, int target, int type)
{
	Menu hMenu = new Menu(MenuHandler_MenuDuration);
	char sBuffer[192], sTemp[64];
	Format(sBuffer, sizeof(sBuffer), "%T", "AdminMenu_Title_Durations", client);
	hMenu.SetTitle(sBuffer);
	hMenu.ExitBackButton = true;

	for (int i = 0; i <= iNumTimes; i++)
	{
		if (IsAllowedBlockLength(client, g_iTimeMinutes[i]))
		{
			Format(sTemp, sizeof(sTemp), "%d %d %d", GetClientUserId(target), type, i); // TargetID TYPE_BLOCK index_of_Time
			hMenu.AddItem(sTemp, g_sTimeDisplays[i]);
		}
	}

	hMenu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_MenuDuration(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_End:
			delete menu;
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			char sOption[32], sTemp[3][8];
			menu.GetItem(param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 3, 8);
			// TargetID TYPE_BLOCK index_of_Time
			int target = GetClientOfUserId(StringToInt(sTemp[0]));

			if (Bool_ValidMenuTarget(param1, target))
			{
				int type = StringToInt(sTemp[1]);
				int lengthIndex = StringToInt(sTemp[2]);

				if (iNumReasons) // we have reasons to show
					AdminMenu_Reason(param1, target, type, lengthIndex);
				else
					CreateBlock(param1, target, g_iTimeMinutes[lengthIndex], type);
			}
		}
	}
	return 0;
}

void AdminMenu_Reason(int client, int target, int type, int lengthIndex)
{
	Menu hMenu = new Menu(MenuHandler_MenuReason);
	char sBuffer[192], sTemp[64];
	Format(sBuffer, sizeof(sBuffer), "%T", "AdminMenu_Title_Reasons", client);
	hMenu.SetTitle(sBuffer);
	hMenu.ExitBackButton = true;

	for (int i = 0; i <= iNumReasons; i++)
	{
		Format(sTemp, sizeof(sTemp), "%d %d %d %d", GetClientUserId(target), type, i, lengthIndex); // TargetID TYPE_BLOCK ReasonIndex LenghtIndex
		hMenu.AddItem(sTemp, g_sReasonDisplays[i]);
	}

	hMenu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_MenuReason(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_End:
			delete menu;
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			char sOption[64], sTemp[4][8];
			menu.GetItem(param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 4, 8);
			// TargetID TYPE_BLOCK ReasonIndex LenghtIndex
			int target = GetClientOfUserId(StringToInt(sTemp[0]));

			if (Bool_ValidMenuTarget(param1, target))
			{
				int type = StringToInt(sTemp[1]);
				int reasonIndex = StringToInt(sTemp[2]);
				int lengthIndex = StringToInt(sTemp[3]);
				int length;
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
	return 0;
}

void AdminMenu_List(int client, int index)
{
	char sTitle[192], sOption[32];
	Format(sTitle, sizeof(sTitle), "%T", "AdminMenu_Select_List", client);
	int iClients;

	Menu hMenu = new Menu(MenuHandler_MenuList);
	hMenu.SetTitle(sTitle);

	if (!g_iPeskyPanels[client][viewingList])
		hMenu.ExitBackButton = true;

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && !IsFakeClient(i) && (g_MuteType[i] > bNot || g_GagType[i] > bNot))
		{
			iClients++;
			strcopy(sTitle, sizeof(sTitle), g_sName[i]);
			AdminMenu_GetPunishPhrase(client, i, sTitle, sizeof(sTitle));
			Format(sOption, sizeof(sOption), "%d", GetClientUserId(i));
			hMenu.AddItem(sOption, sTitle);
		}
	}

	if (!iClients)
	{
		Format(sTitle, sizeof(sTitle), "%T", "ListMenu_Option_Empty", client);
		hMenu.AddItem("0", sTitle, ITEMDRAW_DISABLED);
	}

	hMenu.DisplayAt(client, index, MENU_TIME_FOREVER);
}

public int MenuHandler_MenuList(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_End:
			delete menu;
		case MenuAction_Cancel:
		{
			if (!g_iPeskyPanels[param1][viewingList] && param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
		}
		case MenuAction_Select:
		{
			char sOption[32];
			menu.GetItem(param2, sOption, sizeof(sOption));
			int target = GetClientOfUserId(StringToInt(sOption));

			if (Bool_ValidMenuTarget(param1, target))
				AdminMenu_ListTarget(param1, target, GetMenuSelectionPosition());
			else
				AdminMenu_List(param1, GetMenuSelectionPosition());
		}
	}
	return 0;
}

void AdminMenu_ListTarget(int client, int target, int index, int viewMute = 0, int viewGag = 0)
{
	int userid = GetClientUserId(target);
	Menu hMenu = CreateMenu(MenuHandler_MenuListTarget);

	char sBuffer[192], sOption[32];

	hMenu.SetTitle(g_sName[target]);
	hMenu.Pagination = MENU_NO_PAGINATION;
	hMenu.ExitButton = true;
	hMenu.ExitBackButton = false;

	if (g_MuteType[target] > bNot)
	{
		Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Mute", client);
		Format(sOption, sizeof(sOption), "0 %d %d %b %b", userid, index, viewMute, viewGag);
		hMenu.AddItem(sOption, sBuffer);

		if (viewMute)
		{
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Admin", client, g_sMuteAdminName[target]);
			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			char sMuteTemp[192], _sMuteTime[192];
			Format(sMuteTemp, sizeof(sMuteTemp), "%T", "ListMenu_Option_Duration", client);
			switch (g_MuteType[target])
			{
				case bPerm:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Perm", client);
				case bTime:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Time", client, g_iMuteLength[target]);
				case bSess:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Duration_Temp", client);
				default:
					Format(sBuffer, sizeof(sBuffer), "error");
			}
			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			FormatTime(_sMuteTime, sizeof(_sMuteTime), NULL_STRING, g_iMuteTime[target]);
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Issue", client, _sMuteTime);
			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			Format(sMuteTemp, sizeof(sMuteTemp), "%T", "ListMenu_Option_Expire", client);
			switch (g_MuteType[target])
			{
				case bTime:
				{
					FormatTime(_sMuteTime, sizeof(_sMuteTime), NULL_STRING, (g_iMuteTime[target] + g_iMuteLength[target] * 60));
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Time", client, _sMuteTime);
				}
				case bPerm:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Perm", client);
				case bSess:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sMuteTemp, "ListMenu_Option_Expire_Temp_Reconnect", client);
				default:
					Format(sBuffer, sizeof(sBuffer), "error");
			}

			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			if (strlen(g_sMuteReason[target]) > 0)
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason", client);
				Format(sOption, sizeof(sOption), "1 %d %d %b %b", userid, index, viewMute, viewGag);
				hMenu.AddItem(sOption, sBuffer);
			}
			else
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason_None", client);
				hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);
			}
		}
	}

	if (g_GagType[target] > bNot)
	{
		Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Gag", client);
		Format(sOption, sizeof(sOption), "2 %d %d %b %b", userid, index, viewMute, viewGag);
		hMenu.AddItem(sOption, sBuffer);

		if (viewGag)
		{
			Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Admin", client, g_sGagAdminName[target]);
			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			char sGagTemp[192], _sGagTime[192];
			Format(sGagTemp, sizeof(sGagTemp), "%T", "ListMenu_Option_Duration", client);

			switch (g_GagType[target])
			{
				case bPerm:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Perm", client);
				case bTime:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Time", client, g_iGagLength[target]);
				case bSess:
					Format(sBuffer, sizeof(sBuffer), "%s%T", sGagTemp, "ListMenu_Option_Duration_Temp", client);
				default:
					Format(sBuffer, sizeof(sBuffer), "error");
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

			hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);

			if (strlen(g_sGagReason[target]) > 0)
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason", client);
				Format(sOption, sizeof(sOption), "3 %d %d %b %b", userid, index, viewMute, viewGag);
				hMenu.AddItem(sOption, sBuffer);
			}
			else
			{
				Format(sBuffer, sizeof(sBuffer), "%T", "ListMenu_Option_Reason_None", client);
				hMenu.AddItem("", sBuffer, ITEMDRAW_DISABLED);
			}
		}
	}

	g_iPeskyPanels[client][curIndex] = index;
	g_iPeskyPanels[client][curTarget] = target;
	g_iPeskyPanels[client][viewingGag] = viewGag;
	g_iPeskyPanels[client][viewingMute] = viewMute;
	hMenu.Display(client, MENU_TIME_FOREVER);
}

public int MenuHandler_MenuListTarget(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_End:
			delete menu;
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack)
				AdminMenu_List(param1, g_iPeskyPanels[param1][curIndex]);
		}
		case MenuAction_Select:
		{
			char sOption[64], sTemp[5][8];
			menu.GetItem(param2, sOption, sizeof(sOption));
			ExplodeString(sOption, " ", sTemp, 5, 8);

			int target = GetClientOfUserId(StringToInt(sTemp[1]));
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
	return 0;
}

void AdminMenu_ListTargetReason(int client, int target, int showMute, int showGag)
{
	char sTemp[192], sBuffer[192];
	Panel hPanel = new Panel();
	hPanel.SetTitle(g_sName[target]);
	hPanel.DrawItem(" ", ITEMDRAW_SPACER | ITEMDRAW_RAWLINE);

	if (showMute)
	{
		Format(sTemp, sizeof(sTemp), "%T", "ReasonPanel_Punishment_Mute", client);
		switch (g_MuteType[target])
		{
			case bPerm:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Perm", client);
			case bTime:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Time", client, g_iMuteLength[target]);
			case bSess:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Temp", client);
			default:
				Format(sBuffer, sizeof(sBuffer), "error");
		}
		hPanel.DrawText(sBuffer);

		Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Reason", client, g_sMuteReason[target]);
		hPanel.DrawText(sBuffer);
	}
	else if (showGag)
	{
		Format(sTemp, sizeof(sTemp), "%T", "ReasonPanel_Punishment_Gag", client);
		switch (g_GagType[target])
		{
			case bPerm:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Perm", client);
			case bTime:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Time", client, g_iGagLength[target]);
			case bSess:
				Format(sBuffer, sizeof(sBuffer), "%s%T", sTemp, "ReasonPanel_Temp", client);
			default:
				Format(sBuffer, sizeof(sBuffer), "error");
		}
		hPanel.DrawText(sBuffer);

		Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Reason", client, g_sGagReason[target]);
		hPanel.DrawText(sBuffer);
	}

	hPanel.DrawItem(" ", ITEMDRAW_SPACER | ITEMDRAW_RAWLINE);
	hPanel.CurrentKey = 10;
	Format(sBuffer, sizeof(sBuffer), "%T", "ReasonPanel_Back", client);
	hPanel.DrawItem(sBuffer);
	hPanel.Send(client, PanelHandler_ListTargetReason, MENU_TIME_FOREVER);
	delete hPanel;
}

public int PanelHandler_ListTargetReason(Menu menu, MenuAction action, int param1, int param2)
{
	if (action == MenuAction_Select)
	{
		AdminMenu_ListTarget(param1, g_iPeskyPanels[param1][curTarget],
			g_iPeskyPanels[param1][curIndex],
			g_iPeskyPanels[param1][viewingMute],
			g_iPeskyPanels[param1][viewingGag]);
	}
	return 0;
}


// SQL CALLBACKS //

public void GotDatabase(Database db, const char[] error, any data)
{
	#if defined DEBUG
	PrintToServer("GotDatabase(data: %d, lock: %d, g_h: %d, db: %d)", data, g_iConnectLock, g_hDatabase, db);
	#endif

	// If this happens to be an old connection request, ignore it.
	if (data != g_iConnectLock || g_hDatabase)
	{
		if (db)
			delete db;
		return;
	}

	g_iConnectLock = 0;
	g_DatabaseState = DatabaseState_Connected;
	g_hDatabase = db;

	// See if the connection is valid. If not, don't un-mark the caches
	// as needing rebuilding, in case the next connection request works.
	if (!g_hDatabase)
	{
		LogError("Connecting to database failed: %s", error);
		return;
	}

	// Set character set to UTF8MB4 in the database
	char query[128];
	Format(query, sizeof(query), "SET NAMES utf8mb4");
	db.Query(Query_ErrorCheck, query);

	// Process queue
	SQLiteDB.Query(Query_ProcessQueue,
		"SELECT	id, steam_id, time, start_time, reason, name, admin_id, admin_ip, type \
		FROM	queue2");

	// Force recheck players
	ForcePlayersRecheck();
}

public void Query_AddBlockInsert(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	dataPack.Reset();

	char reason[256];

	int iAdminUserId = dataPack.ReadCell();
	int iAdmin = 0;

	if(iAdminUserId > 0) {
		iAdmin = GetClientOfUserId(iAdminUserId);
	}

	int iTarget = GetClientOfUserId(dataPack.ReadCell());

	if (!iTarget) {
		iTarget = -1;
	}

	int length = dataPack.ReadCell();
	int type = dataPack.ReadCell();
	dataPack.ReadString(reason, sizeof(reason));

	// Fire forward
	Call_StartForward(g_hFwd_OnPlayerPunished);
	Call_PushCell(iAdmin);
	Call_PushCell(iTarget);
	Call_PushCell(length);
	Call_PushCell(type);
	Call_PushString(reason);
	Call_Finish();

	if (DB_Conn_Lost(results) || error[0])
	{
		LogError("Query_AddBlockInsert failed: %s", error);

		char name[MAX_NAME_LENGTH], auth[64], adminAuth[32], adminIp[20];
		dataPack.ReadString(name, sizeof(name));
		dataPack.ReadString(auth, sizeof(auth));
		dataPack.ReadString(adminAuth, sizeof(adminAuth));
		dataPack.ReadString(adminIp, sizeof(adminIp));

		InsertTempBlock(length, type, name, auth, reason, adminAuth, adminIp);
	}

	delete dataPack;
}

public void Query_UnBlockSelect(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	char adminAuth[30], targetAuth[30];
	char reason[256];

	dataPack.Reset();
	int adminUserID = dataPack.ReadCell();
	int targetUserID = dataPack.ReadCell();
	int type = dataPack.ReadCell(); // not in use unless DEBUG
	dataPack.ReadString(adminAuth, sizeof(adminAuth));
	dataPack.ReadString(targetAuth, sizeof(targetAuth));
	dataPack.ReadString(reason, sizeof(reason));

	int admin = GetClientOfUserId(adminUserID);
	int target = GetClientOfUserId(targetUserID);

	#if defined DEBUG
	PrintToServer("Query_UnBlockSelect(adminUID: %d/%d, targetUID: %d/%d, type: %d, adminAuth: %s, targetAuth: %s, reason: %s)",
		adminUserID, admin, targetUserID, target, type, adminAuth, targetAuth, reason);
	#endif

	char targetName[MAX_NAME_LENGTH];
	strcopy(targetName, MAX_NAME_LENGTH, target && IsClientInGame(target) ? g_sName[target] : targetAuth); //FIXME

	bool hasErrors = false;
	// If error is not an empty string the query failed
	if (DB_Conn_Lost(results) || error[0] != '\0')
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
	if (!DB_Conn_Lost(results) && !results.RowCount)
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

		TempUnBlock(dataPack); // Datapack closed inside.
		return;
	}
	else
	{
		bool b_success = false;
		// Get the values from the founded blocks.
		while (results.MoreRows)
		{
			// Oh noes! What happened?!
			if (!results.FetchRow())
				continue;

			int bid = results.FetchInt(0);
			int iAID = results.FetchInt(1);
			int cAID = results.FetchInt(2);
			int cImmunity = results.FetchInt(3);
			int cType = results.FetchInt(4);

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

				DataPack newDataPack = new DataPack();
				newDataPack.WriteCell(adminUserID);
				newDataPack.WriteCell(cType);
				newDataPack.WriteString(g_sName[target]);
				newDataPack.WriteString(targetAuth);

				char unbanReason[sizeof(reason) * 2 + 1];
				db.Escape(reason, unbanReason, sizeof(unbanReason));

				char query[2048];
				Format(query, sizeof(query),
					"UPDATE	%s_comms \
					SET		RemovedBy = %d, \
							RemoveType = 'U', \
							RemovedOn = UNIX_TIMESTAMP(), \
							ureason = '%s' \
					WHERE	bid = %d",
					DatabasePrefix, iAID, unbanReason, bid);
				#if defined LOG_QUERIES
				LogToFile(logQuery, "Query_UnBlockSelect. QUERY: %s", query);
				#endif
				db.Query(Query_UnBlockUpdate, query, newDataPack);
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

				dataPack.Position = view_as<DataPackPos>(16);

				if (g_MuteType[target] > bNot)
				{
					dataPack.WriteCell(TYPE_UNMUTE);
					TempUnBlock(dataPack);
				}
				else if (g_GagType[target] > bNot)
				{
					dataPack.WriteCell(TYPE_UNGAG);
					TempUnBlock(dataPack);
				}
			}
		}
	}

	if (dataPack != null)
		delete dataPack;
}

public void Query_UnBlockUpdate(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int admin, type;
	char targetName[MAX_NAME_LENGTH], targetAuth[30];

	dataPack.Reset();
	admin = GetClientOfUserId(dataPack.ReadCell());
	type = dataPack.ReadCell();
	dataPack.ReadString(targetName, sizeof(targetName));
	dataPack.ReadString(targetAuth, sizeof(targetAuth));
	delete dataPack;

	if (DB_Conn_Lost(results) || error[0] != '\0')
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
public void Query_ProcessQueue(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null || error[0])
	{
		LogError("Query_ProcessQueue failed: %s", error);
		return;
	}

	char auth[64];
	char name[MAX_NAME_LENGTH];
	char reason[256];
	char adminAuth[64], adminIp[20];
	char query[4096];

	while (results.MoreRows)
	{
		// Oh noes! What happened?!
		if (!results.FetchRow())
			continue;

		char sAuthEscaped[sizeof(auth) * 2 + 1];
		char banName[MAX_NAME_LENGTH * 2 + 1];
		char banReason[sizeof(reason) * 2 + 1];
		char sAdmAuthEscaped[sizeof(adminAuth) * 2 + 1];
		char sAdmAuthYZEscaped[sizeof(adminAuth) * 2 + 1];

		// if we get to here then there are rows in the queue pending processing
		//steam_id TEXT, time INTEGER, start_time INTEGER, reason TEXT, name TEXT, admin_id TEXT, admin_ip TEXT, type INTEGER
		int id = results.FetchInt(0);
		results.FetchString(1, auth, sizeof(auth));
		int time = results.FetchInt(2);
		int startTime = results.FetchInt(3);
		results.FetchString(4, reason, sizeof(reason));
		results.FetchString(5, name, sizeof(name));
		results.FetchString(6, adminAuth, sizeof(adminAuth));
		results.FetchString(7, adminIp, sizeof(adminIp));
		int type = results.FetchInt(8);

		if (DB_Connect()) {
			db.Escape(auth, sAuthEscaped, sizeof(sAuthEscaped));
			db.Escape(name, banName, sizeof(banName));
			db.Escape(reason, banReason, sizeof(banReason));
			db.Escape(adminAuth, sAdmAuthEscaped, sizeof(sAdmAuthEscaped));
			db.Escape(adminAuth[8], sAdmAuthYZEscaped, sizeof(sAdmAuthYZEscaped));
		}
		else
			continue;
		// all blocks should be entered into db!

		FormatEx(query, sizeof(query),
			"INSERT INTO	 %s_comms (authid, name, created, ends, length, reason, aid, adminIp, sid, type) \
				VALUES		 ('%s', '%s', %d, %d, %d, '%s', \
								IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '0'), \
								'%s', %d, %d)",
			DatabasePrefix, sAuthEscaped, banName, startTime, (startTime + (time * 60)), (time * 60), banReason, DatabasePrefix, sAdmAuthEscaped, sAdmAuthYZEscaped, adminIp, serverID, type);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "Query_ProcessQueue. QUERY: %s", query);
		#endif
		db.Query(Query_AddBlockFromQueue, query, id);
	}
}

public void Query_AddBlockFromQueue(Database db, DBResultSet results, const char[] error, any data)
{
	char query[512];
	if (error[0] == '\0')
	{
		// The insert was successful so delete the record from the queue
		FormatEx(query, sizeof(query),
			"DELETE FROM queue2 \
			WHERE		id = %d",
			data);
		#if defined LOG_QUERIES
		LogToFile(logQuery, "Query_AddBlockFromQueue. QUERY: %s", query);
		#endif
		SQLiteDB.Query(Query_ErrorCheck, query);
	}
}

public void Query_ErrorCheck(Database db, DBResultSet results, const char[] error, any data)
{
	if (DB_Conn_Lost(results) || error[0])
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
}

public void Query_VerifyBlock(Database db, DBResultSet results, const char[] error, any userid)
{
	char clientAuth[64];
	int client = GetClientOfUserId(userid);

	#if defined DEBUG
	PrintToServer("Query_VerifyBlock(userid: %d, client: %d)", userid, client);
	#endif

	if (!client)
		return;

	/* Failure happen. Do retry with delay */
	if (DB_Conn_Lost(results))
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
	if (results.RowCount > 0)
	{
		while (results.FetchRow())
		{
			if (NotApplyToThisServer(results.FetchInt(8)))
				continue;

			char sAdmName[MAX_NAME_LENGTH], sAdmAuth[64];
			char sReason[256];
			int remaining_time = results.FetchInt(0);
			int length = results.FetchInt(1);
			int type = results.FetchInt(2);
			int time = results.FetchInt(3);
			results.FetchString(4, sReason, sizeof(sReason));
			results.FetchString(5, sAdmName, sizeof(sAdmName));
			int immunity = results.FetchInt(6);
			int aid = results.FetchInt(7);
			results.FetchString(9, sAdmAuth, sizeof(sAdmAuth));

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
					//Set mute type based on length
					if (length > 0)
						g_MuteType[client] = bTime;
					else if (length == 0)
						g_MuteType[client] = bPerm;
					else
						g_MuteType[client] = bSess;

					//Perform mute/unmute
					if (g_MuteType[client] == bSess)
					{
						PerformUnMute(client);
					}
					else if (g_MuteType[client] > bSess)
					{
						PerformMute(client, time, length / 60, sAdmName, sAdmAuth, immunity, sReason, remaining_time);
						PrintToChat(client, "%s%t", PREFIX, "Muted on connect");
					}
				}
				case TYPE_GAG:
				{
					//Set gag type based on length
					if (length > 0)
						g_GagType[client] = bTime;
					else if (length == 0)
						g_GagType[client] = bPerm;
					else
						g_GagType[client] = bSess;

					//Perform gag/ungag
					if (g_GagType[client] == bSess)
					{
						PerformUnGag(client);
					}
					else if (g_GagType[client] > bSess)
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

public Action ClientRecheck(Handle timer, any userid)
{
	#if defined DEBUG
	PrintToServer("ClientRecheck(userid: %d)", userid);
	#endif

	int client = GetClientOfUserId(userid);
	if (!client)
		return Plugin_Continue;

	if (IsClientConnected(client))
		OnClientPostAdminCheck(client);

	g_hPlayerRecheck[client] = null;
	return Plugin_Continue;
}

public Action Timer_MuteExpire(Handle timer, DataPack dataPack)
{
	dataPack.Reset();
	g_hMuteExpireTimer[dataPack.ReadCell()] = INVALID_HANDLE;

	int client = GetClientOfUserId(dataPack.ReadCell());
	if (!client)
		return Plugin_Continue;

	#if defined DEBUG
	char clientAuth[64];
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	PrintToServer("Mute expired for %s", clientAuth);
	#endif

	PrintToChat(client, "%s%t", PREFIX, "Mute expired");

	MarkClientAsUnMuted(client);
	if (IsClientInGame(client))
		BaseComm_SetClientMute(client, false);
	return Plugin_Continue;
}

public Action Timer_GagExpire(Handle timer, DataPack dataPack)
{
	dataPack.Reset();
	g_hGagExpireTimer[dataPack.ReadCell()] = null;

	int client = GetClientOfUserId(dataPack.ReadCell());
	if (!client)
		return Plugin_Continue;

	#if defined DEBUG
	char clientAuth[64];
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	PrintToServer("Gag expired for %s", clientAuth);
	#endif

	PrintToChat(client, "%s%t", PREFIX, "Gag expired");

	MarkClientAsUnGagged(client);
	if (IsClientInGame(client))
		BaseComm_SetClientGag(client, false);
	return Plugin_Continue;
}

public Action Timer_StopWait(Handle timer, any data)
{
	g_DatabaseState = DatabaseState_None;
	DB_Connect();
	return Plugin_Continue;
}

// PARSER //

static void InitializeConfigParser()
{
	if (ConfigParser == INVALID_HANDLE)
	{
		ConfigParser = new SMCParser();
		ConfigParser.OnEnterSection = ReadConfig_NewSection;
		ConfigParser.OnKeyValue = ReadConfig_KeyValue;
		ConfigParser.OnLeaveSection = ReadConfig_EndSection;
	}
}

static void InternalReadConfig(const char[] path)
{
	ConfigState = ConfigStateNone;

	SMCError err = ConfigParser.ParseFile(path);

	if (err != SMCError_Okay)
	{
		char buffer[64];
		PrintToServer("%s", SMC_GetErrorString(err, buffer, sizeof(buffer)) ? buffer : "Fatal parse error");
	}
}

public SMCResult ReadConfig_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
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

public SMCResult ReadConfig_KeyValue(SMCParser smc, const char[] key, const char[] value, bool key_quotes, bool value_quotes)
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
				serverID = StringToInt(value);

				// get our sb_id value if we have one
				int sbid = GetConVarInt(FindConVar("sb_id"));
				if (sbid != -1)
				{
					serverID = sbid;
				}

				// if it's not valid, make it 0
				// we consider -1 valid here
				if (serverID < -1)
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
				int srvID = StringToInt(value);
				if (srvID >= 0)
				{
					g_hServersWhiteList.Push(srvID);
					#if defined DEBUG
					PrintToServer("Loaded white list server id %d", srvID);
					#endif
				}
			}
		}
	}
	return SMCParse_Continue;
}

public SMCResult ReadConfig_EndSection(SMCParser smc)
{
	return SMCParse_Continue;
}

// STOCK FUNCTIONS //
stock void setGag(int client, int length, const char[] clientAuth)
{
	if (g_GagType[client] == bNot)
	{
		PerformGag(client, _, length / 60, _, _, _, _);
		PrintToChat(client, "%s%t", PREFIX, "Gagged on connect");
		LogMessage("%s is gagged from web", clientAuth);
	}
}

stock void setMute(int client, int length, const char[] clientAuth)
{
	if (g_MuteType[client] == bNot)
	{
		PerformMute(client, _, length / 60, _, _, _, _);
		PrintToChat(client, "%s%t", PREFIX, "Muted on connect");
		LogMessage("%s is muted from web", clientAuth);
	}
}

stock bool DB_Connect()
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
		Database.Connect(GotDatabase, DATABASE, g_iConnectLock);
	}

	return false;
}

stock bool DB_Conn_Lost(DBResultSet db)
{
	if (db == null)
	{
		if (g_hDatabase != null)
		{
			LogError("Lost connection to DB. Reconnect after delay.");
			delete g_hDatabase;
			g_hDatabase = null;
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

stock void InitializeBackupDB()
{
	char error[255];
	SQLiteDB = SQLite_UseDatabase("sourcecomms-queue", error, sizeof(error));
	if (SQLiteDB == INVALID_HANDLE)
	{
		SetFailState(error);
	}

	SQLiteDB.Query(Query_ErrorCheck,
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

stock void CreateBlock(int client, int targetId = 0, int length = -1, int type, const char[] sReason = "", const char[] sArgs = "")
{
	#if defined DEBUG
	PrintToServer("CreateBlock(admin: %d, target: %d, length: %d, type: %d, reason: %s, args: %s)", client, targetId, length, type, sReason, sArgs);
	#endif

	int target_list[MAXPLAYERS], target_count;
	bool tn_is_ml;
	char target_name[MAX_NAME_LENGTH];
	char reason[256];
	bool skipped = false;

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
		char sArg[3][192];

		if (ExplodeString(sArgs, "\"", sArg, 3, 192, true) == 3 && strlen(sArg[0]) == 0) // exploding by quotes
		{
			char sTempArg[2][192];
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

	int admImmunity = GetAdmImmunity(client);
	char adminAuth[64];

	if (client && IsClientInGame(client))
	{
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}
	else
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
	}

	for (int i = 0; i < target_count; i++)
	{
		char auth[64];
		int target = target_list[i];

		if (target && IsClientInGame(target))
		{
			if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth), false))
			{
				g_bPlayerStatus[target] = false;
			}
			if (strncmp(auth[6], "ID_", 3) != 0 )
			{
				g_bPlayerStatus[target] = false;
			}
			else
			{
				g_bPlayerStatus[target] = true;
			}
		}

		#if defined DEBUG
		PrintToServer("Processing block for %s", auth);
		#endif

		if (!g_bPlayerStatus[target])
		{
			// The target has not been blocks verify. It must be completed before you can block anyone.
			char name[32];
			GetClientName(target, name, sizeof(name));
			ReplyToCommand(client, "%s%t", PREFIX, "Player Comms Not Verified", name);
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

stock void ProcessUnBlock(int client, int targetId = 0, int type, char[] sReason = "", const char[] sArgs = "")
{
	#if defined DEBUG
	PrintToServer("ProcessUnBlock(admin: %d, target: %d, type: %d, reason: %s, args: %s)", client, targetId, type, sReason, sArgs);
	#endif

	int target_list[MAXPLAYERS], target_count;
	bool tn_is_ml;
	char target_name[MAX_NAME_LENGTH];
	char reason[256];

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
		char sBuffer[256];
		char sArg[3][192];
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

	char adminAuth[64];
	char targetAuth[64];

	if (client && IsClientConnected(client))
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

		for (int i = 0; i < target_count; i++)
		{
			int target = target_list[i];

			if (target && IsClientConnected(target))
			{
				if (!GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth), false))
				{
					g_bPlayerStatus[target] = false;
					continue;
				}
				if (strncmp(targetAuth[6], "ID_", 3) != 0 )
				{
					g_bPlayerStatus[target] = false;
					continue;
				}
			}

			if (!g_bPlayerStatus[target])
			{
				// The target has not been blocks verify. It must be completed before you can unblock anyone.
				char name[32];
				GetClientName(target, name, sizeof(name));
				ReplyToCommand(client, "%s%t", PREFIX, "Player Comms Not Verified", name);
				continue; // skip
			}

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

			ProcessUnBlock(client, target, type, reason);
		}

		#if defined DEBUG
		PrintToServer("Showing activity to server in ProcessUnBlock for targets_count > 1");
		#endif
		ShowActivityToServer(client, type + TYPE_TEMP_SHIFT, _, _, target_name, tn_is_ml);
	}
	else
	{
		char typeWHERE[100];
		int target = target_list[0];

		if (IsClientInGame(target))
		{
			if (!GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth), false))
				g_bPlayerStatus[target] = false;
			if (strncmp(targetAuth[6], "ID_", 3) != 0 )
				g_bPlayerStatus[target] = false;
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
					FormatEx(typeWHERE, sizeof(typeWHERE), "c.type = '%d'", TYPE_MUTE);
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
					FormatEx(typeWHERE, sizeof(typeWHERE), "c.type = '%d'", TYPE_GAG);
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
					FormatEx(typeWHERE, sizeof(typeWHERE), "(c.type = '%d' OR c.type = '%d')", TYPE_MUTE, TYPE_GAG);
			}
		}

		// Pack everything into a data pack so we can retain it
		DataPack dataPack = new DataPack();
		dataPack.WriteCell(GetClientUserId2(client));
		dataPack.WriteCell(GetClientUserId(target));
		dataPack.WriteCell(type);
		dataPack.WriteString(adminAuth);
		dataPack.WriteString(targetAuth);
		dataPack.WriteString(reason);

		// Check current player status. If player has temporary punishment - don't get info from DB
		if (DB_Connect())
		{
			char sAdminAuthEscaped[sizeof(adminAuth) * 2 + 1];
			char sAdminAuthYZEscaped[sizeof(adminAuth) * 2 + 1];
			char sTargetAuthEscaped[sizeof(targetAuth) * 2 + 1];
			char sTargetAuthYZEscaped[sizeof(targetAuth) * 2 + 1];

			g_hDatabase.Escape(adminAuth, sAdminAuthEscaped, sizeof(sAdminAuthEscaped));
			g_hDatabase.Escape(adminAuth[8], sAdminAuthYZEscaped, sizeof(sAdminAuthYZEscaped));
			g_hDatabase.Escape(targetAuth, sTargetAuthEscaped, sizeof(sTargetAuthEscaped));
			g_hDatabase.Escape(targetAuth[8], sTargetAuthYZEscaped, sizeof(sTargetAuthYZEscaped));

			char query[4096];
			Format(query, sizeof(query),
				"SELECT		c.bid, \
							IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '0') as iaid, \
							c.aid, \
							IF (a.immunity>=g.immunity, a.immunity, IFNULL(g.immunity,0)) as immunity, \
							c.type \
				FROM		%s_comms	 AS c \
				LEFT JOIN	%s_admins	AS a ON a.aid = c.aid \
				LEFT JOIN	%s_srvgroups AS g ON g.name = a.srv_group \
				WHERE		RemoveType IS NULL \
								AND (c.authid = '%s' OR c.authid REGEXP '^STEAM_[0-9]:%s$') \
								AND (length = '0' OR ends > UNIX_TIMESTAMP()) \
								AND %s",
				DatabasePrefix, sAdminAuthEscaped, sAdminAuthYZEscaped, DatabasePrefix, DatabasePrefix, DatabasePrefix, sTargetAuthEscaped, sTargetAuthYZEscaped, typeWHERE);

			#if defined LOG_QUERIES
			LogToFile(logQuery, "ProcessUnBlock. QUERY: %s", query);
			#endif

			g_hDatabase.Query(Query_UnBlockSelect, query, dataPack);
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

stock bool TempUnBlock(DataPack dataPack)
{
	char adminAuth[30], targetAuth[30];
	char reason[256];

	dataPack.Reset();
	int adminUserID = dataPack.ReadCell();
	int targetUserID = dataPack.ReadCell();
	int type = dataPack.ReadCell();
	dataPack.ReadString(adminAuth, sizeof(adminAuth));
	dataPack.ReadString(targetAuth, sizeof(targetAuth));
	dataPack.ReadString(reason, sizeof(reason));
	delete dataPack; // Need to close datapack

	#if defined DEBUG
	PrintToServer("TempUnBlock(adminUID: %d, targetUID: %d, type: %d, adminAuth: %s, targetAuth: %s, reason: %s)", adminUserID, targetUserID, type, adminAuth, targetAuth, reason);
	#endif

	int admin = GetClientOfUserId(adminUserID);
	int target = GetClientOfUserId(targetUserID);
	if (!target)
		return false; // target has gone away

	int AdmImmunity = GetAdmImmunity(admin);
	bool AdmImCheck = (DisUBImCheck == 0
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
	bool bHasPermission = (!admin && StrEqual(adminAuth, "STEAM_ID_SERVER")) || AdmHasFlag(admin) || AdmImCheck;
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

stock void InsertTempBlock(int length, int type, const char[] name, const char[] auth, const char[] reason, const char[] adminAuth, const char[] adminIp)
{
	LogMessage("Saving punishment for %s into queue", auth);

	char banName[MAX_NAME_LENGTH * 2 + 1];
	char banReason[256 * 2 + 1];
	char sAuthEscaped[64 * 2 + 1];
	char sAdminAuthEscaped[64 * 2 + 1];
	char sQuery[4096], sQueryVal[2048];
	char sQueryMute[2048], sQueryGag[2048];

	// escaping everything
	SQLiteDB.Escape(name, banName, sizeof(banName));
	SQLiteDB.Escape(reason, banReason, sizeof(banReason));
	SQLiteDB.Escape(auth, sAuthEscaped, sizeof(sAuthEscaped));
	SQLiteDB.Escape(adminAuth, sAdminAuthEscaped, sizeof(sAdminAuthEscaped));

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

	SQLiteDB.Query(Query_ErrorCheck, sQuery);
}

stock void ServerInfo()
{
	int pieces[4];
	int longip = CvarHostIp.IntValue;
	pieces[0] = (longip >> 24) & 0x000000FF;
	pieces[1] = (longip >> 16) & 0x000000FF;
	pieces[2] = (longip >> 8) & 0x000000FF;
	pieces[3] = longip & 0x000000FF;
	FormatEx(ServerIp, sizeof(ServerIp), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);
	CvarPort.GetString(ServerPort, sizeof(ServerPort));
}

stock void ReadConfig()
{
	InitializeConfigParser();

	if (ConfigParser == null)
	{
		return;
	}

	char ConfigFile1[PLATFORM_MAX_PATH], ConfigFile2[PLATFORM_MAX_PATH];
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

void AdminMenu_GetPunishPhrase(int client, int target, char[] name, int length)
{
	char Buffer[192];
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

bool Bool_ValidMenuTarget(int client, int target)
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

stock bool IsAllowedBlockLength(int admin, int length, int target_count = 1)
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

stock bool AdmHasFlag(int admin)
{
	return admin && CheckCommandAccess(admin, "", UNBLOCK_FLAG, true);
}

stock int GetAdmImmunity(int admin)
{
	if (admin == 0)
		return 0;

	AdminId aid = GetUserAdmin(admin);
	if (aid == INVALID_ADMIN_ID)
		return 0;

	int iImmunity = aid.ImmunityLevel;
	int iGroupCount = aid.GroupCount;
	if (iGroupCount > 0)
	{
		int iGroupImmunity;
		char szDummy[4]; // for AdminId.GetGroup()

		for (int iGroupID; iGroupID < iGroupCount; iGroupID++)
		{
			iGroupImmunity = (aid.GetGroup(iGroupID, szDummy, sizeof(szDummy))).ImmunityLevel;
			if (iGroupImmunity > iImmunity)
				iImmunity = iGroupImmunity;
		}
	}

	return iImmunity;
}

stock int GetClientUserId2(int client)
{
	return client ? GetClientUserId(client) : 0; // 0 is for CONSOLE
}

stock void ForcePlayersRecheck()
{
	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i) && !IsFakeClient(i) && g_hPlayerRecheck[i] == INVALID_HANDLE)
		{
			#if defined DEBUG
			{
				char clientAuth[64];
				GetClientAuthId(i, AuthId_Steam2, clientAuth, sizeof(clientAuth));
				PrintToServer("Creating Recheck timer for %s", clientAuth);
			}
			#endif
			g_hPlayerRecheck[i] = CreateTimer(float(i), ClientRecheck, GetClientUserId(i));
		}
	}
}

stock bool NotApplyToThisServer(int srvID)
{
	return ConfigWhiteListOnly && FindValueInArray(g_hServersWhiteList, srvID) == -1;
}

stock void MarkClientAsUnMuted(int target)
{
	g_MuteType[target] = bNot;
	g_iMuteTime[target] = 0;
	g_iMuteLength[target] = 0;
	g_iMuteLevel[target] = -1;
	g_sMuteAdminName[target][0] = '\0';
	g_sMuteReason[target][0] = '\0';
	g_sMuteAdminAuth[target][0] = '\0';
}

stock void MarkClientAsUnGagged(int target)
{
	g_GagType[target] = bNot;
	g_iGagTime[target] = 0;
	g_iGagLength[target] = 0;
	g_iGagLevel[target] = -1;
	g_sGagAdminName[target][0] = '\0';
	g_sGagReason[target][0] = '\0';
	g_sGagAdminAuth[target][0] = '\0';
}

stock void MarkClientAsMuted(int target, int time = NOW, int length = -1, const char[] adminName = "CONSOLE", const char[] adminAuth = "STEAM_ID_SERVER", int adminImmunity = 0, const char[] reason = "")
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

stock void MarkClientAsGagged(int target, int time = NOW, int length = -1, const char[] adminName = "CONSOLE", const char[] adminAuth = "STEAM_ID_SERVER", int adminImmunity = 0, const char[] reason = "")
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

stock void CloseMuteExpireTimer(int target)
{
	if (g_hMuteExpireTimer[target] != INVALID_HANDLE && CloseHandle(g_hMuteExpireTimer[target]))
		g_hMuteExpireTimer[target] = INVALID_HANDLE;
}

stock void CloseGagExpireTimer(int target)
{
	if (g_hGagExpireTimer[target] != INVALID_HANDLE && CloseHandle(g_hGagExpireTimer[target]))
		g_hGagExpireTimer[target] = INVALID_HANDLE;
}

stock void CreateMuteExpireTimer(int target, int remainingTime = 0)
{
	if (g_iMuteLength[target] > 0)
	{
		DataPack dataPack;

		if (remainingTime)
			g_hMuteExpireTimer[target] = CreateDataTimer(float(remainingTime), Timer_MuteExpire, dataPack, TIMER_FLAG_NO_MAPCHANGE);
		else
			g_hMuteExpireTimer[target] = CreateDataTimer(float(g_iMuteLength[target] * 60), Timer_MuteExpire, dataPack, TIMER_FLAG_NO_MAPCHANGE);

		dataPack.WriteCell(target);
		dataPack.WriteCell(GetClientUserId(target));
	}
}

stock void CreateGagExpireTimer(int target, int remainingTime = 0)
{
	if (g_iGagLength[target] > 0)
	{
		DataPack dataPack;

		if (remainingTime)
			g_hGagExpireTimer[target] = CreateDataTimer(float(remainingTime), Timer_GagExpire, dataPack, TIMER_FLAG_NO_MAPCHANGE);
		else
			g_hGagExpireTimer[target] = CreateDataTimer(float(g_iGagLength[target] * 60), Timer_GagExpire, dataPack, TIMER_FLAG_NO_MAPCHANGE);

		dataPack.WriteCell(target);
		dataPack.WriteCell(GetClientUserId(target));
	}
}

stock void PerformUnMute(int target)
{
	MarkClientAsUnMuted(target);
	BaseComm_SetClientMute(target, false);
	CloseMuteExpireTimer(target);
}

stock void PerformUnGag(int target)
{
	MarkClientAsUnGagged(target);
	BaseComm_SetClientGag(target, false);
	CloseGagExpireTimer(target);
}

stock void PerformMute(int target, int time = NOW, int length = -1, const char[] adminName = "CONSOLE", const char[] adminAuth = "STEAM_ID_SERVER", int adminImmunity = 0, const char[] reason = "", int remaining_time = 0)
{
	MarkClientAsMuted(target, time, length, adminName, adminAuth, adminImmunity, reason);
	BaseComm_SetClientMute(target, true);
	CreateMuteExpireTimer(target, remaining_time);
}

stock void PerformGag(int target, int time = NOW, int length = -1, const char[] adminName = "CONSOLE", const char[] adminAuth = "STEAM_ID_SERVER", int adminImmunity = 0, const char[] reason = "", int remaining_time = 0)
{
	MarkClientAsGagged(target, time, length, adminName, adminAuth, adminImmunity, reason);
	BaseComm_SetClientGag(target, true);
	CreateGagExpireTimer(target, remaining_time);
}

stock void SavePunishment(int admin = 0, int target, int type, int length = -1, const char[] reason = "")
{
	if (type < TYPE_MUTE || type > TYPE_SILENCE)
		return;

	// target information
	char targetAuth[64];
	if (IsClientInGame(target))
	{
		GetClientAuthId(target, AuthId_Steam2, targetAuth, sizeof(targetAuth));
	}
	else
	{
		return;
	}

	char adminIp[24];
	char adminAuth[64];
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

	char sName[MAX_NAME_LENGTH];
	strcopy(sName, sizeof(sName), g_sName[target]);

	if (DB_Connect())
	{
		// Accepts length in minutes, writes to db in seconds! In all over places in plugin - length is in minutes.
		char banName[MAX_NAME_LENGTH * 2 + 1];
		char banReason[256 * 2 + 1];
		char sAuthidEscaped[64 * 2 + 1];
		char sAdminAuthIdEscaped[64 * 2 + 1];
		char sAdminAuthIdYZEscaped[64 * 2 + 1];
		char sQuery[4096], sQueryAdm[512], sQueryVal[1024];
		char sQueryMute[1024], sQueryGag[1024];
		sQueryMute[0] = 0;
		sQueryGag[0]  = 0;

		// escaping everything
		g_hDatabase.Escape(sName, banName, sizeof(banName));
		g_hDatabase.Escape(reason, banReason, sizeof(banReason));
		g_hDatabase.Escape(targetAuth, sAuthidEscaped, sizeof(sAuthidEscaped));
		g_hDatabase.Escape(adminAuth, sAdminAuthIdEscaped, sizeof(sAdminAuthIdEscaped));
		g_hDatabase.Escape(adminAuth[8], sAdminAuthIdYZEscaped, sizeof(sAdminAuthIdYZEscaped));

		// bid	authid	name	created ends lenght reason aid adminip	sid	removedBy removedType removedon type ureason
		FormatEx(sQueryAdm, sizeof(sQueryAdm),
			"IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), 0)",
			DatabasePrefix, sAdminAuthIdEscaped, sAdminAuthIdYZEscaped);

		if (length >= 0)
		{
			// authid name, created, ends, length, reason, aid, adminIp, sid
			FormatEx(sQueryVal, sizeof(sQueryVal),
				"'%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', %s, '%s', %d",
				sAuthidEscaped, banName, length * 60, length * 60, banReason, sQueryAdm, adminIp, serverID);
		}
		else // Session mutes
		{
			// authid name, created, ends, length, reason, aid, adminIp, sid
			FormatEx(sQueryVal, sizeof(sQueryVal),
				"'%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', %s, '%s', %d",
				sAuthidEscaped, banName, SESSION_MUTE_FALLBACK, -1, banReason, sQueryAdm, adminIp, serverID);
		}

		switch (type)
		{
			case TYPE_GAG:
				FormatEx(sQueryGag, sizeof(sQueryGag), "(%s, %d)", sQueryVal, type);
			case TYPE_MUTE:
				FormatEx(sQueryMute, sizeof(sQueryMute), "(%s, %d)", sQueryVal, type);
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
		DataPack dataPack = new DataPack();
		dataPack.WriteCell(admin > 0 ? GetClientUserId(admin) : 0);
		dataPack.WriteCell(GetClientUserId(target));
		dataPack.WriteCell(length);
		dataPack.WriteCell(type);
		dataPack.WriteString(reason);
		dataPack.WriteString(sName);
		dataPack.WriteString(targetAuth);
		dataPack.WriteString(adminAuth);
		dataPack.WriteString(adminIp);

		g_hDatabase.Query(Query_AddBlockInsert, sQuery, dataPack, DBPrio_High);
	}
	else
		InsertTempBlock(length, type, sName, targetAuth, reason, adminAuth, adminIp);
}

stock void ShowActivityToServer(int admin, int type, int length = 0, char[] reason = "", char[] targetName, bool ml = false)
{
	#if defined DEBUG
	PrintToServer("ShowActivityToServer(admin: %d, type: %d, length: %d, reason: %s, name: %s, ml: %b",
		admin, type, length, reason, targetName, ml);
	#endif

	char actionName[32], translationName[64];
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
public int Native_SetClientMute(Handle hPlugin, int numParams)
{
    int target = GetNativeCell(1);
    if (target < 1 || target > MaxClients)
    {
        ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
        return false;
    }

    if (!IsClientInGame(target))
    {
        ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
        return false;
    }

    bool muteState = GetNativeCell(2);
    int muteLength = GetNativeCell(3);

    if (muteState && muteLength == 0)
    {
        ThrowNativeError(SP_ERROR_NATIVE, "Permanent mute is not allowed!");
        return false;
    }

    bool bSaveToDB = GetNativeCell(4);
    if (!muteState && bSaveToDB)
    {
        ThrowNativeError(SP_ERROR_NATIVE, "Removing punishments from DB is not allowed!");
        return false;
    }

    char sReason[256];
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

public int Native_SetClientGag(Handle hPlugin, int numParams)
{
	int target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
		return false;
	}

	if (!IsClientInGame(target))
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
		return false;
	}

	bool gagState = GetNativeCell(2);
	int gagLength = GetNativeCell(3);
	if (gagState && gagLength == 0)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Permanent gag is not allowed!");
		return false;
	}

	bool bSaveToDB = GetNativeCell(4);
	if (!gagState && bSaveToDB)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Removing punishments from DB is not allowed!");
		return false;
	}

	char sReason[256];
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

public int Native_GetClientMuteType(Handle hPlugin, int numParams)
{
	int target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
		return bNot;
	}

	if (!IsClientInGame(target))
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
		return bNot;
	}

	return g_MuteType[target];
}

public int Native_GetClientGagType(Handle hPlugin, int numParams)
{
	int target = GetNativeCell(1);
	if (target < 1 || target > MaxClients)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid client index %d", target);
		return bNot;
	}

	if (!IsClientInGame(target))
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Client %d is not in game", target);
		return bNot;
	}

	return g_GagType[target];
}
//Yarr!
