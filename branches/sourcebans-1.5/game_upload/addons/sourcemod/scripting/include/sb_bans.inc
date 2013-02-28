/**
 * SourceBans Include File
 *
 * @author GameConnect
 * @version 1.5.0
 * @copyright SourceBans (C)2007-2013 GameConnect.net.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: sourcebans.inc 144 2013-02-07 00:30:23Z tsunami@gameconnect.net $
 */

#if defined _sb_bans_included
  #endinput
#endif
#define _sb_bans_included

/**
 * Submits a ban to the SourceBans database
 *
 * @param client		The index of the client
 * @param target		The index of the target
 * @param reason		The reason for the submission
 * @noreturn
 */
native SB_SubmitBan(client, target, const String:reason[]);

public SharedPlugin:__pl_sb_bans = 
{
	name = "sb_bans",
	file = "sb_bans.smx",
	#if defined REQUIRE_PLUGIN
	required = 1
	#else
	required = 0
	#endif
};

#if !defined REQUIRE_PLUGIN
public __pl_sourcebans_SetNTVOptional()
{
	MarkNativeAsOptional("SB_SubmitBan");
}
#endif