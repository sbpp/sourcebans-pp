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
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

#pragma semicolon 1
#include <sourcemod>
#include <sourcebans>

#undef REQUIRE_PLUGIN
#include <adminmenu>
#tryinclude <updater>

#define SB_VERSION "1.5.4.6F"
#define SBR_VERSION "1.5.4.6"

#if defined _updater_included
#define UPDATE_URL "https://sarabveer.github.io/SourceBans-Fork/updater/updatefile.txt"
#endif

//GLOBAL DEFINES
#define YELLOW				0x01
#define NAMECOLOR			0x02
#define TEAMCOLOR			0x03
#define GREEN				0x04

#define DISABLE_ADDBAN		1
#define DISABLE_UNBAN		2

#define FLAG_LETTERS_SIZE 26

//#define DEBUG

enum State/* ConfigState */
{
	ConfigStateNone = 0, 
	ConfigStateConfig, 
	ConfigStateReasons, 
	ConfigStateHacking
}

new g_BanTarget[MAXPLAYERS + 1] =  { -1, ... };
new g_BanTime[MAXPLAYERS + 1] =  { -1, ... };

new State:ConfigState;
new Handle:ConfigParser;
new Handle:updaterCvar = INVALID_HANDLE;
new Handle:hTopMenu = INVALID_HANDLE;

new const String:Prefix[] = "[SourceBans] ";

new String:ServerIp[24];
new String:ServerPort[7];
new String:DatabasePrefix[10] = "sb";
new String:WebsiteAddress[128];

/* Admin Stuff*/
new AdminCachePart:loadPart;
new bool:loadAdmins;
new bool:loadGroups;
new bool:loadOverrides;
new curLoading = 0;
new AdminFlag:g_FlagLetters[FLAG_LETTERS_SIZE];

/* Admin KeyValues */
new String:groupsLoc[128];
new String:adminsLoc[128];
new String:overridesLoc[128];

/* Cvar handle*/
new Handle:CvarHostIp;
new Handle:CvarPort;

/* Database handle */
new Handle:DB;
new Handle:SQLiteDB;

/* Menu file globals */
new Handle:ReasonMenuHandle;
new Handle:HackingMenuHandle;

/* Datapack and Timer handles */
new Handle:PlayerRecheck[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... };
new Handle:PlayerDataPack[MAXPLAYERS + 1] =  { INVALID_HANDLE, ... };

/* Player ban check status */
new bool:PlayerStatus[MAXPLAYERS + 1];

/* Disable of addban and unban */
new CommandDisable;
new bool:backupConfig = true;
new bool:enableAdmins = true;

/* Require a lastvisited from SB site */
new bool:requireSiteLogin = false;

/* Log Stuff */
new String:logFile[256];

/* Own Chat Reason */
new g_ownReasons[MAXPLAYERS + 1] =  { false, ... };

new Float:RetryTime = 15.0;
new ProcessQueueTime = 5;
new bool:LateLoaded;
new bool:AutoAdd;
new bool:g_bConnecting = false;

new serverID = -1;

public Plugin:myinfo = 
{
	name = "SourceBans++", 
	author = "SourceBans Development Team, Sarabveer(VEER™)", 
	description = "Advanced ban management for the Source engine", 
	version = SBR_VERSION, 
	url = "https://sarabveer.github.io/SourceBans-Fork/"
};

#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
#else
public bool:AskPluginLoad(Handle:myself, bool:late, String:error[], err_max)
#endif
{
	RegPluginLibrary("sourcebans");
	CreateNative("SBBanPlayer", Native_SBBanPlayer);
	LateLoaded = late;
	
	#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
	return APLRes_Success;
	#else
	return true;
	#endif
}

public OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("plugin.basecommands");
	LoadTranslations("sourcebans.phrases");
	LoadTranslations("basebans.phrases");
	loadAdmins = loadGroups = loadOverrides = false;
	
	CvarHostIp = FindConVar("hostip");
	CvarPort = FindConVar("hostport");
	CreateConVar("sb_version", SB_VERSION, _, FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY);
	CreateConVar("sbr_version", SBR_VERSION, _, FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY);
	RegServerCmd("sm_rehash", sm_rehash, "Reload SQL admins");
	RegAdminCmd("sm_ban", CommandBan, ADMFLAG_BAN, "sm_ban <#userid|name> <minutes|0> [reason]", "sourcebans");
	RegAdminCmd("sm_banip", CommandBanIp, ADMFLAG_BAN, "sm_banip <ip|#userid|name> <time> [reason]", "sourcebans");
	RegAdminCmd("sm_addban", CommandAddBan, ADMFLAG_RCON, "sm_addban <time> <steamid> [reason]", "sourcebans");
	RegAdminCmd("sm_unban", CommandUnban, ADMFLAG_UNBAN, "sm_unban <steamid|ip> [reason]", "sourcebans");
	RegAdminCmd("sb_reload", 
		_CmdReload, 
		ADMFLAG_RCON, 
		"Reload sourcebans config and ban reason menu options", 
		"sourcebans");
	
	RegConsoleCmd("say", ChatHook);
	RegConsoleCmd("say_team", ChatHook);
	
	if ((ReasonMenuHandle = CreateMenu(ReasonSelected)) != INVALID_HANDLE)
	{
		SetMenuPagination(ReasonMenuHandle, 8);
		SetMenuExitBackButton(ReasonMenuHandle, true);
	}
	
	if ((HackingMenuHandle = CreateMenu(HackingSelected)) != INVALID_HANDLE)
	{
		SetMenuPagination(HackingMenuHandle, 8);
		SetMenuExitBackButton(HackingMenuHandle, true);
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
		LogToFile(logFile, "Database failure: Could not find Database conf \"sourcebans\". See FAQ: https://sarabveer.github.io/SourceBans-Fork/faq/");
		SetFailState("Database failure: Could not find Database conf \"sourcebans\"");
		return;
	}
	SQL_TConnect(GotDatabase, "sourcebans");
	
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
public Action:Updater_OnPluginDownloading() {
	if (!GetConVarBool(updaterCvar)) {
		return Plugin_Handled;
	}
	return Plugin_Continue;
}

public OnLibraryAdded(const String:name[]) {
	if (StrEqual(name, "updater")) {
		Updater_AddPlugin(UPDATE_URL);
	}
}

public Updater_OnPluginUpdated() {
	ReloadPlugin();
}
#endif

public OnAllPluginsLoaded()
{
	new Handle:topmenu;
	#if defined DEBUG
	LogToFile(logFile, "OnAllPluginsLoaded()");
	#endif
	
	if (LibraryExists("adminmenu") && ((topmenu = GetAdminTopMenu()) != INVALID_HANDLE))
	{
		OnAdminMenuReady(topmenu);
	}
}

public OnConfigsExecuted()
{
	decl String:filename[200];
	BuildPath(Path_SM, filename, sizeof(filename), "plugins/basebans.smx");
	if (FileExists(filename))
	{
		decl String:newfilename[200];
		BuildPath(Path_SM, newfilename, sizeof(newfilename), "plugins/disabled/basebans.smx");
		ServerCommand("sm plugins unload basebans");
		if (FileExists(newfilename))
			DeleteFile(newfilename);
		RenameFile(newfilename, filename);
		LogToFile(logFile, "plugins/basebans.smx was unloaded and moved to plugins/disabled/basebans.smx");
	}
}

public OnMapStart()
{
	ResetSettings();
}

public OnMapEnd()
{
	for (new i = 0; i <= MaxClients; i++)
	{
		if (PlayerDataPack[i] != INVALID_HANDLE)
		{
			/* Need to close reason pack */
			CloseHandle(PlayerDataPack[i]);
			PlayerDataPack[i] = INVALID_HANDLE;
		}
	}
}

// CLIENT CONNECTION FUNCTIONS //

public Action:OnClientPreAdminCheck(client)
{
	if (!DB || GetUserAdmin(client) != INVALID_ADMIN_ID)
		return Plugin_Continue;
	
	return curLoading > 0 ? Plugin_Handled : Plugin_Continue;
}

