// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
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
//   SourceComms 0.9.266
//   Copyright (C) 2013-2014 Alexandr Duplishchev
//   Licensed under GNU GPL version 3, or later.
//   Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
//
// *************************************************************************

#if defined _sourcecomms_included
#endinput
#endif
#define _sourcecomms_included

/* Punishments types */
enum bType {
	bNot = 0,  // Player chat or voice is not blocked
	bSess,  // ... blocked for player session (until reconnect)
	bTime,  // ... blocked for some time
	bPerm // ... permanently blocked
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