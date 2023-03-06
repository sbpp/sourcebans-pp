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
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

#pragma semicolon 1

#include <sourcemod>
#include <sourcebanspp>

#undef REQUIRE_PLUGIN
#include <adminmenu>
#tryinclude <updater>

#pragma newdecls required

#define SB_VERSION "1.8.0"

#if defined _updater_included
#define UPDATE_URL "https://sbpp.github.io/updater/updatefile.txt"
#endif

//GLOBAL DEFINES
#define DISABLE_ADDBAN		1
#define DISABLE_UNBAN		2

#define FLAG_LETTERS_SIZE 26

#define AUTO_ADD_SERVER_DISABLED     0
#define AUTO_ADD_SERVER_WITH_RCON    2

//#define DEBUG

enum State/* ConfigState */
{
	ConfigStateNone = 0,
	ConfigStateConfig,
	ConfigStateReasons,
	ConfigStateHacking,
	ConfigStateTime
}

State ConfigState;

#define Prefix "\x04[SourceBans++]\x01 "

/* Admin Stuff*/
AdminCachePart loadPart;

AdminFlag g_FlagLetters[FLAG_LETTERS_SIZE];

/* Cvar handle*/
ConVar CvarHostIp;
ConVar CvarPort;

ConVar sb_id;

/* Database handle */
Database DB;
Database SQLiteDB;

char
	ServerIp[24]
	, ServerPort[7]
	, DatabasePrefix[10] = "sb"
	, WebsiteAddress[128]
	, groupsLoc[128] /* Admin KeyValues */
	, adminsLoc[128]
	, overridesLoc[128]
	, logFile[256]; /* Log Stuff */

float RetryTime = 15.0;

bool
	loadAdmins /* Admin Stuff*/
	, loadGroups
	, loadOverrides
	, LateLoaded
	, g_bConnecting = false
	, requireSiteLogin = false /* Require a lastvisited from SB site */
	, backupConfig = true
	, enableAdmins = true
	, PlayerStatus[MAXPLAYERS + 1]; /* Player ban check status */

int
	g_BanTarget[MAXPLAYERS + 1] =  { -1, ... }
	, g_BanTime[MAXPLAYERS + 1] =  { -1, ... }
	, AutoAdd = 0
	, curLoading
	, serverID = -1
	, ProcessQueueTime = 5
	, g_ownReasons[MAXPLAYERS + 1] =  { false, ... } /* Own Chat Reason */
	, CommandDisable; /* Disable of addban and unban */


SMCParser ConfigParser;

Handle
	g_hFwd_OnBanAdded
	, g_hFwd_OnReportAdded
	, g_hFwd_OnClientPreAdminCheck
	, PlayerRecheck[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... }; /* Timer handle */

DataPack PlayerDataPack[MAXPLAYERS + 1] =  { null, ... };

TopMenu	hTopMenu = null;

Menu
	TimeMenuHandle
	, ReasonMenuHandle
	, HackingMenuHandle;

public Plugin myinfo =
{
	name = "SourceBans++: Main Plugin",
	author = "SourceBans Development Team, SourceBans++ Dev Team",
	description = "Advanced ban management for the Source engine",
	version = SB_VERSION,
	url = "https://sbpp.github.io"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	RegPluginLibrary("sourcebans++");

	CreateNative("SBBanPlayer", Native_SBBanPlayer);
	CreateNative("SBPP_BanPlayer", Native_SBBanPlayer);
	CreateNative("SBPP_ReportPlayer", Native_SBReportPlayer);

	g_hFwd_OnBanAdded = CreateGlobalForward("SBPP_OnBanPlayer", ET_Ignore, Param_Cell, Param_Cell, Param_Cell, Param_String);
	g_hFwd_OnReportAdded = CreateGlobalForward("SBPP_OnReportPlayer", ET_Ignore, Param_Cell, Param_Cell, Param_String);
	g_hFwd_OnClientPreAdminCheck = CreateGlobalForward("SBPP_OnClientPreAdminCheck", ET_Ignore, Param_Cell);

	LateLoaded = late;

	return APLRes_Success;
}

public void OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("plugin.basecommands");
	LoadTranslations("sbpp_main.phrases");
	LoadTranslations("basebans.phrases");
	loadAdmins = loadGroups = loadOverrides = false;

	CvarHostIp = FindConVar("hostip");
	CvarPort = FindConVar("hostport");
	CreateConVar("sb_version", SB_VERSION, _, FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY);
	RegServerCmd("sm_rehash", sm_rehash, "Reload SQL admins");
	RegAdminCmd("sm_ban", CommandBan, ADMFLAG_BAN, "sm_ban <#userid|name> <minutes|0> [reason]", "sourcebans");
	RegAdminCmd("sm_banip", CommandBanIp, ADMFLAG_BAN, "sm_banip <ip|#userid|name> <time> [reason]", "sourcebans");
	RegAdminCmd("sm_addban", CommandAddBan, ADMFLAG_RCON, "sm_addban <time> <steamid> [reason]", "sourcebans");
	RegAdminCmd("sm_unban", CommandUnban, ADMFLAG_UNBAN, "sm_unban <steamid|ip> [reason]", "sourcebans");
	RegAdminCmd("sb_reload", CommandReload, ADMFLAG_RCON, "Reload sourcebans config and ban reason menu options", "sourcebans");

	RegConsoleCmd("say", ChatHook);
	RegConsoleCmd("say_team", ChatHook);

	sb_id = CreateConVar
	(
		"sb_id",
		"-1",
		"Set to a value other than -1 to override the serverid in sourcebans.cfg",
		FCVAR_NONE,
		true,
		-1.0,
		false
	);
	HookConVarChange(sb_id, sbid_reload);

	if ((TimeMenuHandle = CreateMenu(MenuHandler_BanTimeList, MenuAction_Select|MenuAction_Cancel|MenuAction_DrawItem)) != INVALID_HANDLE)
	{
		TimeMenuHandle.Pagination = 8;
		TimeMenuHandle.ExitBackButton = true;
	}

	if ((ReasonMenuHandle = new Menu(ReasonSelected)) != INVALID_HANDLE)
	{
		ReasonMenuHandle.Pagination = 8;
		ReasonMenuHandle.ExitBackButton = true;
	}

	if ((HackingMenuHandle = new Menu(HackingSelected)) != INVALID_HANDLE)
	{
		HackingMenuHandle.Pagination = 8;
		HackingMenuHandle.ExitBackButton = true;
	}

	g_FlagLetters = CreateFlagLetters();

	BuildPath(Path_SM, logFile, sizeof(logFile), "logs/sourcebans.log");
	g_bConnecting = true;

	// Catch config error and show link to FAQ
	if (!SQL_CheckConfig("sourcebans"))
	{
		if (ReasonMenuHandle != INVALID_HANDLE)
			CloseHandle(ReasonMenuHandle);
		if (HackingMenuHandle != INVALID_HANDLE)
			CloseHandle(HackingMenuHandle);
		LogToFile(logFile, "Database failure: Could not find Database conf \"sourcebans\". See Docs: https://sbpp.github.io/docs/");
		SetFailState("Database failure: Could not find Database conf \"sourcebans\"");
		return;
	}

	Database.Connect(GotDatabase, "sourcebans");

	BuildPath(Path_SM, groupsLoc, sizeof(groupsLoc), "configs/sourcebans/sb_admin_groups.cfg");

	BuildPath(Path_SM, adminsLoc, sizeof(adminsLoc), "configs/sourcebans/sb_admins.cfg");

	BuildPath(Path_SM, overridesLoc, sizeof(overridesLoc), "configs/sourcebans/overrides_backup.cfg");

	InitializeBackupDB();

	// This timer is what processes the SQLite queue when the database is unavailable
	CreateTimer(float(ProcessQueueTime * 60), ProcessQueue);

	if (LateLoaded)
	{
		AccountForLateLoading();
	}

	#if defined _updater_included
	if (LibraryExists("updater"))
	{
		Updater_AddPlugin(UPDATE_URL);
	}
	#endif
}

#if defined _updater_included
public void OnLibraryAdded(const char[] name)
{
	if (StrEqual(name, "updater"))
	{
		Updater_AddPlugin(UPDATE_URL);
	}
}
#endif

public void OnAllPluginsLoaded()
{
	TopMenu topmenu;
	#if defined DEBUG
	LogToFile(logFile, "OnAllPluginsLoaded()");
	#endif

	if (LibraryExists("adminmenu") && ((topmenu = GetAdminTopMenu()) != INVALID_HANDLE))
	{
		OnAdminMenuReady(topmenu);
	}
}

public void OnConfigsExecuted()
{
	char filename[200];
	BuildPath(Path_SM, filename, sizeof(filename), "plugins/basebans.smx");
	if (FileExists(filename))
	{
		char newfilename[200];
		BuildPath(Path_SM, newfilename, sizeof(newfilename), "plugins/disabled/basebans.smx");
		ServerCommand("sm plugins unload basebans");
		if (FileExists(newfilename))
			DeleteFile(newfilename);
		RenameFile(newfilename, filename);
		LogToFile(logFile, "plugins/basebans.smx was unloaded and moved to plugins/disabled/basebans.smx");
	}
}

public void OnMapStart()
{
	ResetSettings();
}

void sbid_reload(ConVar convar, const char[] oldValue, const char[] newValue)
{
	ResetSettings();
}

public void OnMapEnd()
{
	for (int i = 0; i <= MaxClients; i++)
	{
		if (PlayerDataPack[i] != null)
		{
			/* Need to close reason pack */
			delete PlayerDataPack[i];
		}
	}
}

// CLIENT CONNECTION FUNCTIONS //

public Action OnClientPreAdminCheck(int client)
{
	if (!DB || GetUserAdmin(client) != INVALID_ADMIN_ID)
		return Plugin_Continue;

	return curLoading > 0 ? Plugin_Handled : Plugin_Continue;
}

public void OnClientDisconnect(int client)
{
	if (PlayerRecheck[client] != INVALID_HANDLE)
	{
		KillTimer(PlayerRecheck[client]);
		PlayerRecheck[client] = INVALID_HANDLE;
	}
	g_ownReasons[client] = false;
}

public bool OnClientConnect(int client, char[] rejectmsg, int maxlen)
{
	PlayerStatus[client] = false;
	return true;
}

