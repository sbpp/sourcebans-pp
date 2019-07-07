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
//   SourceBans Checker 1.0.2
//   Copyright (C) 2010-2013 Nicholas Hastings
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1288490>
//
// *************************************************************************
#pragma semicolon 1
#pragma newdecls required

public Plugin myinfo = {
	name = "SourceBans++: Bans Checker",
	author = "psychonic, Ca$h Munny, SourceBans++ Dev Team",
	description = "Notifies admins of prior bans from Sourcebans upon player connect.",
	version = "1.7.0",
	url = "https://sbpp.github.io" };

static const char PREFIX[]			= "\x04[SourceBans++]\x01 ";
static const char LISTBANS_USAGE[]	= "sm_listbans <#userid|name> - Lists a user's prior bans from Sourcebans";
static const char LISTCOMMS_USAGE[]	= "sm_listcomms <#userid|name> - Lists a user's prior comms from Sourcebans";
Database g_DB;
char g_DatabasePrefix[10] = "sb";
bool g_bConnecting; /* One connecting per time. */
char g_szLastError[256]; /* To prevent spam same error. */

public void OnPluginStart ()
{
	DB_Connect();
	LoadTranslations( "common.phrases" );
	LoadTranslations( "sbpp_checker.phrases" );
	RegAdminCmd( "sm_listbans", sm_list_Handler, ADMFLAG_GENERIC, LISTBANS_USAGE );
	RegAdminCmd( "sm_listcomms", sm_list_Handler, ADMFLAG_GENERIC, LISTCOMMS_USAGE );
	RegAdminCmd( "sb_reload", sb_reload_Handler, ADMFLAG_CONFIG, "Reload sourcebans config and ban reason menu options." );
	ReadConfig();
}

public void OnMapStart ()
{
	ReadConfig();
}

public void OnClientAuthorized (int iClient, const char[] szAuth)
{
	if ( !g_DB )
	{
		DB_Connect();
	}
	else if ( szAuth[0] != 'B' && szAuth[9] != 'L' )
	{
		/* Do not check bots nor check player with lan steamid. */
		char szIP[30], szQuery[320 + sizeof g_DatabasePrefix * 2 + sizeof szIP];
		GetClientIP( iClient, szIP, sizeof szIP );
		FormatEx( szQuery, sizeof szQuery, "SELECT COUNT(bid) FROM %s_bans WHERE ((type = 0 AND authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) UNION SELECT COUNT(bid) FROM %s_comms WHERE authid REGEXP '^STEAM_[0-9]:%s$'", g_DatabasePrefix, szAuth[8], szIP, g_DatabasePrefix, szAuth[8] );
		g_DB.Query( DB_OnClientAuthorized_Callback, szQuery, GetClientUserId( iClient ), DBPrio_Low );
	}
}

