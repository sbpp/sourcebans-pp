/**
 * SourceComms Include File
 */

#if defined _sourcecomms_included
  #endinput
#endif
#define _sourcecomms_included

/* Punishments types */
enum bType{
    bNot = 0,   // Player chat or voice is not blocked
    bSess,      // ... blocked for player session (until reconnect)
    bTime,      // ... blocked for some time
    bPerm       // ... permanently blocked
}

/**
 * Sets a client's mute state.
 *
 * @param client		Client index.
 * @param muteState		True to mute client, false to unmute.
 * -------------------------------------Parameters below this line are used only for muteState=true-------------------------------------
 * ----------------------------------for muteState=false these parameters are ignored (saveToDB=false)----------------------------------
 * @param muteLength		Length of punishment in minutes. Value < 0 muting client for session. Permanent (0) is not allowed at this time.
 * @param saveToDB		If true, punishment will be saved in database.
 * @param reason		Reason for punishment.
 * @return				True if this caused a change in mute state, false otherwise.
 */
native bool:SourceComms_SetClientMute(client, bool:muteState, muteLength = -1, bool:saveToDB = false, const String:reason[] = "Muted through natives");

/**
 * Sets a client's gag state.
 *
 * @param client		Client index.
 * @param gagState		True to gag client, false to ungag.
 * --------------------------------------Parameters below this line are used only for gagState=true--------------------------------------
 * -----------------------------------for gagState=false these parameters are ignored (saveToDB=false)-----------------------------------
 * @param gagLength		Length of punishment in minutes. Value < 0 gagging client for session. Permanent (0) is not allowed at this time.
 * @param saveToDB		If true, punishment will be saved in database.
 * @param reason		Reason for punishment.
 * @return				True if this caused a change in gag state, false otherwise.
 */
native bool:SourceComms_SetClientGag(client, bool:gagState, gagLength = -1, bool:saveToDB = false, const String:reason[] = "Gagged through natives");

/**
 * Returns the client's mute type
 *
 * @param client	The client index of the player to check mute status
 * @return          The client's current mute type index (see enum bType in the begin).
 */
native bType:SourceComms_GetClientMuteType(client);


/**
 * Returns the client's gag type
 *
 * @param client	The client index of the player to check gag status
 * @return          The client's current gag type index (see enum bType in the begin).
 */
native bType:SourceComms_GetClientGagType(client);

public SharedPlugin:__pl_sourcecomms =
{
	name = "sourcecomms",
	file = "sourcecomms.smx",
	#if defined REQUIRE_PLUGIN
		required = 1
	#else
		required = 0
	#endif
};

public __pl_sourcecomms_SetNTVOptional()
{
	MarkNativeAsOptional("SourceComms_SetClientMute");
	MarkNativeAsOptional("SourceComms_SetClientGag");
	MarkNativeAsOptional("SourceComms_GetClientMuteType");
	MarkNativeAsOptional("SourceComms_GetClientGagType");

}
