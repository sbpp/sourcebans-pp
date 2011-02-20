/**
 * SourceBans Sample Plugin
 *
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
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
	author = "GameConnect",
	description = "Sample plugin to show SourceBans functionality",
	version = "1.5.0",
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
