/**
 * SourceBans Core Plugin
 *
 * @author GameConnect
 * @version 1.5.0
 * @copyright SourceBans (C)2007-2013 GameConnect.net.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id$
 */

#pragma semicolon 1

#include <sourcemod>
#include <sourcebans>
#include <regex>

//#define _DEBUG

public Plugin:myinfo =
{
	name        = "SourceBans",
	author      = "GameConnect",
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
	ConfigState_Times,
	ConfigState_Loaded
}
enum DatabaseState
{
	DatabaseState_None = 0,
	DatabaseState_Connecting,
	DatabaseState_Connected
}

new ConfigState:g_iConfigState;
new g_iConnectLock = 0;
new DatabaseState:g_iDatabaseState;
new g_iSequence    = 0;
new g_iServerPort;
new Handle:g_hConfig;
new Handle:g_hConfigParser;
new Handle:g_hDatabase;
new Handle:g_hBanReasons;
new Handle:g_hBanTimes;
new Handle:g_hBanTimesFlags;
new Handle:g_hBanTimesLength;
new Handle:g_hHackingReasons;
new Handle:g_hOnConnect;
new Handle:g_hOnReload;
new String:g_sConfigFile[PLATFORM_MAX_PATH];
new String:g_sDatabasePrefix[16];
new String:g_sServerIp[16];


/**
 * Plugin Forwards
 */
public APLRes:AskPluginLoad2(Handle:myself, bool:late, String:error[], err_max)
{
	CreateNative("SB_Connect",         Native_Connect);
	CreateNative("SB_Escape",          Native_Escape);
	CreateNative("SB_Execute",         Native_Execute);
	CreateNative("SB_GetConfigString", Native_GetConfigString);
	CreateNative("SB_GetConfigValue",  Native_GetConfigValue);
	CreateNative("SB_Init",            Native_Init);
	CreateNative("SB_Query",           Native_Query);
	CreateNative("SB_Reload",          Native_Reload);
	RegPluginLibrary("sourcebans");
	
	return APLRes_Success;
}

public OnPluginStart()
{
	CreateConVar("sb_version", SB_VERSION, "Advanced admin and ban management for the Source engine", FCVAR_NOTIFY|FCVAR_PLUGIN);
	RegAdminCmd("sb_reload", Command_Reload, ADMFLAG_RCON, "Reload SourceBans config and ban reason menu options");
	
	LoadTranslations("common.phrases");
	LoadTranslations("sourcebans.phrases");
	BuildPath(Path_SM, g_sConfigFile, sizeof(g_sConfigFile), "configs/sourcebans.cfg");
	
	g_hOnConnect      = CreateGlobalForward("SB_OnConnect", ET_Event, Param_Cell);
	g_hOnReload       = CreateGlobalForward("SB_OnReload",  ET_Event);
	g_hConfig         = CreateTrie();
	g_hBanReasons     = CreateArray(256);
	g_hBanTimes       = CreateArray(256);
	g_hBanTimesFlags  = CreateArray(256);
	g_hBanTimesLength = CreateArray(256);
	g_hHackingReasons = CreateArray(256);
	
	g_hConfigParser   = SMC_CreateParser();
	SMC_SetReaders(g_hConfigParser, ReadConfig_NewSection, ReadConfig_KeyValue, ReadConfig_EndSection);
	
	new iServerIp     = GetConVarInt(FindConVar("hostip"));
	g_iServerPort     = GetConVarInt(FindConVar("hostport"));
	Format(g_sServerIp, sizeof(g_sServerIp), "%i.%i.%i.%i", (iServerIp >> 24) & 0xFF,
	                                                        (iServerIp >> 16) & 0xFF,
	                                                        (iServerIp >>  8) & 0xFF,
	                                                        iServerIp         & 0xFF);
	
	// Store server IP and port locally
	SetTrieString(g_hConfig, "ServerIP",     g_sServerIp);
	SetTrieValue(g_hConfig,  "ServerPort",   g_iServerPort);
	// Store whether the admins plugin is enabled or disabled
	SetTrieValue(g_hConfig,  "EnableAdmins", LibraryExists("sb_admins"));
}

public OnMapStart()
{
	SB_Reload();
}

public OnMapEnd()
{
	/**
	 * Clean up on map end just so we can start a fresh connection when we need it later.
	 */
	if(g_hDatabase)
	{
		CloseHandle(g_hDatabase);
		g_hDatabase = INVALID_HANDLE;
	}
}

public OnLibraryAdded(const String:name[])
{
	if(StrEqual(name, "sb_admins"))
		SetTrieValue(g_hConfig, "EnableAdmins", true);
}

