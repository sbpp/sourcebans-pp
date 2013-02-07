/**
 * SourceBans Include File
 *
 * @author GameConnect
 * @version 1.5.0
 * @copyright SourceBans (C)2007-2013 GameConnect.net.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id$
 */

#if defined _sourcebans_included
  #endinput
#endif
#define _sourcebans_included

#define SB_PREFIX		"[SourceBans] "
#define SB_VERSION	"1.5.0-dev"

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
	MarkNativeAsOptional("SB_AddAdmin");
	MarkNativeAsOptional("SB_DeleteAdmin");
	MarkNativeAsOptional("SB_AddGroup");
	MarkNativeAsOptional("SB_DeleteGroup");
	MarkNativeAsOptional("SB_SetAdminGroups");
	MarkNativeAsOptional("SB_SubmitBan");
}


/**
 * When connected to SourceBans database
 *
 * @param database	The database handle
 * @noreturn		
 */
forward SB_OnConnect(Handle:database);

/**
 * When SourceBans config is reloaded
 *
 * @noreturn		
 */
forward SB_OnReload();


/**
 * Connect to SourceBans database
 *
 * @noreturn		
 */
native SB_Connect();

/**
 * Escapes a database string for literal insertion
 *
 * @param string		String to quote.
 * @param buffer		Buffer to store quoted string in.
 * @param maxlength	Maximum length of the buffer.
 * @param written		Optionally returns the number of bytes written.
 * @return					True on success, false if buffer is not big enough.
 *									The buffer must be at least 2*strlen(string)+1.
 * @error						Invalid database or statement Handle.
 */
native bool:SB_Escape(const String:string[], String:buffer[], maxlength, &written = 0);

/**
 * Execute query on SourceBans database and ignore result set
 *
 * @noreturn
 */
native SB_Execute(const String:query[], DBPriority:prio = DBPrio_Normal);

/**
 * Get SourceBans admin id from client
 *
 * @param  client		The index of the client
 * @return value		The admin id of the client
 */
native SB_GetAdminId(client);

/**
 * Get string value from SourceBans config
 *
 * @param key				The key of the value to retrieve
 * @param buffer		The string buffer to store the value in
 * @param maxlength	The max length of the string buffer
 * @noreturn
 */
native SB_GetConfigString(const String:key[], String:buffer[], maxlength);

/**
 * Get value from SourceBans config
 *
 * @param 	key		The key of the value to retrieve
 * @return	value	The value
 */
native SB_GetConfigValue(const String:key[]);

/**
 * Initialize third party SourceBans plugin in case it was late loaded
 *
 * @noreturn
 */
native SB_Init();

/**
 * Query SourceBans database
 *
 * @noreturn
 */
native SB_Query(SQLTCallback:callback, const String:query[], any:data = 0, DBPriority:prio = DBPrio_Normal);

/**
 * Reload SourceBans config
 *
 * @noreturn		
 */
native SB_Reload();

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

/**
 * Submits a ban to the SourceBans database
 *
 * @param client		The index of the client
 * @param target		The index of the target
 * @param reason		The reason for the submission
 * @noreturn
 */
native SB_SubmitBan(client, target, const String:reason[]);


stock bool:DisablePlugin(const String:file[])
{
	decl String:sNewPath[PLATFORM_MAX_PATH + 1], String:sOldPath[PLATFORM_MAX_PATH + 1];
	BuildPath(Path_SM, sNewPath, sizeof(sNewPath), "plugins/disabled/%s.smx", file);
	BuildPath(Path_SM, sOldPath, sizeof(sOldPath), "plugins/%s.smx", file);
	
	// If plugins/<file>.smx does not exist, ignore
	if(!FileExists(sOldPath))
		return false;
	
	// If plugins/disabled/<file>.smx exists, delete it
	if(FileExists(sNewPath))
		DeleteFile(sNewPath);
	
	// Unload plugins/<file>.smx and move it to plugins/disabled/<file>.smx
	ServerCommand("sm plugins unload %s", file);
	RenameFile(sNewPath, sOldPath);
	LogMessage("plugins/%s.smx was unloaded and moved to plugins/disabled/%s.smx", file, file);
	return true;
}