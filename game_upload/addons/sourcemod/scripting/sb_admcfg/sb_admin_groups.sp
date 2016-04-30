// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2015 Sarabveer Singh <sarabveer@sarabveer.me>
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

#if SOURCEMOD_V_MAJOR >= 1 && SOURCEMOD_V_MINOR >= 7
#define GROUP_STATE_NONE		0
#define GROUP_STATE_GROUPS		1
#define GROUP_STATE_INGROUP		2
#define GROUP_STATE_OVERRIDES	3
#define GROUP_PASS_FIRST		1
#define GROUP_PASS_SECOND		2

static SMCParser g_hGroupParser;
static GroupId:g_CurGrp = INVALID_GROUP_ID;
static g_GroupState = GROUP_STATE_NONE;
static g_GroupPass = 0;
static bool:g_NeedReparse = false;

public SMCResult ReadGroups_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
{
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel++;
		return SMCParse_Continue;
	}
	
	if (g_GroupState == GROUP_STATE_NONE)
	{
		if (StrEqual(name, "Groups", false))
		{
			g_GroupState = GROUP_STATE_GROUPS;
		} else {
			g_IgnoreLevel++;
		}
	} else if (g_GroupState == GROUP_STATE_GROUPS) {
		if ((g_CurGrp = CreateAdmGroup(name)) == INVALID_GROUP_ID)
		{
			g_CurGrp = FindAdmGroup(name);
		}
		g_GroupState = GROUP_STATE_INGROUP;
	} else if (g_GroupState == GROUP_STATE_INGROUP) {
		if (StrEqual(name, "Overrides", false))
		{
			g_GroupState = GROUP_STATE_OVERRIDES;
		} else {
			g_IgnoreLevel++;
		}
	} else {
		g_IgnoreLevel++;
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_KeyValue(SMCParser smc, 
	const char[] key, 
	const char[] value, 
	bool key_quotes, 
	bool value_quotes)
{
	if (g_CurGrp == INVALID_GROUP_ID || g_IgnoreLevel)
	{
		return SMCParse_Continue;
	}
	
	new AdminFlag:flag;
	
	if (g_GroupPass == GROUP_PASS_FIRST)
	{
		if (g_GroupState == GROUP_STATE_INGROUP)
		{
			if (StrEqual(key, "flags", false))
			{
				new len = strlen(value);
				for (new i = 0; i < len; i++)
				{
					if (!FindFlagByChar(value[i], flag))
					{
						continue;
					}
					SetAdmGroupAddFlag(g_CurGrp, flag, true);
				}
			} else if (StrEqual(key, "immunity", false)) {
				g_NeedReparse = true;
			}
		} else if (g_GroupState == GROUP_STATE_OVERRIDES) {
			new OverrideRule:rule = Command_Deny;
			
			if (StrEqual(value, "allow", false))
			{
				rule = Command_Allow;
			}
			
			if (key[0] == '@')
			{
				AddAdmGroupCmdOverride(g_CurGrp, key[1], Override_CommandGroup, rule);
			} else {
				AddAdmGroupCmdOverride(g_CurGrp, key, Override_Command, rule);
			}
		}
	} else if (g_GroupPass == GROUP_PASS_SECOND
		 && g_GroupState == GROUP_STATE_INGROUP) {
		/* Check for immunity again, core should handle double inserts */
		if (StrEqual(key, "immunity", false))
		{
			/* If it's a value we know about, use it */
			if (StrEqual(value, "*"))
			{
				SetAdmGroupImmunityLevel(g_CurGrp, 2);
			} else if (StrEqual(value, "$")) {
				SetAdmGroupImmunityLevel(g_CurGrp, 1);
			} else {
				new level;
				if (StringToIntEx(value, level))
				{
					SetAdmGroupImmunityLevel(g_CurGrp, level);
				} else {
					new GroupId:id;
					if (value[0] == '@')
					{
						id = FindAdmGroup(value[1]);
					} else {
						id = FindAdmGroup(value);
					}
					if (id != INVALID_GROUP_ID)
					{
						SetAdmGroupImmuneFrom(g_CurGrp, id);
					} else {
						ParseError("Unable to find group: \"%s\"", value);
					}
				}
			}
		}
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_EndSection(SMCParser smc)
{
	/* If we're ignoring, skip out */
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel--;
		return SMCParse_Continue;
	}
	
	if (g_GroupState == GROUP_STATE_OVERRIDES)
	{
		g_GroupState = GROUP_STATE_INGROUP;
	} else if (g_GroupState == GROUP_STATE_INGROUP) {
		g_GroupState = GROUP_STATE_GROUPS;
		g_CurGrp = INVALID_GROUP_ID;
	} else if (g_GroupState == GROUP_STATE_GROUPS) {
		g_GroupState = GROUP_STATE_NONE;
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_CurrentLine(SMCParser smc, const char[] line, int lineno)
{
	g_CurrentLine = lineno;
	
	return SMCParse_Continue;
}

static InitializeGroupParser()
{
	if (!g_hGroupParser)
	{
		g_hGroupParser = new SMCParser();
		g_hGroupParser.OnEnterSection = ReadGroups_NewSection;
		g_hGroupParser.OnKeyValue = ReadGroups_KeyValue;
		g_hGroupParser.OnLeaveSection = ReadGroups_EndSection;
		g_hGroupParser.OnRawLine = ReadGroups_CurrentLine;
	}
}

static InternalReadGroups(const String:path[], pass)
{
	/* Set states */
	InitGlobalStates();
	g_GroupState = GROUP_STATE_NONE;
	g_CurGrp = INVALID_GROUP_ID;
	g_GroupPass = pass;
	g_NeedReparse = false;
	
	SMCError err = g_hGroupParser.ParseFile(path);
	if (err != SMCError_Okay)
	{
		char buffer[64];
		if (g_hGroupParser.GetErrorString(err, buffer, sizeof(buffer)))
		{
			ParseError("%s", buffer);
		} else {
			ParseError("Fatal parse error");
		}
	}
}

ReadGroups()
{
	InitializeGroupParser();
	
	BuildPath(Path_SM, g_Filename, sizeof(g_Filename), "configs/sourcebans/sb_admin_groups.cfg");
	
	InternalReadGroups(g_Filename, GROUP_PASS_FIRST);
	if (g_NeedReparse)
	{
		InternalReadGroups(g_Filename, GROUP_PASS_SECOND);
	}
}
/* SOURCEMOD 1.7 PLUGIN STOPS HERE */
#else
enum GroupState
{
	GroupState_None, 
	GroupState_Groups, 
	GroupState_InGroup, 
	GroupState_Overrides, 
}

enum GroupPass
{
	GroupPass_Invalid, 
	GroupPass_First, 
	GroupPass_Second, 
}

static SMCParser g_hGroupParser;
static GroupId g_CurGrp = INVALID_GROUP_ID;
static GroupState g_GroupState = GroupState_None;
static GroupPass g_GroupPass = GroupPass_Invalid;
static bool g_NeedReparse = false;

public SMCResult ReadGroups_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
{
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel++;
		return SMCParse_Continue;
	}
	
	if (g_GroupState == GroupState_None)
	{
		if (StrEqual(name, "Groups", false))
		{
			g_GroupState = GroupState_Groups;
		} else {
			g_IgnoreLevel++;
		}
	} else if (g_GroupState == GroupState_Groups) {
		if ((g_CurGrp = CreateAdmGroup(name)) == INVALID_GROUP_ID)
		{
			g_CurGrp = FindAdmGroup(name);
		}
		g_GroupState = GroupState_InGroup;
	} else if (g_GroupState == GroupState_InGroup) {
		if (StrEqual(name, "Overrides", false))
		{
			g_GroupState = GroupState_Overrides;
		} else {
			g_IgnoreLevel++;
		}
	} else {
		g_IgnoreLevel++;
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_KeyValue(SMCParser smc, 
	const char[] key, 
	const char[] value, 
	bool key_quotes, 
	bool value_quotes)
{
	if (g_CurGrp == INVALID_GROUP_ID || g_IgnoreLevel)
	{
		return SMCParse_Continue;
	}
	
	AdminFlag flag;
	
	if (g_GroupPass == GroupPass_First)
	{
		if (g_GroupState == GroupState_InGroup)
		{
			if (StrEqual(key, "flags", false))
			{
				int len = strlen(value);
				for (int i = 0; i < len; i++)
				{
					if (!FindFlagByChar(value[i], flag))
					{
						continue;
					}
					g_CurGrp.SetFlag(flag, true);
				}
			} else if (StrEqual(key, "immunity", false)) {
				g_NeedReparse = true;
			}
		} else if (g_GroupState == GroupState_Overrides) {
			OverrideRule rule = Command_Deny;
			
			if (StrEqual(value, "allow", false))
			{
				rule = Command_Allow;
			}
			
			if (key[0] == '@')
			{
				g_CurGrp.AddCommandOverride(key[1], Override_CommandGroup, rule);
			} else {
				g_CurGrp.AddCommandOverride(key, Override_Command, rule);
			}
		}
	} else if (g_GroupPass == GroupPass_Second
		 && g_GroupState == GroupState_InGroup) {
		/* Check for immunity again, core should handle double inserts */
		if (StrEqual(key, "immunity", false))
		{
			/* If it's a value we know about, use it */
			if (StrEqual(value, "*"))
			{
				g_CurGrp.ImmunityLevel = 2;
			} else if (StrEqual(value, "$")) {
				g_CurGrp.ImmunityLevel = 1;
			} else {
				int level;
				if (StringToIntEx(value, level))
				{
					g_CurGrp.ImmunityLevel = level;
				} else {
					GroupId id;
					if (value[0] == '@')
					{
						id = FindAdmGroup(value[1]);
					} else {
						id = FindAdmGroup(value);
					}
					if (id != INVALID_GROUP_ID)
					{
						g_CurGrp.AddGroupImmunity(id);
					} else {
						ParseError("Unable to find group: \"%s\"", value);
					}
				}
			}
		}
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_EndSection(SMCParser smc)
{
	/* If we're ignoring, skip out */
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel--;
		return SMCParse_Continue;
	}
	
	if (g_GroupState == GroupState_Overrides)
	{
		g_GroupState = GroupState_InGroup;
	} else if (g_GroupState == GroupState_InGroup) {
		g_GroupState = GroupState_Groups;
		g_CurGrp = INVALID_GROUP_ID;
	} else if (g_GroupState == GroupState_Groups) {
		g_GroupState = GroupState_None;
	}
	
	return SMCParse_Continue;
}

public SMCResult ReadGroups_CurrentLine(SMCParser smc, const char[] line, int lineno)
{
	g_CurrentLine = lineno;
	
	return SMCParse_Continue;
}

static void InitializeGroupParser()
{
	if (!g_hGroupParser)
	{
		g_hGroupParser = new SMCParser();
		g_hGroupParser.OnEnterSection = ReadGroups_NewSection;
		g_hGroupParser.OnKeyValue = ReadGroups_KeyValue;
		g_hGroupParser.OnLeaveSection = ReadGroups_EndSection;
		g_hGroupParser.OnRawLine = ReadGroups_CurrentLine;
	}
}

static void InternalReadGroups(const char[] path, GroupPass pass)
{
	/* Set states */
	InitGlobalStates();
	g_GroupState = GroupState_None;
	g_CurGrp = INVALID_GROUP_ID;
	g_GroupPass = pass;
	g_NeedReparse = false;
	
	SMCError err = g_hGroupParser.ParseFile(path);
	if (err != SMCError_Okay)
	{
		char buffer[64];
		if (g_hGroupParser.GetErrorString(err, buffer, sizeof(buffer)))
		{
			ParseError("%s", buffer);
		} else {
			ParseError("Fatal parse error");
		}
	}
}

void ReadGroups()
{
	InitializeGroupParser();
	
	BuildPath(Path_SM, g_Filename, sizeof(g_Filename), "configs/sourcebans/sb_admin_groups.cfg");
	
	InternalReadGroups(g_Filename, GroupPass_First);
	if (g_NeedReparse)
	{
		InternalReadGroups(g_Filename, GroupPass_Second);
	}
}
#endif