public OnClientDisconnect(client)
{
	if (PlayerRecheck[client] != INVALID_HANDLE)
	{
		KillTimer(PlayerRecheck[client]);
		PlayerRecheck[client] = INVALID_HANDLE;
	}
	g_ownReasons[client] = false;
}

public bool:OnClientConnect(client, String:rejectmsg[], maxlen)
{
	PlayerStatus[client] = false;
	return true;
}

public OnClientAuthorized(client, const String:auth[])
{
	/* Do not check bots nor check player with lan steamid. */
	if (auth[0] == 'B' || auth[9] == 'L' || DB == INVALID_HANDLE)
	{
		PlayerStatus[client] = true;
		return;
	}
	
	decl String:Query[256], String:ip[30];
	GetClientIP(client, ip, sizeof(ip));
	FormatEx(Query, sizeof(Query), "SELECT bid FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, auth[8], ip);
	#if defined DEBUG
	LogToFile(logFile, "Checking ban for: %s", auth);
	#endif
	
	SQL_TQuery(DB, VerifyBan, Query, GetClientUserId(client), DBPrio_High);
}

public OnRebuildAdminCache(AdminCachePart:part)
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
			SQL_TConnect(GotDatabase, "sourcebans");
		}
	}
	else {
		GotDatabase(DB, DB, "", 0);
	}
}

// COMMAND CODE //

public Action:ChatHook(client, args)
{
	// is this player preparing to ban someone
	if (g_ownReasons[client])
	{
		// get the reason
		new String:reason[512];
		GetCmdArgString(reason, sizeof(reason));
		StripQuotes(reason);
		
		g_ownReasons[client] = false;
		
		if (StrEqual(reason[0], "!noreason"))
		{
			PrintToChat(client, "%c[%cSourceBans%c]%c %t", GREEN, NAMECOLOR, GREEN, NAMECOLOR, "Chat Reason Aborted");
			return Plugin_Handled;
		}
		
		// ban him!
		PrepareBan(client, g_BanTarget[client], g_BanTime[client], reason, sizeof(reason));
		
		// block the reason to be sent in chat
		return Plugin_Handled;
	}
	return Plugin_Continue;
}

public Action:_CmdReload(client, args)
{
	ResetSettings();
	return Plugin_Handled;
}

public Action:CommandBan(client, args)
{
	if (args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_ban <#userid|name> <time|0> [reason]", Prefix);
		return Plugin_Handled;
	}
	
	// This is mainly for me sanity since client used to be called admin and target used to be called client
	new admin = client;
	
	// Get the target, find target returns a message on failure so we do not
	decl String:buffer[100];
	GetCmdArg(1, buffer, sizeof(buffer));
	new target = FindTarget(client, buffer, true);
	if (target == -1)
	{
		return Plugin_Handled;
	}
	
	// Get the ban time
	GetCmdArg(2, buffer, sizeof(buffer));
	new time = StringToInt(buffer);
	if (!time && client && !(CheckCommandAccess(client, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT)))
	{
		ReplyToCommand(client, "You do not have Perm Ban Permission");
		return Plugin_Handled;
	}
	
	// Get the reason
	new String:reason[128];
	if (args >= 3)
	{
		GetCmdArg(3, reason, sizeof(reason));
		for (new i = 4; i <= args; i++)
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
		ReplyToCommand(admin, "%c[%cSourceBans%c]%c %t", GREEN, NAMECOLOR, GREEN, NAMECOLOR, "Ban Not Verified");
		return Plugin_Handled;
	}
	
	
	CreateBan(client, target, time, reason);
	return Plugin_Handled;
}

public Action:CommandBanIp(client, args)
{
	if (args < 2)
	{
		ReplyToCommand(client, "%sUsage: sm_banip <ip|#userid|name> <time> [reason]", Prefix);
		return Plugin_Handled;
	}
	
	decl len, next_len;
	decl String:Arguments[256];
	decl String:arg[50], String:time[20];
	
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
	
	decl String:target_name[MAX_TARGET_LENGTH];
	decl target_list[1], bool:tn_is_ml;
	new target = -1;
	
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
			GetClientIP(target, arg, sizeof(arg));
	}
	
	decl String:adminIp[24], String:adminAuth[64];
	new minutes = StringToInt(time);
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
	new Handle:dataPack = CreateDataPack();
	WritePackCell(dataPack, client);
	WritePackCell(dataPack, minutes);
	WritePackString(dataPack, Arguments[len]);
	WritePackString(dataPack, arg);
	WritePackString(dataPack, adminAuth);
	WritePackString(dataPack, adminIp);
	
	decl String:Query[256];
	FormatEx(Query, sizeof(Query), "SELECT bid FROM %s_bans WHERE type = 1 AND ip     = '%s' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", 
		DatabasePrefix, arg);
	
	SQL_TQuery(DB, SelectBanIpCallback, Query, dataPack, DBPrio_High);
	return Plugin_Handled;
}

public Action:CommandUnban(client, args)
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
	
	decl len, String:Arguments[256], String:arg[50], String:adminAuth[64];
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
	new Handle:dataPack = CreateDataPack();
	WritePackCell(dataPack, client);
	WritePackString(dataPack, Arguments[len]);
	WritePackString(dataPack, arg);
	WritePackString(dataPack, adminAuth);
	
	decl String:query[200];
	if (strncmp(arg, "STEAM_", 6) == 0)
	{
		Format(query, sizeof(query), "SELECT bid FROM %s_bans WHERE (type = 0 AND authid = '%s') AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, arg);
	} else {
		Format(query, sizeof(query), "SELECT bid FROM %s_bans WHERE (type = 1 AND ip     = '%s') AND (length = '0' OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", DatabasePrefix, arg);
	}
	SQL_TQuery(DB, SelectUnbanCallback, query, dataPack);
	return Plugin_Handled;
}

public Action:CommandAddBan(client, args)
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
	
	decl String:arg_string[256], String:time[50], String:authid[50];
	GetCmdArgString(arg_string, sizeof(arg_string));
	
	new len, total_len;
	
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
	
	decl String:adminIp[24], String:adminAuth[64];
	new minutes = StringToInt(time);
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
	new Handle:dataPack = CreateDataPack();
	WritePackCell(dataPack, client);
	WritePackCell(dataPack, minutes);
	WritePackString(dataPack, arg_string[total_len]);
	WritePackString(dataPack, authid);
	WritePackString(dataPack, adminAuth);
	WritePackString(dataPack, adminIp);
	
	decl String:Query[256];
	FormatEx(Query, sizeof(Query), "SELECT bid FROM %s_bans WHERE type = 0 AND authid = '%s' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemoveType IS NULL", 
		DatabasePrefix, authid);
	
	SQL_TQuery(DB, SelectAddbanCallback, Query, dataPack, DBPrio_High);
	return Plugin_Handled;
}

public Action:sm_rehash(args)
{
	if (enableAdmins)
		DumpAdminCache(AdminCache_Groups, true);
	DumpAdminCache(AdminCache_Overrides, true);
	return Plugin_Handled;
}



// MENU CODE //

public OnAdminMenuReady(Handle:topmenu)
{
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
	new TopMenuObject:player_commands = FindTopMenuCategory(hTopMenu, ADMINMENU_PLAYERCOMMANDS);
	
	if (player_commands != INVALID_TOPMENUOBJECT)
	{
		// just to avoid "unused variable 'res'" warning
		#if defined DEBUG
		new TopMenuObject:res = AddToTopMenu(hTopMenu, 
			"sm_ban",  // Name
			TopMenuObject_Item,  // We are a submenu
			AdminMenu_Ban,  // Handler function
			player_commands,  // We are a submenu of Player Commands
			"sm_ban",  // The command to be finally called (Override checks)
			ADMFLAG_BAN); // What flag do we need to see the menu option
		decl String:temp[125];
		Format(temp, 125, "Result of AddToTopMenu: %d", res);
		LogToFile(logFile, temp);
		LogToFile(logFile, "Added Ban option to admin menu");
		#else
		AddToTopMenu(hTopMenu, 
			"sm_ban",  // Name
			TopMenuObject_Item,  // We are a submenu
			AdminMenu_Ban,  // Handler function
			player_commands,  // We are a submenu of Player Commands
			"sm_ban",  // The command to be finally called (Override checks)
			ADMFLAG_BAN); // What flag do we need to see the menu option
		#endif
	}
}

