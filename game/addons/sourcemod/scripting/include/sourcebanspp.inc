#if defined _sourcebanspp_included
#endinput
#endif
#define _sourcebanspp_included

public SharedPlugin __pl_sourcebanspp =
{
	name = "sourcebans++",
	file = "sbpp_main.smx",
	#if defined REQUIRE_PLUGIN
	required = 1
	#else
	required = 0
	#endif
};

#if !defined REQUIRE_PLUGIN
public __pl_sourcebanspp_SetNTVOptional()
{
	MarkNativeAsOptional("SBBanPlayer");
	MarkNativeAsOptional("SBPP_BanPlayer");
	MarkNativeAsOptional("SBPP_ReportPlayer");
}
#endif


/*********************************************************
 * Ban Player from server
 *
 * @param iAdmin	The client index of the admin who is banning the client
 * @param iTarget	The client index of the player to ban
 * @param iTime		The time to ban the player for (in minutes, 0 = permanent)
 * @param sReason	The reason to ban the player from the server
 * @noreturn
 *********************************************************/
#pragma deprecated Use SBPP_BanPlayer() instead.
native void SBBanPlayer(int iAdmin, int iTarget, int iTime, const char[] sReason);

/*********************************************************
 * Ban Player from server
 *
 * @param iAdmin	The client index of the admin who is banning the client
 * @param iTarget	The client index of the player to ban
 * @param iTime		The time to ban the player for (in minutes, 0 = permanent)
 * @param sReason	The reason to ban the player from the server
 * @noreturn
 *********************************************************/
native void SBPP_BanPlayer(int iAdmin, int iTarget, int iTime, const char[] sReason);

/*********************************************************
 * Reports a player
 *
 * @param iReporter	The client index of the reporter
 * @param iTarget	The client index of the player to report
 * @param sReason	The reason to report the player
 * @noreturn
 *********************************************************/
native void SBPP_ReportPlayer(int iReporter, int iTarget, const char[] sReason);

/*********************************************************
 * Called when the admin banning the player.
 *
 * @param iAdmin	The client index of the admin who is banning the client
 * @param iTarget    The client index of the player to ban
 * @param iTime      The time to ban the player for (in minutes, 0 = permanent)
 * @param sReason    The reason to ban the player from the server
 *********************************************************/
forward void SBPP_OnBanPlayer(int iAdmin, int iTarget, int iTime, const char[] sReason);

/*********************************************************
 * Called when a player was blocked from server because he is banned.
 *
 * @param client	 The client index of user that gets blocked from the server
 * @param iTarget    The name of user.
 *********************************************************/
forward void SBPP_OnPlayerBlocked(int client, const char[] name);

/*********************************************************
 * Called when a new report is inserted
 *
 * @param iReporter	The client index of the reporter
 * @param iTarget	The client index of the player to report
 * @param sReason	The reason to report the player
 * @noreturn
 *********************************************************/
forward void SBPP_OnReportPlayer(int iReporter, int iTarget, const char[] sReason);