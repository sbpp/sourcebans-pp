#if defined _sbpp_sql_included
	#endinput
#endif
#define _sbpp_sql_included

#include <sbpp/core.sp>

stock Database g_SBPP_SQL_dbHandle;
stock char g_SBPP_SQL_szPrefix[32] = "sb";

enum /* States of connection. */
{
	SBPP_SQL_State_None			= 0,
	SBPP_SQL_State_Connecting	= 1 << 0,
	SBPP_SQL_State_Wait			= 1 << 1,
	SBPP_SQL_State_Connected	= 1 << 2
}

typedef ConnectCallback = function void (const bool bSuccessful);

stock static int s_iState, s_iTimestamp;
stock static bool s_bIgnoreForward;
stock static ConnectCallback s_fnCallback = INVALID_FUNCTION;	/* For now, Database.Connect() can't have function as pass data. */
stock static char s_szPrevError[PLATFORM_MAX_PATH];
stock static Handle s_hSBPP_SQL_Handle_Find, s_hSBPP_SQL_Handle_OnClose, s_hSBPP_SQL_Handle_OnUpdate, s_hSBPP_SQL_Prefix_OnUpdate;

forward Action	_SBPP_SQL_Handle_Find (Database &db, int &iState, char szPrefix[sizeof g_SBPP_SQL_szPrefix], int &iTimestamp);
forward void	_SBPP_SQL_Handle_OnClose ();
forward void	_SBPP_SQL_Handle_OnUpdate (const Database db);
forward void	_SBPP_SQL_Prefix_OnUpdate (const char[] szPrefix, const int iTimestamp);

void SBPP_SQL_Init (const ConnectCallback fnCallback = INVALID_FUNCTION)
{
	LoadTranslations( "sbpp/sql.phrases.txt" );
	s_hSBPP_SQL_Handle_Find		= CreateGlobalForward( "_SBPP_SQL_Handle_Find", ET_Hook, Param_CellByRef, Param_CellByRef, Param_String, Param_CellByRef );
	s_hSBPP_SQL_Handle_OnClose	= CreateGlobalForward( "_SBPP_SQL_Handle_OnClose", ET_Ignore );
	s_hSBPP_SQL_Handle_OnUpdate	= CreateGlobalForward( "_SBPP_SQL_Handle_OnUpdate", ET_Ignore, Param_Cell );
	s_hSBPP_SQL_Prefix_OnUpdate	= CreateGlobalForward( "_SBPP_SQL_Prefix_OnUpdate", ET_Ignore, Param_String );
	s_fnCallback = fnCallback;
	SBPP_SQL_Find();
	SBPP_SQL_Prefix_Read();
	RegAdminCmd( "sbpp_prefix_reload", sbpp_prefix_reload_Handler, ADMFLAG_CONFIG, "Reload prefix of database tables." );
}

stock void SBPP_SQL_Reconnect (const ConnectCallback fnCallback = INVALID_FUNCTION)
{
	if ( s_iState != SBPP_SQL_State_Connecting && s_iState != SBPP_SQL_State_Wait )
	{
		s_bIgnoreForward = true;
		Call_StartForward( s_hSBPP_SQL_Handle_OnClose );
		Call_Finish();
		s_bIgnoreForward = false;
		delete g_SBPP_SQL_dbHandle;
		s_fnCallback = fnCallback;
		SBPP_SQL_Connect();
	}
}

stock void SBPP_SQL_Prefix_Read ()
{
	SMCParser Parser = new SMCParser();
	Parser.OnKeyValue = ReadConfig_KeyValue;
	char szBuf[PLATFORM_MAX_PATH];
	BuildPath( Path_SM, szBuf, sizeof szBuf, "configs/sourcebans/sourcebans.cfg" );
	if ( FileExists( szBuf ) )
	{
		SMCError err = Parser.ParseFile( szBuf );
		if ( err )
		{
			Parser.GetErrorString( err, szBuf, sizeof szBuf );
			SBPP_LogIssue( szBuf[0] ? szBuf : "%t", "Unknown parse error." );
		}
	}
	else
	{
		SBPP_LogIssue( "%t", "Database config not found '%s'.", szBuf );
	}
	CloseHandle( Parser );
}

stock static Action sbpp_prefix_reload_Handler (const int iClient, const int iArgs)
{
	SBPP_SQL_Prefix_Read();
	return Plugin_Stop;	/* Prevent to call it multiple times, we send prefix to other plugins through forwards. */
}

stock static SMCResult ReadConfig_KeyValue (const SMCParser Parser, const char[] szKey, const char[] szVal, bool key_quotes, bool value_quotes )
{
	if ( !strcmp( "DatabasePrefix", szKey ) )
	{
		if ( strcmp( g_SBPP_SQL_szPrefix, szVal ) )
		{
			strcopy( g_SBPP_SQL_szPrefix, sizeof g_SBPP_SQL_szPrefix, szVal );
			s_bIgnoreForward = true;
			Call_StartForward( s_hSBPP_SQL_Prefix_OnUpdate );
			Call_PushString( g_SBPP_SQL_szPrefix );
			Call_Finish();
			s_bIgnoreForward = false;
		}
	}
	return SMCParse_Continue;
}

