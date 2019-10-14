#if defined _sbpp_core_included
	#endinput
#endif
#define _sbpp_core_included
#include <sourcemod> /* DEBUG ONLY. Remove me later. */

#define SBPP_VERSION "1.7.0:1049"

#define SBPP_LogMsg( LogToFile( szLog,
#define SBPP_LogIssue( LogToFile( szIssues,

stock char szLog[PLATFORM_MAX_PATH], szIssues[PLATFORM_MAX_PATH], PREFIX[] = "\x04[SourceBans++]\x01 ";

stock void SBPP_Core_Init ()
{
	BuildPath( Path_SM, szLog, sizeof szLog, "logs/sbpp.log" );
	BuildPath( Path_SM, szIssues, sizeof szIssues, "logs/sbpp.issues.log" );
}