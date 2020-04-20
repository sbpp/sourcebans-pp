#if defined _sbpp_core_included
	#endinput
#endif
#define _sbpp_core_included

#define SBPP_VERSION "1.7.0:1049"

#define SBPP_LogMsg( LogToFile( SBPP_Core_szLog,
#define SBPP_LogError( LogToFile( SBPP_Core_szError,

stock char SBPP_Core_szLog[PLATFORM_MAX_PATH], SBPP_Core_szError[PLATFORM_MAX_PATH];
stock const char SBPP_PREFIX[] = "\x04[SourceBans++]\x01 ";

void SBPP_Core_Init ()
{
	BuildPath( Path_SM, SBPP_Core_szLog, sizeof SBPP_Core_szLog, "logs/sbpp" );
	CreateDirectory( SBPP_Core_szLog, 1 << 6 | 1 << 7 | 1 << 8 );
	BuildPath( Path_SM, SBPP_Core_szLog, sizeof SBPP_Core_szLog, "logs/sbpp/sbpp.log" );
	BuildPath( Path_SM, SBPP_Core_szError, sizeof SBPP_Core_szError, "logs/sbpp/issues.log" );
}