public AdminMenu_Ban(Handle:topmenu, 
	TopMenuAction:action,  // Action being performed
	TopMenuObject:object_id,  // The object ID (if used)
	param,  // client idx of admin who chose the option (if used)
	String:buffer[],  // Output buffer (if used)
	maxlength) // Output buffer (if used)
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
			Format(buffer, maxlength, "%T", "Ban player", param);
			
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

public ReasonSelected(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_Select:
		{
			decl String:info[128], String:key[128];
			GetMenuItem(menu, param2, key, sizeof(key), _, info, sizeof(info));
			
			if (StrEqual("Hacking", key))
			{
				DisplayMenu(HackingMenuHandle, param1, MENU_TIME_FOREVER);
				return;
			}
			
			else if (StrEqual("Own Reason", key)) // admin wants to use his own reason
			{
				g_ownReasons[param1] = true;
				PrintToChat(param1, "%c[%cSourceBans%c]%c %t", GREEN, NAMECOLOR, GREEN, NAMECOLOR, "Chat Reason");
				return;
			}
			
			else if (g_BanTarget[param1] != -1 && g_BanTime[param1] != -1)
				PrepareBan(param1, g_BanTarget[param1], g_BanTime[param1], info, sizeof(info));
		}
		
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_Disconnected)
			{
				if (PlayerDataPack[param1] != INVALID_HANDLE)
				{
					CloseHandle(PlayerDataPack[param1]);
					PlayerDataPack[param1] = INVALID_HANDLE;
				}
			}
			
			else
			{
				DisplayBanTimeMenu(param1);
			}
		}
	}
}

public HackingSelected(Handle:menu, MenuAction:action, param1, param2)
{
	switch (action)
	{
		case MenuAction_Select:
		{
			decl String:info[128], String:key[128];
			GetMenuItem(menu, param2, key, sizeof(key), _, info, sizeof(info));
			
			if (g_BanTarget[param1] != -1 && g_BanTime[param1] != -1)
				PrepareBan(param1, g_BanTarget[param1], g_BanTime[param1], info, sizeof(info));
		}
		
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_Disconnected)
			{
				new Handle:Pack = PlayerDataPack[param1];
				
				if (Pack != INVALID_HANDLE)
				{
					ReadPackCell(Pack); // admin index
					ReadPackCell(Pack); // target index
					ReadPackCell(Pack); // admin userid
					ReadPackCell(Pack); // target userid
					ReadPackCell(Pack); // time
					new Handle:ReasonPack = Handle:ReadPackCell(Pack);
					
					if (ReasonPack != INVALID_HANDLE)
					{
						CloseHandle(ReasonPack);
					}
					
					CloseHandle(Pack);
					PlayerDataPack[param1] = INVALID_HANDLE;
				}
			}
			
			else
			{
				DisplayMenu(ReasonMenuHandle, param1, MENU_TIME_FOREVER);
			}
		}
	}
}

public MenuHandler_BanPlayerList(Handle:menu, MenuAction:action, param1, param2)
{
	#if defined DEBUG
	LogToFile(logFile, "MenuHandler_BanPlayerList()");
	#endif
	
	switch (action)
	{
		case MenuAction_End:
		{
			CloseHandle(menu);
		}
		
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
			{
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
			}
		}
		
		case MenuAction_Select:
		{
			decl String:info[32], String:name[32];
			new userid, target;
			
			GetMenuItem(menu, param2, info, sizeof(info), _, name, sizeof(name));
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
}

public MenuHandler_BanTimeList(Handle:menu, MenuAction:action, param1, param2)
{
	#if defined DEBUG
	LogToFile(logFile, "MenuHandler_BanTimeList()");
	#endif
	
	switch (action)
	{
		case MenuAction_End:
		{
			CloseHandle(menu);
		}
		
		case MenuAction_Cancel:
		{
			if (param2 == MenuCancel_ExitBack && hTopMenu != INVALID_HANDLE)
			{
				DisplayTopMenu(hTopMenu, param1, TopMenuPosition_LastCategory);
			}
		}
		
		case MenuAction_Select:
		{
			decl String:info[32];
			
			GetMenuItem(menu, param2, info, sizeof(info));
			g_BanTime[param1] = StringToInt(info);
			
			//DisplayBanReasonMenu(param1);
			DisplayMenu(ReasonMenuHandle, param1, MENU_TIME_FOREVER);
		}
	}
}

stock DisplayBanTargetMenu(client)
{
	#if defined DEBUG
	LogToFile(logFile, "DisplayBanTargetMenu()");
	#endif
	new Handle:menu = CreateMenu(MenuHandler_BanPlayerList); // Create a new menu, pass it the handler.
	
	decl String:title[100];
	Format(title, sizeof(title), "%T:", "Ban player", client);
	
	//Format(title, sizeof(title), "Ban player", client);	// Create the title of the menu
	SetMenuTitle(menu, title); // Set the title
	SetMenuExitBackButton(menu, true); // Yes we want back/exit
	
	AddTargetsToMenu(menu,  // Add clients to our menu
		client,  // The client that called the display
		false,  // We want to see people connecting
		false); // And dead people
	
	DisplayMenu(menu, client, MENU_TIME_FOREVER); // Show the menu to the client FOREVER!
}

stock DisplayBanTimeMenu(client)
{
	#if defined DEBUG
	LogToFile(logFile, "DisplayBanTimeMenu()");
	#endif
	
	new Handle:menu = CreateMenu(MenuHandler_BanTimeList);
	
	decl String:title[100];
	Format(title, sizeof(title), "%T:", "Ban player", client);
	//Format(title, sizeof(title), "Ban player", client);
	SetMenuTitle(menu, title);
	SetMenuExitBackButton(menu, true);
	
	if (CheckCommandAccess(client, "sm_unban", ADMFLAG_UNBAN | ADMFLAG_ROOT))
		AddMenuItem(menu, "0", "Permanent");
	AddMenuItem(menu, "10", "10 Minutes");
	AddMenuItem(menu, "30", "30 Minutes");
	AddMenuItem(menu, "60", "1 Hour");
	AddMenuItem(menu, "240", "4 Hours");
	AddMenuItem(menu, "1440", "1 Day");
	AddMenuItem(menu, "10080", "1 Week");
	
	DisplayMenu(menu, client, MENU_TIME_FOREVER);
}

stock ResetMenu()
{
	if (ReasonMenuHandle != INVALID_HANDLE)
	{
		RemoveAllMenuItems(ReasonMenuHandle);
	}
}

// QUERY CALL BACKS //

public GotDatabase(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		LogToFile(logFile, "Database failure: %s. See FAQ: https://sarabveer.github.io/SourceBans-Fork/faq/", error);
		g_bConnecting = false;
		
		// Parse the overrides backup!
		ParseBackupConfig_Overrides();
		return;
	}
	
	DB = hndl;
	
	decl String:query[1024];
	FormatEx(query, sizeof(query), "SET NAMES \"UTF8\"");
	SQL_TQuery(DB, ErrorCheckCallback, query);
	
	InsertServerInfo();
	
	//CreateTimer(900.0, PruneBans);
	
	if (loadOverrides)
	{
		Format(query, 1024, "SELECT type, name, flags FROM %s_overrides", DatabasePrefix);
		SQL_TQuery(DB, OverridesDone, query);
		loadOverrides = false;
	}
	
	if (loadGroups && enableAdmins)
	{
		FormatEx(query, 1024, "SELECT name, flags, immunity, groups_immune   \
					FROM %s_srvgroups ORDER BY id", DatabasePrefix);
		curLoading++;
		SQL_TQuery(DB, GroupsDone, query);
		
		#if defined DEBUG
		LogToFile(logFile, "Fetching Group List");
		#endif
		loadGroups = false;
	}
	
	if (loadAdmins && enableAdmins)
	{
		new String:queryLastLogin[50] = "";
		
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
		SQL_TQuery(DB, AdminsDone, query);
		
		#if defined DEBUG
		LogToFile(logFile, "Fetching Admin List");
		LogToFile(logFile, query);
		#endif
		loadAdmins = false;
	}
	g_bConnecting = false;
}

