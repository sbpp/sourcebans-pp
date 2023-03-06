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
//   SourceSleuth 1.3 fix
//   Copyright (C) 2013-2015 ecca
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1818793> - <https://github.com/ecca/SourceMod-Plugins>
//
// *************************************************************************

#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>
#undef REQUIRE_PLUGIN
#include <sourcebanspp>

#define PLUGIN_VERSION "1.8.0"

#define LENGTH_ORIGINAL 1
#define LENGTH_CUSTOM 2
#define LENGTH_DOUBLE 3
#define LENGTH_NOTIFY 4
#define LENGTH_KICK 5

#define PREFIX "[SourceSleuth] "

//- Handles -//
Database hDatabase = null;
ArrayList g_hAllowedArray = null;

//- ConVars -//
ConVar g_cVar_actions;
ConVar g_cVar_banduration;
ConVar g_cVar_sbprefix;
ConVar g_cVar_bansAllowed;
ConVar g_cVar_bantype;
ConVar g_cVar_bypass;
ConVar g_cVar_excludeOld;
ConVar g_cVar_excludeTime;

//- Bools -//
bool CanUseSourcebans = false;

public Plugin myinfo =
{
	name = "SourceBans++: SourceSleuth",
	author = "ecca, SourceBans++ Dev Team",
	description = "Useful for TF2 servers. Plugin will check for banned ips and ban the player.",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public void OnPluginStart()
{
	LoadTranslations("sbpp_sleuth.phrases");

	CreateConVar("sm_sourcesleuth_version", PLUGIN_VERSION, "SourceSleuth plugin version", FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY | FCVAR_DONTRECORD);

	g_cVar_actions = CreateConVar("sm_sleuth_actions", "3", "Sleuth Ban Type: 1 - Original Length, 2 - Custom Length, 3 - Double Length, 4 - Notify Admins Only, 5 - Kick Client", 0, true, 1.0, true, 5.0);
	g_cVar_banduration = CreateConVar("sm_sleuth_duration", "0", "Required: sm_sleuth_actions 1: Bantime to ban player if we got a match (0 = permanent (defined in minutes) )", 0);
	g_cVar_sbprefix = CreateConVar("sm_sleuth_prefix", "sb", "Prexfix for sourcebans tables: Default sb", 0);
	g_cVar_bansAllowed = CreateConVar("sm_sleuth_bansallowed", "0", "How many active bans are allowed before we act", 0);
	g_cVar_bantype = CreateConVar("sm_sleuth_bantype", "0", "0 - ban all type of lengths, 1 - ban only permanent bans", 0, true, 0.0, true, 1.0);
	g_cVar_bypass = CreateConVar("sm_sleuth_adminbypass", "0", "0 - Inactivated, 1 - Allow all admins with ban flag to pass the check", 0, true, 0.0, true, 1.0);
	g_cVar_excludeOld = CreateConVar("sm_sleuth_excludeold", "0", "0 - Inactivated, 1 - Allow old bans to be excluded from ban check", 0, true, 0.0, true, 1.0);
	g_cVar_excludeTime = CreateConVar("sm_sleuth_excludetime", "31536000", "Amount of time in seconds to allow old bans to be excluded from ban check", 0, true, 1.0, false);

	g_hAllowedArray = new ArrayList(256);

	AutoExecConfig(true, "Sm_SourceSleuth");

	Database.Connect(SQL_OnConnect, "sourcebans");

	RegAdminCmd("sm_sleuth_reloadlist", ReloadListCallBack, ADMFLAG_ROOT);

	LoadWhiteList();
}

public void OnAllPluginsLoaded()
{
	CanUseSourcebans = LibraryExists("sourcebans++");
}

public void OnLibraryAdded(const char[] name)
{
	if (StrEqual("sourcebans++", name))
	{
		CanUseSourcebans = true;
	}
}

public void OnLibraryRemoved(const char[] name)
{
	if (StrEqual("sourcebans++", name))
	{
		CanUseSourcebans = false;
	}
}

public void SQL_OnConnect(Database db, const char[] error, any data)
{
	if (db == null)
	{
		LogError("SourceSleuth: Database connection error: %s", error);
	}
	else
	{
		hDatabase = db;
	}
}

public Action ReloadListCallBack(int client, int args)
{
	g_hAllowedArray.Clear();

	LoadWhiteList();

	LogMessage("%L reloaded the whitelist", client);

	if (client != 0)
	{
		PrintToChat(client, "%sWhiteList has been reloaded!", PREFIX);
	}

	return Plugin_Continue;
}

public void OnClientPostAdminCheck(int client)
{
	if (CanUseSourcebans && !IsFakeClient(client))
	{
		char steamid[32];
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));

		if (g_cVar_bypass.BoolValue && CheckCommandAccess(client, "sleuth_admin", ADMFLAG_BAN, false))
		{
			return;
		}

		if (g_hAllowedArray.FindString(steamid) == -1)
		{
			char IP[32], Prefix[64];
			GetClientIP(client, IP, sizeof(IP));

			g_cVar_sbprefix.GetString(Prefix, sizeof(Prefix));

			char query[1024];

			FormatEx(query, sizeof(query), "SELECT * FROM %s_bans WHERE ip='%s' AND RemoveType IS NULL AND (ends > %d OR ((1 = %d AND length = 0 AND ends > %d) OR (0 = %d AND length = 0)))", Prefix, IP, g_cVar_bantype.IntValue == 0 ? GetTime() : 0, g_cVar_excludeOld.IntValue, GetTime() - g_cVar_excludeTime.IntValue, g_cVar_excludeOld.IntValue);

			DataPack datapack = new DataPack();

			datapack.WriteCell(GetClientUserId(client));
			datapack.WriteString(steamid);
			datapack.WriteString(IP);
			datapack.Reset();

			hDatabase.Query(SQL_CheckHim, query, datapack);
		}
	}
}

