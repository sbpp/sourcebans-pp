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
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

#if defined _sourcebans_included
#endinput
#endif
#define _sourcebans_included

public SharedPlugin:__pl_sourcebans =
{
	name = "sourcebans", 
	file = "sourcebans.smx",
	#if defined REQUIRE_PLUGIN
	required = 1
	#else
	required = 0
	#endif
};

#if !defined REQUIRE_PLUGIN
public __pl_sourcebans_SetNTVOptional()
{
	MarkNativeAsOptional("SBBanPlayer");
}
#endif


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