public void OnClientAuthorized(int client, const char[] auth)
{
	/* Do not check bots nor check player with lan steamid. */
	if (auth[0] == 'B' || auth[9] == 'L' || DB == INVALID_HANDLE)
	{
		PlayerStatus[client] = true;
		return;
	}

	char Query[256], ip[30];

	GetClientIP(client, ip, sizeof(ip));

	FormatEx(Query, sizeof(Query), "SELECT bid, ip FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, auth[8], ip);

	#if defined DEBUG
	LogToFile(logFile, "Checking ban for: %s", auth);
	#endif

	DB.Query(VerifyBan, Query, GetClientUserId(client), DBPrio_High);
}

public void OnRebuildAdminCache(AdminCachePart part)
{
	loadPart = part;
	switch (loadPart)
	{
		case AdminCache_Overrides:
		loadOverrides = true;
		case AdminCache_Groups:
		loadGroups = true;
		case AdminCache_Admins:
		loadAdmins = true;
	}
	if (DB == INVALID_HANDLE) {
		if (!g_bConnecting) {
			g_bConnecting = true;
			Database.Connect(GotDatabase, "sourcebans");
		}
	}
	else {
		GotDatabase(DB, "", 0);
	}
}

// COMMAND CODE //

public Action ChatHook(int client, int args)
{
	// is this player preparing to ban someone
	if (g_ownReasons[client])
	{
		// get the reason
		char reason[512];
		GetCmdArgString(reason, sizeof(reason));
		StripQuotes(reason);

		g_ownReasons[client] = false;

		if (StrEqual(reason[0], "!noreason"))
		{
			PrintToChat(client, "%s%t", Prefix, "Chat Reason Aborted");
			return Plugin_Handled;
		}

		// ban him!
		PrepareBan(client, g_BanTarget[client], g_BanTime[client], reason, sizeof(reason));

		// block the reason to be sent in chat
		return Plugin_Handled;
	}
	return Plugin_Continue;
}

public Action CommandReload(int client, int args)
{
	ResetSettings();
	return Plugin_Handled;
}

public Action CommandBan(int client, int args)
{
	if ( !args && client )
	{
		DisplayBanTargetMenu(client);
		return Plugin_Handled;
	}
	else if ( args < 2 )
	{
		ReplyToCommand(client, "%sUsage: sm_ban <#userid|name> <time|0> [reason]", Prefix);
		return Plugin_Handled;
	}

	// This is mainly for me sanity since client used to be called admin and target used to be called client
	int admin = client;

	// Get the target, find target returns a message on failure so we do not
	char buffer[100];

	GetCmdArg(1, buffer, sizeof(buffer));

	int  target = FindTarget(client, buffer, true);

	if (target == -1)
	{
		return Plugin_Handled;
	}

	// Get the ban time
	GetCmdArg(2, buffer, sizeof(buffer));

	int time = StringToInt(buffer);
	
	if (!StringToIntEx(buffer, time))
	{
		ReplyToCommand(client, "%sUsage: sm_ban <#userid|name> <time|0> [reason]", Prefix);
		return Plugin_Handled;
	}

	if (!time && client && !(CheckCommandAccess(client, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "You do not have Perm Ban Permission");
		return Plugin_Handled;
	}

	// Get the reason
	char reason[128];

	if (args >= 3)
	{
		GetCmdArg(3, reason, sizeof(reason));
		for (int i = 4; i <= args; i++)
		{
			GetCmdArg(i, buffer, sizeof(buffer));
			Format(reason, sizeof(reason), "%s %s", reason, buffer);
		}
	}
	else
	{
		reason[0] = '\0';
	}

	g_BanTarget[client] = target;
	g_BanTime[client] = time;

	if (!PlayerStatus[target])
	{
		// The target has not been banned verify. It must be completed before you can ban anyone.
		ReplyToCommand(admin, "%s%t", Prefix, "Ban Not Verified");
		return Plugin_Handled;
	}


	CreateBan(client, target, time, reason);
	return Plugin_Handled;
}

public Action CommandBanIp(int client, int args)
{
	if (args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_banip <ip|#userid|name> <time> [reason]", Prefix);
		return Plugin_Handled;
	}

	int len, next_len;
	char Arguments[256], arg[50], time[20], targetName[MAX_NAME_LENGTH];

	GetCmdArgString(Arguments, sizeof(Arguments));
	len = BreakString(Arguments, arg, sizeof(arg));

	if ((next_len = BreakString(Arguments[len], time, sizeof(time))) != -1)
	{
		len += next_len;
	}
	else
	{
		len = 0;
		Arguments[0] = '\0';
	}

	char target_name[MAX_TARGET_LENGTH];
	int target_list[1];
	bool tn_is_ml;

	int target = -1;

	if (ProcessTargetString(
			arg,
			client,
			target_list,
			1,
			COMMAND_FILTER_CONNECTED | COMMAND_FILTER_NO_MULTI,
			target_name,
			sizeof(target_name),
			tn_is_ml) > 0)
	{
		target = target_list[0];

		if (!IsFakeClient(target) && CanUserTarget(client, target))
		{
			GetClientName(target, targetName, sizeof(targetName));
			GetClientIP(target, arg, sizeof(arg));
		}
	}

	char adminIp[24], adminAuth[64];

	int minutes = StringToInt(time);

	if (!minutes && client && !(CheckCommandAccess(client, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "You do not have Perm Ban Permission");
		return Plugin_Handled;
	}
	if (!client)
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
		strcopy(adminIp, sizeof(adminIp), ServerIp);
	} else {
		GetClientIP(client, adminIp, sizeof(adminIp));
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}

	// Pack everything into a data pack so we can retain it
	DataPack dataPack = new DataPack();
	dataPack.WriteCell(client);
	dataPack.WriteCell(minutes);
	dataPack.WriteString(Arguments[len]);
	dataPack.WriteString(arg);
	dataPack.WriteString(targetName);
	dataPack.WriteString(adminAuth);
	dataPack.WriteString(adminIp);

	char sQuery[256];

	FormatEx(sQuery, sizeof(sQuery), "SELECT bid FROM %s_bans WHERE type = 1 AND ip     = '%s' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL",
		DatabasePrefix, arg);

	DB.Query(SelectBanIpCallback, sQuery, dataPack, DBPrio_High);

	return Plugin_Handled;
}

public Action CommandUnban(int client, int args)
{
	if (args < 1)
	{
		ReplyToCommand(client, "%sUsage: sm_unban <steamid|ip> [reason]", Prefix);
		return Plugin_Handled;
	}

	if (CommandDisable & DISABLE_UNBAN)
	{
		// They must go to the website to unban people
		ReplyToCommand(client, "%s%t", Prefix, "Can Not Unban", WebsiteAddress);
		return Plugin_Handled;
	}

	int len;
	char Arguments[256], arg[50], adminAuth[64];

	GetCmdArgString(Arguments, sizeof(Arguments));

	if ((len = BreakString(Arguments, arg, sizeof(arg))) == -1)
	{
		len = 0;
		Arguments[0] = '\0';
	}
	if (!client)
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
	} else {
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}

	// Pack everything into a data pack so we can retain it
	DataPack dataPack = new DataPack();
	dataPack.WriteCell(client);
	dataPack.WriteString(Arguments[len]);
	dataPack.WriteString(arg);
	dataPack.WriteString(adminAuth);

	char query[200];

	if (strncmp(arg, "STEAM_", 6) == 0)
	{
		Format(query, sizeof(query), "SELECT bid FROM %s_bans WHERE (type = 0 AND authid = '%s') AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, arg);
	} else {
		Format(query, sizeof(query), "SELECT bid FROM %s_bans WHERE (type = 1 AND ip     = '%s') AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, arg);
	}

	DB.Query(SelectUnbanCallback, query, dataPack);

	return Plugin_Handled;
}

public Action CommandAddBan(int client, int args)
{
	if (args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_addban <time> <steamid> [reason]", Prefix);
		return Plugin_Handled;
	}

	if (CommandDisable & DISABLE_ADDBAN)
	{
		// They must go to the website to add bans
		ReplyToCommand(client, "%s%t", Prefix, "Can Not Add Ban", WebsiteAddress);
		return Plugin_Handled;
	}

	char arg_string[256], time[50], authid[50];

	GetCmdArgString(arg_string, sizeof(arg_string));

	int len, total_len;

	/* Get time */
	if ((len = BreakString(arg_string, time, sizeof(time))) == -1)
	{
		ReplyToCommand(client, "%sUsage: sm_addban <time> <steamid> [reason]", Prefix);
		return Plugin_Handled;
	}
	total_len += len;

	/* Get steamid */
	if ((len = BreakString(arg_string[total_len], authid, sizeof(authid))) != -1)
	{
		total_len += len;
	}
	else
	{
		total_len = 0;
		arg_string[0] = '\0';
	}

	char adminIp[24], adminAuth[64];

	int  minutes = StringToInt(time);

	if (!minutes && client && !(CheckCommandAccess(client, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "You do not have Perm Ban Permission");
		return Plugin_Handled;
	}
	if (!client)
	{
		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
		strcopy(adminIp, sizeof(adminIp), ServerIp);
	} else {
		GetClientIP(client, adminIp, sizeof(adminIp));
		GetClientAuthId(client, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}

	// Pack everything into a data pack so we can retain it
	DataPack dataPack = new DataPack();
	dataPack.WriteCell(client);
	dataPack.WriteCell(minutes);
	dataPack.WriteString(arg_string[total_len]);
	dataPack.WriteString(authid);
	dataPack.WriteString(adminAuth);
	dataPack.WriteString(adminIp);

	char sQuery[256];

	FormatEx(sQuery, sizeof sQuery, "SELECT bid FROM %s_bans WHERE type = 0 AND authid = '%s' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL",
		DatabasePrefix, authid);

	DB.Query(SelectAddbanCallback, sQuery, dataPack, DBPrio_High);

	return Plugin_Handled;
}

public Action sm_rehash(int args)
{
	if (enableAdmins)
		DumpAdminCache(AdminCache_Groups, true);
	DumpAdminCache(AdminCache_Overrides, true);
	return Plugin_Handled;
}



// MENU CODE //

public void OnAdminMenuReady(Handle hTemp)
{
	TopMenu topmenu = view_as<TopMenu>(hTemp);
	#if defined DEBUG
	LogToFile(logFile, "OnAdminMenuReady()");
	#endif

	/* Block us from being called twice */
	if (topmenu == hTopMenu)
	{
		return;
	}

	/* Save the Handle */
	hTopMenu = topmenu;

	/* Find the "Player Commands" category */
	TopMenuObject player_commands = hTopMenu.FindCategory(ADMINMENU_PLAYERCOMMANDS);

	if (player_commands != INVALID_TOPMENUOBJECT)
	{
		// just to avoid "unused variable 'res'" warning
		#if defined DEBUG
		TopMenuObject res = hTopMenu.AddItem(
			"sm_ban",  // Name
			AdminMenu_Ban,  // Handler function
			player_commands,  // We are a submenu of Player Commands
			"sm_ban",  // The command to be finally called (Override checks)
			ADMFLAG_BAN); // What flag do we need to see the menu option
		char temp[125];
		Format(temp, 125, "Result of AddToTopMenu: %d", res);
		LogToFile(logFile, temp);
		LogToFile(logFile, "Added Ban option to admin menu");
		#else
		hTopMenu.AddItem(
			"sm_ban",  // Name
			AdminMenu_Ban,  // Handler function
			player_commands,  // We are a submenu of Player Commands
			"sm_ban",  // The command to be finally called (Override checks)
			ADMFLAG_BAN); // What flag do we need to see the menu option
		#endif
	}
}

public void AdminMenu_Ban(TopMenu topmenu,
	TopMenuAction action,  // Action being performed
	TopMenuObject object_id,  // The object ID (if used)
	int param,  // client idx of admin who chose the option (if used)
	char[] buffer,  // Output buffer (if used)
	int maxlength) // Output buffer (if used)
{
	/* Clear the Ownreason bool, so he is able to chat again;) */
	g_ownReasons[param] = false;

	#if defined DEBUG
	LogToFile(logFile, "AdminMenu_Ban()");
	#endif

	switch (action)
	{
		// We are only being displayed, We only need to show the option name
		case TopMenuAction_DisplayOption:
		{
			FormatEx(buffer, maxlength, "%T", "Ban player", param);

			#if defined DEBUG
			LogToFile(logFile, "AdminMenu_Ban() -> Formatted the Ban option text");
			#endif
		}

		case TopMenuAction_SelectOption:
		{
			DisplayBanTargetMenu(param); // Someone chose to ban someone, show the list of users menu

			#if defined DEBUG
			LogToFile(logFile, "AdminMenu_Ban() -> DisplayBanTargetMenu()");
			#endif
		}
	}
}

public int ReasonSelected(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_Select:
		{
			char info[128], key[128];

			menu.GetItem(param2, key, sizeof(key), _, info, sizeof(info));

			if (StrEqual("Hacking", key))
			{
				HackingMenuHandle.Display(param1, MENU_TIME_FOREVER);
				return 0;
			}

			else if (StrEqual("Own Reason", key)) // admin wants to use his own reason
			{
				g_ownReasons[param1] = true;
				PrintToChat(param1, "%s%t", Prefix, "Chat Reason");
				return 0;
			}

			else if (g_BanTarget[param1] != -1 && g_BanTime[param1] != -1)
				PrepareBan(param1, g_BanTarget[param1], g_BanTime[param1], info, sizeof(info));
		}

		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_Disconnected)
			{
				if (PlayerDataPack[param1] != null)
				{
					delete PlayerDataPack[param1];
				}
			}

			else
			{
				DisplayBanTimeMenu(param1);
			}
		}
	}
	return 0;
}

public int HackingSelected(Menu menu, MenuAction action, int param1, int param2)
{
	switch (action)
	{
		case MenuAction_Select:
		{
			char info[128], key[128];

			menu.GetItem(param2, key, sizeof(key), _, info, sizeof(info));

			if (g_BanTarget[param1] != -1 && g_BanTime[param1] != -1)
				PrepareBan(param1, g_BanTarget[param1], g_BanTime[param1], info, sizeof(info));
		}

		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_Disconnected)
			{
				DataPack Pack = PlayerDataPack[param1];

				if (Pack != null)
				{
					Pack.ReadCell(); // admin index
					Pack.ReadCell(); // target index
					Pack.ReadCell(); // admin userid
					Pack.ReadCell(); // target userid
					Pack.ReadCell(); // time

					DataPack ReasonPack = Pack.ReadCell();

					if (ReasonPack != INVALID_HANDLE)
					{
						CloseHandle(ReasonPack);
					}

					delete Pack;
					PlayerDataPack[param1] = null;
				}
			}

			else
			{
				DisplayMenu(ReasonMenuHandle, param1, MENU_TIME_FOREVER);
			}
		}
	}
	return 0;
}

