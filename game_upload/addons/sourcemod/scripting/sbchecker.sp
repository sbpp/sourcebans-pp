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
//   SourceBans Checker 1.0.2
//   Copyright (C) 2010-2013 Nicholas Hastings
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1288490>
//
// *************************************************************************

#include <sourcemod>

#define VERSION "(SB++) 1.5.4.6"
#define LISTBANS_USAGE "sm_listsbbans <#userid|name> - Lists a user's prior bans from Sourcebans"
#define INVALID_TARGET -1

new String:g_DatabasePrefix[10] = "sb";
new Handle:g_ConfigParser;
new Handle:g_DB;

ConVar ShortMessage;

public Plugin:myinfo = 
{
	name = "SourceBans Checker", 
	author = "psychonic, Ca$h Munny, Sarabveer(VEER™)", 
	description = "Notifies admins of prior bans from Sourcebans upon player connect.", 
	version = VERSION, 
	url = "https://sarabveer.github.io/SourceBans-Fork/"
};

public OnPluginStart()
{
	LoadTranslations("common.phrases");
	
	CreateConVar("sbchecker_version", VERSION, "", FCVAR_NOTIFY);
	
	ShortMessage = CreateConVar("sb_short_message", "0", "Use shorter message for displying prev bans", _, true, 0.0, true, 1.0);
	
	RegAdminCmd("sm_listbans", OnListSourceBansCmd, ADMFLAG_BAN, LISTBANS_USAGE);
	RegAdminCmd("sb_reload", OnReloadCmd, ADMFLAG_RCON, "Reload sourcebans config and ban reason menu options");
	
	SQL_TConnect(OnDatabaseConnected, "sourcebans");
}

public OnMapStart()
{
	ReadConfig();
}

public Action:OnReloadCmd(client, args)
{
	ReadConfig();
	return Plugin_Handled;
}

public OnDatabaseConnected(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
		SetFailState("Failed to connect to SourceBans DB, %s", error);
	
	g_DB = hndl;
}

public OnClientAuthorized(client, const String:auth[])
{
	if (g_DB == INVALID_HANDLE)
		return;
	
	/* Do not check bots nor check player with lan steamid. */
	if (auth[0] == 'B' || auth[9] == 'L')
		return;
	
	decl String:query[512], String:ip[30];
	GetClientIP(client, ip, sizeof(ip));
	FormatEx(query, sizeof(query), "SELECT COUNT(bid) FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, auth[8], ip);
	
	SQL_TQuery(g_DB, OnConnectBanCheck, query, GetClientUserId(client), DBPrio_Low);
}

public OnConnectBanCheck(Handle:owner, Handle:hndl, const String:error[], any:userid)
{
	new client = GetClientOfUserId(userid);
	
	if (!client || hndl == INVALID_HANDLE || !SQL_FetchRow(hndl))
		return;
	
	new bancount = SQL_FetchInt(hndl, 0);
	if (bancount > 0)
	{
		if (ShortMessage.BoolValue)
		{
			PrintToBanAdmins("\x04[SB]\x01Player \"%N\" has %d previous ban%s.", 
				client, bancount, ((bancount > 0) ? "s":""));
		}
		else
		{
			PrintToBanAdmins("\x04[SourceBans]\x01 Warning: Player \"%N\" has %d previous ban%s on record.", 
				client, bancount, ((bancount > 0) ? "s":""));
		}
	}
}

public Action:OnListSourceBansCmd(client, args)
{
	if (args < 1)
	{
		ReplyToCommand(client, LISTBANS_USAGE);
	}
	
	if (g_DB == INVALID_HANDLE)
	{
		ReplyToCommand(client, "Error: Database not ready.");
		return Plugin_Handled;
	}
	
	decl String:targetarg[64];
	GetCmdArg(1, targetarg, sizeof(targetarg));
	
	new target = FindTarget(client, targetarg, true, true);
	if (target == INVALID_TARGET)
	{
		ReplyToCommand(client, "Error: Could not find a target matching '%s'.", targetarg);
		return Plugin_Handled;
	}
	
	decl String:auth[32];
	if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth))
		 || auth[0] == 'B' || auth[9] == 'L')
	{
		ReplyToCommand(client, "Error: Could not retrieve %N's steam id.", target);
		return Plugin_Handled;
	}
	
	decl String:query[1024], String:ip[30];
	GetClientIP(target, ip, sizeof(ip));
	FormatEx(query, sizeof(query), "SELECT created, %s_admins.user, ends, length, reason, RemoveType FROM %s_bans LEFT JOIN %s_admins ON %s_bans.aid = %s_admins.aid WHERE ((type = 0 AND %s_bans.authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, auth[8], ip);
	
	decl String:targetName[MAX_NAME_LENGTH];
	GetClientName(target, targetName, sizeof(targetName));
	
	new Handle:pack = CreateDataPack();
	WritePackCell(pack, (client == 0) ? 0 : GetClientUserId(client));
	WritePackString(pack, targetName);
	
	SQL_TQuery(g_DB, OnListBans, query, pack, DBPrio_Low);
	
	if (client == 0)
	{
		ReplyToCommand(client, "[SourceBans] Note: if you are using this command through an rcon tool, you will not see results.");
	}
	else
	{
		ReplyToCommand(client, "\x04[SourceBans]\x01 Look for %N's ban results in console.", target);
	}
	
	return Plugin_Handled;
}

