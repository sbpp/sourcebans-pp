// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>
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
#include <sourcemod>
#undef REQUIRE_PLUGIN
#include <sourcebans>

#define PLUGIN_VERSION "1.6.2"

#define LENGTH_ORIGINAL 1
#define LENGTH_CUSTOM 2
#define LENGTH_DOUBLE 3
#define LENGTH_NOTIFY 4

//- Handles -//
new Handle:hDatabase = INVALID_HANDLE;
new Handle:g_hAllowedArray = INVALID_HANDLE;

//- ConVars -//
ConVar g_cVar_actions;
ConVar g_cVar_banduration;
ConVar g_cVar_sbprefix;
ConVar g_cVar_bansAllowed;
ConVar g_cVar_bantype;
ConVar g_cVar_bypass;

//- Bools -//
new bool:CanUseSourcebans = false;

public Plugin:myinfo =
{
	name = "SourceBans++: SourceSleuth",
	author = "ecca, SourceBans++ Dev Team",
	description = "Useful for TF2 servers. Plugin will check for banned ips and ban the player.",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public OnPluginStart()
{
	LoadTranslations("sourcesleuth.phrases");

	CreateConVar("sm_sourcesleuth_version", PLUGIN_VERSION, "SourceSleuth plugin version", FCVAR_SPONLY | FCVAR_REPLICATED | FCVAR_NOTIFY | FCVAR_DONTRECORD);

	g_cVar_actions = CreateConVar("sm_sleuth_actions", "3", "Sleuth Ban Type: 1 - Original Length, 2 - Custom Length, 3 - Double Length, 4 - Notify Admins Only", 0, true, 1.0, true, 4.0);
	g_cVar_banduration = CreateConVar("sm_sleuth_duration", "0", "Required: sm_sleuth_actions 1: Bantime to ban player if we got a match (0 = permanent (defined in minutes) )", 0);
	g_cVar_sbprefix = CreateConVar("sm_sleuth_prefix", "sb", "Prexfix for sourcebans tables: Default sb", 0);
	g_cVar_bansAllowed = CreateConVar("sm_sleuth_bansallowed", "0", "How many active bans are allowed before we act", 0);
	g_cVar_bantype = CreateConVar("sm_sleuth_bantype", "0", "0 - ban all type of lengths, 1 - ban only permanent bans", 0, true, 0.0, true, 1.0);
	g_cVar_bypass = CreateConVar("sm_sleuth_adminbypass", "0", "0 - Inactivated, 1 - Allow all admins with ban flag to pass the check", 0, true, 0.0, true, 1.0);

	g_hAllowedArray = CreateArray(256);

	AutoExecConfig(true, "Sm_SourceSleuth");

	SQL_TConnect(SQL_OnConnect, "sourcebans");

	RegAdminCmd("sm_sleuth_reloadlist", ReloadListCallBack, ADMFLAG_ROOT);

	LoadWhiteList();
}

public OnAllPluginsLoaded()
{
	CanUseSourcebans = LibraryExists("sourcebans");
}

public OnLibraryAdded(const String:name[])
{
	if (StrEqual("sourcebans", name))
	{
		CanUseSourcebans = true;
	}
}

public OnLibraryRemoved(const String:name[])
{
	if (StrEqual("sourcebans", name))
	{
		CanUseSourcebans = false;
	}
}

public SQL_OnConnect(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		LogError("SourceSleuth: Database connection error: %s", error);
	}
	else
	{
		hDatabase = hndl;
	}
}

public Action:ReloadListCallBack(client, args)
{
	ClearArray(g_hAllowedArray);

	LoadWhiteList();

	LogMessage("%L reloaded the whitelist", client);

	if (client != 0)
	{
		PrintToChat(client, "[SourceSleuth] WhiteList has been reloaded!");
	}

	return Plugin_Continue;
}

