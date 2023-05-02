// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2023 SourceBans++ Dev Team <https://github.com/sbpp>
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
//  This file based off work(s) covered by the following copyright(s):
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

#if defined _sourcebanschecker_included
#endinput
#endif
#define _sourcebanschecker_included

public SharedPlugin __pl_sourcebanschecker =
{
	name = "sourcebans++",
	file = "sbpp_checker.smx",
	#if defined REQUIRE_PLUGIN
	required = 1
	#else
	required = 0
	#endif
};

#if !defined REQUIRE_PLUGIN
public void __pl_sourcebanschecker_SetNTVOptional()
{
	MarkNativeAsOptional("SBPP_CheckerGetClientsBans");
	MarkNativeAsOptional("SBPP_CheckerGetClientsComms");
}
#endif


/*********************************************************
 * Get the number of bans of a client.
 *
 * @param iClient	The client index of who you want to get the number of bans.
 * @return        The number of bans of the client.
 *********************************************************/
native int SBPP_CheckerGetClientsBans(int iClient);


/*********************************************************
 * Get the number of comms bans of a client.
 *
 * @param iClient	The client index of who you want to get the number of comms bans.
 * @return        The number of comms bans of the client.
 *********************************************************/
native int SBPP_CheckerGetClientsComms(int iClient);