public OnListBans(Handle:owner, Handle:hndl, const String:error[], any:pack)
{
	ResetPack(pack);
	new clientuid = ReadPackCell(pack);
	new client = GetClientOfUserId(clientuid);
	decl String:targetName[MAX_NAME_LENGTH];
	ReadPackString(pack, targetName, sizeof(targetName));
	CloseHandle(pack);
	
	if (clientuid > 0 && client == 0)
		return;
	
	if (hndl == INVALID_HANDLE)
	{
		PrintListResponse(clientuid, client, "[SourceBans] DB error while retrieving bans for %s:\n%s", targetName, error);
		return;
	}
	
	if (SQL_GetRowCount(hndl) == 0)
	{
		PrintListResponse(clientuid, client, "[SourceBans] No bans found for %s.", targetName);
		return;
	}
	
	PrintListResponse(clientuid, client, "[SourceBans] Listing bans for %s", targetName);
	PrintListResponse(clientuid, client, "Ban Date    Banned By   Length      End Date    R  Reason");
	PrintListResponse(clientuid, client, "-------------------------------------------------------------------------------");
	while (SQL_FetchRow(hndl))
	{
		new String:createddate[11] = "<Unknown> ";
		new String:bannedby[11] = "<Unknown> ";
		new String:lenstring[11] = "N/A       ";
		new String:enddate[11] = "N/A       ";
		decl String:reason[28];
		new String:RemoveType[2] = " ";
		
		if (!SQL_IsFieldNull(hndl, 0))
		{
			FormatTime(createddate, sizeof(createddate), "%Y-%m-%d", SQL_FetchInt(hndl, 0));
		}
		
		if (!SQL_IsFieldNull(hndl, 1))
		{
			new size_bannedby = sizeof(bannedby);
			SQL_FetchString(hndl, 1, bannedby, size_bannedby);
			new len = SQL_FetchSize(hndl, 1);
			if (len > size_bannedby - 1)
			{
				reason[size_bannedby - 4] = '.';
				reason[size_bannedby - 3] = '.';
				reason[size_bannedby - 2] = '.';
			}
			else
			{
				for (new i = len; i < size_bannedby - 1; i++)
				{
					bannedby[i] = ' ';
				}
			}
		}
		
		// NOT NULL
		new size_lenstring = sizeof(lenstring);
		new length = SQL_FetchInt(hndl, 3);
		if (length == 0)
		{
			strcopy(lenstring, size_lenstring, "Permanent ");
		}
		else
		{
			new len = IntToString(length, lenstring, size_lenstring);
			if (len < size_lenstring - 1)
			{
				// change the '\0' to a ' '. the original \0 at the end will still be there
				lenstring[len] = ' ';
			}
		}
		
		if (!SQL_IsFieldNull(hndl, 2))
		{
			FormatTime(enddate, sizeof(enddate), "%Y-%m-%d", SQL_FetchInt(hndl, 2));
		}
		
		// NOT NULL
		new reason_size = sizeof(reason);
		SQL_FetchString(hndl, 4, reason, reason_size);
		new len = SQL_FetchSize(hndl, 4);
		if (len > reason_size - 1)
		{
			reason[reason_size - 4] = '.';
			reason[reason_size - 3] = '.';
			reason[reason_size - 2] = '.';
		}
		else
		{
			for (new i = len; i < reason_size - 1; i++)
			{
				reason[i] = ' ';
			}
		}
		
		if (!SQL_IsFieldNull(hndl, 5))
		{
			SQL_FetchString(hndl, 5, RemoveType, sizeof(RemoveType));
		}
		
		PrintListResponse(clientuid, client, "%s  %s  %s  %s  %s  %s", createddate, bannedby, lenstring, enddate, RemoveType, reason);
	}
}

