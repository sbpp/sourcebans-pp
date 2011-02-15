/**
 * =============================================================================
 * SourceBans Include File
 *
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */

#if defined _sourcebans_included
  #endinput
#endif
#define _sourcebans_included

#define SB_PREFIX		"[SourceBans] "
#define SB_VERSION	"2.0.0"

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

public __pl_sourcebans_SetNTVOptional()
{
	MarkNativeAsOptional("SB_GetAdminId");
	MarkNativeAsOptional("SB_GetSettingCell");
	MarkNativeAsOptional("SB_GetSettingString");
	MarkNativeAsOptional("SB_Reload");
	MarkNativeAsOptional("SB_AddAdmin");
	MarkNativeAsOptional("SB_DeleteAdmin");
	MarkNativeAsOptional("SB_AddGroup");
	MarkNativeAsOptional("SB_DeleteGroup");
	MarkNativeAsOptional("SB_SetAdminGroups");
	MarkNativeAsOptional("SB_SubmitBan");
}


/*********************************************************
 * When connected to SourceBans database
 *
 * @param database	The database handle
 * @noreturn		
 *********************************************************/
forward SB_OnConnect(Handle:database);

/*********************************************************
 * When SourceBans config is reloaded
 *
 * @noreturn		
 *********************************************************/
forward SB_OnReload();


/*********************************************************
 * Connect to SourceBans database
 *
 * @noreturn		
 *********************************************************/
native SB_Connect();

/*********************************************************
 * Get SourceBans admin id from client
 *
 * @param  client		The index of the client
 * @return value		The admin id of the client
 *********************************************************/
native SB_GetAdminId(client);

/*********************************************************
 * Get setting from SourceBans config
 *
 * @param 	key		The name of the setting to retrieve
 * @return	value	The value of the setting
 *********************************************************/
native SB_GetSettingCell(const String:key[]);

/*********************************************************
 * Get string setting from SourceBans config
 *
 * @param key				The name of the setting to retrieve
 * @param buffer		The string buffer to store the value in
 * @param maxlength	The max length of the string buffer
 * @noreturn		
 *********************************************************/
native SB_GetSettingString(const String:key[], String:buffer[], maxlength);

/*********************************************************
 * Reload SourceBans config
 *
 * @noreturn		
 *********************************************************/
native SB_Reload();

/*********************************************************
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
 *********************************************************/
native SB_AddAdmin(client, const String:name[], const String:authtype[], const String:identity[], const String:password[] = "", const String:groups[] = "");

/*********************************************************
 * Deletes an admin from the SourceBans database
 *
 * @param client			The index of the client (person deleting admin)
 * @param authtype		The authentication type (AUTHMETHOD_STEAM, AUTHMETHOD_IP, AUTHMETHOD_NAME)
 * @param identity		The steamid, ip, or name of admin based on authtype
 * @noreturn
 * @error						Invalid authtype
 *********************************************************/
native SB_DeleteAdmin(client, const String:authtype[], const String:identity[]);

/*********************************************************
 * Adds a group to the SourceBans database
 *
 * @param client		The index of the client (person adding group)
 * @param name			The name of the new group
 * @param flags			The flags to assign to the group
 * @param immunity	The immunity to assign to the group (optional)
 * @noreturn
 *********************************************************/
native SB_AddGroup(client, const String:name[], const String:flags[], immunity = 0);

/*********************************************************
 * Deletes a group from the SourceBans database
 *
 * @param client		The index of the client (person deleting group)
 * @param name			The name of the group to delete
 * @noreturn
 *********************************************************/
native SB_DeleteGroup(client, const String:name[]);

/*********************************************************
 * Sets an admin's groups in the SourceBans database
 *
 * @param client		The index of the client (person setting groups)
 * @param authtype	The authentication type of the admin (AUTHMETHOD_STEAM, AUTHMETHOD_IP, AUTHMETHOD_NAME)
 * @param identity	The steamid, ip, or name of admin based on authtype
 * @param groups		The groups to set the admin to; blank, or default is to reset groups
 * @noreturn
 * @error						Invalid authtype
 *********************************************************/
native SB_SetAdminGroups(client, const String:authtype[], const String:identity[], const String:groups[] = "");

/*********************************************************
 * Submits a ban to the SourceBans database
 *
 * @param client		The index of the client
 * @param target		The index of the target
 * @param reason		The reason for the submission
 * @noreturn
 *********************************************************/
native SB_SubmitBan(client, target, const String:reason[]);