public int MenuHandler_BanPlayerList(Menu menu, MenuAction action, int param1, int param2)
{
	#if defined DEBUG
	LogToFile(logFile, "MenuHandler_BanPlayerList()");
	#endif

	switch (action)
	{
		case MenuAction_End:
		{
			delete menu;
		}

		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
			{
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
			}
		}

		case MenuAction_Select:
		{
			char info[32], name[32];
			int userid, target;

			menu.GetItem(param2, info, sizeof(info), _, name, sizeof(name));
			userid = StringToInt(info);

			if ((target = GetClientOfUserId(userid)) == 0)
			{
				PrintToChat(param1, "%s%t", Prefix, "Player no longer available");
			}
			else if (!CanUserTarget(param1, target))
			{
				PrintToChat(param1, "%s%t", Prefix, "Unable to target");
			}
			else
			{
				g_BanTarget[param1] = target;
				DisplayBanTimeMenu(param1);
			}
		}
	}
	return 0;
}

public int MenuHandler_BanTimeList(Menu menu, MenuAction action, int param1, int param2)
{
	#if defined DEBUG
	LogToFile(logFile, "MenuHandler_BanTimeList()");
	#endif

	switch (action)
	{
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
			{
				hTopMenu.Display(param1, TopMenuPosition_LastCategory);
			}
		}

		case MenuAction_Select:
		{
			char info[32];

			menu.GetItem(param2, info, sizeof(info));
			g_BanTime[param1] = StringToInt(info);

			//DisplayBanReasonMenu(param1);
			ReasonMenuHandle.Display(param1, MENU_TIME_FOREVER);
		}

		case MenuAction_DrawItem:
		{
			char time[16];

			menu.GetItem(param2, time, sizeof(time));

			return (StringToInt(time) > 0 || CheckCommandAccess(param1, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT)) ? ITEMDRAW_DEFAULT : ITEMDRAW_DISABLED;
		}
	}

	return 0;
}

stock void DisplayBanTargetMenu(int client)
{
	#if defined DEBUG
	LogToFile(logFile, "DisplayBanTargetMenu()");
	#endif

	Menu menu = new Menu(MenuHandler_BanPlayerList); // Create a new menu, pass it the handler.

	char title[100];

	FormatEx(title, sizeof(title), "%T:", "Ban player", client);

	menu.SetTitle(title); // Set the title
	menu.ExitBackButton = true; // Yes we want back/exit

	AddTargetsToMenu(menu,  // Add clients to our menu
		client,  // The client that called the display
		false,  // We want to see people connecting
		false); // And dead people

	menu.Display(client, MENU_TIME_FOREVER); // Show the menu to the client FOREVER!
}

stock void DisplayBanTimeMenu(int client)
{
	#if defined DEBUG
	LogToFile(logFile, "DisplayBanTimeMenu()");
	#endif

	char title[100];
	FormatEx(title, sizeof(title), "%T:", "Ban player", client);
	SetMenuTitle(TimeMenuHandle, title);

	DisplayMenu(TimeMenuHandle, client, MENU_TIME_FOREVER);
}

stock void ResetMenu()
{
	if (TimeMenuHandle != INVALID_HANDLE)
	{
		RemoveAllMenuItems(TimeMenuHandle);
	}

	if (ReasonMenuHandle != INVALID_HANDLE)
	{
		RemoveAllMenuItems(ReasonMenuHandle);
	}

	if (HackingMenuHandle != INVALID_HANDLE)
	{
		RemoveAllMenuItems(HackingMenuHandle);
	}
}

// QUERY CALL BACKS //

public void GotDatabase(Database db, const char[] error, any data)
{
	if (db == INVALID_HANDLE)
	{
		LogToFile(logFile, "Database failure: %s. See Docs: https://sbpp.github.io/docs/", error);
		g_bConnecting = false;

		// Parse the overrides backup!
		ParseBackupConfig_Overrides();
		return;
	}

	DB = db;

	char query[1024];

	Format(query, sizeof(query), "SET NAMES utf8mb4");
	DB.Query(ErrorCheckCallback, query);

	InsertServerInfo();

	//CreateTimer(900.0, PruneBans);

	if (loadOverrides)
	{
		Format(query, 1024, "SELECT type, name, flags FROM %s_overrides", DatabasePrefix);
		DB.Query(OverridesDone, query);
		loadOverrides = false;
	}

	if (loadGroups && enableAdmins)
	{
		FormatEx(query, 1024, "SELECT name, flags, immunity, groups_immune   \
					FROM %s_srvgroups ORDER BY id", DatabasePrefix);
		curLoading++;
		DB.Query(GroupsDone, query);

		#if defined DEBUG
		LogToFile(logFile, "Fetching Group List");
		#endif
		loadGroups = false;
	}

	if (loadAdmins && enableAdmins)
	{
		char queryLastLogin[50] = "";

		if (requireSiteLogin)
			queryLastLogin = "lastvisit IS NOT NULL AND lastvisit != '' AND";

		if (serverID == -1)
		{
			FormatEx(query, 1024, "SELECT authid, srv_password, (SELECT name FROM %s_srvgroups WHERE name = srv_group AND flags != '') AS srv_group, srv_flags, user, immunity  \
						FROM %s_admins_servers_groups AS asg \
						LEFT JOIN %s_admins AS a ON a.aid = asg.admin_id \
						WHERE %s (server_id = (SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1)  \
						OR srv_group_id = ANY (SELECT group_id FROM %s_servers_groups WHERE server_id = (SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1))) \
						GROUP BY aid, authid, srv_password, srv_group, srv_flags, user",
				DatabasePrefix, DatabasePrefix, DatabasePrefix, queryLastLogin, DatabasePrefix, ServerIp, ServerPort, DatabasePrefix, DatabasePrefix, ServerIp, ServerPort);
		} else {
			FormatEx(query, 1024, "SELECT authid, srv_password, (SELECT name FROM %s_srvgroups WHERE name = srv_group AND flags != '') AS srv_group, srv_flags, user, immunity  \
						FROM %s_admins_servers_groups AS asg \
						LEFT JOIN %s_admins AS a ON a.aid = asg.admin_id \
						WHERE %s server_id = %d  \
						OR srv_group_id = ANY (SELECT group_id FROM %s_servers_groups WHERE server_id = %d) \
						GROUP BY aid, authid, srv_password, srv_group, srv_flags, user",
				DatabasePrefix, DatabasePrefix, DatabasePrefix, queryLastLogin, serverID, DatabasePrefix, serverID);
		}
		curLoading++;
		DB.Query(AdminsDone, query);

		#if defined DEBUG
		LogToFile(logFile, "Fetching Admin List");
		LogToFile(logFile, query);
		#endif
		loadAdmins = false;
	}
	g_bConnecting = false;
}