public VerifyInsert(Handle:owner, Handle:hndl, const String:error[], any:dataPack)
{
	if (dataPack == INVALID_HANDLE)
	{
		LogToFile(logFile, "Ban Failed: %s", error);
		return;
	}
	
	if (hndl == INVALID_HANDLE || error[0])
	{
		LogToFile(logFile, "Verify Insert Query Failed: %s", error);
		new admin = ReadPackCell(dataPack);
		ReadPackCell(dataPack); // target
		ReadPackCell(dataPack); // admin userid
		ReadPackCell(dataPack); // target userid
		new time = ReadPackCell(dataPack);
		new Handle:reasonPack = Handle:ReadPackCell(dataPack);
		new String:reason[128];
		ReadPackString(reasonPack, reason, sizeof(reason));
		decl String:name[50];
		ReadPackString(dataPack, name, sizeof(name));
		decl String:auth[30];
		ReadPackString(dataPack, auth, sizeof(auth));
		decl String:ip[20];
		ReadPackString(dataPack, ip, sizeof(ip));
		decl String:adminAuth[30];
		ReadPackString(dataPack, adminAuth, sizeof(adminAuth));
		decl String:adminIp[20];
		ReadPackString(dataPack, adminIp, sizeof(adminIp));
		ResetPack(dataPack);
		ResetPack(reasonPack);
		
		PlayerDataPack[admin] = INVALID_HANDLE;
		UTIL_InsertTempBan(time, name, auth, ip, reason, adminAuth, adminIp, Handle:dataPack);
		return;
	}
	
	new admin = ReadPackCell(dataPack);
	new client = ReadPackCell(dataPack);
	
	if (!IsClientConnected(client) || IsFakeClient(client))
		return;
	
	ReadPackCell(dataPack); // admin userid
	new UserId = ReadPackCell(dataPack);
	new time = ReadPackCell(dataPack);
	new Handle:ReasonPack = Handle:ReadPackCell(dataPack);
	
	decl String:Name[64];
	new String:Reason[128];
	
	ReadPackString(dataPack, Name, sizeof(Name));
	ReadPackString(ReasonPack, Reason, sizeof(Reason));
	
	if (!time)
	{
		if (Reason[0] == '\0')
		{
			ShowActivityEx(admin, Prefix, "%t", "Permabanned player", Name);
		} else {
			ShowActivityEx(admin, Prefix, "%t", "Permabanned player reason", Name, Reason);
		}
	} else {
		if (Reason[0] == '\0')
		{
			ShowActivityEx(admin, Prefix, "%t", "Banned player", Name, time);
		} else {
			ShowActivityEx(admin, Prefix, "%t", "Banned player reason", Name, time, Reason);
		}
	}
	
	LogAction(admin, client, "\"%L\" banned \"%L\" (minutes \"%d\") (reason \"%s\")", admin, client, time, Reason);
	
	if (PlayerDataPack[admin] != INVALID_HANDLE)
	{
		CloseHandle(PlayerDataPack[admin]);
		CloseHandle(ReasonPack);
		PlayerDataPack[admin] = INVALID_HANDLE;
	}
	
	// Kick player
	if (GetClientUserId(client) == UserId)
		KickClient(client, "%t", "Banned Check Site", WebsiteAddress);
}

public SelectBanIpCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl admin, minutes, String:adminAuth[30], String:adminIp[30], String:banReason[256], String:ip[16], String:Query[512];
	new String:reason[128];
	ResetPack(data);
	admin = ReadPackCell(data);
	minutes = ReadPackCell(data);
	ReadPackString(data, reason, sizeof(reason));
	ReadPackString(data, ip, sizeof(ip));
	ReadPackString(data, adminAuth, sizeof(adminAuth));
	ReadPackString(data, adminIp, sizeof(adminIp));
	SQL_EscapeString(DB, reason, banReason, sizeof(banReason));
	
	if (error[0])
	{
		LogToFile(logFile, "Ban IP Select Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%sFailed to ban %s.", Prefix, ip);
		else
			PrintToServer("%sFailed to ban %s.", Prefix, ip);
		return;
	}
	if (SQL_GetRowCount(hndl))
	{
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%s is already banned.", Prefix, ip);
		else
			PrintToServer("%s%s is already banned.", Prefix, ip);
		return;
	}
	if (serverID == -1)
	{
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (type, ip, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						(1, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						(SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s' LIMIT 0,1), ' ')", 
			DatabasePrefix, ip, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, DatabasePrefix, ServerIp, ServerPort);
	} else {
		FormatEx(Query, sizeof(Query), "INSERT INTO %s_bans (type, ip, name, created, ends, length, reason, aid, adminIp, sid, country) VALUES \
						(1, '%s', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %d, %d, '%s', (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), '%s', \
						%d, ' ')", 
			DatabasePrefix, ip, (minutes * 60), (minutes * 60), banReason, DatabasePrefix, adminAuth, adminAuth[8], adminIp, serverID);
	}
	
	SQL_TQuery(DB, InsertBanIpCallback, Query, data, DBPrio_High);
}

public InsertBanIpCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	// if the pack is good unpack it and close the handle
	new admin, minutes;
	new String:reason[128];
	decl String:arg[30];
	if (data != INVALID_HANDLE)
	{
		ResetPack(data);
		admin = ReadPackCell(data);
		minutes = ReadPackCell(data);
		ReadPackString(data, reason, sizeof(reason));
		ReadPackString(data, arg, sizeof(arg));
		CloseHandle(data);
	} else {
		// Technically this should not be possible
		ThrowError("Invalid Handle in InsertBanIpCallback");
	}
	
	// If error is not an empty string the query failed
	if (error[0] != '\0')
	{
		LogToFile(logFile, "Ban IP Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%ssm_banip failed", Prefix);
		return;
	}
	
	LogAction(admin, 
		-1, 
		"\"%L\" added ban (minutes \"%d\") (ip \"%s\") (reason \"%s\")", 
		admin, 
		minutes, 
		arg, 
		reason);
	if (admin && IsClientInGame(admin))
		PrintToChat(admin, "%s%s successfully banned", Prefix, arg);
	else
		PrintToServer("%s%s successfully banned", Prefix, arg);
}

public SelectUnbanCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl admin, String:arg[30], String:adminAuth[30], String:unbanReason[256];
	new String:reason[128];
	ResetPack(data);
	admin = ReadPackCell(data);
	ReadPackString(data, reason, sizeof(reason));
	ReadPackString(data, arg, sizeof(arg));
	ReadPackString(data, adminAuth, sizeof(adminAuth));
	SQL_EscapeString(DB, reason, unbanReason, sizeof(unbanReason));
	
	// If error is not an empty string the query failed
	if (error[0] != '\0')
	{
		LogToFile(logFile, "Unban Select Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_unban failed", Prefix);
		}
		return;
	}
	
	// If there was no results then a ban does not exist for that id
	if (hndl == INVALID_HANDLE || !SQL_GetRowCount(hndl))
	{
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%sNo active bans found for that filter", Prefix);
		} else {
			PrintToServer("%sNo active bans found for that filter", Prefix);
		}
		return;
	}
	
	// There is ban
	if (hndl != INVALID_HANDLE && SQL_FetchRow(hndl))
	{
		// Get the values from the existing ban record
		new bid = SQL_FetchInt(hndl, 0);
		
		decl String:query[1000];
		Format(query, sizeof(query), "UPDATE %s_bans SET RemovedBy = (SELECT aid FROM %s_admins WHERE authid = '%s' OR authid REGEXP '^STEAM_[0-9]:%s$'), RemoveType = 'U', RemovedOn = UNIX_TIMESTAMP(), ureason = '%s' WHERE bid = %d", 
			DatabasePrefix, DatabasePrefix, adminAuth, adminAuth[8], unbanReason, bid);
		
		SQL_TQuery(DB, InsertUnbanCallback, query, data);
	}
	return;
}

public InsertUnbanCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	// if the pack is good unpack it and close the handle
	decl admin, String:arg[30];
	new String:reason[128];
	if (data != INVALID_HANDLE)
	{
		ResetPack(data);
		admin = ReadPackCell(data);
		ReadPackString(data, reason, sizeof(reason));
		ReadPackString(data, arg, sizeof(arg));
		CloseHandle(data);
	} else {
		// Technically this should not be possible
		ThrowError("Invalid Handle in InsertUnbanCallback");
	}
	
	// If error is not an empty string the query failed
	if (error[0] != '\0')
	{
		LogToFile(logFile, "Unban Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_unban failed", Prefix);
		}
		return;
	}
	
	LogAction(admin, -1, "\"%L\" removed ban (filter \"%s\") (reason \"%s\")", admin, arg, reason);
	if (admin && IsClientInGame(admin))
	{
		PrintToChat(admin, "%s%s successfully unbanned", Prefix, arg);
	} else {
		PrintToServer("%s%s successfully unbanned", Prefix, arg);
	}
}

public SelectAddbanCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl admin, minutes, String:adminAuth[30], String:adminIp[30], String:authid[20], String:banReason[256], String:Query[512];
	new String:reason[128];
	ResetPack(data);
	admin = ReadPackCell(data);
	minutes = ReadPackCell(data);
	ReadPackString(data, reason, sizeof(reason));
	ReadPackString(data, authid, sizeof(authid));
	ReadPackString(data, adminAuth, sizeof(adminAuth));
	ReadPackString(data, adminIp, sizeof(adminIp));
	SQL_EscapeString(DB, reason, banReason, sizeof(banReason));
	
	if (error[0])
	{
		LogToFile(logFile, "Add Ban Select Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%sFailed to ban %s.", Prefix, authid);
		else
			PrintToServer("%sFailed to ban %s.", Prefix, authid);
		return;
	}
	if (SQL_GetRowCount(hndl))
	{
		if (admin && IsClientInGame(admin))
			PrintToChat(admin, "%s%s is already banned.", Prefix, authid);
		else
			PrintToServer("%s%s is already banned.", Prefix, authid);
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
	
	SQL_TQuery(DB, InsertAddbanCallback, Query, data, DBPrio_High);
}

public InsertAddbanCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl admin, minutes, String:authid[20];
	new String:reason[128];
	ResetPack(data);
	admin = ReadPackCell(data);
	minutes = ReadPackCell(data);
	ReadPackString(data, reason, sizeof(reason));
	ReadPackString(data, authid, sizeof(authid));
	
	// If error is not an empty string the query failed
	if (error[0] != '\0')
	{
		LogToFile(logFile, "Add Ban Insert Query Failed: %s", error);
		if (admin && IsClientInGame(admin))
		{
			PrintToChat(admin, "%ssm_addban failed", Prefix);
		}
		return;
	}
	
	LogAction(admin, 
		-1, 
		"\"%L\" added ban (minutes \"%i\") (id \"%s\") (reason \"%s\")", 
		admin, 
		minutes, 
		authid, 
		reason);
	if (admin && IsClientInGame(admin))
	{
		PrintToChat(admin, "%s%s successfully banned", Prefix, authid);
	} else {
		PrintToServer("%s%s successfully banned", Prefix, authid);
	}
}

// ProcessQueueCallback is called as the result of selecting all the rows from the queue table
public ProcessQueueCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE || strlen(error) > 0)
	{
		LogToFile(logFile, "Failed to retrieve queued bans from sqlite database, %s", error);
		return;
	}
	
	decl String:auth[30];
	decl time;
	decl startTime;
	new String:reason[128];
	decl String:name[64];
	decl String:ip[20];
	decl String:adminAuth[30];
	decl String:adminIp[20];
	decl String:query[1024];
	decl String:banName[128];
	decl String:banReason[256];
	while (SQL_MoreRows(hndl))
	{
		// Oh noes! What happened?!
		if (!SQL_FetchRow(hndl))
			continue;
		
		// if we get to here then there are rows in the queue pending processing
		SQL_FetchString(hndl, 0, auth, sizeof(auth));
		time = SQL_FetchInt(hndl, 1);
		startTime = SQL_FetchInt(hndl, 2);
		SQL_FetchString(hndl, 3, reason, sizeof(reason));
		SQL_FetchString(hndl, 4, name, sizeof(name));
		SQL_FetchString(hndl, 5, ip, sizeof(ip));
		SQL_FetchString(hndl, 6, adminAuth, sizeof(adminAuth));
		SQL_FetchString(hndl, 7, adminIp, sizeof(adminIp));
		SQL_EscapeString(SQLiteDB, name, banName, sizeof(banName));
		SQL_EscapeString(SQLiteDB, reason, banReason, sizeof(banReason));
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
			new Handle:authPack = CreateDataPack();
			WritePackString(authPack, auth);
			ResetPack(authPack);
			SQL_TQuery(DB, AddedFromSQLiteCallback, query, authPack);
		} else {
			// The ban is no longer valid and should be deleted from the queue
			FormatEx(query, sizeof(query), "DELETE FROM queue WHERE steam_id = '%s'", auth);
			SQL_TQuery(SQLiteDB, ErrorCheckCallback, query);
		}
	}
	// We have finished processing the queue but should process again in ProcessQueueTime minutes
	CreateTimer(float(ProcessQueueTime * 60), ProcessQueue);
}

public AddedFromSQLiteCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	decl String:buffer[512];
	decl String:auth[40];
	ReadPackString(data, auth, sizeof(auth));
	if (error[0] == '\0')
	{
		// The insert was successful so delete the record from the queue
		FormatEx(buffer, sizeof(buffer), "DELETE FROM queue WHERE steam_id = '%s'", auth);
		SQL_TQuery(SQLiteDB, ErrorCheckCallback, buffer);
		
		// They are added to main banlist, so remove the temp ban
		RemoveBan(auth, BANFLAG_AUTHID);
		
	} else {
		// the insert failed so we leave the record in the queue and increase our temporary ban
		FormatEx(buffer, sizeof(buffer), "banid %d %s", ProcessQueueTime, auth);
		ServerCommand(buffer);
	}
	CloseHandle(data);
}

public ServerInfoCallback(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (error[0])
	{
		LogToFile(logFile, "Server Select Query Failed: %s", error);
		return;
	}
	
	if (hndl == INVALID_HANDLE || SQL_GetRowCount(hndl) == 0)
	{
		// get the game folder name used to determine the mod
		decl String:desc[64], String:query[200];
		GetGameFolderName(desc, sizeof(desc));
		FormatEx(query, sizeof(query), "INSERT INTO %s_servers (ip, port, rcon, modid) VALUES ('%s', '%s', '', (SELECT mid FROM %s_mods WHERE modfolder = '%s'))", DatabasePrefix, ServerIp, ServerPort, DatabasePrefix, desc);
		SQL_TQuery(DB, ErrorCheckCallback, query);
	}
}

public ErrorCheckCallback(Handle:owner, Handle:hndle, const String:error[], any:data)
{
	if (error[0])
	{
		LogToFile(logFile, "Query Failed: %s", error);
	}
}

