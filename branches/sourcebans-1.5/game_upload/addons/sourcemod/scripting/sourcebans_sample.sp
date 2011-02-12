/**
* sourcebans.sp
*
* This file contains all Source Server Plugin Functions
* @author SteamFriends Development Team
* @version 0.0.0.$Rev: 108 $
* @copyright SteamFriends (www.steamfriends.com)
* @package SourceBans
* @link http://www.sourcebans.net
*/

#pragma semicolon 1
#include <sourcemod>
#include <sourcebans>

#undef REQUIRE_PLUGIN
#include <adminmenu>

new g_bSBAvailable = false;

public Plugin:myinfo =
{
	name = "SourceBans Sample Plugin",
	author = "SteamFriends Development Team",
	description = "Sample plugin to show SourceBans functionality",
	version = "1.0.0 RC2",
	url = "http://www.sourcebans.net"
};

public OnAllPluginsLoaded()
{
	if (LibraryExists("sourcebans"))
	{
		g_bSBAvailable = true;
	}
}

public OnLibraryAdded(const String:name[])
{
	if (StrEqual(name, "sourcebans"))
	{
		g_bSBAvailable = true;
	}
}

public OnLibraryRemoved(const String:name[])
{
	if (StrEqual(name, "sourcebans"))
	{
		g_bSBAvailable = false;
	}
}

public banSample()
{
	new client = 1;
	
	if (g_bSBAvailable)
	{
		SBBanPlayer(client, client, 5, "Ohnoes i banned myself");
	}
	else
	{
		BanClient(client, 5, BANFLAG_AUTO, "Ohnoes i banned myself");
	}
}