public void VerifyInsert(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	if (dataPack == INVALID_HANDLE)
	{
		LogToFile(logFile, "%t: %s", "Ban Fail", error);

		return;
	}

	if (results == null)
	{
		LogToFile(logFile, "Verify Insert Query Failed: %s", error);

		int admin = dataPack.ReadCell();
		dataPack.ReadCell(); // target
		dataPack.ReadCell(); // admin userid
		dataPack.ReadCell(); // target userid
		int time = dataPack.ReadCell();

		DataPack reasonPack = dataPack.ReadCell();

		char reason[128], name[50], auth[30], ip[20], adminAuth[30], adminIp[20];

		reasonPack.ReadString(reason, sizeof reason);

		dataPack.ReadString(name, sizeof name);
		dataPack.ReadString(auth, sizeof auth);
		dataPack.ReadString(ip, sizeof ip);
		dataPack.ReadString(adminAuth, sizeof adminAuth);
		dataPack.ReadString(adminIp, sizeof adminIp);

		dataPack.Reset();
		reasonPack.Reset();

		PlayerDataPack[admin] = null;
		UTIL_InsertTempBan(time, name, auth, ip, reason, adminAuth, adminIp, dataPack);
		return;
	}

	int admin = dataPack.ReadCell();
	int client = dataPack.ReadCell();

	if (!IsClientConnected(client) || IsFakeClient(client))
		return;

	dataPack.ReadCell(); // admin userid

	int UserId = dataPack.ReadCell();
	int time = dataPack.ReadCell();

	DataPack ReasonPack = dataPack.ReadCell();

	char Name[64], Reason[128];

	dataPack.ReadString(Name, sizeof(Name));
	ReasonPack.ReadString(Reason, sizeof(Reason));

	if (!time)
	{
		if (Reason[0] == '\0')
		{
			ShowActivity2(admin, Prefix, "%t", "Permabanned Player", Name);
		} else {
			ShowActivity2(admin, Prefix, "%t", "Permabanned Player Reason", Name, Reason);
		}
	} else {
		if (Reason[0] == '\0')
		{
			ShowActivity2(admin, Prefix, "%t", "Banned Player", Name, time);
		} else {
			ShowActivity2(admin, Prefix, "%t", "Banned Player Reason", Name, time, Reason);
		}
	}

	LogAction(admin, client, "%t", "Ban Log", admin, client, time, Reason);

	if (PlayerDataPack[admin] != INVALID_HANDLE)
	{
		delete PlayerDataPack[admin];
		delete ReasonPack;
	}

	// Kick player
	if (GetClientUserId(client) == UserId)
	{
		char length[32];
		if(time == 0)
			FormatEx(length, sizeof(length), "%T", "permanent", client);
		else
			FormatEx(length, sizeof(length), "%d %T", time, time == 1 ? "minute" : "minutes", client);

		KickClient(client, "%t\n\n%t", "Banned Check Site", WebsiteAddress, "Kick Reason", admin, Reason, length);
	}
}

public void SelectBanIpCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int admin, minutes;
	char adminAuth[30], adminIp[30], banReason[256], ip[16], reason[128], Query[512];
	char targetName[MAX_NAME_LENGTH], sTEscapedName[MAX_NAME_LENGTH * 2 + 1];

	dataPack.Reset();
	admin = dataPack.ReadCell();
	minutes = dataPack.ReadCell();
	dataPack.ReadString(reason, sizeof(reason));
	dataPack.ReadString(ip, sizeof(ip));
	dataPack.ReadString(targetName, sizeof(targetName));
	dataPack.ReadString(adminAuth, sizeof(adminAuth));
	dataPack.ReadString(adminIp, sizeof(adminIp));
	DB.Escape(reason, banReason, sizeof(banReason));
	DB.Escape(targetName, sTEscapedName, sizeof(sTEscapedName));

	if (results == null)
	{
		LogToFile(logFile, "Ban IP Select Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%t", Prefix, "Ban Fail");
		else
			PrintToServer("%s%t", Prefix, "Ban Fail");

		return;
	}
	if (results.RowCount)
	{
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%t", Prefix, "Already Banned", ip);
		else
			PrintToServer("%s%t", Prefix, "Already Banned", ip);

		return;
	}
	if (serverID == -1)
	{
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (type, ip, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						(1, '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						(SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1), ' ')",
			DatabasePrefix, ip, sTEscapedName, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, DatabasePrefix, ServerIp, ServerPort);
	} else {
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (type, ip, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						(1, '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						%d, ' ')",
			DatabasePrefix, ip, sTEscapedName, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, serverID);
	}

	db.Query(InsertBanIpCallback, Query, dataPack, DBPrio_High);
}

public void InsertBanIpCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	// if the pack is good unpack it and close the handle
	int admin, minutes;
	int target = -1;
	char reason[128];
	char targetIP[30];

	if (dataPack != null)
	{
		dataPack.Reset();
		admin = dataPack.ReadCell();
		minutes = dataPack.ReadCell();
		dataPack.ReadString(reason, sizeof(reason));
		dataPack.ReadString(targetIP, sizeof(targetIP));
		
		for(int i = 1; i <= MaxClients; i++)
		{
			if(!IsClientInGame(i) || IsFakeClient(i))
				continue;
			
			char ip[30];
			GetClientIP(i, ip, sizeof(ip));
			if(StrEqual(targetIP, ip, false))
			{
				target = i;
				break;
			}
		}

		// Kick player
		if(target != -1)
		{
			char length[32];
			if(minutes == 0)
				FormatEx(length, sizeof(length), "permament");
			else
				FormatEx(length, sizeof(length), "%d %s", minutes, minutes == 1 ? "minute" : "minutes");

			if (IsClientConnected(target))
				KickClient(target, "%t\n\n%t", "Banned Check Site", WebsiteAddress, "Kick Reason", admin, reason, length);
		}

		delete dataPack;
	} else {
		// Technically this should not be possible
		ThrowError("Invalid Handle in InsertBanIpCallback");
	}

	// If error is not an empty string the query failed
	if (results == null)
	{
		LogToFile(logFile, "Ban IP Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%ssm_banip failed", Prefix);
		return;
	}

	LogAction(admin, -1, "%t", "Ban Added", admin, minutes, targetIP, reason);

	if (admin && IsClientInGame(admin))
		PrintToChat(admin, "%s%t", Prefix, "Ban Success Name", targetIP);
	else
		PrintToServer("%s%t", Prefix, "Ban Success Name", targetIP);
}

public void SelectUnbanCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int admin;
	char arg[30], adminAuth[30], unbanReason[256];
	char reason[128];

	dataPack.Reset();
	admin = dataPack.ReadCell();
	dataPack.ReadString(reason, sizeof(reason));
	dataPack.ReadString(arg, sizeof(arg));
	dataPack.ReadString(adminAuth, sizeof(adminAuth));

	db.Escape(reason, unbanReason, sizeof(unbanReason));

	// If error is not an empty string the query failed
	if (results == null)
	{
		LogToFile(logFile, "Unban Select Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_unban failed", Prefix);
		}
		delete dataPack;
		return;
	}

	// If there was no results then a ban does not exist for that id
	if (!results.RowCount)
	{
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%t", Prefix, "No Active Ban");
		else
			PrintToServer("%s%t", Prefix, "No Active Ban");

		delete dataPack;

		return;
	}

	// There is ban
	if (results != null && results.FetchRow())
	{
		// Get the values from the existing ban record
		int bid = results.FetchInt(0);

		char query[1000];
		Format(query, sizeof(query), "UPDATE %s_bans SET RemovedBy = (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), RemoveType = 'U', RemovedOn = UNIX_TIMESTAMP(), ureason = '%s' WHERE bid = %d",
			DatabasePrefix, DatabasePrefix, adminAuth, adminAuth[8], unbanReason, bid);

		db.Query(InsertUnbanCallback, query, dataPack);
	}
	return;
}

public void InsertUnbanCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	// if the pack is good unpack it and close the handle
	int admin;
	char arg[30];
	char reason[128];

	if (dataPack != null)
	{
		dataPack.Reset();
		admin = dataPack.ReadCell();
		dataPack.ReadString(reason, sizeof(reason));
		dataPack.ReadString(arg, sizeof(arg));
		delete dataPack;
	} else {
		// Technically this should not be possible
		ThrowError("Invalid Handle in InsertUnbanCallback");
	}

	// If error is not an empty string the query failed
	if (results == null)
	{
		LogToFile(logFile, "Unban Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_unban failed", Prefix);
		}
		return;
	}

	LogAction(admin, -1, "%t", "Ban Removed", admin, arg, reason);
	
	if (admin && IsClientInGame(admin))
		PrintToChat(admin, "%s%t", Prefix, "Unban Success Name", arg);
	else
		PrintToServer("%s%t", Prefix, "Unban Success Name", arg);
}

public void SelectAddbanCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int admin, minutes;
	char adminAuth[30], adminIp[30], authid[20], banReason[256], Query[512];
	char reason[128];

	dataPack.Reset();
	admin = dataPack.ReadCell();
	minutes = dataPack.ReadCell();
	dataPack.ReadString(reason, sizeof(reason));
	dataPack.ReadString(authid, sizeof(authid));
	dataPack.ReadString(adminAuth, sizeof(adminAuth));
	dataPack.ReadString(adminIp, sizeof(adminIp));
	db.Escape(reason, banReason, sizeof(banReason));

	if (results == null)
	{
		LogToFile(logFile, "Add Ban Select Query Failed: %s", error);

		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%t", Prefix, "Ban Fail");
		else
			PrintToServer("%s%t", Prefix, "Ban Fail");

		return;
	}
	if (results.RowCount)
	{
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%t", Prefix, "Already Banned", authid);
		else
			PrintToServer("%s%t", Prefix, "Already Banned", authid);

		return;
	}
	if (serverID == -1)
	{
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (authid, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						('%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						(SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1), ' ')",
			DatabasePrefix, authid, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, DatabasePrefix, ServerIp, ServerPort);
	} else {
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (authid, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						('%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						%d, ' ')",
			DatabasePrefix, authid, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, serverID);
	}

	db.Query(InsertAddbanCallback, Query, dataPack, DBPrio_High);
}

public void InsertAddbanCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int admin, minutes;
	char authid[20];
	char reason[128];

	dataPack.Reset();
	admin = dataPack.ReadCell();
	minutes = dataPack.ReadCell();
	dataPack.ReadString(reason, sizeof(reason));
	dataPack.ReadString(authid, sizeof(authid));
	delete dataPack;

	// If error is not an empty string the query failed
	if (results == null)
	{
		LogToFile(logFile, "Add Ban Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_addban failed", Prefix);
		}
		return;
	}

	LogAction(admin, -1, "%t", "Ban Added", admin, minutes, authid, reason);

	if (admin && IsClientInGame(admin))
		PrintToChat(admin, "%s%t", Prefix, "Ban Success Name", authid);
	else
		PrintToServer("%s%t", Prefix, "Ban Success Name", authid);
}