public VerifyBan(Handle:owner, Handle:hndl, const String:error[], any:userid)
{
	decl String:clientName[64];
	decl String:clientAuth[64];
	decl String:clientIp[64];
	new client = GetClientOfUserId(userid);
	
	if (!client)
		return;
	
	/* Failure happen. Do retry with delay */
	if (hndl == INVALID_HANDLE)
	{
		LogToFile(logFile, "Verify Ban Query Failed: %s", error);
		PlayerRecheck[client] = CreateTimer(RetryTime, ClientRecheck, client);
		return;
	}
	GetClientIP(client, clientIp, sizeof(clientIp));
	GetClientAuthId(client, AuthId_Steam2, clientAuth, sizeof(clientAuth));
	GetClientName(client, clientName, sizeof(clientName));
	if (SQL_GetRowCount(hndl) > 0)
	{
		decl String:buffer[40];
		decl String:Name[128];
		decl String:Query[512];
		
		SQL_EscapeString(DB, clientName, Name, sizeof(Name));
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
		
		SQL_TQuery(DB, ErrorCheckCallback, Query, client, DBPrio_High);
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

public AdminsDone(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	//SELECT authid, srv_password , srv_group, srv_flags, user
	if (hndl == INVALID_HANDLE || strlen(error) > 0)
	{
		--curLoading;
		CheckLoadAdmins();
		LogToFile(logFile, "Failed to retrieve admins from the database, %s", error);
		return;
	}
	decl String:authType[] = "steam";
	decl String:identity[66];
	decl String:password[66];
	decl String:groups[256];
	decl String:flags[32];
	decl String:name[66];
	new admCount = 0;
	new Immunity = 0;
	new AdminId:curAdm = INVALID_ADMIN_ID;
	new Handle:adminsKV = CreateKeyValues("Admins");
	
	while (SQL_MoreRows(hndl))
	{
		SQL_FetchRow(hndl);
		if (SQL_IsFieldNull(hndl, 0))
			continue; // Sometimes some rows return NULL due to some setups
		
		SQL_FetchString(hndl, 0, identity, 66);
		SQL_FetchString(hndl, 1, password, 66);
		SQL_FetchString(hndl, 2, groups, 256);
		SQL_FetchString(hndl, 3, flags, 32);
		SQL_FetchString(hndl, 4, name, 66);
		
		Immunity = SQL_FetchInt(hndl, 5);
		
		TrimString(name);
		TrimString(identity);
		TrimString(groups);
		TrimString(flags);
		
		// Disable writing to file if they chose to
		if (backupConfig)
		{
			KvJumpToKey(adminsKV, name, true);
			
			KvSetString(adminsKV, "auth", authType);
			KvSetString(adminsKV, "identity", identity);
			
			if (strlen(flags) > 0)
				KvSetString(adminsKV, "flags", flags);
			
			if (strlen(groups) > 0)
				KvSetString(adminsKV, "group", groups);
			
			if (strlen(password) > 0)
				KvSetString(adminsKV, "password", password);
			
			if (Immunity > 0)
				KvSetNum(adminsKV, "immunity", Immunity);
			
			KvRewind(adminsKV);
		}
		
		// find or create the admin using that identity
		if ((curAdm = FindAdminByIdentity(authType, identity)) == INVALID_ADMIN_ID)
		{
			curAdm = CreateAdmin(name);
			// That should never happen!
			if (!BindAdminIdentity(curAdm, authType, identity))
			{
				LogToFile(logFile, "Unable to bind admin %s to identity %s", name, identity);
				RemoveAdmin(curAdm);
				continue;
			}
		}
		
		#if defined DEBUG
		LogToFile(logFile, "Given %s (%s) admin", name, identity);
		#endif
		
		new curPos = 0;
		new GroupId:curGrp = INVALID_GROUP_ID;
		new numGroups;
		decl String:iterGroupName[64];
		
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
				numGroups = GetAdminGroupCount(curAdm);
				for (new i = 0; i < numGroups; i++)
				{
					GetAdminGroup(curAdm, i, iterGroupName, sizeof(iterGroupName));
					// Admin is already part of the group, so don't try to inherit its permissions.
					if (StrEqual(iterGroupName, groups[curPos]))
					{
						numGroups = -2;
						break;
					}
				}
				
				// Only try to inherit the group, if it's a new one.
				if (numGroups != -2 && !AdminInheritGroup(curAdm, curGrp))
				{
					LogToFile(logFile, "Unable to inherit group \"%s\"", groups[curPos]);
				}
				
				if (GetAdminImmunityLevel(curAdm) < Immunity)
				{
					SetAdminImmunityLevel(curAdm, Immunity);
				}
				#if defined DEBUG
				LogToFile(logFile, "Admin %s (%s) has %d immunity", name, identity, Immunity);
				#endif
			}
		}
		
		if (strlen(password) > 0)
			SetAdminPassword(curAdm, password);
		
		for (new i = 0; i < strlen(flags); ++i)
		{
			if (flags[i] < 'a' || flags[i] > 'z')
				continue;
			
			if (g_FlagLetters[flags[i]-'a'] < Admin_Reservation)
				continue;
			
			SetAdminFlag(curAdm, g_FlagLetters[flags[i]-'a'], true);
		}
		++admCount;
	}
	
	if (backupConfig)
		KeyValuesToFile(adminsKV, adminsLoc);
	CloseHandle(adminsKV);
	
	#if defined DEBUG
	LogToFile(logFile, "Finished loading %i admins.", admCount);
	#endif
	
	--curLoading;
	CheckLoadAdmins();
}

public GroupsDone(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		curLoading--;
		CheckLoadAdmins();
		LogToFile(logFile, "Failed to retrieve groups from the database, %s", error);
		return;
	}
	decl String:grpName[128], String:immuneGrpName[128];
	decl String:grpFlags[32];
	new Immunity;
	new grpCount = 0;
	new Handle:groupsKV = CreateKeyValues("Groups");
	
	new GroupId:curGrp = INVALID_GROUP_ID;
	while (SQL_MoreRows(hndl))
	{
		SQL_FetchRow(hndl);
		if (SQL_IsFieldNull(hndl, 0))
			continue; // Sometimes some rows return NULL due to some setups
		SQL_FetchString(hndl, 0, grpName, 128);
		SQL_FetchString(hndl, 1, grpFlags, 32);
		Immunity = SQL_FetchInt(hndl, 2);
		SQL_FetchString(hndl, 3, immuneGrpName, 128);
		
		TrimString(grpName);
		TrimString(grpFlags);
		TrimString(immuneGrpName);
		
		// Ignore empty rows..
		if (!strlen(grpName))
			continue;
		
		curGrp = CreateAdmGroup(grpName);
		
		if (backupConfig)
		{
			KvJumpToKey(groupsKV, grpName, true);
			if (strlen(grpFlags) > 0)
				KvSetString(groupsKV, "flags", grpFlags);
			if (Immunity > 0)
				KvSetNum(groupsKV, "immunity", Immunity);
			
			KvRewind(groupsKV);
		}
		
		if (curGrp == INVALID_GROUP_ID)
		{  //This occurs when the group already exists
			curGrp = FindAdmGroup(grpName);
		}
		
		for (new i = 0; i < strlen(grpFlags); ++i)
		{
			if (grpFlags[i] < 'a' || grpFlags[i] > 'z')
				continue;
			
			if (g_FlagLetters[grpFlags[i]-'a'] < Admin_Reservation)
				continue;
			
			SetAdmGroupAddFlag(curGrp, g_FlagLetters[grpFlags[i]-'a'], true);
		}
		
		// Set the group immunity.
		if (Immunity > 0)
		{
			SetAdmGroupImmunityLevel(curGrp, Immunity);
			#if defined DEBUG
			LogToFile(logFile, "Group %s has %d immunity", grpName, Immunity);
			#endif
		}
		
		grpCount++;
	}
	
	if (backupConfig)
		KeyValuesToFile(groupsKV, groupsLoc);
	CloseHandle(groupsKV);
	
	#if defined DEBUG
	LogToFile(logFile, "Finished loading %i groups.", grpCount);
	#endif
	
	// Load the group overrides
	decl String:query[512];
	FormatEx(query, 512, "SELECT sg.name, so.type, so.name, so.access FROM %s_srvgroups_overrides so LEFT JOIN %s_srvgroups sg ON sg.id = so.group_id ORDER BY sg.id", DatabasePrefix, DatabasePrefix);
	SQL_TQuery(DB, LoadGroupsOverrides, query);
	
	/*if (reparse)
	{
		decl String:query[512];
		FormatEx(query,512,"SELECT name, immunity, groups_immune FROM %s_srvgroups ORDER BY id",DatabasePrefix);
		SQL_TQuery(DB,GroupsSecondPass,query);
	}
	else
	{
		curLoading--;
		CheckLoadAdmins();
	}*/
}

