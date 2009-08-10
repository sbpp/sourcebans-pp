#if defined _sourcebans_included
  #endinput
#endif
#define _sourcebans_included

public SharedPlugin:__pl_sourcebans = 
{
	name = "SourceBans",
	file = "sourcebans.smx",
	required = 0
};


public __pl_sourcebans_SetNTVOptional()
{
	MarkNativeAsOptional("SBBanPlayer");
}


/*********************************************************
 * Ban Player from server
 *
 * @param client	The client index of the admin who is banning the client
 * @param target	The client index of the player to ban
 * @param time		The time to ban the player for (in minutes, 0 = permanent)
 * @param reason	The reason to ban the player from the server
 * @noreturn		
 *********************************************************/
native SBBanPlayer(client, target, time, String:reason[]);

//Yarr!