public OnClientPostAdminCheck(client)
{
	if (CanUseSourcebans && !IsFakeClient(client))
	{
		new String:steamid[32];
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));

		if (g_cVar_bypass.BoolValue && CheckCommandAccess(client, "sleuth_admin", ADMFLAG_BAN, false))
		{
			return;
		}

		if (FindStringInArray(g_hAllowedArray, steamid) == -1)
		{
			new String:IP[32], String:Prefix[64];
			GetClientIP(client, IP, sizeof(IP));

			g_cVar_sbprefix.GetString(Prefix, sizeof(Prefix));

			new String:query[1024];

			FormatEx(query, sizeof(query), "SELECT * FROM %s_bans WHERE ip='%s' AND RemoveType IS NULL AND (ends > %d OR length = 0)", Prefix, IP, g_cVar_bantype.IntValue == 0 ? GetTime() : 0);

			new Handle:datapack = CreateDataPack();

			WritePackCell(datapack, GetClientUserId(client));
			WritePackString(datapack, steamid);
			WritePackString(datapack, IP);
			ResetPack(datapack);

			SQL_TQuery(hDatabase, SQL_CheckHim, query, datapack);
		}
	}
}

public SQL_CheckHim(Handle:owner, Handle:hndl, const String:error[], any:datapack)
{
	new client;
	decl String:steamid[32], String:IP[32];

	if (datapack != INVALID_HANDLE)
	{
		client = GetClientOfUserId(ReadPackCell(datapack));
		ReadPackString(datapack, steamid, sizeof(steamid));
		ReadPackString(datapack, IP, sizeof(IP));
		CloseHandle(datapack);
	}

	if (hndl == INVALID_HANDLE)
	{
		LogError("SourceSleuth: Database query error: %s", error);
		return;
	}

	if (SQL_FetchRow(hndl))
	{
		new TotalBans = SQL_GetRowCount(hndl);

		if (TotalBans > g_cVar_bansAllowed.IntValue)
		{
			switch (g_cVar_actions.IntValue)
			{
				case LENGTH_ORIGINAL:
				{
					new length = SQL_FetchInt(hndl, 6);
					new time = length * 60;

					BanPlayer(client, time);
				}
				case LENGTH_CUSTOM:
				{
					new time = g_cVar_banduration.IntValue;
					BanPlayer(client, time);
				}
				case LENGTH_DOUBLE:
				{
					new length = SQL_FetchInt(hndl, 6);

					new time = 0;

					if (length != 0)
					{
						time = length / 60 * 2;
					}

					BanPlayer(client, time);
				}
				case LENGTH_NOTIFY:
				{
					/* Notify Admins when a client with an ip on the bans list connects */
					PrintToAdmins("[SourceSleuth] %t", "sourcesleuth_admintext", client, steamid, IP);
				}
			}
		}
	}
}

stock BanPlayer(client, time)
{
	decl String:Reason[255];
	Format(Reason, sizeof(Reason), "[SourceSleuth] %t", "sourcesleuth_banreason");
	SourceBans_BanPlayer(0, client, time, Reason);
}

PrintToAdmins(const String:format[], any:...)
{
	new String:g_Buffer[256];

	for (new i = 1; i <= MaxClients; i++)
	{
		if (CheckCommandAccess(i, "sm_sourcesleuth_printtoadmins", ADMFLAG_BAN) && IsClientInGame(i))
		{
			VFormat(g_Buffer, sizeof(g_Buffer), format, 2);

			PrintToChat(i, "%s", g_Buffer);
		}
	}
}

public LoadWhiteList()
{
	decl String:path[PLATFORM_MAX_PATH], String:line[256];

	BuildPath(Path_SM, path, PLATFORM_MAX_PATH, "configs/sourcesleuth_whitelist.cfg");

	new Handle:fileHandle = OpenFile(path, "r");

	if (fileHandle == INVALID_HANDLE)
	{
		LogError("Could not find the config file (addons/sourcemod/configs/sourcesleuth_whitelist.cfg)");

		return;
	}

	while (!IsEndOfFile(fileHandle) && ReadFileLine(fileHandle, line, sizeof(line)))
	{
		ReplaceString(line, sizeof(line), "\n", "", false);

		PushArrayString(g_hAllowedArray, line);
	}

	CloseHandle(fileHandle);
}