// Reparse to apply inherited immunity
public GroupsSecondPass(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		curLoading--;
		CheckLoadAdmins();
		LogToFile(logFile, "Failed to retrieve groups from the database, %s", error);
		return;
	}
	decl String:grpName[128], String:immunityGrpName[128];
	
	new GroupId:curGrp = INVALID_GROUP_ID;
	new GroupId:immuneGrp = INVALID_GROUP_ID;
	while (SQL_MoreRows(hndl))
	{
		SQL_FetchRow(hndl);
		if (SQL_IsFieldNull(hndl, 0))
			continue; // Sometimes some rows return NULL due to some setups
		
		SQL_FetchString(hndl, 0, grpName, 128);
		TrimString(grpName);
		if (strlen(grpName) == 0)
			continue;
		
		SQL_FetchString(hndl, 2, immunityGrpName, sizeof(immunityGrpName));
		TrimString(immunityGrpName);
		
		curGrp = FindAdmGroup(grpName);
		if (curGrp == INVALID_GROUP_ID)
			continue;
		
		immuneGrp = FindAdmGroup(immunityGrpName);
		if (immuneGrp == INVALID_GROUP_ID)
			continue;
		
		SetAdmGroupImmuneFrom(curGrp, immuneGrp);
		
		#if defined DEBUG
		LogToFile(logFile, "Group %s inhertied immunity from group %s", grpName, immunityGrpName);
		#endif
	}
	--curLoading;
	CheckLoadAdmins();
}

public LoadGroupsOverrides(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		curLoading--;
		CheckLoadAdmins();
		LogToFile(logFile, "Failed to retrieve group overrides from the database, %s", error);
		return;
	}
	decl String:sGroupName[128], String:sType[16], String:sCommand[64], String:sAllowed[16];
	decl OverrideRule:iRule, OverrideType:iType;
	
	new Handle:groupsKV = CreateKeyValues("Groups");
	FileToKeyValues(groupsKV, groupsLoc);
	
	new GroupId:curGrp = INVALID_GROUP_ID;
	while (SQL_MoreRows(hndl))
	{
		SQL_FetchRow(hndl);
		if (SQL_IsFieldNull(hndl, 0))
			continue; // Sometimes some rows return NULL due to some setups
		
		SQL_FetchString(hndl, 0, sGroupName, sizeof(sGroupName));
		TrimString(sGroupName);
		if (strlen(sGroupName) == 0)
			continue;
		
		SQL_FetchString(hndl, 1, sType, sizeof(sType));
		SQL_FetchString(hndl, 2, sCommand, sizeof(sCommand));
		SQL_FetchString(hndl, 3, sAllowed, sizeof(sAllowed));
		
		curGrp = FindAdmGroup(sGroupName);
		if (curGrp == INVALID_GROUP_ID)
			continue;
		
		iRule = StrEqual(sAllowed, "allow") ? Command_Allow : Command_Deny;
		iType = StrEqual(sType, "group") ? Override_CommandGroup : Override_Command;
		
		#if defined DEBUG
		PrintToServer("AddAdmGroupCmdOverride(%i, %s, %i, %i)", curGrp, sCommand, iType, iRule);
		#endif
		
		// Save overrides into admin_groups.cfg backup
		if (KvJumpToKey(groupsKV, sGroupName))
		{
			KvJumpToKey(groupsKV, "Overrides", true);
			if (iType == Override_Command)
				KvSetString(groupsKV, sCommand, sAllowed);
			else
			{
				Format(sCommand, sizeof(sCommand), "@%s", sCommand);
				KvSetString(groupsKV, sCommand, sAllowed);
			}
			KvRewind(groupsKV);
		}
		
		AddAdmGroupCmdOverride(curGrp, sCommand, iType, iRule);
	}
	curLoading--;
	CheckLoadAdmins();
	
	if (backupConfig)
		KeyValuesToFile(groupsKV, groupsLoc);
	CloseHandle(groupsKV);
}

public OverridesDone(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		LogToFile(logFile, "Failed to retrieve overrides from the database, %s", error);
		ParseBackupConfig_Overrides();
		return;
	}
	
	new Handle:hKV = CreateKeyValues("SB_Overrides");
	
	decl String:sFlags[32], String:sName[64], String:sType[64];
	while (SQL_FetchRow(hndl))
	{
		SQL_FetchString(hndl, 0, sType, sizeof(sType));
		SQL_FetchString(hndl, 1, sName, sizeof(sName));
		SQL_FetchString(hndl, 2, sFlags, sizeof(sFlags));
		
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
			KvJumpToKey(hKV, "override_commands", true);
			KvSetString(hKV, sName, sFlags);
			KvGoBack(hKV);
		}
		else if (StrEqual(sType, "group"))
		{
			AddCommandOverride(sName, Override_CommandGroup, ReadFlagString(sFlags));
			KvJumpToKey(hKV, "override_groups", true);
			KvSetString(hKV, sName, sFlags);
			KvGoBack(hKV);
		}
	}
	
	KvRewind(hKV);
	
	if (backupConfig)
		KeyValuesToFile(hKV, overridesLoc);
	CloseHandle(hKV);
}

// TIMER CALL BACKS //

public Action:ClientRecheck(Handle:timer, any:client)
{
	decl String:Authid[64];
	if (!PlayerStatus[client] && IsClientConnected(client) && GetClientAuthId(client, AuthId_Steam2, Authid, sizeof(Authid)))
	{
		OnClientAuthorized(client, Authid);
	}
	
	PlayerRecheck[client] = INVALID_HANDLE;
	return Plugin_Stop;
}

/*
public Action:PruneBans(Handle:timer)
{
	decl String:Query[512];
	FormatEx(Query, sizeof(Query),
			"UPDATE %s_bans SET RemovedBy = 0, RemoveType = 'E', RemovedOn = UNIX_TIMESTAMP() WHERE length != '0' AND ends < UNIX_TIMESTAMP()",
			DatabasePrefix);

	SQL_TQuery(DB, ErrorCheckCallback, Query);
	return Plugin_Continue;
}
*/

public Action:ProcessQueue(Handle:timer, any:data)
{
	decl String:buffer[512];
	Format(buffer, sizeof(buffer), "SELECT steam_id, time, start_time, reason, name, ip, admin_id, admin_ip FROM queue");
	SQL_TQuery(SQLiteDB, ProcessQueueCallback, buffer);
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
		} else if (strcmp("BanReasons", name, false) == 0) {
			ConfigState = ConfigStateReasons;
		} else if (strcmp("HackingReasons", name, false) == 0) {
			ConfigState = ConfigStateHacking;
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
				AutoAdd = StringToInt(value) == 1;
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
	}
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_EndSection(Handle:smc)
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
public Native_SBBanPlayer(Handle:plugin, numParams)
{
	new client = GetNativeCell(1);
	new target = GetNativeCell(2);
	new time = GetNativeCell(3);
	new String:reason[128];
	GetNativeString(4, reason, 128);
	
	if (reason[0] == '\0')
		strcopy(reason, sizeof(reason), "Banned by SourceBans");
	
	if (client && IsClientInGame(client))
	{
		new AdminId:aid = GetUserAdmin(client);
		if (aid == INVALID_ADMIN_ID)
		{
			ThrowNativeError(1, "Ban Error: Player is not an admin.");
			return 0;
		}
		
		if (!GetAdminFlag(aid, Admin_Ban))
		{
			ThrowNativeError(2, "Ban Error: Player does not have BAN flag.");
			return 0;
		}
	}
	
	PrepareBan(client, target, time, reason, sizeof(reason));
	return true;
}


// STOCK FUNCTIONS //

public InitializeBackupDB()
{
	decl String:error[255];
	SQLiteDB = SQLite_UseDatabase("sourcebans-queue", error, sizeof(error));
	if (SQLiteDB == INVALID_HANDLE)
		SetFailState(error);
	
	SQL_LockDatabase(SQLiteDB);
	SQL_FastQuery(SQLiteDB, "CREATE TABLE IF NOT EXISTS queue (steam_id TEXT PRIMARY KEY ON CONFLICT REPLACE, time INTEGER, start_time INTEGER, reason TEXT, name TEXT, ip TEXT, admin_id TEXT, admin_ip TEXT);");
	SQL_UnlockDatabase(SQLiteDB);
}