// ProcessQueueCallback is called as the result of selecting all the rows from the queue table
public void ProcessQueueCallback(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		LogToFile(logFile, "Failed to retrieve queued bans from sqlite database, %s", error);
		return;
	}

	char auth[30];
	int time;
	int startTime;
	char reason[128];
	char name[64];
	char ip[20];
	char adminAuth[30];
	char adminIp[20];
	char query[1024];
	char banName[128];
	char banReason[256];
	while (results.MoreRows)
	{
		// Oh noes! What happened?!
		if (!results.FetchRow())
			continue;

		// if we get to here then there are rows in the queue pending processing
		results.FetchString(0, auth, sizeof(auth));
		time = results.FetchInt(1);
		startTime = results.FetchInt(2);
		results.FetchString(3, reason, sizeof(reason));
		results.FetchString(4, name, sizeof(name));
		results.FetchString(5, ip, sizeof(ip));
		results.FetchString(6, adminAuth, sizeof(adminAuth));
		results.FetchString(7, adminIp, sizeof(adminIp));
		db.Escape(name, banName, sizeof(banName));
		db.Escape(reason, banReason, sizeof(banReason));
		if (startTime + time * 60 > GetTime() || time == 0)
		{
			// This ban is still valid and should be entered into the db
			if (serverID == -1)
			{
				FormatEx(query, sizeof(query),
					"INSERT INTO %s_bans (ip, authid, name, created, ends, length, reason, aid, adminIp, sid) VALUES  \
						('%s', '%s', '%s', %d, %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						(SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1))",
					DatabasePrefix, ip, auth, banName, startTime, startTime + time * 60, time * 60, banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, DatabasePrefix, ServerIp, ServerPort);
			}
			else
			{
				FormatEx(query, sizeof(query),
					"INSERT INTO %s_bans (ip, authid, name, created, ends, length, reason, aid, adminIp, sid) VALUES  \
						('%s', '%s', '%s', %d, %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						%d)",
					DatabasePrefix, ip, auth, banName, startTime, startTime + time * 60, time * 60, banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, serverID);
			}
			DataPack authPack = new DataPack();
			authPack.WriteString(auth);
			authPack.Reset();
			db.Query(AddedFromSQLiteCallback, query, authPack);
		} else {
			// The ban is no longer valid and should be deleted from the queue
			FormatEx(query, sizeof(query), "DELETE FROM queue WHERE steam_id = '%s'", auth);
			SQLiteDB.Query(ErrorCheckCallback, query);
		}
	}
	// We have finished processing the queue but should process again in ProcessQueueTime minutes
	CreateTimer(float(ProcessQueueTime * 60), ProcessQueue);
}

public void AddedFromSQLiteCallback(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	char buffer[512];
	char auth[40];

	dataPack.ReadString(auth, sizeof(auth));
	if (results == null)
	{
		// The insert was successful so delete the record from the queue
		FormatEx(buffer, sizeof(buffer), "DELETE FROM queue WHERE steam_id = '%s'", auth);
		SQLiteDB.Query(ErrorCheckCallback, buffer);

		// They are added to main banlist, so remove the temp ban
		RemoveBan(auth, BANFLAG_AUTHID);

	} else {
		// the insert failed so we leave the record in the queue and increase our temporary ban
		FormatEx(buffer, sizeof(buffer), "banid %d %s", ProcessQueueTime, auth);
		ServerCommand(buffer);
	}
	delete dataPack;
}

public void ServerInfoCallback(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		LogToFile(logFile, "Server Select Query Failed: %s", error);
		return;
	}

	if (!results.RowCount)
	{
		// get the game folder name used to determine the mod
		char desc[64], query[200], rcon[128];
		GetGameFolderName(desc, sizeof(desc));
		Format(rcon, sizeof(rcon), "");

		if (AutoAdd == AUTO_ADD_SERVER_WITH_RCON)
		{
			ConVar cvarRconPassword = FindConVar("rcon_password");
			if (cvarRconPassword != null)
			{
				cvarRconPassword.GetString(rcon, sizeof(rcon));
			}
		}

		FormatEx(query, sizeof(query), "INSERT INTO %s_servers (ip, port, rcon, modid) VALUES ('%s', '%s', '%s', (SELECT mid FROM %s_mods WHERE modfolder = '%s'))", DatabasePrefix, ServerIp, ServerPort, rcon, DatabasePrefix, desc);
		db.Query(ErrorCheckCallback, query);
	}
}

public void ErrorCheckCallback(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		LogToFile(logFile, "Query Failed: %s", error);
	}
}

public void VerifyBan(Database db, DBResultSet results, const char[] error, int userid)
{
	char clientName[64], clientAuth[64], clientIp[64];

	int client = GetClientOfUserId(userid);

	if (!client)
		return;

	/* Failure happen. Do retry with delay */
	if (results == null)
	{
		LogToFile(logFile, "Verify Ban Query Failed: %s", error);
		PlayerRecheck[client] = CreateTimer(RetryTime, ClientRecheck, client);
		return;
	}

	GetClientIP(client, clientIp, sizeof(clientIp));
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	GetClientName(client, clientName, sizeof(clientName));

	if (results.RowCount > 0)
	{
		char buffer[40], Name[128], Query[512];

		// Amending to ban record's IP field
		if (results.FetchRow())
		{
			char sIP[32];

			int iBid = results.FetchInt(0);
			results.FetchString(1, sIP, sizeof sIP);

			if (StrEqual(sIP, ""))
			{
				char sQuery[256];

				FormatEx(sQuery, sizeof sQuery, "UPDATE %s_bans SET `ip` = '%s' WHERE `bid` = '%d'", DatabasePrefix, clientIp, iBid);

				DB.Query(SQL_OnIPMend, sQuery, client);
			}
		}

		DB.Escape(clientName, Name, sizeof Name);

		if (serverID == -1)
		{
			FormatEx(Query, sizeof(Query), "INSERT INTO %s_banlog (sid ,time ,name ,bid) VALUES  \
				((SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1), UNIX_TIMESTAMP(), '%s', \
				(SELECT bid FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND RemoveType IS NULL LIMIT 0,1))",
				DatabasePrefix, DatabasePrefix, ServerIp, ServerPort, Name, DatabasePrefix, clientAuth[8], clientIp);
		}
		else
		{
			FormatEx(Query, sizeof(Query), "INSERT INTO %s_banlog (sid ,time ,name ,bid) VALUES  \
				(%d, UNIX_TIMESTAMP(), '%s', \
				(SELECT bid FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND RemoveType IS NULL LIMIT 0,1))",
				DatabasePrefix, serverID, Name, DatabasePrefix, clientAuth[8], clientIp);
		}

		db.Query(ErrorCheckCallback, Query, client, DBPrio_High);

		FormatEx(buffer, sizeof(buffer), "banid 5 %s", clientAuth);
		ServerCommand(buffer);
		KickClient(client, "%t", "Banned Check Site", WebsiteAddress);

		return;
	}

	#if defined DEBUG
	LogToFile(logFile, "%s is NOT banned.", clientAuth);
	#endif

	PlayerStatus[client] = true;
}

public void SQL_OnIPMend(Database db, DBResultSet results, const char[] error, int iClient)
{
	if (results == null)
	{
		char sIP[32], sSteamID[32];

		GetClientAuthId(iClient, AuthId_Steam3, sSteamID, sizeof sSteamID);
		GetClientIP(iClient, sIP, sizeof sIP);

		LogToFile(logFile, "Failed to mend IP address for %s (%s): %s", sSteamID, sIP, error);
	}
}

public void AdminsDone(Database db, DBResultSet results, const char[] error, any data)
{
	//SELECT authid, srv_password , srv_group, srv_flags, user
	if (results == null)
	{
		--curLoading;
		CheckLoadAdmins(AdminCache_Admins);
		LogToFile(logFile, "Failed to retrieve admins from the database, %s", error);
		return;
	}
	char authType[] = "steam";
	char identity[66];
	char password[66];
	char groups[256];
	char flags[32];
	char name[66];
	int admCount = 0;
	int Immunity = 0;
	AdminId curAdm = INVALID_ADMIN_ID;
	KeyValues adminsKV = new KeyValues("Admins");

	while (results.FetchRow())
	{
		if (results.IsFieldNull(0))
			continue; // Sometimes some rows return NULL due to some setups

		results.FetchString(0, identity, 66);
		results.FetchString(1, password, 66);
		results.FetchString(2, groups, 256);
		results.FetchString(3, flags, 32);
		results.FetchString(4, name, 66);

		Immunity = results.FetchInt(5);

		TrimString(name);
		TrimString(identity);
		TrimString(groups);
		TrimString(flags);

		// Disable writing to file if they chose to
		if (backupConfig)
		{
			adminsKV.JumpToKey(name, true);

			adminsKV.SetString("auth", authType);
			adminsKV.SetString("identity", identity);

			if (strlen(flags) > 0)
				adminsKV.SetString("flags", flags);

			if (strlen(groups) > 0)
				adminsKV.SetString("group", groups);

			if (strlen(password) > 0)
				adminsKV.SetString("password", password);

			if (Immunity > 0)
				adminsKV.SetNum("immunity", Immunity);

			adminsKV.Rewind();
		}

		// find or create the admin using that identity
		if ((curAdm = FindAdminByIdentity(authType, identity)) == INVALID_ADMIN_ID)
		{
			curAdm = CreateAdmin(name);
			// That should never happen!
			if (!curAdm.BindIdentity(authType, identity))
			{
				LogToFile(logFile, "Unable to bind admin %s to identity %s", name, identity);
				RemoveAdmin(curAdm);
				continue;
			}
		}

		#if defined DEBUG
		LogToFile(logFile, "Given %s (%s) admin", name, identity);
		#endif

		int curPos = 0;
		GroupId curGrp = INVALID_GROUP_ID;
		int numGroups;
		char iterGroupName[64];

		// Who thought this comma seperated group parsing would be a good idea?!
		/*
		decl String:grp[64];
		new nextPos = 0;
		while ((nextPos = SplitString(groups[curPos],",",grp,64)) != -1)
		{
			curPos += nextPos;
			curGrp = FindAdmGroup(grp);
			if (curGrp == INVALID_GROUP_ID)
			{
				LogToFile(logFile, "Unknown group \"%s\"",grp);
			}
			else
			{
				// Check, if he's not in the group already.
				numGroups = GetAdminGroupCount(curAdm);
				for(new i=0;i<numGroups;i++)
				{
					GetAdminGroup(curAdm, i, iterGroupName, sizeof(iterGroupName));
					// Admin is already part of the group, so don't try to inherit its permissions.
					if(StrEqual(iterGroupName, grp))
					{
						numGroups = -2;
						break;
					}
				}
				// Only try to inherit the group, if it's a new one.
				if (numGroups != -2 && !AdminInheritGroup(curAdm,curGrp))
				{
					LogToFile(logFile, "Unable to inherit group \"%s\"",grp);
				}
			}
		}*/

		if (strcmp(groups[curPos], "") != 0)
		{
			curGrp = FindAdmGroup(groups[curPos]);
			if (curGrp == INVALID_GROUP_ID)
			{
				LogToFile(logFile, "Unknown group \"%s\"", groups[curPos]);
			}
			else
			{
				// Check, if he's not in the group already.
				numGroups = curAdm.GroupCount;
				for (int i = 0; i < numGroups; i++)
				{
					curAdm.GetGroup(i, iterGroupName, sizeof(iterGroupName));
					// Admin is already part of the group, so don't try to inherit its permissions.
					if (StrEqual(iterGroupName, groups[curPos]))
					{
						numGroups = -2;
						break;
					}
				}

				// Only try to inherit the group, if it's a new one.
				if (numGroups != -2 && !curAdm.InheritGroup(curGrp))
				{
					LogToFile(logFile, "Unable to inherit group \"%s\"", groups[curPos]);
				}

				if (curAdm.ImmunityLevel < Immunity)
				{
					curAdm.ImmunityLevel = Immunity;
				}
				#if defined DEBUG
				LogToFile(logFile, "Admin %s (%s) has %d immunity", name, identity, Immunity);
				#endif
			}
		}

		if (strlen(password) > 0)
			curAdm.SetPassword(password);

		for (int i = 0; i < strlen(flags); ++i)
		{
			if (flags[i] < 'a' || flags[i] > 'z')
				continue;

			int idx = flags[i]-'a';
			if (g_FlagLetters[idx] < Admin_Reservation)
				continue;

			curAdm.SetFlag(g_FlagLetters[idx], true);
		}
		++admCount;
	}

	if (backupConfig)
		adminsKV.ExportToFile(adminsLoc);
	delete adminsKV;

	#if defined DEBUG
	LogToFile(logFile, "Finished loading %i admins.", admCount);
	#endif

	--curLoading;
	CheckLoadAdmins(AdminCache_Admins);
}