public OnLibraryRemoved(const String:name[])
{
	if(StrEqual(name, "sb_admins"))
		SetTrieValue(g_hConfig, "EnableAdmins", false);
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
			   StrEqual("RequireSiteLogin", key, false) ||
			   StrEqual("Unban",            key, false))
				SetTrieValue(g_hConfig,  key, StringToInt(value));
			// If value is a float
			else if(StrEqual("RetryTime",   key, false))
				SetTrieValue(g_hConfig,  key, StringToFloat(value));
			// If value is a string
			else if(value[0])
				SetTrieString(g_hConfig, key, value);
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
		SetTrieValue(g_hConfig, "ServerID", SQL_FetchInt(hndl, 0));
		
		Call_StartForward(g_hOnConnect);
		Call_PushCell(g_hDatabase);
		Call_Finish();
		return;
	}
	
	decl String:sFolder[32], String:sQuery[1024];
	GetGameFolderName(sFolder, sizeof(sFolder));
	
	Format(sQuery, sizeof(sQuery), "INSERT INTO {{servers}} (ip, port, modid) \
	                                VALUES      ('%s', %i, (SELECT mid FROM {{mods}} WHERE modfolder = '%s'))",
	                                g_sServerIp, g_iServerPort, sFolder);
	SB_Query(Query_ServerInsert, sQuery);
}

public Query_ServerInsert(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if(error[0])
	{
		LogError("%T (%s)", "Failed to query database", LANG_SERVER, error);
		return;
	}
	
	// Store server ID locally
	SetTrieValue(g_hConfig, "ServerID", SQL_GetInsertId(owner));
	
	Call_StartForward(g_hOnConnect);
	Call_PushCell(g_hDatabase);
	Call_Finish();
}

public Query_ExecuteCallback(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	new Handle:plugin = Handle:ReadPackCell(pack);
	new SQLTCallback:callback = SQLTCallback:ReadPackCell(pack);
	new data = ReadPackCell(pack);
	CloseHandle(pack);
	
	Call_StartFunction(plugin, callback);
	Call_PushCell(owner);
	Call_PushCell(hndl);
	Call_PushString(error);
	Call_PushCell(data);
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
	PrintToServer("%sOnConnect(%x,%x,%d) ConnectLock=%d", SB_PREFIX, owner, hndl, data, g_iConnectLock);
	#endif
	
	// If this happens to be an old connection request, ignore it.
	if(data != g_iConnectLock || g_hDatabase)
	{
		if(hndl)
			CloseHandle(hndl);
		return;
	}
	
	g_iConnectLock   = 0;
	g_iDatabaseState = DatabaseState_Connected;
	g_hDatabase      = hndl;
	
	// See if the connection is valid.  If not, don't un-mark the caches
	// as needing rebuilding, in case the next connection request works.
	if(!g_hDatabase)
	{
		LogError("%T (%s)", "Could not connect to database", LANG_SERVER, error);
		return;
	}
	
	// Set character set to UTF-8 in the database
	SB_Execute("SET NAMES 'UTF8'");
	
	// Select server from the database
	decl String:sQuery[1024];
	Format(sQuery, sizeof(sQuery), "SELECT sid \
	                                FROM   {{servers}} \
	                                WHERE  ip   = '%s' \
	                                  AND  port = %i",
	                                g_sServerIp, g_iServerPort);
	SB_Query(Query_ServerSelect, sQuery);
}


/**
 * Natives
 */
public Native_Connect(Handle:plugin, numParams)
{
	if(g_hDatabase)
		return true;
	
	if(g_iDatabaseState != DatabaseState_Connecting)
	{
		g_iDatabaseState = DatabaseState_Connecting;
		g_iConnectLock   = ++g_iSequence;
		// Connect using the "sourcebans" section, or the "default" section if "sourcebans" does not exist
		SQL_TConnect(OnConnect, SQL_CheckConfig("sourcebans") ? "sourcebans" : "default", g_iConnectLock);
	}
	
	return false;
}

public Native_Escape(Handle:plugin, numParams)
{
	// Get max length for the string buffer
	new iLen = GetNativeCell(3);
	if(iLen <= 0)
		return false;
	
	decl String:sData[iLen], String:sBuffer[iLen];
	GetNativeString(1, sData, iLen);
	
	new written = GetNativeCellRef(4);
	new bool:success = SQL_EscapeString(g_hDatabase, sData, sBuffer, iLen, written);
	
	// Store value in string buffer
	SetNativeString(2, sBuffer, iLen);
	return success;
}

