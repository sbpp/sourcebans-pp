/**
 * =============================================================================
 * SourceBans Core Plugin
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

//#define _DEBUG

public Plugin:myinfo =
{
	name        = "SourceBans",
	author      = "InterWave Studios",
	description = "Advanced admin and ban management for the Source engine",
	version     = SB_VERSION,
	url         = "http://www.sourcebans.net"
};


/**
 * Globals
 */
enum ConfigState
{
	ConfigState_None = 0,
	ConfigState_Config,
	ConfigState_Reasons,
	ConfigState_Hacking,
	ConfigState_Times
}

new ConfigState:g_iConfigState;
new g_iConnectLock    = 0;
new g_iSequence       = 0;
new g_iServerPort;
new bool:g_bConnected = false;
new Handle:g_hConfigParser;
new Handle:g_hDatabase;
new Handle:g_hBanReasons;
new Handle:g_hBanTimes;
new Handle:g_hBanTimesFlags;
new Handle:g_hBanTimesLength;
new Handle:g_hHackingReasons;
new Handle:g_hOnConnect;
new Handle:g_hOnReload;
new Handle:g_hSettings;
new String:g_sConfigFile[PLATFORM_MAX_PATH];
new String:g_sDatabasePrefix[16];
new String:g_sServerIp[16];


/**
 * Plugin Forwards
 */
public OnPluginStart()
{
	CreateConVar("sb_version", SB_VERSION, "Advanced admin and ban management for the Source engine", FCVAR_NOTIFY|FCVAR_PLUGIN);
	RegAdminCmd("sb_reload", Command_Reload, ADMFLAG_RCON, "Reload SourceBans config and ban reason menu options");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	BuildPath(Path_SM, g_sConfigFile, sizeof(g_sConfigFile), "configs/sourcebans.cfg");
	
	g_hOnConnect      = CreateGlobalForward("SB_OnConnect", ET_Event, Param_Cell);
	g_hOnReload       = CreateGlobalForward("SB_OnReload",  ET_Event);
	g_hBanReasons     = CreateArray(256);
	g_hBanTimes       = CreateArray(256);
	g_hBanTimesFlags  = CreateArray(256);
	g_hBanTimesLength = CreateArray(256);
	g_hHackingReasons = CreateArray(256);
	g_hSettings       = CreateTrie();
	
	g_hConfigParser   = SMC_CreateParser();
	SMC_SetReaders(g_hConfigParser, ReadConfig_NewSection, ReadConfig_KeyValue, ReadConfig_EndSection);
	
	new iIp           = GetConVarInt(FindConVar("hostip"));
	g_iServerPort     = GetConVarInt(FindConVar("hostport"));
	Format(g_sServerIp, sizeof(g_sServerIp), "%i.%i.%i.%i", (iIp >> 24) & 0x000000FF,
																													(iIp >> 16) & 0x000000FF,
																													(iIp >>  8) & 0x000000FF,
																													iIp         & 0x000000FF);
	
	// Store server IP and port locally
	SetTrieString(g_hSettings, "ServerIP",     g_sServerIp);
	SetTrieValue(g_hSettings,  "ServerPort",   g_iServerPort);
	// Store whether the admins plugin is enabled or disabled
	SetTrieValue(g_hSettings,  "EnableAdmins", LibraryExists("sb_admins"));
}

public OnMapStart()
{
	// Reload settings from config file
	SB_Reload();
	
	// Connect to database
	if(!g_bConnected)
		SB_Connect();
}

public OnLibraryAdded(const String:name[])
{
	if(StrEqual(name, "sb_admins"))
		SetTrieValue(g_hSettings, "EnableAdmins", true);
}

public OnLibraryRemoved(const String:name[])
{
	if(StrEqual(name, "sb_admins"))
		SetTrieValue(g_hSettings, "EnableAdmins", false);
}

