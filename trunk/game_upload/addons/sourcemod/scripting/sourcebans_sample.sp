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
#include "dbi.inc"

public Plugin:myinfo =
{
	name = "SourceBans Sample Plugin",
	author = "SteamFriends Development Team",
	description = "Sample plugin to show SourceBans functionality",
	version = "1.0.0 RC2",
	url = "http://www.sourcebans.net"
};


public banSample()
{
	new client = 1;
	
	SBBanPlayer(client, client, 5, "Ohnoes i banned myself");
}