public Native_Execute(Handle:plugin, numParams)
{
	decl String:sQuery[4096];
	GetNativeString(1, sQuery, sizeof(sQuery));
	
	new DBPriority:prio = DBPriority:GetNativeCell(2);
	
	ExecuteQuery(Query_ErrorCheck, sQuery, 0, prio);
}

public Native_GetConfigString(Handle:plugin, numParams)
{
	// Get max length for the string buffer
	new iLen = GetNativeCell(3);
	if(iLen <= 0)
		return;
	
	// Get value for key
	decl String:sKey[32], String:sValue[iLen];
	GetNativeString(1, sKey, sizeof(sKey));
	GetTrieString(g_hConfig, sKey, sValue, iLen);
	
	// Store value in string buffer
	SetNativeString(2, sValue, iLen);
}

public Native_GetConfigValue(Handle:plugin, numParams)
{
	// Get value for key
	decl String:sKey[32];
	new iValue;
	GetNativeString(1, sKey, sizeof(sKey));
	GetTrieValue(g_hConfig, sKey, iValue);
	
	// Return value
	return iValue;
}

public Native_Init(Handle:plugin, numParams)
{
	// If config is loaded, call reload forward
	if(g_iConfigState == ConfigState_Loaded)
	{
		Call_StartForward(g_hOnReload);
		Call_Finish();
	}
	
	// If server ID has been fetched, call connect forward
	new iServerId;
	if(GetTrieValue(g_hConfig, "ServerID", iServerId))
	{
		Call_StartForward(g_hOnConnect);
		Call_PushCell(g_hDatabase);
		Call_Finish();
	}
}

public Native_Query(Handle:plugin, numParams)
{
	decl String:sQuery[4096];
	GetNativeString(2, sQuery, sizeof(sQuery));
	
	new SQLTCallback:callback = SQLTCallback:GetNativeCell(1);
	new data = GetNativeCell(3);
	new DBPriority:prio = DBPriority:GetNativeCell(4);
	
	new Handle:hPack = CreateDataPack();
	WritePackCell(hPack, _:plugin);
	WritePackCell(hPack, _:callback);
	WritePackCell(hPack, data);
	
	ExecuteQuery(Query_ExecuteCallback, sQuery, hPack, prio);
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
	if(iError != SMCError_Okay)
	{
		decl String:sError[64];
		if(SMC_GetErrorString(iError, sError, sizeof(sError)))
			LogError(sError);
		else
			LogError("Fatal parse error");
		return;
	}
	
	g_iConfigState      = ConfigState_Loaded;
	
	GetTrieString(g_hConfig, "DatabasePrefix", g_sDatabasePrefix, sizeof(g_sDatabasePrefix));
	GetTrieString(g_hConfig, "ServerIP",       g_sServerIp,       sizeof(g_sServerIp));
	GetTrieValue(g_hConfig,  "ServerPort",     g_iServerPort);
	SetTrieValue(g_hConfig,  "BanReasons",     g_hBanReasons);
	SetTrieValue(g_hConfig,  "BanTimes",       g_hBanTimes);
	SetTrieValue(g_hConfig,  "BanTimesFlags",  g_hBanTimesFlags);
	SetTrieValue(g_hConfig,  "BanTimesLength", g_hBanTimesLength);
	SetTrieValue(g_hConfig,  "HackingReasons", g_hHackingReasons);
	
	Call_StartForward(g_hOnReload);
	Call_Finish();
}


/**
 * Stocks
 */
ExecuteQuery(SQLTCallback:callback, String:sQuery[4096], any:data = 0, DBPriority:prio = DBPrio_Normal)
{
	if(!SB_Connect())
		return;
	
	// Format {{table}} as DatabasePrefix_table
	decl String:sSearch[65], String:sReplace[65], String:sTable[65];
	static Handle:hTables;
	if(!hTables)
		hTables = CompileRegex("\\{\\{([0-9a-zA-Z\\$_]+?)\\}\\}");
	
	while(MatchRegex(hTables, sQuery) > 0)
	{
		GetRegexSubString(hTables, 0, sSearch, sizeof(sSearch));
		GetRegexSubString(hTables, 1, sTable,  sizeof(sTable));
		Format(sReplace, sizeof(sReplace), "%s_%s", g_sDatabasePrefix, sTable);
		
		ReplaceString(sQuery, sizeof(sQuery), sSearch, sReplace);
	}
	
	SQL_TQuery(g_hDatabase, callback, sQuery, data, prio);
}