#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
#else
public bool:AskPluginLoad(Handle:myself, bool:late, String:error[], err_max)
#endif
{
	CreateNative("SB_Connect",          Native_Connect);
	CreateNative("SB_GetSettingCell",   Native_GetSettingCell);
	CreateNative("SB_GetSettingString", Native_GetSettingString);
	CreateNative("SB_Reload",           Native_Reload);
	RegPluginLibrary("sourcebans");
	#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 3
	return APLRes_Success;
	#else
	return true;
	#endif
}


/**
 * Commands
 */
public Action:Command_Reload(client, args)
{
	SB_Reload();
	return Plugin_Handled;
}


/**
 * Config Parser
 */
public SMCResult:ReadConfig_EndSection(Handle:smc)
{
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_KeyValue(Handle:smc, const String:key[], const String:value[], bool:key_quotes, bool:value_quotes)
{
	if(!key[0])
		return SMCParse_Continue;
	
	switch(g_iConfigState)
	{
		case ConfigState_Config:
		{
			// If value is an integer
			if(StrEqual("Addban",           key, false) ||
				 StrEqual("ProcessQueueTime", key, false) ||
				 StrEqual("Unban",            key, false))
				SetTrieValue(g_hSettings,  key, StringToInt(value));
			// If value is a float
			else if(StrEqual("RetryTime",   key, false))
				SetTrieValue(g_hSettings,  key, StringToFloat(value));
			// If value is a string
			else
				SetTrieString(g_hSettings, key, value);
		}
		case ConfigState_Hacking:
			PushArrayString(g_hHackingReasons, value);
		case ConfigState_Reasons:
			PushArrayString(g_hBanReasons,     value);
		case ConfigState_Times:
		{
			if(StrEqual("flags",       key, false))
				PushArrayString(g_hBanTimesFlags,  value);
			else if(StrEqual("length", key, false))
				PushArrayString(g_hBanTimesLength, value);
		}
	}
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_NewSection(Handle:smc, const String:name[], bool:opt_quotes)
{
	if(StrEqual("Config",              name, false))
		g_iConfigState = ConfigState_Config;
	else if(StrEqual("BanReasons",     name, false))
		g_iConfigState = ConfigState_Reasons;
	else if(StrEqual("BanTimes",       name, false))
		g_iConfigState = ConfigState_Times;
	else if(StrEqual("HackingReasons", name, false))
		g_iConfigState = ConfigState_Hacking;
	else if(g_iConfigState == ConfigState_Times)
		PushArrayString(g_hBanTimes, name);
	return SMCParse_Continue;
}


/**
 * Query Callbacks
 */
public Query_ServerSelect(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
	{
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
		return;
	}
	if(SQL_FetchRow(hndl))
	{
		// Store server ID locally
		SetTrieValue(g_hSettings, "ServerID", SQL_FetchInt(hndl, 0));
		
		Call_StartForward(g_hOnConnect);
		Call_PushCell(g_hDatabase);
		Call_Finish();
		return;
	}
	
	decl String:sFolder[32], String:sQuery[256];
	GetGameFolderName(sFolder, sizeof(sFolder));
	
	Format(sQuery, sizeof(sQuery), "INSERT INTO %s_servers (ip, port, mod_id) \
																	VALUES      ('%s', %i, (SELECT id FROM %s_mods WHERE folder = '%s'))",
																	g_sDatabasePrefix, g_sServerIp, g_iServerPort, g_sDatabasePrefix, sFolder);
	SQL_TQuery(g_hDatabase, Query_ServerInsert, sQuery);
}

public Query_ServerInsert(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
	{
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
		return;
	}
	
	// Store server ID locally
	SetTrieValue(g_hSettings, "ServerID", SQL_GetInsertId(owner));
	
	Call_StartForward(g_hOnConnect);
	Call_PushCell(g_hDatabase);
	Call_Finish();
}

public Query_ErrorCheck(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
}


/**
 * Connect Callback
 */
public OnConnect(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	#if defined _DEBUG
	PrintToServer("OnDatabaseConnect(%x,%x,%d) ConnectLock=%d", owner, hndl, data, g_iConnectLock);
	#endif
	
	// If this happens to be an old connection request, ignore it.
	if(data != g_iConnectLock || g_hDatabase)
	{
		if(hndl)
			CloseHandle(hndl);
		
		return;
	}
	
	g_iConnectLock = 0;
	g_bConnected   = true;
	g_hDatabase    = hndl;
	
	// See if the connection is valid.  If not, don't un-mark the caches
	// as needing rebuilding, in case the next connection request works.
	if(!g_hDatabase)
	{
		LogError("%T (%s)", "Could not connect to database", LANG_SERVER, error);
		return;
	}
	
	// Set character set to UTF-8 in the database
	SQL_TQuery(g_hDatabase, Query_ErrorCheck, "SET NAMES 'UTF8'");
	
	// Select server from the database
	decl String:sQuery[96];
	Format(sQuery, sizeof(sQuery), "SELECT id \
																	FROM   %s_servers \
																	WHERE  ip   = '%s' \
																	  AND  port = %i",
																	g_sDatabasePrefix, g_sServerIp, g_iServerPort);
	SQL_TQuery(g_hDatabase, Query_ServerSelect, sQuery);
}


/**
 * Natives
 */
public Native_Connect(Handle:plugin, numParams)
{
	g_iConnectLock = ++g_iSequence;
	// Connect using the "sourcebans" section, or the "default" section if "sourcebans" does not exist
	SQL_TConnect(OnConnect, SQL_CheckConfig("sourcebans") ? "sourcebans" : "default", g_iConnectLock);
}

public Native_GetSettingCell(Handle:plugin, numParams)
{
	// Get value from setting
	decl String:sSetting[32];
	new iBuffer;
	GetNativeString(1, sSetting, sizeof(sSetting));
	GetTrieValue(g_hSettings, sSetting, iBuffer);
	
	// Return value
	return iBuffer;
}

public Native_GetSettingString(Handle:plugin, numParams)
{
	// Get max length for the string buffer
	new iLen = GetNativeCell(3);
	if(iLen <= 0)
		return;
	
	// Get value from setting
	decl String:sBuffer[iLen + 1], String:sSetting[32];
	GetNativeString(1, sSetting, sizeof(sSetting));
	GetTrieString(g_hSettings, sSetting, sBuffer, iLen + 1);
	
	// Store value in string buffer
	SetNativeString(2, sBuffer, iLen + 1);
}

public Native_Reload(Handle:plugin, numParams)
{
	if(!FileExists(g_sConfigFile))
		SetFailState("%sFile not found: %s", SB_PREFIX, g_sConfigFile);
	
	// Empty ban reason and ban time arrays
	ClearArray(g_hBanReasons);
	ClearArray(g_hBanTimes);
	ClearArray(g_hBanTimesFlags);
	ClearArray(g_hBanTimesLength);
	ClearArray(g_hHackingReasons);
	
	// Reset config state
	g_iConfigState      = ConfigState_None;
	
	// Parse config file
	new SMCError:iError = SMC_ParseFile(g_hConfigParser, g_sConfigFile);
	if(iError          != SMCError_Okay)
	{
		decl String:sError[64];
		if(SMC_GetErrorString(iError, sError, sizeof(sError)))
			LogError(sError);
		else
			LogError("Fatal parse error");
		return;
	}
	
	GetTrieString(g_hSettings, "DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	SetTrieValue(g_hSettings,  "BanReasons",     g_hBanReasons);
	SetTrieValue(g_hSettings,  "BanTimes",       g_hBanTimes);
	SetTrieValue(g_hSettings,  "BanTimesFlags",  g_hBanTimesFlags);
	SetTrieValue(g_hSettings,  "BanTimesLength", g_hBanTimesLength);
	SetTrieValue(g_hSettings,  "HackingReasons", g_hHackingReasons);
	
	Call_StartForward(g_hOnReload);
	Call_Finish();
}