stock static void SBPP_SQL_Find ()
{
	Database db;
	s_bIgnoreForward = true;
	Call_StartForward( s_hSBPP_SQL_Handle_Find );
	Call_PushCellRef( db );
	Call_PushCellRef( s_iState );
	Call_PushStringEx( g_SBPP_SQL_szPrefix, sizeof g_SBPP_SQL_szPrefix, SM_PARAM_STRING_UTF8, SM_PARAM_COPYBACK );
	Call_PushCellRef( s_iTimestamp );
	Call_Finish();
	s_bIgnoreForward = false;
	if ( s_iState == SBPP_SQL_State_None )
	{
		SBPP_SQL_Connect();
	}
	else if ( s_iState == SBPP_SQL_State_Wait )
	{
		CreateTimer( 15.0, CheckWait );
	}
	else if ( s_iState == SBPP_SQL_State_Connected )
	{
		_SBPP_SQL_Handle_OnUpdate( db );
	}
}

stock static void SBPP_SQL_Connect ()
{
	s_iState = SBPP_SQL_State_Connecting;
	Database.Connect( SBPP_SQL_Connect_Callback, "sourcebans" );
}

stock static void SBPP_SQL_Connect_Callback (const Database db, const char[] szError, const any aData)
{
	bool bSuccessful;
	if ( db )
	{
		g_SBPP_SQL_dbHandle = db;
		if ( !g_SBPP_SQL_dbHandle.SetCharset( "utf8mb4" ) )
		{
			g_SBPP_SQL_dbHandle.SetCharset( "utf8" );
		}
		s_bIgnoreForward = true;
		Call_StartForward( s_hSBPP_SQL_Handle_OnUpdate );
		Call_PushCell( g_SBPP_SQL_dbHandle );
		Call_Finish();
		s_bIgnoreForward = false;
		if ( s_szPrevError[0] )
		{
			SBPP_LogMsg( "%t", "Successful reconnect to database." );
			s_szPrevError[0] = '\0';
		}
		bSuccessful = true;
	}
	else
	{
		s_iState = SBPP_SQL_State_None;
		if ( strcmp( s_szPrevError, szError ) )
		{
			SBPP_LogMsg( szError );
			strcopy( s_szPrevError, sizeof s_szPrevError, szError );
		}
	}
	s_iState = SBPP_SQL_State_Connected;
	CallCallback( bSuccessful );
}

public Action _SBPP_SQL_Handle_Find (Database &db, int &iState, char szPrefix[sizeof g_SBPP_SQL_szPrefix], int &iTimestamp)
{
	if ( !s_bIgnoreForward )
	{
		if ( g_SBPP_SQL_dbHandle )
		{
			db = g_SBPP_SQL_dbHandle;
			iState = SBPP_SQL_State_Connected;
			strcopy( szPrefix, sizeof szPrefix, g_SBPP_SQL_szPrefix );
			iTimestamp = s_iTimestamp;
			return Plugin_Stop;
		}
		else if ( s_iState == SBPP_SQL_State_Connecting )
		{
			iState = SBPP_SQL_State_Wait;
			strcopy( szPrefix, sizeof szPrefix, g_SBPP_SQL_szPrefix );
			iTimestamp = s_iTimestamp;
			return Plugin_Stop;
		}
	}
	return Plugin_Continue;
}

public void _SBPP_SQL_Handle_OnClose ()
{
	if ( !s_bIgnoreForward )
	{
		delete g_SBPP_SQL_dbHandle;
		s_iState = SBPP_SQL_State_Wait;
		CreateTimer( 15.0, CheckWait );
	}
}

public void _SBPP_SQL_Handle_OnUpdate (const Database db)
{
	if ( !s_bIgnoreForward )
	{
		g_SBPP_SQL_dbHandle = view_as<Database>( CloneHandle( db ) );
		s_iState = SBPP_SQL_State_Connected;
		CallCallback( true );
	}
}

public void _SBPP_SQL_Prefix_OnUpdate (const char[] szPrefix, const int iTimestamp)
{
	if ( !s_bIgnoreForward )
	{
		strcopy( g_SBPP_SQL_szPrefix, sizeof g_SBPP_SQL_szPrefix, szPrefix );
	}
}

stock static void CallCallback (const bool bSuccessful)
{
	if ( s_fnCallback != INVALID_FUNCTION )
	{
		Call_StartFunction( null, s_fnCallback );
		Call_PushCell( bSuccessful );
		Call_Finish();
		s_fnCallback = INVALID_FUNCTION;
	}
}

stock static Action CheckWait (Handle tTimer)
{
	if ( s_iState != SBPP_SQL_State_Connected )
	{
		SBPP_SQL_Find();
	}
	return Plugin_Stop;
}