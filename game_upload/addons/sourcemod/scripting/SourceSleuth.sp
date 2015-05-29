// *************************************************************************
//  This file is part of SourceBans (FORK).
//
//  Copyright (C) 2014-2015 Sarabveer Singh <sarabveer@sarabveer.me>
//  
//  SourceBans (FORK) is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//  
//  SourceBans (FORK) is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//  
//  You should have received a copy of the GNU Affero General Public License
//  along with SourceBans (FORK).  If not, see <http://www.gnu.org/licenses/>.
//
//  This file incorporates work covered by the following copyright:  
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

#define PLUGIN_VERSION "SB-1.5.2F"

//- Handles -//
new Handle:hDatabase = INVALID_HANDLE;
new Handle:g_cVar_actions = INVALID_HANDLE;
new Handle:g_cVar_banduration = INVALID_HANDLE;
new Handle:g_cVar_sbprefix = INVALID_HANDLE;
new Handle:g_cVar_bansAllowed = INVALID_HANDLE;
new Handle:g_cVar_bantype = INVALID_HANDLE;
new Handle:g_cVar_bypass = INVALID_HANDLE;
new Handle:g_hAllowedArray = INVALID_HANDLE;

//- Bools -//
new bool:CanUseSourcebans = false;

public Plugin:myinfo = 
{
	name	= "SourceSleuth",
	author	= "ecca, Sarabveer(VEER™)",
	description= "Useful for TF2 servers. Plugin will check for banned ips and ban the player.",
	version	= PLUGIN_VERSION,
	url		= "http://sourcemod.net"
};

public OnPluginStart()
{
	LoadTranslations("sourcesleuth.phrases");
	
	CreateConVar("sm_sourcesleuth_version", PLUGIN_VERSION, "SourceSleuth plugin version", FCVAR_PLUGIN|FCVAR_SPONLY|FCVAR_REPLICATED|FCVAR_NOTIFY|FCVAR_DONTRECORD);
	
	g_cVar_actions = CreateConVar("sm_sleuth_actions", "3", "Sleuth Ban Type: 1 - Original Length, 2 - Custom Length, 3 - Double Length, 4 - Notify Admins Only", FCVAR_PLUGIN, true, 1.0, true, 4.0);
	g_cVar_banduration = CreateConVar("sm_sleuth_duration", "0", "Required: sm_sleuth_actions 1: Bantime to ban player if we got a match (0 = permanent (defined in minutes) )", FCVAR_PLUGIN);
	g_cVar_sbprefix = CreateConVar("sm_sleuth_prefix", "sb", "Prexfix for sourcebans tables: Default sb", FCVAR_PLUGIN);
	g_cVar_bansAllowed = CreateConVar("sm_sleuth_bansallowed", "0", "How many active bans are allowed before we act", FCVAR_PLUGIN);
	g_cVar_bantype = CreateConVar("sm_sleuth_bantype", "0", "0 - ban all type of lengths, 1 - ban only permanent bans", FCVAR_PLUGIN, true, 0.0, true, 1.0);
	g_cVar_bypass = CreateConVar("sm_sleuth_adminbypass", "0", "0 - Inactivated, 1 - Allow all admins with ban flag to pass the check", FCVAR_PLUGIN, true, 0.0, true, 1.0);
	
	g_hAllowedArray = CreateArray(256);
	
	AutoExecConfig(true, "Sm_SourceSleuth");

	SQL_TConnect(SQL_OnConnect, "sourcebans");
	
	RegAdminCmd("sm_sleuth_reloadlist", ReloadListCallBack, ADMFLAG_ROOT);
	
	LoadWhiteList();
}

public OnAllPluginsLoaded()
{
	if (LibraryExists("sourcebans"))
	{
		CanUseSourcebans = true;
	}
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
	
	if(client != 0)
	{
		PrintToChat(client, "[SourceSleuth] WhiteList has been reloaded!");
	}
	
	return Plugin_Continue;
}

public OnClientPostAdminCheck(client)
{
	if(CanUseSourcebans && !IsFakeClient(client))
	{
		new String:steamid[32];
		GetClientAuthId(client, AuthId_Steam2, steamid, sizeof(steamid));
		
		if (GetConVarBool(g_cVar_bypass) && CheckCommandAccess(client, "sleuth_admin", ADMFLAG_BAN, false)) 
		{
			return;
		}
		
		if(FindStringInArray(g_hAllowedArray, steamid) == -1)
		{
			new String:IP[32], String:Prefix[64];
			GetClientIP(client, IP, sizeof(IP));
			GetConVarString(g_cVar_sbprefix, Prefix, sizeof(Prefix));
			
			new String:query[1024];
			
			if(GetConVarInt(g_cVar_bantype) == 0)
			{
				FormatEx(query, sizeof(query),  "SELECT * FROM %s_bans WHERE ip='%s' AND RemoveType IS NULL AND ends > %d", Prefix, IP, GetTime());
			}
			else
			{
				FormatEx(query, sizeof(query),  "SELECT * FROM %s_bans WHERE ip='%s' AND RemoveType IS NULL AND length='0'", Prefix, IP);
			}
			
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
	new String:steamid[32], String:IP[32], String:Reason[255], String:text[255];
	
	if(datapack != INVALID_HANDLE)
	{
		client = GetClientOfUserId(ReadPackCell(datapack));
		ReadPackString(datapack, steamid, sizeof(steamid));
		ReadPackString(datapack, IP, sizeof(IP));
		CloseHandle(datapack); 
	}
	
	if (hndl == INVALID_HANDLE)
	{
		LogError("SourceSleuth: Database query error: %s", error);
	}
	
	if (SQL_FetchRow(hndl))
	{
		new TotalBans = SQL_GetRowCount(hndl);
		
		if(TotalBans > GetConVarInt(g_cVar_bansAllowed))
		{
			switch (GetConVarInt(g_cVar_actions))
			{
				case 1:
				{
					new length = SQL_FetchInt(hndl, 6);
					new time = length*60;
					
					Format(Reason, sizeof(Reason), "[SourceSleuth] %t", "sourcesleuth_banreason");
					
					SBBanPlayer(0, client, time, Reason);
				}
				case 2:
				{
					new time = GetConVarInt(g_cVar_banduration);

					Format(Reason, sizeof(Reason), "[SourceSleuth] %t", "sourcesleuth_banreason");
					
					SBBanPlayer(0, client, time, Reason);
				}
				case 3:
				{
					new length = SQL_FetchInt(hndl, 6);
					new time = length/60*2;

					Format(Reason, sizeof(Reason), "[SourceSleuth] %t", "sourcesleuth_banreason");
					
					SBBanPlayer(0, client, time, Reason);
				}
				case 4:
				{
					Format(text, sizeof(text), "[SourceSleuth] %t", "sourcesleuth_admintext",client, steamid, IP);
					PrintToAdmins("%s", text);
				}
			}
		}
	}
}

PrintToAdmins(const String:format[], any:...)
{
	new String:g_Buffer[256];
	
	for (new i=1;i<=MaxClients;i++)
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

	while(!IsEndOfFile(fileHandle) && ReadFileLine(fileHandle, line, sizeof(line)))
	{
  		ReplaceString(line, sizeof(line), "\n", "", false);

		PushArrayString(g_hAllowedArray, line);
	}

	CloseHandle(fileHandle);
}