#if defined _sbpp_sql_included
	#endinput
#endif
#define _sbpp_sql_included

#include <sbpp/core.sp>

#define SBPP_SQL_Init() SBPP_SQL_Init( DoNothing )	/* This something like function overloading. For now, we can't set default parameter-function. */
#define SBPP_SQL_Reconnect() SBPP_SQL_Reconnect( DoNothing )	/* This something like function overloading. For now, we can't set default parameter-function. */

enum {	/* States */
	SBPP_SQL_State_None			= 0,
	SBPP_SQL_State_Connecting	= 1 << 0,
	SBPP_SQL_State_Wait			= 1 << 1,
	SBPP_SQL_State_Connected	= 1 << 2, }

forward Action	_SBPP_SQL_Find (Database &db, int &iState);
forward void	_SBPP_SQL_Close ();
forward void	_SBPP_SQL_Release (const Database db);

typedef ConnectCallback = function void (const bool bSuccessful);

stock Database g_dbSQL;
stock static int s_iState;
stock static bool s_bIgnoreForward;
stock static ConnectCallback s_fnCallback;		/* For now, Database.Connect() can't have function as pass data. */
stock static char s_szPrevError[PLATFORM_MAX_PATH];
stock static Handle s_hSBPP_SQL_Find, s_hSBPP_SQL_Close, s_hSBPP_SQL_Release;

stock void SBPP_SQL_Init (const ConnectCallback fnCallback)
{
	LoadTranslations( "sbpp.sql.phrases.txt" );
	s_hSBPP_SQL_Find	= CreateGlobalForward( "_SBPP_SQL_Find", ET_Hook, Param_CellByRef, Param_CellByRef );
	s_hSBPP_SQL_Close	= CreateGlobalForward( "_SBPP_SQL_Close", ET_Ignore );
	s_hSBPP_SQL_Release	= CreateGlobalForward( "_SBPP_SQL_Release", ET_Ignore, Param_Cell );
	s_fnCallback = fnCallback;
	SBPP_SQL_Find();
}

stock void SBPP_SQL_Reconnect (const ConnectCallback fnCallback)
{
	if ( s_iState != SBPP_SQL_State_Connecting && s_iState != SBPP_SQL_State_Wait )
	{
		s_bIgnoreForward = true;
		Call_StartForward( s_hSBPP_SQL_Close );
		Call_Finish();
		s_bIgnoreForward = false;
		delete g_dbSQL;
		s_fnCallback = fnCallback;
		SBPP_SQL_Connect();
	}
}

stock static void SBPP_SQL_Find ()
{
	int iState;
	Database db;
	s_bIgnoreForward = true;
	Call_StartForward( s_hSBPP_SQL_Find );
	Call_PushCellRef( db );
	Call_PushCellRef( iState );
	Call_Finish();
	s_bIgnoreForward = false;
	switch ( iState )
	{
		case SBPP_SQL_State_None:
		{
			SBPP_SQL_Connect();
		}
		case SBPP_SQL_State_Connecting:
		{
			s_iState = SBPP_SQL_State_Wait;
			CreateTimer( 15.0, CheckWait, _ );
		}
		case SBPP_SQL_State_Connected:
		{
			_SBPP_SQL_Release( db );
		}
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
		g_dbSQL = db;
		if ( !g_dbSQL.SetCharset( "utf8mb4" ) ) { g_dbSQL.SetCharset( "utf8" ); }
		s_bIgnoreForward = true;
		Call_StartForward( s_hSBPP_SQL_Release );
		Call_PushCell( g_dbSQL );
		Call_Finish();
		s_bIgnoreForward = false;
		bSuccessful = true;
		if ( s_szPrevError[0] )
		{
			SBPP_LogMsg( "%t", "Successful reconnect" );
			s_szPrevError = "";
		}
	}
	else if ( szError[0] )
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

stock static void CallCallback (const bool bSuccessful)
{
	if ( s_fnCallback != DoNothing )
	{
		Call_StartFunction( null, s_fnCallback );
		Call_PushCell( bSuccessful );
		Call_Finish();
		s_fnCallback = DoNothing;
	}
}

stock static Action CheckWait (Handle tTimer)
{
	if ( s_iState == SBPP_SQL_State_Wait )
	{
		SBPP_SQL_Find();
	}
	return Plugin_Stop;
}

public Action _SBPP_SQL_Find (Database &db, int &iState)
{
	if ( !s_bIgnoreForward )
	{
		if ( g_dbSQL )
		{
			db = g_dbSQL;
			iState = SBPP_SQL_State_Connected;
			return Plugin_Stop;
		}
		if ( s_iState == SBPP_SQL_State_Connecting )
		{
			iState = SBPP_SQL_State_Connecting;
			return Plugin_Stop;
		}
	}
	return Plugin_Continue;
}

public void _SBPP_SQL_Close ()
{
	if ( !s_bIgnoreForward )
	{
		delete g_dbSQL;
		s_iState = SBPP_SQL_State_Wait;
		CreateTimer( 15.0, CheckWait, _ );
	}
}

public void _SBPP_SQL_Release (const Database db)
{
	if ( !s_bIgnoreForward )
	{
		g_dbSQL =  view_as<Database>( CloneHandle( db ) );
		s_iState = SBPP_SQL_State_Connected;
		CallCallback( true );
	}
}

stock void DoNothing (const bool bSuccessful) {}