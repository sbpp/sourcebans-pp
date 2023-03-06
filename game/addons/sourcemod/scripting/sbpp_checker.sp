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
//   SourceBans Checker 1.0.2
//   Copyright (C) 2010-2013 Nicholas Hastings
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1288490>
//
// *************************************************************************

#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>

#define VERSION "1.8.0"
#define LISTBANS_USAGE "sm_listbans <#userid|name> - Lists a user's prior bans from Sourcebans"
#define LISTCOMMS_USAGE "sm_listcomms <#userid|name> - Lists a user's prior comms from Sourcebans"
#define INVALID_TARGET -1
#define Prefix "\x04[SourceBans++]\x01 "

char g_DatabasePrefix[10] = "sb";
SMCParser g_ConfigParser;
Database g_DB;

int g_iBanCounts[MAXPLAYERS + 1];
int g_iCommsCounts[MAXPLAYERS + 1];


public Plugin myinfo =
{
	name = "SourceBans++: Bans Checker",
	author = "psychonic, Ca$h Munny, SourceBans++ Dev Team",
	description = "Notifies admins of prior bans from Sourcebans upon player connect.",
	version = VERSION,
	url = "https://sbpp.github.io"
};

public void OnPluginStart()
{
	LoadTranslations("common.phrases");
	LoadTranslations("sbpp_checker.phrases");

	CreateConVar("sbchecker_version", VERSION, "", FCVAR_NOTIFY);
	RegAdminCmd("sm_listbans", OnListSourceBansCmd, ADMFLAG_GENERIC, LISTBANS_USAGE);
	RegAdminCmd("sm_listcomms", OnListSourceCommsCmd, ADMFLAG_GENERIC, LISTCOMMS_USAGE);
	RegAdminCmd("sb_reload", OnReloadCmd, ADMFLAG_RCON, "Reload sourcebans config and ban reason menu options");

	Database.Connect(OnDatabaseConnected, "sourcebans");
}

public void OnMapStart()
{
	ReadConfig();
}

public Action OnReloadCmd(int client, int args)
{
	ReadConfig();
	return Plugin_Handled;
}

public void OnDatabaseConnected(Database db, const char[] error, any data)
{
	if (db == null)
		SetFailState("Failed to connect to SourceBans DB, %s", error);

	g_DB = db;
}

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	RegPluginLibrary("sourcebans++");

	CreateNative("SBPP_CheckerGetClientsBans", Native_SBCheckerGetClientsBans);
	CreateNative("SBPP_CheckerGetClientsComms", Native_SBCheckerGetClientsComms);

	return APLRes_Success;
}

public int Native_SBCheckerGetClientsBans(Handle plugin, int numParams)
{
	int client = GetNativeCell(1);
	return g_iBanCounts[client];
}

public int Native_SBCheckerGetClientsComms(Handle plugin, int numParams)
{
	int client = GetNativeCell(1);
	return g_iCommsCounts[client];
}

public void OnClientAuthorized(int client, const char[] auth)
{
	if (g_DB == null)
		return;

	/* Do not check bots nor check player with lan steamid. */
	if (auth[0] == 'B' || auth[9] == 'L')
		return;

	char query[512], ip[30];
	GetClientIP(client, ip, sizeof(ip));
	FormatEx(query, sizeof(query), "SELECT COUNT(bid) FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) UNION SELECT COUNT(bid) FROM %s_comms WHERE authid REGEXP '^STEAM_[0-9]:%s$'", g_DatabasePrefix, auth[8], ip, g_DatabasePrefix,auth[8]);
	g_DB.Query(OnConnectBanCheck, query, GetClientUserId(client), DBPrio_Low);
}

public void OnConnectBanCheck(Database db, DBResultSet results, const char[] error, any userid)
{
	int client = GetClientOfUserId(userid);
	if (!client || results == null || !results.FetchRow())
		return;

	int bancount = results.FetchInt(0);
	int commcount = 0;
	if(results.FetchRow()){
		commcount = results.FetchInt(0);
	}

	g_iBanCounts[client] = bancount;
	g_iCommsCounts[client] = commcount;

	if ( bancount && commcount ) {
		PrintToBanAdmins("%s%t", Prefix, "Ban and Comm Warning", client, bancount, ((bancount > 1 || bancount == 0) ? "s":""), commcount, ((commcount > 1 || commcount == 0) ? "s":""));
	}
	else if ( commcount ) {
		PrintToBanAdmins("%s%t", Prefix, "Comm Warning", client, commcount, ((commcount > 1 || commcount == 0) ? "s":""));
	}
	else if ( bancount ) {
		PrintToBanAdmins("%s%t", Prefix, "Ban Warning", client, bancount, ((bancount > 1 || bancount == 0) ? "s":""));
	}
}