public void GroupsDone(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		curLoading--;
		CheckLoadAdmins(AdminCache_Groups);
		LogToFile(logFile, "Failed to retrieve groups from the database, %s", error);
		return;
	}

	char grpName[128], immuneGrpName[128];
	char grpFlags[32];
	int Immunity;
	int grpCount = 0;
	KeyValues groupsKV = new KeyValues("Groups");

	GroupId curGrp = INVALID_GROUP_ID;
	while (results.FetchRow())
	{
		if (results.IsFieldNull(0))
			continue; // Sometimes some rows return NULL due to some setups
		results.FetchString(0, grpName, 128);
		results.FetchString(1, grpFlags, 32);
		Immunity = results.FetchInt(2);
		results.FetchString(3, immuneGrpName, 128);

		TrimString(grpName);
		TrimString(grpFlags);
		TrimString(immuneGrpName);

		// Ignore empty rows..
		if (!strlen(grpName))
			continue;

		curGrp = CreateAdmGroup(grpName);

		if (backupConfig)
		{
			groupsKV.JumpToKey(grpName, true);
			if (strlen(grpFlags) > 0)
				groupsKV.SetString("flags", grpFlags);
			if (Immunity > 0)
				groupsKV.SetNum("immunity", Immunity);

			groupsKV.Rewind();
		}

		if (curGrp == INVALID_GROUP_ID)
		{  //This occurs when the group already exists
			curGrp = FindAdmGroup(grpName);
		}

		for (int i = 0; i < strlen(grpFlags); ++i)
		{
			if (grpFlags[i] < 'a' || grpFlags[i] > 'z')
				continue;

			int idx = grpFlags[i]-'a';
			if (g_FlagLetters[idx] < Admin_Reservation)
				continue;

			curGrp.SetFlag(g_FlagLetters[idx], true);
		}

		// Set the group immunity.
		if (Immunity > 0)
		{
			curGrp.ImmunityLevel = Immunity;
			#if defined DEBUG
			LogToFile(logFile, "Group %s has %d immunity", grpName, Immunity);
			#endif
		}

		grpCount++;
	}

	if (backupConfig)
		groupsKV.ExportToFile(groupsLoc);
	delete groupsKV;

	#if defined DEBUG
	LogToFile(logFile, "Finished loading %i groups.", grpCount);
	#endif

	// Load the group overrides
	char query[512];
	FormatEx(query, 512, "SELECT sg.name, so.type, so.name, so.access FROM %s_srvgroups_overrides so LEFT JOIN %s_srvgroups sg ON sg.id = so.group_id ORDER BY sg.id", DatabasePrefix, DatabasePrefix);
	db.Query(LoadGroupsOverrides, query);

	/*if (reparse)
	{
		decl String:query[512];
		FormatEx(query,512,"SELECT name, immunity, groups_immune FROM %s_srvgroups ORDER BY id",DatabasePrefix);
		db.Query(GroupsSecondPass,query);
	}
	else
	{
		curLoading--;
		CheckLoadAdmins();
	}*/
}

// Reparse to apply inherited immunity
public void GroupsSecondPass(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		curLoading--;
		CheckLoadAdmins(AdminCache_Groups);
		LogToFile(logFile, "Failed to retrieve groups from the database, %s", error);
		return;
	}

	char grpName[128], immunityGrpName[128];

	GroupId curGrp = INVALID_GROUP_ID;
	GroupId immuneGrp = INVALID_GROUP_ID;
	while (results.FetchRow())
	{
		if (results.IsFieldNull(0))
			continue; // Sometimes some rows return NULL due to some setups

		results.FetchString(0, grpName, 128);
		TrimString(grpName);
		if (strlen(grpName) == 0)
			continue;

		results.FetchString(2, immunityGrpName, sizeof(immunityGrpName));
		TrimString(immunityGrpName);

		curGrp = FindAdmGroup(grpName);
		if (curGrp == INVALID_GROUP_ID)
			continue;

		immuneGrp = FindAdmGroup(immunityGrpName);
		if (immuneGrp == INVALID_GROUP_ID)
			continue;

		curGrp.AddGroupImmunity(immuneGrp);

		#if defined DEBUG
		LogToFile(logFile, "Group %s inhertied immunity from group %s", grpName, immunityGrpName);
		#endif
	}
	--curLoading;
	CheckLoadAdmins(AdminCache_Groups);
}

public void LoadGroupsOverrides(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		curLoading--;
		CheckLoadAdmins(AdminCache_Overrides);
		LogToFile(logFile, "Failed to retrieve group overrides from the database, %s", error);
		return;
	}

	char sGroupName[128], sType[16], sCommand[64], sAllowed[16];
	OverrideRule iRule;
	OverrideType iType;

	KeyValues groupsKV = new KeyValues("Groups");
	groupsKV.ImportFromFile(groupsLoc);

	GroupId curGrp = INVALID_GROUP_ID;
	while (results.FetchRow())
	{
		if (results.IsFieldNull(0))
			continue; // Sometimes some rows return NULL due to some setups

		results.FetchString(0, sGroupName, sizeof(sGroupName));
		TrimString(sGroupName);
		if (strlen(sGroupName) == 0)
			continue;

		results.FetchString(1, sType, sizeof(sType));
		results.FetchString(2, sCommand, sizeof(sCommand));
		results.FetchString(3, sAllowed, sizeof(sAllowed));

		curGrp = FindAdmGroup(sGroupName);
		if (curGrp == INVALID_GROUP_ID)
			continue;

		iRule = StrEqual(sAllowed, "allow") ? Command_Allow : Command_Deny;
		iType = StrEqual(sType, "group") ? Override_CommandGroup : Override_Command;

		#if defined DEBUG
		PrintToServer("AddAdmGroupCmdOverride(%i, %s, %i, %i)", curGrp, sCommand, iType, iRule);
		#endif

		// Save overrides into admin_groups.cfg backup
		if (groupsKV.JumpToKey(sGroupName))
		{
			groupsKV.JumpToKey("Overrides", true);
			if (iType == Override_Command)
				groupsKV.SetString(sCommand, sAllowed);
			else
			{
				Format(sCommand, sizeof(sCommand), "@%s", sCommand);
				groupsKV.SetString(sCommand, sAllowed);
			}
			groupsKV.Rewind();
		}

		curGrp.AddCommandOverride(sCommand, iType, iRule);
	}
	curLoading--;
	CheckLoadAdmins(AdminCache_Overrides);

	if (backupConfig)
		groupsKV.ExportToFile(groupsLoc);
	delete groupsKV;
}

public void OverridesDone(Database db, DBResultSet results, const char[] error, any data)
{
	if (results == null)
	{
		LogToFile(logFile, "Failed to retrieve overrides from the database, %s", error);
		ParseBackupConfig_Overrides();
		return;
	}

	KeyValues hKV = new KeyValues("SB_Overrides");

	char sFlags[32], sName[64], sType[64];
	while (results.FetchRow())
	{
		results.FetchString(0, sType, sizeof(sType));
		results.FetchString(1, sName, sizeof(sName));
		results.FetchString(2, sFlags, sizeof(sFlags));

		// KeyValuesToFile won't add that key, if the value is ""..
		if (sFlags[0] == '\0')
		{
			sFlags[0] = ' ';
			sFlags[1] = '\0';
		}

		#if defined DEBUG
		LogToFile(logFile, "Adding override (%s, %s, %s)", sType, sName, sFlags);
		#endif

		if (StrEqual(sType, "command"))
		{
			AddCommandOverride(sName, Override_Command, ReadFlagString(sFlags));
			hKV.JumpToKey("override_commands", true);
			hKV.SetString(sName, sFlags);
			hKV.GoBack();
		}
		else if (StrEqual(sType, "group"))
		{
			AddCommandOverride(sName, Override_CommandGroup, ReadFlagString(sFlags));
			hKV.JumpToKey("override_groups", true);
			hKV.SetString(sName, sFlags);
			hKV.GoBack();
		}
	}

	hKV.Rewind();

	if (backupConfig)
		hKV.ExportToFile(overridesLoc);
	delete hKV;
}

// TIMER CALL BACKS //

public Action ClientRecheck(Handle timer, any client)
{
	char Authid[64];
	if (!PlayerStatus[client] && IsClientConnected(client) && GetClientAuthId(client, AuthId_Steam2, Authid, sizeof(Authid)))
	{
		OnClientAuthorized(client, Authid);
	}

	PlayerRecheck[client] = INVALID_HANDLE;
	return Plugin_Stop;
}

