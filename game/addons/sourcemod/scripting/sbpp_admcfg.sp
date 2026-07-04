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
//   SourceMod Admin File Reader Plugin
//   Copyright (C) 2004-2008 AlliedModders LLC
//   Licensed under GNU GPL version 3
//   Page: <http://www.sourcemod.net/>
//
// *************************************************************************

#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>

public Plugin myinfo =
{
	name = "SourceBans++: Admin Config Loader",
	author = "AlliedModders LLC, SourceBans++ Dev Team",
	description = "Reads Admin Files",
	version = "1.8.0",
	url = "https://sbpp.github.io"
};


/** Various parsing globals */
bool g_LoggedFileName = false; /* Whether or not the file name has been logged */
int g_ErrorCount = 0; /* Current error count */
int g_IgnoreLevel = 0; /* Nested ignored section count, so users can screw up files safely */
int g_CurrentLine = 0; /* Current line we're on */
char g_Filename[PLATFORM_MAX_PATH]; /* Used for error messages */

/* Maps a lowercased server-group name to its GroupId. Populated while the
 * groups config is parsed and consulted as the case-insensitive fallback in
 * FindAdmGroupInsensitive (issue #1503). Declared before the sub-file includes
 * so ReadGroups() (which fills it) can see it. */
StringMap g_GroupNameMap = null;

#include "sbpp_admcfg/sbpp_admin_groups.sp"
#include "sbpp_admcfg/sbpp_admin_users.sp"

public void OnRebuildAdminCache(AdminCachePart part)
{
	if (part == AdminCache_Groups) {
		ReadGroups();
	} else if (part == AdminCache_Admins) {
		ReadUsers();
	}
}

public void OnPluginEnd()
{
	// SourceMod reclaims the handle on unload anyway; released explicitly for
	// hygiene so the case-insensitive group map doesn't linger.
	delete g_GroupNameMap;
}

void ParseError(const char[] format, any...)
{
	char buffer[512];

	if (!g_LoggedFileName)
	{
		LogError("Error(s) Detected Parsing %s", g_Filename);
		g_LoggedFileName = true;
	}

	VFormat(buffer, sizeof(buffer), format, 2);

	LogError(" (line %d) %s", g_CurrentLine, buffer);

	g_ErrorCount++;
}

void InitGlobalStates()
{
	g_ErrorCount = 0;
	g_IgnoreLevel = 0;
	g_CurrentLine = 0;
	g_LoggedFileName = false;
}

// Copy src into dest, lowercasing every character. Used to build/consult the
// case-insensitive group-name map keys (StringMap keys are case-sensitive).
// CharToLower is ASCII-only, which is fine here: the case drift this fixes is
// ASCII (e.g. "Admin" vs "admin"), and any multibyte bytes pass through
// unchanged and identically on both the register and lookup sides, so a
// non-ASCII name still maps to itself.
void LowercaseCopy(char[] dest, int maxlength, const char[] src)
{
	if (maxlength <= 0)
	{
		return;
	}

	int i = 0;
	for (; i < maxlength - 1 && src[i] != '\0'; i++)
	{
		dest[i] = CharToLower(src[i]);
	}
	dest[i] = '\0';
}

// Record a parsed server-group name so FindAdmGroupInsensitive can resolve a
// later reference to it regardless of case. Called as each group section is
// created while reading sb_admin_groups.cfg. The key is the case-folded name,
// so if two group sections differ only in case ("Admin" and "admin") they map
// to one key and the last-registered id wins in the case-folded fallback
// keyspace. That collision only affects references that miss the exact-case
// FindAdmGroup below; SourceMod itself keeps such groups distinct.
void RegisterGroupName(const char[] group_name, GroupId id)
{
	if (g_GroupNameMap == null || id == INVALID_GROUP_ID)
	{
		return;
	}

	// srvgroups.name is varchar(120); the generator fetches it into a
	// 128-char buffer, so size to match and avoid truncating long names.
	char lower[128];
	LowercaseCopy(lower, sizeof(lower), group_name);
	g_GroupNameMap.SetValue(lower, id);
}

// Resolve an admin-group name to its GroupId, tolerating case differences.
//
// SourceMod's admin cache matches group names case-sensitively (both
// FindAdmGroup and CreateAdmGroup key off a case-sensitive map) and exposes no
// native to enumerate loaded groups, so we track the names ourselves as the
// groups config is parsed. The SourceBans++ config files (sb_admin_groups.cfg /
// sb_admins.cfg) are generated from a database where a group's name can drift
// in case between the group definition and an admin's group reference: legacy
// AMXBans / SourceBans imports, manual DB edits, case-sensitive DB collations,
// or an older generator build that copied the admin's raw srv_group string
// instead of the canonical srvgroups.name. A group defined as "admin" but
// referenced as "Admin" would otherwise fail with a spurious "Unknown group"
// error and silently drop the admin's group inheritance. Try the fast
// exact-case lookup first (covers groups created by any plugin), then fall back
// to the case-folded map (covers the SourceBans++ groups this reader tracks) so
// the reference resolves regardless of the stored case (issue #1503).
//
// Freshness invariant: the GroupIds in the map are only valid for the current
// admin-cache generation. This holds because SourceMod rebuilds groups before
// admins and ReadGroups() clears+repopulates the map on every AdminCache_Groups
// rebuild, so the map never outlives the group cache it describes. The map does
// legitimately persist across an admins-only rebuild (SourceMod leaves the group
// cache untouched then, so the stored ids stay valid).
GroupId FindAdmGroupInsensitive(const char[] group_name)
{
	GroupId id = FindAdmGroup(group_name);
	if (id != INVALID_GROUP_ID)
	{
		return id;
	}

	if (g_GroupNameMap == null)
	{
		return INVALID_GROUP_ID;
	}

	char lower[128];
	LowercaseCopy(lower, sizeof(lower), group_name);

	int mapped;
	if (g_GroupNameMap.GetValue(lower, mapped))
	{
		return view_as<GroupId>(mapped);
	}

	return INVALID_GROUP_ID;
}
