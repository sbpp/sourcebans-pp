// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2024 SourceBans++ Dev Team <https://github.com/sbpp>
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

// sm_sleuth_sdr_mode values. Steam Datagram Relay proxies a player's traffic
// through a Valve POP, so the IP this server sees is a relay address (or a
// 0.0.0.0 / 169.254/16 placeholder), not the player's real IP. The IP-based
// alt-account match Sleuth performs on connect is therefore unreliable for
// SDR clients (#1035, #1110).
#define SDR_MODE_OFF      0  // Legacy behaviour: no SDR detection, IP match runs unconditionally.
#define SDR_MODE_FALLBACK 1  // Default. Detect SDR; skip IP match; log + notify admins so they know.
#define SDR_MODE_BLOCK    2  // Refuse the connection outright. For ops who need strict IP enforcement.

#define PREFIX "[SourceSleuth] "

//- Handles -//
Database hDatabase = null;
ArrayList g_hAllowedArray = null;
// SDR IPv4 ranges. Each item is a 2-cell row [networkInt, maskInt]. Loaded
// from configs/sourcebans/sourcesleuth_sdr.cfg, with 169.254.0.0/16 (the
// link-local FakeIP block Valve uses for SDR clients) added unconditionally
// as a safety net so a missing or empty config file still catches the most
// obvious SDR signal. See LoadSdrRanges().
ArrayList g_hSdrRanges = null;

