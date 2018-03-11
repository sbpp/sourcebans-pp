#pragma semicolon 1

#define DEBUG

#define PLUGIN_AUTHOR "RumbleFrog, SourceBans++ Dev Team"
#define PLUGIN_VERSION "1.6.3-rc.1"

#include <sourcemod>
#include <sourcebans>

#pragma newdecls required

#define Chat_Prefix "[SourceBans++] "

enum Settings
{
	Prefix = 0,
	Cooldown,
	Settings_Count
};

ConVar Convars[Settings_Count];

char sPrefix[16];

float fCooldown = 60.0;
float fLastUse[MAXPLAYERS + 1];

public Plugin myinfo = 
{
	name = "SourceBans++ Report Plugin",
	author = PLUGIN_AUTHOR,
	description = "Adds ability for player to report offending players",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public void OnPluginStart()
{
	CreateConVar("sbpp_report_version", "SBPP Report Version", FCVAR_REPLICATED | FCVAR_SPONLY | FCVAR_DONTRECORD | FCVAR_NOTIFY);
	
	Convars[Prefix] = CreateConVar("sbpp_report_prefix", "sb", "SourceBans++ Database Table Prefix", FCVAR_NONE);
	Convars[Cooldown] = CreateConVar("sbpp_report_cooldown", "60.0", "Cooldown in seconds between per report per user", FCVAR_NONE, true, 0.0, false);
	
	RegConsoleCmd("sm_report", CmdReport, "Initialize Report");
	
	Convars[Prefix].AddChangeHook(OnConvarChanged);
	Convars[Cooldown].AddChangeHook(OnConvarChanged);
}

public Action CmdReport(int iClient, int iArgs)
{
	
}

public void OnConvarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (convar == Convars[Prefix])
		Convars[Prefix].GetString(sPrefix, sizeof sPrefix);
	else if (convar == Convars[Cooldown])
		fCooldown = Convars[Cooldown].FloatValue;
}