/*
public Action PruneBans(Handle timer)
{
	char Query[512];
	FormatEx(Query, sizeof(Query),
			"UPDATE %s_bans SET RemovedBy = 0, RemoveType = 'E', RemovedOn = UNIX_TIMESTAMP() WHERE length != '0' AND ends < UNIX_TIMESTAMP()",
			DatabasePrefix);

	DB.Query(ErrorCheckCallback, Query);
	return Plugin_Continue;
}
*/

public Action ProcessQueue(Handle timer, any data)
{
	char buffer[512];
	Format(buffer, sizeof(buffer), "SELECT steam_id, time, start_time, reason, name, ip, admin_id, admin_ip FROM queue");
	SQLiteDB.Query(ProcessQueueCallback, buffer);
	return Plugin_Continue;
}

// PARSER //

static void InitializeConfigParser()
{
	if (ConfigParser == null)
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
		PrintToServer("%s", ConfigParser.GetErrorString(err, buffer, sizeof(buffer)) ? buffer : "Fatal parse error");
	}
}

public SMCResult ReadConfig_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
{
	if (name[0])
	{
		if (strcmp("Config", name, false) == 0) {
			ConfigState = ConfigStateConfig;
		} else if (strcmp("BanReasons", name, false) == 0) {
			ConfigState = ConfigStateReasons;
		} else if (strcmp("HackingReasons", name, false) == 0) {
			ConfigState = ConfigStateHacking;
		} else if (strcmp("BanTime", name, false) == 0) {
			ConfigState = ConfigStateTime;
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
			if (strcmp("website", key, false) == 0)
			{
				strcopy(WebsiteAddress, sizeof(WebsiteAddress), value);
			}
			else if (strcmp("Addban", key, false) == 0)
			{
				if (StringToInt(value) == 0)
				{
					CommandDisable |= DISABLE_ADDBAN;
				}
			}
			else if (strcmp("AutoAddServer", key, false) == 0)
			{
				int sAutoAdd = StringToInt(value);
				AutoAdd = (sAutoAdd < 0 || sAutoAdd > 2) ? 0 : sAutoAdd;
			}
			else if (strcmp("Unban", key, false) == 0)
			{
				if (StringToInt(value) == 0)
				{
					CommandDisable |= DISABLE_UNBAN;
				}
			}
			else if (strcmp("DatabasePrefix", key, false) == 0)
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
				} else if (RetryTime > 60.0) {
					RetryTime = 60.0;
				}
			}
			else if (strcmp("ProcessQueueTime", key, false) == 0)
			{
				ProcessQueueTime = StringToInt(value);
			}
			else if (strcmp("BackupConfigs", key, false) == 0)
			{
				backupConfig = StringToInt(value) == 1;
			}
			else if (strcmp("EnableAdmins", key, false) == 0)
			{
				enableAdmins = StringToInt(value) == 1;
			}
			else if (strcmp("RequireSiteLogin", key, false) == 0)
			{
				requireSiteLogin = StringToInt(value) == 1;
			}
			else if (strcmp("ServerID", key, false) == 0)
			{
				serverID = StringToInt(value);

				int sbid = GetConVarInt(sb_id);
				if (sbid != -1)
				{
					serverID = sbid;
				}
			}
		}

		case ConfigStateReasons:
		{
			if (ReasonMenuHandle != INVALID_HANDLE)
			{
				AddMenuItem(ReasonMenuHandle, key, value);
			}
		}
		case ConfigStateHacking:
		{
			if (HackingMenuHandle != INVALID_HANDLE)
			{
				AddMenuItem(HackingMenuHandle, key, value);
			}
		}
		case ConfigStateTime:
		{
			if (StringToInt(key) > -1 && TimeMenuHandle != INVALID_HANDLE)
			{
				AddMenuItem(TimeMenuHandle, key, value);
			}
		}
	}
	return SMCParse_Continue;
}

public SMCResult ReadConfig_EndSection(SMCParser smc)
{
	return SMCParse_Continue;
}


/*********************************************************
 * Ban Player from server
 *
 * @param client	The client index of the player to ban
 * @param time		The time to ban the player for (in minutes, 0 = permanent)
 * @param reason	The reason to ban the player from the server
 * @noreturn
 *********************************************************/
public int Native_SBBanPlayer(Handle plugin, int numParams)
{
	int client = GetNativeCell(1);
	int target = GetNativeCell(2);
	int time = GetNativeCell(3);
	char reason[128];
	GetNativeString(4, reason, 128);

	if (reason[0] == '\0')
		strcopy(reason, sizeof(reason), "Banned by SourceBans");

	if (client && IsClientInGame(client))
	{
		AdminId aid = GetUserAdmin(client);
		if (aid == INVALID_ADMIN_ID)
		{
			ThrowNativeError(SP_ERROR_NATIVE, "Ban Error: Player is not an admin.");
			return 0;
		}

		if (!aid.HasFlag(Admin_Ban))
		{
			ThrowNativeError(SP_ERROR_NATIVE, "Ban Error: Player does not have BAN flag.");
			return 0;
		}
	}

	PrepareBan(client, target, time, reason, sizeof(reason));
	return true;
}

public int Native_SBReportPlayer(Handle plugin, int numParams)
{
	if (numParams < 3)
	{
		ThrowNativeError(SP_ERROR_NATIVE, "Invalid amount of arguments. Received %d arguments", numParams);
		return 0;
	}

	int iReporter = GetNativeCell(1)
	  , iTarget = GetNativeCell(2)
	  , iReasonLen;

	int iTime = GetTime();

	GetNativeStringLength(3, iReasonLen);

	iReasonLen++;

	char[] sReason = new char[iReasonLen];

	GetNativeString(3, sReason, iReasonLen);

	char sRAuth[32], sTAuth[32], sRName[MAX_NAME_LENGTH + 1], sTName[MAX_NAME_LENGTH + 1],
	sRIP[16], sTIP[16], sREscapedName[MAX_NAME_LENGTH * 2 + 1], sTEscapedName[MAX_NAME_LENGTH * 2 + 1];

	char[] sEscapedReason = new char[iReasonLen * 2 + 1];

	GetClientAuthId(iReporter, AuthId_Steam2, sRAuth, sizeof sRAuth);
	GetClientAuthId(iTarget, AuthId_Steam2, sTAuth, sizeof sTAuth);

	GetClientName(iReporter, sRName, sizeof sRName);
	GetClientName(iTarget, sTName, sizeof sTName);

	GetClientIP(iReporter, sRIP, sizeof sRIP);
	GetClientIP(iTarget, sTIP, sizeof sTIP);

	DB.Escape(sRName, sREscapedName, sizeof sREscapedName);
	DB.Escape(sTName, sTEscapedName, sizeof sTEscapedName);
	DB.Escape(sReason, sEscapedReason, iReasonLen * 2 + 1);

	char[] sQuery = new char[512 + (iReasonLen * 2 + 1)];

	Format(sQuery, 512 + (iReasonLen * 2 + 1), "INSERT INTO %s_submissions (`submitted`, `modid`, `SteamId`, `name`, `email`, `reason`, `ip`, `subname`, `sip`, `archiv`, `server`)"
	... "VALUES ('%d', 0, '%s', '%s', '%s', '%s', '%s', '%s', '%s', 0, '%d')", DatabasePrefix, iTime, sTAuth, sTEscapedName, sRAuth, sEscapedReason, sRIP, sREscapedName, sTIP, (serverID != -1) ? serverID : 0);

	DataPack dataPack = new DataPack();

	dataPack.WriteCell(iReporter);
	dataPack.WriteCell(iTarget);
	dataPack.WriteCell(iReasonLen);
	dataPack.WriteString(sReason);

	DB.Query(SQL_OnReportPlayer, sQuery, dataPack);
	return 0;
}

public void SQL_OnReportPlayer(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	if (results == null)
		LogToFile(logFile, "Failed to submit report: %s", error);
	else
	{
		dataPack.Reset();

		int iReporter = dataPack.ReadCell();
		int iTarget = dataPack.ReadCell();
		int iReasonLen = dataPack.ReadCell();

		char[] sReason = new char[iReasonLen];

		dataPack.ReadString(sReason, iReasonLen);
		delete dataPack;

		Call_StartForward(g_hFwd_OnReportAdded);
		Call_PushCell(iReporter);
		Call_PushCell(iTarget);
		Call_PushString(sReason);
		Call_Finish();
	}
}

// STOCK FUNCTIONS //

public void InitializeBackupDB()
{
	char error[255];

	SQLiteDB = SQLite_UseDatabase("sourcebans-queue", error, sizeof(error));
	if (SQLiteDB == INVALID_HANDLE)
		SetFailState(error);

	SQL_LockDatabase(SQLiteDB);
	SQL_FastQuery(SQLiteDB, "CREATE TABLE IF NOT EXISTS queue (steam_id TEXT PRIMARY KEY ON CONFLICT REPLACE, time INTEGER, start_time INTEGER, reason TEXT, name TEXT, ip TEXT, admin_id TEXT, admin_ip TEXT);");
	SQL_UnlockDatabase(SQLiteDB);
}

public bool CreateBan(int client, int target, int time, const char[] reason)
{
	char adminIp[24], adminAuth[64];
	int admin = client;

	// The server is the one calling the ban
	if (!admin)
	{
		if (reason[0] == '\0')
		{
			// We cannot pop the reason menu if the command was issued from the server
			PrintToServer("%s%T", Prefix, "Include Reason", LANG_SERVER);
			return false;
		}

		// setup dummy adminAuth and adminIp for server
		strcopy(adminAuth, sizeof(adminAuth), "STEAM_ID_SERVER");
		strcopy(adminIp, sizeof(adminIp), ServerIp);
	} else {
		GetClientIP(admin, adminIp, sizeof(adminIp));
		GetClientAuthId(admin, AuthId_Steam2, adminAuth, sizeof(adminAuth));
	}

	// target information
	char ip[24], auth[64], name[64];

	GetClientName(target, name, sizeof(name));
	GetClientIP(target, ip, sizeof(ip));
	if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth)))
		return false;

	int userid = admin ? GetClientUserId(admin) : 0;

	// Pack everything into a data pack so we can retain it
	DataPack dataPack = new DataPack();
	DataPack reasonPack = new DataPack();

	WritePackString(reasonPack, reason);

	dataPack.WriteCell(admin);
	dataPack.WriteCell(target);
	dataPack.WriteCell(userid);
	dataPack.WriteCell(GetClientUserId(target));
	dataPack.WriteCell(time);
	dataPack.WriteCell(reasonPack);
	dataPack.WriteString(name);
	dataPack.WriteString(auth);
	dataPack.WriteString(ip);
	dataPack.WriteString(adminAuth);
	dataPack.WriteString(adminIp);

	dataPack.Reset();
	reasonPack.Reset();

	if (reason[0] != '\0')
	{
		// if we have a valid reason pass move forward with the ban
		if (DB != INVALID_HANDLE)
		{
			UTIL_InsertBan(time, name, auth, ip, reason, adminAuth, adminIp, dataPack);
		} else {
			UTIL_InsertTempBan(time, name, auth, ip, reason, adminAuth, adminIp, dataPack);
		}
	} else {
		// We need a reason so offer the administrator a menu of reasons
		PlayerDataPack[admin] = dataPack;
		DisplayMenu(ReasonMenuHandle, admin, MENU_TIME_FOREVER);
		ReplyToCommand(admin, "%s%t", Prefix, "Check Menu");
	}

	Call_StartForward(g_hFwd_OnBanAdded);
	Call_PushCell(client);
	Call_PushCell(target);
	Call_PushCell(time);
	Call_PushString(reason);
	Call_Finish();

	return true;
}

