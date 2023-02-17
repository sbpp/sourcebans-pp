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
//   SourceMod Admin File Reader Plugin
//   Copyright (C) 2004-2008 AlliedModders LLC
//   Licensed under GNU GPL version 3
//   Page: <http://www.sourcemod.net/>
//
// *************************************************************************

enum UserState
{
	UserState_None,
	UserState_Admins,
	UserState_InAdmin,
}

static SMCParser g_hUserParser;
static UserState g_UserState = UserState_None;
static char g_CurAuth[64];
static char g_CurIdent[64];
static char g_CurName[64];
static char g_CurPass[64];
static ArrayList g_GroupArray;
static int g_CurFlags;
static int g_CurImmunity;

public SMCResult ReadUsers_NewSection(SMCParser smc, const char[] name, bool opt_quotes)
{
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel++;
		return SMCParse_Continue;
	}

	if (g_UserState == UserState_None)
	{
		if (StrEqual(name, "Admins", false))
		{
			g_UserState = UserState_Admins;
		}
		else
		{
			g_IgnoreLevel++;
		}
	}
	else if (g_UserState == UserState_Admins)
	{
		g_UserState = UserState_InAdmin;
		strcopy(g_CurName, sizeof(g_CurName), name);
		g_CurAuth[0] = '\0';
		g_CurIdent[0] = '\0';
		g_CurPass[0] = '\0';
		g_GroupArray.Clear();
		g_CurFlags = 0;
		g_CurImmunity = 0;
	}
	else
	{
		g_IgnoreLevel++;
	}

	return SMCParse_Continue;
}

public SMCResult ReadUsers_KeyValue(SMCParser smc,
	const char[] key,
	const char[] value,
	bool key_quotes,
	bool value_quotes)
{
	if (g_UserState != UserState_InAdmin || g_IgnoreLevel)
	{
		return SMCParse_Continue;
	}

	if (StrEqual(key, "auth", false))
	{
		strcopy(g_CurAuth, sizeof(g_CurAuth), value);
	}
	else if (StrEqual(key, "identity", false))
	{
		strcopy(g_CurIdent, sizeof(g_CurIdent), value);
	}
	else if (StrEqual(key, "password", false))
	{
		strcopy(g_CurPass, sizeof(g_CurPass), value);
	}
	else if (StrEqual(key, "group", false))
	{
		GroupId id = FindAdmGroup(value);
		if (id == INVALID_GROUP_ID)
		{
			ParseError("Unknown group \"%s\"", value);
		}

		g_GroupArray.Push(id);
	}
	else if (StrEqual(key, "flags", false))
	{
		int len = strlen(value);
		AdminFlag flag;

		for (int i = 0; i < len; i++)
		{
			if (!FindFlagByChar(value[i], flag))
			{
				ParseError("Invalid flag detected: %c", value[i]);
			}
			else
			{
				g_CurFlags |= FlagToBit(flag);
			}
		}
	}
	else if (StrEqual(key, "immunity", false))
	{
		g_CurImmunity = StringToInt(value);
	}

	return SMCParse_Continue;
}

public SMCResult ReadUsers_EndSection(SMCParser smc)
{
	if (g_IgnoreLevel)
	{
		g_IgnoreLevel--;
		return SMCParse_Continue;
	}

	if (g_UserState == UserState_InAdmin)
	{
		/* Dump this user to memory */
		if (g_CurIdent[0] != '\0' && g_CurAuth[0] != '\0')
		{
			AdminFlag flags[26];
			AdminId id;
			int i, num_groups, num_flags;

			if ((id = FindAdminByIdentity(g_CurAuth, g_CurIdent)) == INVALID_ADMIN_ID)
			{
				id = CreateAdmin(g_CurName);
				if (!id.BindIdentity(g_CurAuth, g_CurIdent))
				{
					RemoveAdmin(id);
					ParseError("Failed to bind auth \"%s\" to identity \"%s\"", g_CurAuth, g_CurIdent);
					return SMCParse_Continue;
				}
			}

			num_groups = g_GroupArray.Length;
			for (i = 0; i < num_groups; i++)
			{
				id.InheritGroup(g_GroupArray.Get(i));
			}

			id.SetPassword(g_CurPass);
			if (id.ImmunityLevel < g_CurImmunity)
			{
				id.ImmunityLevel = g_CurImmunity;
			}

			num_flags = FlagBitsToArray(g_CurFlags, flags, sizeof(flags));
			for (i = 0; i < num_flags; i++)
			{
				id.SetFlag(flags[i], true);
			}
		}
		else
		{
			ParseError("Failed to create admin: did you forget either the auth or identity properties?");
		}

		g_UserState = UserState_Admins;
	}
	else if (g_UserState == UserState_Admins)
	{
		g_UserState = UserState_None;
	}

	return SMCParse_Continue;
}

public SMCResult ReadUsers_CurrentLine(SMCParser smc, const char[] line, int lineno)
{
	g_CurrentLine = lineno;

	return SMCParse_Continue;
}

static void InitializeUserParser()
{
	if (!g_hUserParser)
	{
		g_hUserParser = new SMCParser();
		g_hUserParser.OnEnterSection = ReadUsers_NewSection;
		g_hUserParser.OnKeyValue = ReadUsers_KeyValue;
		g_hUserParser.OnLeaveSection = ReadUsers_EndSection;
		g_hUserParser.OnRawLine = ReadUsers_CurrentLine;

		g_GroupArray = new ArrayList();
	}
}

void ReadUsers()
{
	InitializeUserParser();

	BuildPath(Path_SM, g_Filename, sizeof(g_Filename), "configs/sourcebans/sb_admins.cfg");

	/* Set states */
	InitGlobalStates();
	g_UserState = UserState_None;

	SMCError err = g_hUserParser.ParseFile(g_Filename);
	if (err != SMCError_Okay)
	{
		char buffer[64];
		if (g_hUserParser.GetErrorString(err, buffer, sizeof(buffer)))
		{
			ParseError("%s", buffer);
		}
		else
		{
			ParseError("Fatal Parse Error");
		}
	}
}