stock Action sm_list_Handler (int iClient, int iArgs)
{
	char szBuf[30];
	GetCmdArg( 0, szBuf, sizeof szBuf );
	bool bBans = StrEqual( "sm_listbans", szBuf, false );
	if ( iArgs < 1 )
	{
		ReplyToCommand( iClient, bBans ? LISTBANS_USAGE : LISTBANS_USAGE );
	}
	else if ( !g_DB )
	{
		DB_Connect();
		ReplyToCommand( iClient, "Plugin not connected to database. Try later." );
	}
	else
	{
		char szTarget[64];
		GetCmdArg( 1, szTarget, sizeof szTarget );
		int iTarget = FindTarget( iClient, szTarget, true, true );
		if ( iTarget != -1 )
		{
			char szAuth[32];
			if ( GetClientAuthId( iTarget, AuthId_Steam2, szAuth, sizeof szAuth ) && szAuth[0] != 'B' && szAuth[9] != 'L' )
			{
				char szQuery[1024], szTargetName[MAX_NAME_LENGTH];
				GetClientName( iTarget, szTargetName, sizeof szTargetName );
				if ( bBans )
				{
					GetClientIP( iTarget, szBuf, sizeof szBuf );
					FormatEx( szQuery, sizeof szQuery, "SELECT created, %s_admins.user, ends, length, reason, RemoveType FROM %s_bans LEFT JOIN %s_admins ON %s_bans.aid = %s_admins.aid WHERE ((type = 0 AND %s_bans.authid REGEXP '^STEAM_[0-9]:%s$') OR (type = 1 AND ip = '%s')) AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, szAuth[8], szBuf );
				}
				else
				{
					FormatEx( szQuery, sizeof szQuery, "SELECT created, %s_admins.user, ends, length, reason, RemoveType, type FROM %s_comms LEFT JOIN %s_admins ON %s_comms.aid = %s_admins.aid WHERE %s_comms.authid REGEXP '^STEAM_[0-9]:%s$' AND ((length > '0' AND ends > UNIX_TIMESTAMP()) OR RemoveType IS NOT NULL)", g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, g_DatabasePrefix, szAuth[8] );
				}
				DataPack hPack = new DataPack();
				hPack.WriteCell( !iClient ? 0 : GetClientUserId( iClient ) );
				hPack.WriteCell( bBans );
				hPack.WriteString( szTargetName );
				g_DB.Query( DB_list_Callback, szQuery, hPack, DBPrio_Low );
				if ( iClient )
				{
					ReplyToCommand( iClient, "\x04%s\x01 Look for %N's %s results in console.", PREFIX, iTarget, bBans ? "ban" : "comm" );
				}
				else
				{
					ReplyToCommand( iClient, "%sNote: if you are using this command through an rcon tool, you will not see results.", PREFIX );
				}
			}
			else
			{
				ReplyToCommand( iClient, "Error: Could not retrieve %N's steam id.", iTarget );
			}
		}
		else
		{
			ReplyToCommand( iClient, "Error: Could not find a target matching '%s'.", szTarget );
		}
	}
	return Plugin_Handled;
}

stock Action sb_reload_Handler (int iClient, int iArgs)
{
	ReadConfig();
	if ( !g_DB || g_szLastError[0] )
	{
		DB_Connect();
	}
	return Plugin_Handled;
}

stock void PrintToAdmins (const char[] szFormat, any...)
{
	char szMsg[256];
	for ( int i = 1; i <= MaxClients; ++i )
	{
		if ( IsClientInGame(i) && !IsFakeClient(i) && CheckCommandAccess( i, "sm_listsourcebans", ADMFLAG_GENERIC ) )
		{
			SetGlobalTransTarget(i);
			VFormat( szMsg, sizeof szMsg, szFormat, 2 );
			PrintToChat( i, szMsg );
		}
	}
}

stock void ReadConfig ()
{
	SMCParser smc = new SMCParser();
	smc.OnKeyValue = ReadConfig_KeyValue;
	char szBuf[PLATFORM_MAX_PATH];
	BuildPath( Path_SM, szBuf, sizeof szBuf, "configs/sourcebans/sourcebans.cfg" );
	if ( FileExists( szBuf ) )
	{
		SMCError err = smc.ParseFile( szBuf );
		if ( err != SMCError_Okay )
		{
			LogError( smc.GetErrorString( err, szBuf, sizeof szBuf ) ? szBuf : "Unknown config parse error." );
		}
	}
	else
	{
		LogError( "Can't find '%s'. Check it to exists and use `sb_reload` command (or change map) to reparse config.", szBuf );
	}
}

stock SMCResult ReadConfig_KeyValue (SMCParser smc, const char[] szKey, const char[] szValue, bool key_quotes, bool value_quotes)
{
	if ( !strcmp( "DatabasePrefix", szKey, false ) )
	{
		if ( !szValue[0] )
		{
			g_DatabasePrefix = "sb";
		}
		else
		{
			strcopy( g_DatabasePrefix, sizeof g_DatabasePrefix, szValue );
		}
	}
	return SMCParse_Continue;
}

stock void DB_Connect ()
{
	if ( !g_bConnecting )
	{
		Database.Connect( DB_Connect_Callback, "sourcebans" );
		g_bConnecting = true;
	}
}

stock void DB_Connect_Callback (Database db, char[] szError, any aData)
{
	if ( !db )
	{
		DB_HandleError( szError );
	}
	else
	{
		g_DB = db;
		if ( g_szLastError[0] )
		{
			g_szLastError[0] = '\0';
		}
	}
	g_bConnecting = false;
}

stock void DB_OnClientAuthorized_Callback (Database db, DBResultSet results, const char[] szError, int iUserid)
{
	if ( results )
	{
		int iClient = GetClientOfUserId(iUserid);
		if ( iClient && results && results.FetchRow() )
		{
			int iBanCount = results.FetchInt(0), iCommCount;
			if ( results.FetchRow() )
			{
				iCommCount = results.FetchInt(0);
			}
			if ( iBanCount && iCommCount )
			{
				PrintToAdmins( "%s%t", PREFIX, "Ban and Comm Warning", iClient, iBanCount, ((iBanCount > 1 || iBanCount == 0) ? "s" : ""), iCommCount, ((iCommCount > 1 || iCommCount == 0) ? "s" : "") );
			}
			else if ( iCommCount )
			{
				PrintToAdmins( "%s%t", PREFIX, "Comm Warning", iClient, iCommCount, ((iCommCount > 1 || iCommCount == 0) ? "s" : "") );
			}
			else if ( iBanCount )
			{
				PrintToAdmins( "%s%t", PREFIX, "Ban Warning", iClient, iBanCount, ((iBanCount > 1 || iBanCount == 0) ? "s" : "") );
			}
		}
	}
	else
	{
		DB_HandleError( szError );
	}
}

stock void DB_list_Callback (Database db, DBResultSet results, const char[] szError, DataPack hPack)
{
	hPack.Reset();
	int iClient = hPack.ReadCell();
	ReplySource rsOld = SetCmdReplySource( SM_REPLY_TO_CONSOLE );
	if ( !iClient || (iClient = GetClientOfUserId( iClient )) )
	{
		bool bBans = hPack.ReadCell();
		char szTargetName[MAX_NAME_LENGTH];
		hPack.ReadString( szTargetName, sizeof szTargetName );
		if ( results )
		{
			if ( results.RowCount )
			{
				char szStartDate[11], szBannedBy[11], szLength[11], szEndDate[11], szRemoveType[2], szCommType[2];
				int iLen, iLength, iReasonSize = bBans ? 28 : 23, i;
				char[] szReason = new char[iReasonSize];
				ReplyToCommand( iClient, "%sListing %ss for %s", PREFIX, bBans ? "ban" : "comm", szTargetName );
				ReplyToCommand( iClient, "Ban Date    Banned By   Length      End Date    %sR  Reason", bBans ? "" : "T  " );
				ReplyToCommand( iClient, "-------------------------------------------------------------------------------" );
				while ( results.FetchRow() )
				{
					szStartDate = "<Unknown> ", szBannedBy = "<Unknown> ", szLength = "N/A       ", szEndDate = "N/A       ", szRemoveType = " ", szCommType = " ";
					if ( !results.IsFieldNull(0) )
					{
						FormatTime( szStartDate, sizeof szStartDate, "%Y-%m-%d", results.FetchInt(0) );
					}
					if ( !results.IsFieldNull(1) )
					{
						results.FetchString( 1, szBannedBy, sizeof szBannedBy );
						iLen = results.FetchSize(1);
						if ( iLen > sizeof szBannedBy - 1 )
						{
							for ( i = 2; i < 5; ++i )
							{
								szBannedBy[sizeof szBannedBy - i] = '.';
							}
						}
						else
						{
							for ( i = iLen; i < sizeof szBannedBy - 1; ++i )
							{
								szBannedBy[i] = ' ';
							}
						}
					}
					iLength = results.FetchInt(3); /* NOT NULL */
					if ( !iLength )
					{
						szLength = "Permanent ";
					}
					else if ( iLength == -1 )
					{
						szLength = "Session   ";
					}
					else
					{
						iLen = IntToString( iLength, szLength, sizeof szLength );
						if ( iLen < sizeof szLength - 1 )
						{
							szLength[iLen] = ' ';
						}
					} /* change the '\0' to a ' '. the original \0 at the end will still be there */
					if ( !results.IsFieldNull(2) )
					{
						FormatTime( szEndDate, sizeof szEndDate, "%Y-%m-%d", results.FetchInt(2) );
					}
					results.FetchString( 4, szReason, iReasonSize ); /* NOT NULL */
					iLen = results.FetchSize(4);
					if ( iLen > iReasonSize - 1 )
					{
						for ( i = 2; i < 5; ++i )
						{
							szReason[iReasonSize - i] = '.';
						}
					}
					else
					{
						for ( i = iLen; i < iReasonSize - 1; ++i )
						{
							szReason[i] = ' ';
						}
					}
					if ( !results.IsFieldNull(5) )
					{
						results.FetchString( 5, szRemoveType, sizeof szRemoveType );
					}
					if ( bBans )
					{
						ReplyToCommand( iClient, "%s  %s  %s  %s  %s  %s", szStartDate, szBannedBy, szLength, szEndDate, szRemoveType, szReason );
					}
					else
					{
						results.FetchString( 6, szCommType, sizeof szCommType ); /* NOT NULL */
						if ( szCommType[0] == '1' )
						{
							szCommType = "M";
						}
						else if ( szCommType[0] == '2' )
						{
							szCommType = "G";
						}
						ReplyToCommand( iClient, "%s  %s  %s  %s  %s  %s  %s", szStartDate, szBannedBy, szLength, szEndDate, szCommType, szRemoveType, szReason );
					}
				}
			}
			else
			{
				ReplyToCommand( iClient, "%sNo %ss found for %s.", PREFIX, bBans ? "ban" : "comm", szTargetName );
			}
		}
		else
		{
			ReplyToCommand( iClient, "%sDB error while retrieving %ss for %s:\n%s", PREFIX, bBans ? "ban" : "comm", szTargetName, szError );
		}
	}
	delete hPack;
	SetCmdReplySource( rsOld );
}

stock void DB_HandleError (const char[] szError)
{
	if ( !StrEqual(g_szLastError, szError, false) )
	{
		strcopy( g_szLastError, sizeof g_szLastError, szError );
		LogError( szError );
	}
}
