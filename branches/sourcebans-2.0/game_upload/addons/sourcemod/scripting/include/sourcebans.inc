#if defined _sourcebans_included
  #endinput
#endif
#define _sourcebans_included

#include <sourcemod>

#define SB_PREFIX		"[SourceBans] "
#define SB_VERSION	"2.0.0"

//#define _DEBUG

public SharedPlugin:__pl_sourcebans = 
{
	name = "SourceBans",
	file = "sourcebans.smx",
	required = 0
};

public __pl_sourcebans_SetNTVOptional()
{
	MarkNativeAsOptional("SB_Reload");
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