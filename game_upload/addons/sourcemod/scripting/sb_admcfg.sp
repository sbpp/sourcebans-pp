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
//  This file incorporates work covered by the following copyright(s):   
//
//   SourceMod Admin File Reader Plugin
//   Copyright (C) 2004-2008 AlliedModders LLC
//   Licensed under GNU GPL version 3
//   Page: <http://www.sourcemod.net/>
//
// *************************************************************************

#pragma semicolon 1

#include <sourcemod>

public Plugin:myinfo = 
{
	name = "SourceBans++: Admin Config Loader", 
	author = "AlliedModders LLC, Sarabveer(VEER™)", 
	description = "Reads admin files", 
	version = "1.5.4", 
	url = "https://github.com/Sarabveer/SourceBans-Fork"
};

/** Various parsing globals */
new bool:g_LoggedFileName = false; /* Whether or not the file name has been logged */
new g_ErrorCount = 0; /* Current error count */
new g_IgnoreLevel = 0; /* Nested ignored section count, so users can screw up files safely */
new g_CurrentLine = 0; /* Current line we're on */
new String:g_Filename[PLATFORM_MAX_PATH]; /* Used for error messages */

#include "sb_admcfg/sb_admin_groups.sp"
#include "sb_admcfg/sb_admin_users.sp"

public OnRebuildAdminCache(AdminCachePart:part)
{
	if (part == AdminCache_Groups) {
		ReadGroups();
	} else if (part == AdminCache_Admins) {
		ReadUsers();
	}
}

ParseError(const String:format[], any:...)
{
	decl String:buffer[512];
	
	if (!g_LoggedFileName)
	{
		LogError("Error(s) detected parsing %s", g_Filename);
		g_LoggedFileName = true;
	}
	
	VFormat(buffer, sizeof(buffer), format, 2);
	
	LogError(" (line %d) %s", g_CurrentLine, buffer);
	
	g_ErrorCount++;
}

InitGlobalStates()
{
	g_ErrorCount = 0;
	g_IgnoreLevel = 0;
	g_CurrentLine = 0;
	g_LoggedFileName = false;
}