public void SQL_CheckHim(Database db, DBResultSet results, const char[] error, DataPack dataPack)
{
	int client;
	char steamid[32], IP[32];

	client = GetClientOfUserId(ReadPackCell(dataPack));
	dataPack.ReadString(steamid, sizeof(steamid));
	dataPack.ReadString(IP, sizeof(IP));
	delete dataPack;

	if (results == null)
	{
		LogError("SourceSleuth: Database query error: %s", error);
		return;
	}

	if (results.FetchRow())
	{
		int TotalBans = results.RowCount;

		if (TotalBans > g_cVar_bansAllowed.IntValue)
		{
			switch (g_cVar_actions.IntValue)
			{
				case LENGTH_ORIGINAL:
				{
					int length = results.FetchInt(6);
					int time = length / 60;

					BanPlayer(client, time);
				}
				case LENGTH_CUSTOM:
				{
					int time = g_cVar_banduration.IntValue;
					BanPlayer(client, time);
				}
				case LENGTH_DOUBLE:
				{
					int length = results.FetchInt(6);

					int time = 0;

					if (length != 0)
					{
						time = length / 60 * 2;
					}

					BanPlayer(client, time);
				}
				case LENGTH_NOTIFY:
				{
					/* Notify Admins when a client with an ip on the bans list connects */
					PrintToAdmins("%s%t", PREFIX, "sourcesleuth_admintext", client, steamid, IP);
				}
				case LENGTH_KICK:
				{
					KickClient(client, "%s%t", PREFIX, "sourcesleuth_kicktext");
				}
			}
		}
	}
}

stock void BanPlayer(int client, int time)
{
	char Reason[255];
	Format(Reason, sizeof(Reason), "%s%T", PREFIX, "sourcesleuth_banreason", client);
	SBPP_BanPlayer(0, client, time, Reason);
}

void PrintToAdmins(const char[] format, any ...)
{
	char g_Buffer[256];

	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i) && CheckCommandAccess(i, "sm_sourcesleuth_printtoadmins", ADMFLAG_BAN))
		{
			SetGlobalTransTarget(i);

			VFormat(g_Buffer, sizeof(g_Buffer), format, 2);

			PrintToChat(i, "%s", g_Buffer);
		}
	}
}

public void LoadWhiteList()
{
	char path[PLATFORM_MAX_PATH], line[256];

	BuildPath(Path_SM, path, PLATFORM_MAX_PATH, "configs/sourcebans/sourcesleuth_whitelist.cfg");

	File fileHandle = OpenFile(path, "r");

	if (fileHandle == null)
	{
		LogError("Could not find the config file (%s)", path);

		return;
	}

	while (!fileHandle.EndOfFile() && fileHandle.ReadLine(line, sizeof(line)))
	{
		ReplaceString(line, sizeof(line), "\n", "", false);

		g_hAllowedArray.PushString(line);
	}

	delete fileHandle;
}
