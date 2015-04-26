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

#if defined _sb_admins_included
  #endinput
#endif
#define _sb_admins_included

/**
 * Get SourceBans admin id from client
 *
 * @param  client		The index of the client
 * @return value		The admin id of the client
 */
native SB_GetAdminId(client);

/**
 * Adds an admin to the SourceBans database
 *
 * @param client		The index of the client (person adding admin)
 * @param name			The name of the admin to add
 * @param authtype	The authentication type (AUTHMETHOD_STEAM, AUTHMETHOD_IP, AUTHMETHOD_NAME)
 * @param identity	The Steam ID, IP address or name of the admin based on authtype
 * @param password	The password for the admin (optional)
 * @param groups		The groups to assign to the admin (optional)
 * @noreturn
 * @error						Invalid authtype
 */
native SB_AddAdmin(client, const String:name[], const String:authtype[], const String:identity[], const String:password[] = "", const String:groups[] = "");

/**
 * Deletes an admin from the SourceBans database
 *
 * @param client			The index of the client (person deleting admin)
 * @param authtype		The authentication type (AUTHMETHOD_STEAM, AUTHMETHOD_IP, AUTHMETHOD_NAME)
 * @param identity		The steamid, ip, or name of admin based on authtype
 * @noreturn
 * @error						Invalid authtype
 */
native SB_DeleteAdmin(client, const String:authtype[], const String:identity[]);

/**
 * Adds a group to the SourceBans database
 *
 * @param client		The index of the client (person adding group)
 * @param name			The name of the new group
 * @param flags			The flags to assign to the group
 * @param immunity	The immunity to assign to the group (optional)
 * @noreturn
 */
native SB_AddGroup(client, const String:name[], const String:flags[], immunity = 0);

/**
 * Deletes a group from the SourceBans database
 *
 * @param client		The index of the client (person deleting group)
 * @param name			The name of the group to delete
 * @noreturn
 */
native SB_DeleteGroup(client, const String:name[]);

/**
 * Sets an admin's groups in the SourceBans database
 *
 * @param client		The index of the client (person setting groups)
 * @param authtype	The authentication type of the admin (AUTHMETHOD_STEAM, AUTHMETHOD_IP, AUTHMETHOD_NAME)
 * @param identity	The steamid, ip, or name of admin based on authtype
 * @param groups		The groups to set the admin to; blank, or default is to reset groups
 * @noreturn
 * @error						Invalid authtype
 */
native SB_SetAdminGroups(client, const String:authtype[], const String:identity[], const String:groups[] = "");

public SharedPlugin:__pl_sb_admins = 
{
	name = "sb_admins",
	file = "sb_admins.smx",
	#if defined REQUIRE_PLUGIN
	required = 1
	#else
	required = 0
	#endif
};

#if !defined REQUIRE_PLUGIN
public __pl_sb_admins_SetNTVOptional()
{
	MarkNativeAsOptional("SB_GetAdminId");
	MarkNativeAsOptional("SB_AddAdmin");
	MarkNativeAsOptional("SB_DeleteAdmin");
	MarkNativeAsOptional("SB_AddGroup");
	MarkNativeAsOptional("SB_DeleteGroup");
	MarkNativeAsOptional("SB_SetAdminGroups");
}
#endif