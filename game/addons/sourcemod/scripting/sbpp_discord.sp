#pragma semicolon 1

#define PLUGIN_AUTHOR "RumbleFrog, SourceBans++ Dev Team"
#define PLUGIN_VERSION "1.6.3-rc.1"

#include <sourcemod>
#include <SteamWorks>
#include <smjansson>

#pragma newdecls required

enum
{
	Ban,
	Report,
	Type_Count
};

ConVar Convars[Type_Count];

char sEndpoints[Type_Count][256];

public Plugin myinfo = 
{
	name = "SourceBans++ Discord Plugin",
	author = PLUGIN_AUTHOR,
	description = "Listens for ban & report forward and sends it to webhook endpoints",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public void OnPluginStart()
{
	CreateConVar("sbpp_discord_version", PLUGIN_VERSION, "SBPP Discord Version", FCVAR_REPLICATED | FCVAR_SPONLY | FCVAR_DONTRECORD | FCVAR_NOTIFY);
	
	Convars[Ban] = CreateConVar("sbpp_discord_banhook", "", "Discord web hook endpoint for ban forward", FCVAR_NONE);
	Convars[Report] = CreateConVar("sbpp_discord_reporthook", "", "Discord web hook endpoint for report forward. If left empty, the ban endpoint will be used instead", FCVAR_NONE);

	Convars[Ban].AddChangeHook(OnConvarChanged);
	Convars[Report].AddChangeHook(OnConvarChanged);
}

public void OnConvarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (convar == Convars[Ban])
		Convars[Ban].GetString(sEndpoints[Ban], sizeof sEndpoints[]);
	else if (convar == Convars[Report])
		Convars[Report].GetString(sEndpoints[Report], sizeof sEndpoints[]);
}