PrintListResponse(userid, client, const String:format[], any:...)
{
	decl String:msg[192];
	VFormat(msg, sizeof(msg), format, 4);
	
	if (userid == 0)
	{
		PrintToServer("%s", msg);
	}
	else
	{
		PrintToConsole(client, "%s", msg);
	}
}

PrintToBanAdmins(const String:format[], any:...)
{
	decl String:msg[128];
	VFormat(msg, sizeof(msg), format, 2);
	
	for (new i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && !IsFakeClient(i)
			 && CheckCommandAccess(i, "sm_listsourcebans", ADMFLAG_BAN)
			)
		{
			PrintToChat(i, "%s", msg);
		}
	}
}

stock ReadConfig()
{
	InitializeConfigParser();
	
	if (g_ConfigParser == INVALID_HANDLE)
	{
		return;
	}
	
	decl String:ConfigFile[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, ConfigFile, sizeof(ConfigFile), "configs/sourcebans/sourcebans.cfg");
	
	if (FileExists(ConfigFile))
	{
		InternalReadConfig(ConfigFile);
	}
	else
	{
		decl String:Error[PLATFORM_MAX_PATH + 64];
		FormatEx(Error, sizeof(Error), "FATAL *** ERROR *** can not find %s", ConfigFile);
		SetFailState(Error);
	}
}

static InitializeConfigParser()
{
	if (g_ConfigParser == INVALID_HANDLE)
	{
		g_ConfigParser = SMC_CreateParser();
		SMC_SetReaders(g_ConfigParser, ReadConfig_NewSection, ReadConfig_KeyValue, ReadConfig_EndSection);
	}
}

static InternalReadConfig(const String:path[])
{
	new SMCError:err = SMC_ParseFile(g_ConfigParser, path);
	
	if (err != SMCError_Okay)
	{
		decl String:buffer[64];
		PrintToServer("%s", SMC_GetErrorString(err, buffer, sizeof(buffer)) ? buffer : "Fatal parse error");
	}
}

public SMCResult:ReadConfig_NewSection(Handle:smc, const String:name[], bool:opt_quotes)
{
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_KeyValue(Handle:smc, const String:key[], const String:value[], bool:key_quotes, bool:value_quotes)
{
	if (strcmp("DatabasePrefix", key, false) == 0)
	{
		strcopy(g_DatabasePrefix, sizeof(g_DatabasePrefix), value);
		
		if (g_DatabasePrefix[0] == '\0')
		{
			g_DatabasePrefix = "sb";
		}
	}
	
	return SMCParse_Continue;
}

public SMCResult:ReadConfig_EndSection(Handle:smc)
{
	return SMCParse_Continue;
} 