public Action OnListSourceBansCmd(int client, int args)
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

	char targetarg[64];
	GetCmdArg(1, targetarg, sizeof(targetarg));

	int target = FindTarget(client, targetarg, true, true);
	if (target == INVALID_TARGET)
	{
		ReplyToCommand(client, "Error: Could not find a target matching '%s'.", targetarg);
		return Plugin_Handled;
	}

	char auth[32];
	if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth))
		 || auth[0] == 'B' || auth[9] == 'L')
	{
		ReplyToCommand(client, "Error: Could not retrieve %N's steam id.", target);
		return Plugin_Handled;
	}

	char query[1024], ip[30];
	GetClientIP(target, ip, sizeof(ip));
	FormatEx(query, sizeof(query), "SELECT created, %s_admins.user, ends, length, reason, RemoveType FROM %s_bans LEFT JOIN %s_admins ON %s_bans.aid = %s_admins.aid WHERE ((type = 0 AND %s_bans.authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, auth[8], ip);

	char targetName[MAX_NAME_LENGTH];
	GetClientName(target, targetName, sizeof(targetName));

	DataPack dataPack = new DataPack();
	dataPack.WriteCell((client == 0) ? 0 : GetClientUserId(client));
	dataPack.WriteString(targetName);

	g_DB.Query(OnListBans, query, dataPack, DBPrio_Low);

	if (client == 0)
	{
		ReplyToCommand(client, "%sNote: if you are using this command through an rcon tool, you will not see results.", Prefix);
	}
	else
	{
		ReplyToCommand(client, "\x04%s\x01 Look for %N's ban results in console.", Prefix, target);
	}

	return Plugin_Handled;
}

public void OnListBans(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	dataPack.Reset();
	int clientuid = dataPack.ReadCell();
	int client = GetClientOfUserId(clientuid);
	char targetName[MAX_NAME_LENGTH];
	dataPack.ReadString(targetName, sizeof(targetName));
	delete dataPack;

	if (clientuid > 0 && client == 0)
		return;

	if (results == null)
	{
		PrintListResponse(clientuid, client, "%sDB error while retrieving bans for %s:\n%s", Prefix, targetName, error);
		return;
	}

	if (results.RowCount == 0)
	{
		PrintListResponse(clientuid, client, "%sNo bans found for %s.", Prefix, targetName);
		return;
	}

	PrintListResponse(clientuid, client, "%sListing bans for %s", Prefix, targetName);
	PrintListResponse(clientuid, client, "Ban Date    Banned By   Length      End Date    R  Reason");
	PrintListResponse(clientuid, client, "-------------------------------------------------------------------------------");
	while (results.FetchRow())
	{
		char createddate[11] = "<Unknown> ";
		char bannedby[11] = "<Unknown> ";
		char lenstring[11] = "N/A       ";
		char enddate[11] = "N/A       ";
		char reason[28];
		char RemoveType[2] = " ";

		if (!results.IsFieldNull(0))
		{
			FormatTime(createddate, sizeof(createddate), "%Y-%m-%d", results.FetchInt(0));
		}

		if (!results.IsFieldNull(1))
		{
			int size_bannedby = sizeof(bannedby);
			results.FetchString(1, bannedby, size_bannedby);
			int len = results.FetchSize(1);
			if (len > size_bannedby - 1)
			{
				reason[size_bannedby - 4] = '.';
				reason[size_bannedby - 3] = '.';
				reason[size_bannedby - 2] = '.';
			}
			else
			{
				for (int i = len; i < size_bannedby - 1; i++)
				{
					bannedby[i] = ' ';
				}
			}
		}

		// NOT NULL
		int size_lenstring = sizeof(lenstring);
		int length = results.FetchInt(3);
		if (length == 0)
		{
			strcopy(lenstring, size_lenstring, "Permanent ");
		}
		else
		{
			int len = IntToString(length, lenstring, size_lenstring);
			if (len < size_lenstring - 1)
			{
				// change the '\0' to a ' '. the original \0 at the end will still be there
				lenstring[len] = ' ';
			}
		}

		if (!results.IsFieldNull(2))
		{
			FormatTime(enddate, sizeof(enddate), "%Y-%m-%d", results.FetchInt(2));
		}

		// NOT NULL
		int reason_size = sizeof(reason);
		results.FetchString(4, reason, reason_size);
		int len = results.FetchSize(4);
		if (len > reason_size - 1)
		{
			reason[reason_size - 4] = '.';
			reason[reason_size - 3] = '.';
			reason[reason_size - 2] = '.';
		}
		else
		{
			for (int i = len; i < reason_size - 1; i++)
			{
				reason[i] = ' ';
			}
		}

		if (!results.IsFieldNull(5))
		{
			results.FetchString(5, RemoveType, sizeof(RemoveType));
		}

		PrintListResponse(clientuid, client, "%s  %s  %s  %s  %s  %s", createddate, bannedby, lenstring, enddate, RemoveType, reason);
	}
}