//- ConVars -//
ConVar g_cVar_actions;
ConVar g_cVar_banduration;
ConVar g_cVar_sbprefix;
ConVar g_cVar_bansAllowed;
ConVar g_cVar_bantype;
ConVar g_cVar_bypass;
ConVar g_cVar_excludeOld;
ConVar g_cVar_excludeTime;
ConVar g_cVar_sdrMode;

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
	g_cVar_sdrMode = CreateConVar("sm_sleuth_sdr_mode", "1", "Steam Datagram Relay handling: 0 - off (legacy IP-only matching), 1 - fallback (skip IP match for SDR clients, log + notify admins), 2 - block (kick SDR clients). Ranges live in configs/sourcebans/sourcesleuth_sdr.cfg.", 0, true, 0.0, true, 2.0);

	g_hAllowedArray = new ArrayList(256);
	g_hSdrRanges = new ArrayList(2);

	AutoExecConfig(true, "Sm_SourceSleuth");

	Database.Connect(SQL_OnConnect, "sourcebans");

	RegAdminCmd("sm_sleuth_reloadlist", ReloadListCallBack, ADMFLAG_ROOT);

	LoadWhiteList();
	LoadSdrRanges();
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
	g_hSdrRanges.Clear();

	LoadWhiteList();
	LoadSdrRanges();

	LogMessage("%L reloaded the whitelist and SDR ranges", client);

	if (client != 0)
	{
		PrintToChat(client, "%sWhiteList and SDR ranges have been reloaded!", PREFIX);
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

			int sdrMode = g_cVar_sdrMode.IntValue;
			if (sdrMode != SDR_MODE_OFF && IsSdrIP(IP))
			{
				if (sdrMode == SDR_MODE_BLOCK)
				{
					LogMessage("%sSDR detected for %L (reported address %s) -- connection blocked (sm_sleuth_sdr_mode=2)", PREFIX, client, IP);
					KickClient(client, "%s%t", PREFIX, "sourcesleuth_sdr_kicktext");
					return;
				}

				// SDR_MODE_FALLBACK: the IP-based ban lookup we'd run next is
				// unreliable for relay-routed clients, so skip it and surface
				// the skip clearly. OnClientPostAdminCheck fires once per
				// connection, which is the rate limit the issue calls for.
				// We deliberately do NOT silently fall through to the IP query;
				// matching a relay IP against the bans table would either
				// false-positive (ban every player from that POP) or
				// false-negative (the original ban was on a real IP).
				LogMessage("%sSDR detected for %L (reported address %s) -- IP-based alt detection skipped (sm_sleuth_sdr_mode=1)", PREFIX, client, IP);
				PrintToAdmins("%s%t", PREFIX, "sourcesleuth_sdr_admintext", client, steamid);
				return;
			}

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

// Loads SDR IPv4 ranges from configs/sourcebans/sourcesleuth_sdr.cfg. The file
// is the source of truth (and ships with the plugin tarball as a snapshot of
// Valve's GetSDRConfig endpoint -- see the file's header for the regen
// command). 169.254.0.0/16 is added unconditionally as a safety net so that
// even a missing or empty file still catches the FakeIP placeholders SDR
// clients are commonly identified by.
//
// Trade-off: a static list will rot as Valve adds POPs. Runtime fetch from
// GetSDRConfig would always be current but adds an HTTP-extension dependency
// the plugin doesn't have today. We chose the static + reloadable file so
// ops can refresh without recompiling; sm_sleuth_reloadlist re-reads it.
void LoadSdrRanges()
{
	int net, mask;
	if (ParseCIDR("169.254.0.0/16", net, mask))
	{
		int row[2];
		row[0] = net;
		row[1] = mask;
		g_hSdrRanges.PushArray(row, sizeof(row));
	}

	char path[PLATFORM_MAX_PATH], line[256];
	BuildPath(Path_SM, path, PLATFORM_MAX_PATH, "configs/sourcebans/sourcesleuth_sdr.cfg");

	File fileHandle = OpenFile(path, "r");
	if (fileHandle == null)
	{
		LogError("%sSDR ranges file not found (%s) -- only the link-local FakeIP range (169.254.0.0/16) will be detected. Install the bundled config from the SourceBans++ release tarball.", PREFIX, path);
		return;
	}

	int loaded = 0;
	while (!fileHandle.EndOfFile() && fileHandle.ReadLine(line, sizeof(line)))
	{
		ReplaceString(line, sizeof(line), "\n", "", false);
		ReplaceString(line, sizeof(line), "\r", "", false);
		TrimString(line);

		if (line[0] == '\0' || (line[0] == '/' && line[1] == '/') || line[0] == '#')
		{
			continue;
		}

		int cidrNet, cidrMask;
		if (!ParseCIDR(line, cidrNet, cidrMask))
		{
			LogError("%sIgnoring malformed SDR range '%s' in %s", PREFIX, line, path);
			continue;
		}

		int row[2];
		row[0] = cidrNet;
		row[1] = cidrMask;
		g_hSdrRanges.PushArray(row, sizeof(row));
		loaded++;
	}

	delete fileHandle;
	LogMessage("%sLoaded %d SDR range(s) from %s (plus the built-in 169.254.0.0/16 FakeIP range)", PREFIX, loaded, path);
}

// True if the address looks like it came through Steam Datagram Relay:
// either it falls in one of the configured relay/FakeIP ranges, or it doesn't
// parse as IPv4 at all (CS2 commonly reports "0.0.0.0" or a non-IP placeholder
// for SDR clients -- see #1035).
bool IsSdrIP(const char[] ip)
{
	int ipInt;
	if (!ParseIPv4(ip, ipInt))
	{
		return true;
	}

	if (ipInt == 0)
	{
		return true;
	}

	int n = g_hSdrRanges.Length;
	for (int i = 0; i < n; i++)
	{
		int net = g_hSdrRanges.Get(i, 0);
		int mask = g_hSdrRanges.Get(i, 1);
		if ((ipInt & mask) == net)
		{
			return true;
		}
	}

	return false;
}

// Parses "A.B.C.D" or "A.B.C.D/N" (N in 0..32, default 32). Writes the
// network address (host-byte-order int) and mask to the out-params. Returns
// false on any parse failure; the network is always (rawIP & mask) so callers
// can compare directly with (clientIP & mask).
bool ParseCIDR(const char[] cidr, int &outNet, int &outMask)
{
	char buf[48];
	strcopy(buf, sizeof(buf), cidr);

	int prefixLen = 32;
	int slashIdx = StrContains(buf, "/");
	if (slashIdx > -1)
	{
		prefixLen = StringToInt(buf[slashIdx + 1]);
		buf[slashIdx] = '\0';
	}

	if (prefixLen < 0 || prefixLen > 32)
	{
		return false;
	}

	int ip;
	if (!ParseIPv4(buf, ip))
	{
		return false;
	}

	if (prefixLen == 0)
	{
		outMask = 0;
	}
	else
	{
		// -1 << (32 - N) gives the high-N-bit mask. Special-cased above for
		// N=0 because shifting by the full word width is undefined.
		outMask = -1 << (32 - prefixLen);
	}
	outNet = ip & outMask;
	return true;
}

// Parses a dotted-quad IPv4 string into a host-byte-order int. Strict: every
// octet must be 1-3 ASCII digits in 0..255, exactly four octets, no extras.
bool ParseIPv4(const char[] s, int &outIP)
{
	char parts[4][8];
	int n = ExplodeString(s, ".", parts, sizeof(parts), sizeof(parts[]));
	if (n != 4)
	{
		return false;
	}

	int ip = 0;
	for (int i = 0; i < 4; i++)
	{
		TrimString(parts[i]);
		int len = strlen(parts[i]);
		if (len == 0 || len > 3)
		{
			return false;
		}
		for (int j = 0; j < len; j++)
		{
			if (!IsCharNumeric(parts[i][j]))
			{
				return false;
			}
		}
		int v = StringToInt(parts[i]);
		if (v < 0 || v > 255)
		{
			return false;
		}
		ip = (ip << 8) | v;
	}

	outIP = ip;
	return true;
}