stock void UTIL_InsertBan(int time, const char[] Name, const char[] Authid, const char[] Ip, const char[] Reason, const char[] AdminAuthid, const char[] AdminIp, DataPack dataPack)
{
	//new Handle:dummy;
	//PruneBans(dummy);
	char banName[128];
	char banReason[256];
	char Query[1024];
	DB.Escape(Name, banName, sizeof(banName));
	DB.Escape(Reason, banReason, sizeof(banReason));
	if (serverID == -1)
	{
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						('%s', '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'),'0'), '%s', \
						(SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1), ' ')",
			DatabasePrefix, Ip, Authid, banName, (time * 60), (time * 60), banReason, DatabasePrefix, AdminAuthid, AdminAuthid[8], AdminIp, DatabasePrefix, ServerIp, ServerPort);
	} else {
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						('%s', '%s', '%s', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', IFNULL((SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'),'0'), '%s', \
						%d, ' ')",
			DatabasePrefix, Ip, Authid, banName, (time * 60), (time * 60), banReason, DatabasePrefix, AdminAuthid, AdminAuthid[8], AdminIp, serverID);
	}
	DB.Query(VerifyInsert, Query, dataPack, DBPrio_High);
}

stock void UTIL_InsertTempBan(int time, const char[] name, const char[] auth, const char[] ip, const char[] reason, const char[] adminAuth, const char[] adminIp, DataPack dataPack)
{
	int admin = dataPack.ReadCell(); // admin index

	int client = dataPack.ReadCell();

	dataPack.ReadCell(); // admin userid
	dataPack.ReadCell(); // target userid
	dataPack.ReadCell(); // time

	DataPack reasonPack = view_as<DataPack>(dataPack.ReadCell());

	if (reasonPack != null)
		delete reasonPack;
	delete dataPack;

	// we add a temporary ban and then add the record into the queue to be processed when the database is available
	char buffer[50];

	Format(buffer, sizeof(buffer), "banid %d %s", ProcessQueueTime, auth);

	ServerCommand(buffer);

	if (IsClientInGame(client))
	{
		char length[32];
		if(time == 0)
			FormatEx(length, sizeof(length), "permament");
		else
			FormatEx(length, sizeof(length), "%d %s", time, time == 1 ? "minute" : "minutes");
		KickClient(client, "%t\n\n%t", "Banned Check Site", WebsiteAddress, "Kick Reason", admin, reason, length);
	}

	char banName[128], banReason[256], query[512];

	SQLiteDB.Escape(name, banName, sizeof(banName));
	SQLiteDB.Escape(reason, banReason, sizeof(banReason));

	FormatEx(query, sizeof(query), "INSERT INTO queue VALUES ('%s', %i, %i, '%s', '%s', '%s', '%s', '%s')",
		auth, time, GetTime(), banReason, banName, ip, adminAuth, adminIp);

	SQLiteDB.Query(ErrorCheckCallback, query);
}

stock void CheckLoadAdmins(AdminCachePart part)
{
	bool bNotify = true;

	Call_StartForward(g_hFwd_OnClientPreAdminCheck);
	Call_PushCell(part);
	Call_Finish(bNotify);

	if (!bNotify)
		return;

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i))
		{
			RunAdminCacheChecks(i);
			NotifyPostAdminCheck(i);
		}
	}
}

stock void InsertServerInfo()
{
    if (DB == INVALID_HANDLE) {
        return;
    }

    char query[100];
    int pieces[4];
    int longip = CvarHostIp.IntValue;

    pieces[0] = (longip >> 24) & 0x000000FF;
    pieces[1] = (longip >> 16) & 0x000000FF;
    pieces[2] = (longip >> 8) & 0x000000FF;
    pieces[3] = longip & 0x000000FF;

    FormatEx(ServerIp, sizeof(ServerIp), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);
    CvarPort.GetString(ServerPort, sizeof(ServerPort));

    if (AutoAdd != AUTO_ADD_SERVER_DISABLED) {
        FormatEx(query, sizeof(query), "SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s'", DatabasePrefix, ServerIp, ServerPort);
        DB.Query(ServerInfoCallback, query);
    }
}

stock void PrepareBan(int client, int target, int time, char[] reason, int size)
{
	#if defined DEBUG
	LogToFile(logFile, "PrepareBan()");
	#endif

	if (!target || !IsClientInGame(target))
		return;

	char authid[64], name[32], bannedSite[512];

	if (!GetClientAuthId(target, AuthId_Steam2, authid, sizeof(authid)))
		return;

	GetClientName(target, name, sizeof(name));

	if (CreateBan(client, target, time, reason))
	{
		if (!time)
		{
			if (reason[0] == '\0')
			{
				ShowActivity(client, "%t", "Permabanned Player", name);
			} else {
				ShowActivity(client, "%t", "Permabanned Player Reason", name, reason);
			}
		} else {
			if (reason[0] == '\0')
			{
				ShowActivity(client, "%t", "Banned Player", name, time);
			} else {
				ShowActivity(client, "%t", "Banned Player Reason", name, time, reason);
			}
		}

		LogAction(client, target, "%t", "Ban Log", client, target, time, reason);

		char length[32];

		if(time == 0)
			FormatEx(length, sizeof(length), "%T", "permanent", client);
		else
			FormatEx(length, sizeof(length), "%d %t", time, time == 1 ? "minute" : "minutes", client);

		if (time > 5 || time == 0)
			time = 5;

		Format(bannedSite, sizeof(bannedSite), "%t\n\n%t", "Banned Check Site", WebsiteAddress, "Kick Reason", client, reason, length);//temp
		BanClient(target, time, BANFLAG_AUTO, bannedSite, bannedSite, "sm_ban", client);
	}

	g_BanTarget[client] = -1;
	g_BanTime[client] = -1;
}

stock void ReadConfig()
{
	InitializeConfigParser();

	if (ConfigParser == null)
	{
		return;
	}

	char ConfigFile[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, ConfigFile, sizeof(ConfigFile), "configs/sourcebans/sourcebans.cfg");

	if (FileExists(ConfigFile))
	{
		InternalReadConfig(ConfigFile);
		PrintToServer("%sLoading configs/sourcebans.cfg config file", Prefix);
	} else {
		char Error[PLATFORM_MAX_PATH + 64];
		FormatEx(Error, sizeof(Error), "%sFATAL *** ERROR *** can not find %s", Prefix, ConfigFile);
		LogToFile(logFile, "FATAL *** ERROR *** can not find %s", ConfigFile);
		SetFailState(Error);
	}
}

stock void ResetSettings()
{
	CommandDisable = 0;

	ResetMenu();
	ReadConfig();
}

stock void ParseBackupConfig_Overrides()
{
	KeyValues hKV = new KeyValues("SB_Overrides");

	if (!hKV.ImportFromFile(overridesLoc))
		return;

	if (!hKV.GotoFirstSubKey())
		return;

	char sSection[16], sFlags[32], sName[64];
	OverrideType type;

	do
	{
		hKV.GetSectionName(sSection, sizeof(sSection));
		if (StrEqual(sSection, "override_commands"))
			type = Override_Command;
		else if (StrEqual(sSection, "override_groups"))
			type = Override_CommandGroup;
		else
			continue;

		if (hKV.GotoFirstSubKey(false))
		{
			do
			{
				hKV.GetSectionName(sName, sizeof(sName));
				hKV.GetString(NULL_STRING, sFlags, sizeof(sFlags));
				AddCommandOverride(sName, type, ReadFlagString(sFlags));
				#if defined _DEBUG
				PrintToServer("Adding override (%s, %s, %s)", sSection, sName, sFlags);
				#endif
			} while (hKV.GotoNextKey(false));
			hKV.GoBack();
		}
	}
	while (hKV.GotoNextKey());
	delete hKV;
}

stock AdminFlag[] CreateFlagLetters()
{
	AdminFlag FlagLetters[FLAG_LETTERS_SIZE];

	FlagLetters['a'-'a'] = Admin_Reservation;
	FlagLetters['b'-'a'] = Admin_Generic;
	FlagLetters['c'-'a'] = Admin_Kick;
	FlagLetters['d'-'a'] = Admin_Ban;
	FlagLetters['e'-'a'] = Admin_Unban;
	FlagLetters['f'-'a'] = Admin_Slay;
	FlagLetters['g'-'a'] = Admin_Changemap;
	FlagLetters['h'-'a'] = Admin_Convars;
	FlagLetters['i'-'a'] = Admin_Config;
	FlagLetters['j'-'a'] = Admin_Chat;
	FlagLetters['k'-'a'] = Admin_Vote;
	FlagLetters['l'-'a'] = Admin_Password;
	FlagLetters['m'-'a'] = Admin_RCON;
	FlagLetters['n'-'a'] = Admin_Cheats;
	FlagLetters['o'-'a'] = Admin_Custom1;
	FlagLetters['p'-'a'] = Admin_Custom2;
	FlagLetters['q'-'a'] = Admin_Custom3;
	FlagLetters['r'-'a'] = Admin_Custom4;
	FlagLetters['s'-'a'] = Admin_Custom5;
	FlagLetters['t'-'a'] = Admin_Custom6;
	FlagLetters['z'-'a'] = Admin_Root;

	return FlagLetters;
}

stock void AccountForLateLoading()
{
	char auth[30];

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientConnected(i) && !IsFakeClient(i))
		{
			PlayerStatus[i] = false;
		}
		if (IsClientInGame(i) && !IsFakeClient(i) && IsClientAuthorized(i) && GetClientAuthId(i, AuthId_Steam2, auth, sizeof(auth)))
		{
			OnClientAuthorized(i, auth);
		}
	}
}

//Yarr!