public bool:CreateBan(client, target, time, String:reason[])
{
	decl String:adminIp[24], String:adminAuth[64];
	new admin = client;
	
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
	decl String:ip[24], String:auth[64], String:name[64];
	
	GetClientName(target, name, sizeof(name));
	GetClientIP(target, ip, sizeof(ip));
	if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth)))
		return false;
	
	new userid = admin ? GetClientUserId(admin) : 0;
	
	// Pack everything into a data pack so we can retain it
	new Handle:dataPack = CreateDataPack();
	new Handle:reasonPack = CreateDataPack();
	WritePackString(reasonPack, reason);
	
	WritePackCell(dataPack, admin);
	WritePackCell(dataPack, target);
	WritePackCell(dataPack, userid);
	WritePackCell(dataPack, GetClientUserId(target));
	WritePackCell(dataPack, time);
	WritePackCell(dataPack, _:reasonPack);
	WritePackString(dataPack, name);
	WritePackString(dataPack, auth);
	WritePackString(dataPack, ip);
	WritePackString(dataPack, adminAuth);
	WritePackString(dataPack, adminIp);
	
	ResetPack(dataPack);
	ResetPack(reasonPack);
	
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
		ReplyToCommand(admin, "%c[%cSourceBans%c]%c %t", GREEN, NAMECOLOR, GREEN, NAMECOLOR, "Check Menu");
	}
	
	return true;
}

stock UTIL_InsertBan(time, const String:Name[], const String:Authid[], const String:Ip[], const String:Reason[], const String:AdminAuthid[], const String:AdminIp[], Handle:Pack)
{
	//new Handle:dummy;
	//PruneBans(dummy);
	decl String:banName[128];
	decl String:banReason[256];
	decl String:Query[1024];
	SQL_EscapeString(DB, Name, banName, sizeof(banName));
	SQL_EscapeString(DB, Reason, banReason, sizeof(banReason));
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
	
	SQL_TQuery(DB, VerifyInsert, Query, Pack, DBPrio_High);
}

stock UTIL_InsertTempBan(time, const String:name[], const String:auth[], const String:ip[], const String:reason[], const String:adminAuth[], const String:adminIp[], Handle:dataPack)
{
	ReadPackCell(dataPack); // admin index
	new client = ReadPackCell(dataPack);
	ReadPackCell(dataPack); // admin userid
	ReadPackCell(dataPack); // target userid
	ReadPackCell(dataPack); // time
	new Handle:reasonPack = Handle:ReadPackCell(dataPack);
	if (reasonPack != INVALID_HANDLE)
	{
		CloseHandle(reasonPack);
	}
	CloseHandle(dataPack);
	
	// we add a temporary ban and then add the record into the queue to be processed when the database is available
	decl String:buffer[50];
	Format(buffer, sizeof(buffer), "banid %d %s", ProcessQueueTime, auth);
	ServerCommand(buffer);
	if (IsClientInGame(client))
		KickClient(client, "%t", "Banned Check Site", WebsiteAddress);
	
	decl String:banName[128];
	decl String:banReason[256];
	decl String:query[512];
	SQL_EscapeString(SQLiteDB, name, banName, sizeof(banName));
	SQL_EscapeString(SQLiteDB, reason, banReason, sizeof(banReason));
	FormatEx(query, sizeof(query), "INSERT INTO queue VALUES ('%s', %i, %i, '%s', '%s', '%s', '%s', '%s')", 
		auth, time, GetTime(), banReason, banName, ip, adminAuth, adminIp);
	SQL_TQuery(SQLiteDB, ErrorCheckCallback, query);
}

stock CheckLoadAdmins()
{
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && IsClientAuthorized(i))
		{
			RunAdminCacheChecks(i);
			NotifyPostAdminCheck(i);
		}
	}
}

stock InsertServerInfo()
{
	if (DB == INVALID_HANDLE)
	{
		return;
	}
	
	decl String:query[100], pieces[4];
	new longip = GetConVarInt(CvarHostIp);
	pieces[0] = (longip >> 24) & 0x000000FF;
	pieces[1] = (longip >> 16) & 0x000000FF;
	pieces[2] = (longip >> 8) & 0x000000FF;
	pieces[3] = longip & 0x000000FF;
	FormatEx(ServerIp, sizeof(ServerIp), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);
	GetConVarString(CvarPort, ServerPort, sizeof(ServerPort));
	
	if (AutoAdd != false)
	{
		FormatEx(query, sizeof(query), "SELECT sid FROM %s_servers WHERE ip = '%s' AND port = '%s'", DatabasePrefix, ServerIp, ServerPort);
		SQL_TQuery(DB, ServerInfoCallback, query);
	}
}

stock PrepareBan(client, target, time, String:reason[], size)
{
	#if defined DEBUG
	LogToFile(logFile, "PrepareBan()");
	#endif
	if (!target || !IsClientInGame(target))
		return;
	decl String:authid[64], String:name[32], String:bannedSite[512];
	if (!GetClientAuthId(target, AuthId_Steam2, authid, sizeof(authid)))
		return;
	GetClientName(target, name, sizeof(name));
	
	
	if (CreateBan(client, target, time, reason))
	{
		if (!time)
		{
			if (reason[0] == '\0')
			{
				ShowActivity(client, "%t", "Permabanned player", name);
			} else {
				ShowActivity(client, "%t", "Permabanned player reason", name, reason);
			}
		} else {
			if (reason[0] == '\0')
			{
				ShowActivity(client, "%t", "Banned player", name, time);
			} else {
				ShowActivity(client, "%t", "Banned player reason", name, time, reason);
			}
		}
		LogAction(client, target, "\"%L\" banned \"%L\" (minutes \"%d\") (reason \"%s\")", client, target, time, reason);
		
		if (time > 5 || time == 0)
			time = 5;
		Format(bannedSite, sizeof(bannedSite), "%T", "Banned Check Site", target, WebsiteAddress);
		BanClient(target, time, BANFLAG_AUTO, bannedSite, bannedSite, "sm_ban", client);
	}
	
	g_BanTarget[client] = -1;
	g_BanTime[client] = -1;
}

stock ReadConfig()
{
	InitializeConfigParser();
	
	if (ConfigParser == INVALID_HANDLE)
	{
		return;
	}
	
	decl String:ConfigFile[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, ConfigFile, sizeof(ConfigFile), "configs/sourcebans/sourcebans.cfg");
	
	if (FileExists(ConfigFile))
	{
		InternalReadConfig(ConfigFile);
		PrintToServer("%sLoading configs/sourcebans.cfg config file", Prefix);
	} else {
		decl String:Error[PLATFORM_MAX_PATH + 64];
		FormatEx(Error, sizeof(Error), "%sFATAL *** ERROR *** can not find %s", Prefix, ConfigFile);
		LogToFile(logFile, "FATAL *** ERROR *** can not find %s", ConfigFile);
		SetFailState(Error);
	}
}

stock ResetSettings()
{
	CommandDisable = 0;
	
	ResetMenu();
	ReadConfig();
}

stock ParseBackupConfig_Overrides()
{
	new Handle:hKV = CreateKeyValues("SB_Overrides");
	if (!FileToKeyValues(hKV, overridesLoc))
		return;
	
	if (!KvGotoFirstSubKey(hKV))
		return;
	
	decl String:sSection[16], String:sFlags[32], String:sName[64];
	decl OverrideType:type;
	do
	{
		KvGetSectionName(hKV, sSection, sizeof(sSection));
		if (StrEqual(sSection, "override_commands"))
			type = Override_Command;
		else if (StrEqual(sSection, "override_groups"))
			type = Override_CommandGroup;
		else
			continue;
		
		if (KvGotoFirstSubKey(hKV, false))
		{
			do
			{
				KvGetSectionName(hKV, sName, sizeof(sName));
				KvGetString(hKV, NULL_STRING, sFlags, sizeof(sFlags));
				AddCommandOverride(sName, type, ReadFlagString(sFlags));
				#if defined _DEBUG
				PrintToServer("Adding override (%s, %s, %s)", sSection, sName, sFlags);
				#endif
			} while (KvGotoNextKey(hKV, false));
			KvGoBack(hKV);
		}
	}
	while (KvGotoNextKey(hKV));
	CloseHandle(hKV);
}

stock AdminFlag:CreateFlagLetters()
{
	new AdminFlag:FlagLetters[FLAG_LETTERS_SIZE];
	
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

stock AccountForLateLoading()
{
	decl String:auth[30];
	for (new i = 1; i <= GetMaxClients(); i++)
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