public Action OnListSourceCommsCmd(int client, int args)
{
	if (args < 1)
	{
		ReplyToCommand(client, LISTCOMMS_USAGE);
	}

	if (g_DB == INVALID_HANDLE)
	{
		ReplyToCommand(client, "Error: Database not ready.");
		return Plugin_Handled;
	}

	char targetarg[64];
	GetCmdArg(1, targetarg, sizeof(targetarg));

	int target = FindTarget(client, targetarg, true, true);
	if (target == INVALID_TARGET)
	{
		ReplyToCommand(client, "Error: Could not find a target matching '%s'.", targetarg);
		return Plugin_Handled;
	}

	char auth[32];
	if (!GetClientAuthId(target, AuthId_Steam2, auth, sizeof(auth))
		 || auth[0] == 'B' || auth[9] == 'L')
	{
		ReplyToCommand(client, "Error: Could not retrieve %N's steam id.", target);
		return Plugin_Handled;
	}

	char query[1024];
	FormatEx(query, sizeof(query), "SELECT created, %s_admins.user, ends, length, reason, RemoveType, type FROM %s_comms LEFT JOIN %s_admins ON %s_comms.aid = %s_admins.aid WHERE %s_comms.authid REGEXP '^STEAM_[0-9]:%s$' AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, auth[8]);

	char targetName[MAX_NAME_LENGTH];
	GetClientName(target, targetName, sizeof(targetName));

	DataPack dataPack = new DataPack();
	dataPack.WriteCell((client == 0) ? 0 : GetClientUserId(client));
	dataPack.WriteString(targetName);

	g_DB.Query(OnListComms, query, dataPack, DBPrio_Low);

	if (client == 0)
	{
		ReplyToCommand(client, "%sNote: if you are using this command through an rcon tool, you will not see results.", Prefix);
	}
	else
	{
		ReplyToCommand(client, "\x04%s\x01 Look for %N's comm results in console.", Prefix, target);
	}

	return Plugin_Handled;
}

public void OnListComms(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	dataPack.Reset();
	int clientuid = dataPack.ReadCell();
	int client = GetClientOfUserId(clientuid);
	char targetName[MAX_NAME_LENGTH];
	dataPack.ReadString(targetName, sizeof(targetName));
	delete dataPack;

	if (clientuid > 0 && client == 0)
		return;

	if (results == null)
	{
		PrintListResponse(clientuid, client, "%sDB error while retrieving comms for %s:\n%s", Prefix, targetName, error);
		return;
	}

	if (results.RowCount == 0)
	{
		PrintListResponse(clientuid, client, "%sNo comms found for %s.", Prefix, targetName);
		return;
	}

	PrintListResponse(clientuid, client, "%sListing comms for %s", Prefix, targetName);
	PrintListResponse(clientuid, client, "Ban Date    Banned By   Length      End Date    T  R  Reason");
	PrintListResponse(clientuid, client, "-------------------------------------------------------------------------------");
	while (results.FetchRow())
	{
		char createddate[11] = "<Unknown> ";
		char bannedby[11] = "<Unknown> ";
		char lenstring[11] = "N/A       ";
		char enddate[11] = "N/A       ";
		char reason[23];
		char CommType[2] = " ";
		char RemoveType[2] = " ";

		if (!results.IsFieldNull(0))
		{
			FormatTime(createddate, sizeof(createddate), "%Y-%m-%d", results.FetchInt(0));
		}

		if (!results.IsFieldNull(1))
		{
			int size_bannedby = sizeof(bannedby);
			results.FetchString(1, bannedby, size_bannedby);
			int len = results.FetchSize(1);
			if (len > size_bannedby - 1)
			{
				reason[size_bannedby - 4] = '.';
				reason[size_bannedby - 3] = '.';
				reason[size_bannedby - 2] = '.';
			}
			else
			{
				for (int i = len; i < size_bannedby - 1; i++)
				{
					bannedby[i] = ' ';
				}
			}
		}

		// NOT NULL
		int size_lenstring = sizeof(lenstring);
		int length = results.FetchInt(3);
		if (length == 0)
		{
			strcopy(lenstring, size_lenstring, "Permanent ");
		}
		else
		{
			int len = IntToString(length, lenstring, size_lenstring);
			if (len < size_lenstring - 1)
			{
				// change the '\0' to a ' '. the original \0 at the end will still be there
				lenstring[len] = ' ';
			}
		}

		if (!results.IsFieldNull(2))
		{
			FormatTime(enddate, sizeof(enddate), "%Y-%m-%d", results.FetchInt(2));
		}

		// NOT NULL
		int reason_size = sizeof(reason);
		results.FetchString(4, reason, reason_size);
		int len = results.FetchSize(4);
		if (len > reason_size - 1)
		{
			reason[reason_size - 4] = '.';
			reason[reason_size - 3] = '.';
			reason[reason_size - 2] = '.';
		}
		else
		{
			for (int i = len; i < reason_size - 1; i++)
			{
				reason[i] = ' ';
			}
		}

		if (!results.IsFieldNull(5))
		{
			results.FetchString(5, RemoveType, sizeof(RemoveType));
		}
		// NOT NULL
		results.FetchString(6, CommType, sizeof(RemoveType));
		if(StrEqual(CommType,"1"))
			strcopy(CommType, sizeof(CommType), "M");
		if(StrEqual(CommType,"2"))
			strcopy(CommType, sizeof(CommType), "G");

		PrintListResponse(clientuid, client, "%s  %s  %s  %s  %s  %s  %s", createddate, bannedby, lenstring, enddate, CommType, RemoveType, reason);
	}
}

void PrintListResponse(int userid, int client, const char[] format, any ...)
{
	char msg[192];
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

void PrintToBanAdmins(const char[] format, any ...)
{
	char msg[256];

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && !IsFakeClient(i) && CheckCommandAccess(i, "sm_listsourcebans", ADMFLAG_GENERIC))
		{
			SetGlobalTransTarget(i);
			VFormat(msg, sizeof(msg), format, 2);
			PrintToChat(i, "%s", msg);
		}
	}
}

