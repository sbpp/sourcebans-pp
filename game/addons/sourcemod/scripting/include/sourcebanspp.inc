// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2024 SourceBans++ Dev Team <https://github.com/sbpp>
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

#if defined _sourcebanspp_included
#endinput
#endif
#define _sourcebanspp_included

// -------------------------------------------------------------------------
// Native API revision (aligned with SourceBans++ 2.0 panel / plugin release)
// -------------------------------------------------------------------------
//
// MAJOR_REVISION tracks the documented native API epoch (2 = 2.0 line).
// Bump MAJOR only when a change requires third-party plugins to recompile
// (removed/changed native signatures, removed forwards). Bump MINOR for
// additive natives/forwards that old .smx files can ignore at runtime.
//
// 2.0 epoch (this header):
//   sourcebanspp.inc — library "sourcebans++" (sbpp_main.smx)
//     Additive: forward SBPP_OnClientPostAdminCheck (#1431).
//     Unchanged: SBPP_BanPlayer; SBBanPlayer remains deprecated.
//     Existing third-party .smx that only use prior natives/forwards
//     generally keep working without a rebuild.
//
//   sourcebanschecker.inc — RegPluginLibrary "sourcechecker++" (#1034)
//     Recompile / update detection if you #include checker and use
//     LibraryExists/GetFeatureStatus: was "sourcebans++", now
//     "sourcechecker++". Additive natives: SBPP_CheckerGetClientsMutes,
//     SBPP_CheckerGetClientsGags (#1032); require sbpp_checker.smx that
//     implements them if you call those natives.
//
// Optional compile-time guard (add to your plugin if you ship against
// this header and want a hard fail when the epoch drifts):
//
//   #if SOURCEBANSPP_VERSION_MAJOR != 2
//   #error Rebuild against SourceBans++ 2.0 sourcebanspp.inc
//   #endif
//
// Version constants — scripts/resolve-plugin-version.sh writes
// include/sbpp_version.inc (release tag → configs/version.json →
// git describe → "dev"; mirrors Sbpp\Version::resolve). Checked-in
// fallback supports direct spcomp; release/plugin-build CI regenerates.
#include <sbpp_version>

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
public void __pl_sourcebanspp_SetNTVOptional()
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
 * Called when a new report is inserted
 *
 * @param iReporter	The client index of the reporter
 * @param iTarget	The client index of the player to report
 * @param sReason	The reason to report the player
 * @noreturn
 *********************************************************/
forward void SBPP_OnReportPlayer(int iReporter, int iTarget, const char[] sReason);

/*********************************************************
 * Called when OnClientPreAdminCheck is about to be triggered
 *
 * @param	part	The AdminCachePart being processed
 * @return	bBlock	If enabled, blocks the trigger for OnClientPreAdminCheck
 *********************************************************/
forward bool SBPP_OnClientPreAdminCheck(AdminCachePart part);

/*********************************************************
 * Called after a client's admin rights have been fully applied
 * from the database (flags and groups assigned, cache checked).
 *
 * @param	client	The client index who received admin rights
 * @noreturn
 *********************************************************/
forward void SBPP_OnClientPostAdminCheck(int client);

//Yarr!