stock void ReadConfig()
{
	InitializeConfigParser();

	if (g_ConfigParser == null)
	{
		return;
	}

	char ConfigFile[PLATFORM_MAX_PATH];
	BuildPath(Path_SM, ConfigFile, sizeof(ConfigFile), "configs/sourcebans/sourcebans.cfg");

	if (FileExists(ConfigFile))
	{
		InternalReadConfig(ConfigFile);
	}
	else
	{
		char Error[PLATFORM_MAX_PATH + 64];
		FormatEx(Error, sizeof(Error), "FATAL *** ERROR *** can not find %s", ConfigFile);
		SetFailState(Error);
	}
}

static void InitializeConfigParser()
{
	if (g_ConfigParser == null)
	{
		g_ConfigParser = new SMCParser();
		g_ConfigParser.OnEnterSection = ReadConfig_NewSection;
		g_ConfigParser.OnKeyValue = ReadConfig_KeyValue;
		g_ConfigParser.OnLeaveSection = ReadConfig_EndSection;
	}
}

static void InternalReadConfig(const char[] path)
{
	SMCError err = g_ConfigParser.ParseFile(path);

	if (err != SMCError_Okay)
	{
		char buffer[64];
		PrintToServer("%s", g_ConfigParser.GetErrorString(err, buffer, sizeof(buffer)) ? buffer : "Fatal parse error");
	}
}

public SMCResult ReadConfig_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
{
	return SMCParse_Continue;
}

public SMCResult ReadConfig_KeyValue(SMCParser smc, const char[] key, const char[] value, bool key_quotes, bool value_quotes)
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

public SMCResult ReadConfig_EndSection(SMCParser smc)
{
	return SMCParse_Continue;
}
