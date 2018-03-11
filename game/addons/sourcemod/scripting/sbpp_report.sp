#pragma semicolon 1

#define DEBUG

#define PLUGIN_AUTHOR "RumbleFrog, SourceBans++ Dev Team"
#define PLUGIN_VERSION "1.6.3-rc.1"

#include <sourcemod>
#include <sourcebans>

#pragma newdecls required

#define Chat_Prefix "[SourceBans++] "

enum
{
	Prefix = 0,
	Cooldown,
	MinLen,
	Settings_Count
};

ConVar Convars[Settings_Count];

char sPrefix[16];

bool bInReason[MAXPLAYERS + 1];

int iMinLen = 10
	, iTargetCache[MAXPLAYERS + 1];

float fCooldown = 60.0
	, fNextUse[MAXPLAYERS + 1];

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
	CreateConVar("sbpp_report_version", PLUGIN_VERSION, "SBPP Report Version", FCVAR_REPLICATED | FCVAR_SPONLY | FCVAR_DONTRECORD | FCVAR_NOTIFY);
	
	Convars[Prefix] = CreateConVar("sbpp_report_prefix", "sb", "SourceBans++ Database Table Prefix", FCVAR_NONE);
	Convars[Cooldown] = CreateConVar("sbpp_report_cooldown", "60.0", "Cooldown in seconds between per report per user", FCVAR_NONE, true, 0.0, false);
	Convars[MinLen] = CreateConVar("sbpp_report_minlen", "10", "Minimum reason length", FCVAR_NONE, true, 0.0, false);
	
	RegConsoleCmd("sm_report", CmdReport, "Initialize Report");
	
	Convars[Prefix].AddChangeHook(OnConvarChanged);
	Convars[Cooldown].AddChangeHook(OnConvarChanged);
	Convars[MinLen].AddChangeHook(OnConvarChanged);
}

public Action CmdReport(int iClient, int iArgs)
{
	if (!IsValidClient(iClient))
		return Plugin_Handled;
		
	if (OnCooldown(iClient))
	{
		PrintToChat(iClient, "%sPlease wait %.0f seconds before reporting again", Chat_Prefix, GetRemainingTime(iClient));
		
		return Plugin_Handled;
	}
	
	Menu PList = new Menu(ReportMenu);
	
	char sName[MAX_NAME_LENGTH], sIndex[4];
	
	for (int i = 0; i <= MaxClients; i++)
	{
		GetClientName(i, sName, sizeof sName);
		IntToString(i, sIndex, sizeof sIndex);
		
		PList.AddItem(sIndex, sName);
	}
	
	PList.Display(iClient, MENU_TIME_FOREVER);
	
	return Plugin_Handled;
}

public int ReportMenu(Menu menu, MenuAction action, int iClient, int iItem)
{
	switch (action)
	{
		case MenuAction_Select:
		{
			char sIndex[4];
			
			menu.GetItem(iItem, sIndex, sizeof sIndex);
			
			iTargetCache[iClient] = StringToInt(sIndex);
			
			bInReason[iClient] = true;
			
			PrintToChat(iClient, "%sPlease enter the reason for the report or \"cancel\" to cancel", Chat_Prefix);
		}
		case MenuAction_End:
			delete menu;
	}
}

public Action OnClientSayCommand(int iClient, const char[] sCommand, const char[] sArgs)
{
	if (!bInReason[iClient])
		return Plugin_Continue;
		
	if (!IsValidClient(iClient) || (iTargetCache[iClient] != -1 && !IsValidClient(iTargetCache[iClient])))
	{
		ResetInReason(iClient);
		
		return Plugin_Continue;
	}
	
	if (StrEqual(sArgs, "cancel", false))
	{
		PrintToChat(iClient, "%sReport cancelled", Chat_Prefix);
		
		ResetInReason(iClient);
		
		return Plugin_Stop;
	}
	
	if (strlen(sArgs) < iMinLen)
	{
		PrintToChat(iClient, "%sReason is too short. More details is required", Chat_Prefix);

		return Plugin_Stop;
	}
	
	SourceBans_ReportPlayer(iClient, iTargetCache[iClient], sArgs);
	
	AddCooldown(iClient);
	
	ResetInReason(iClient);
	
	return Plugin_Stop;
}

void ResetInReason(int iClient)
{
	bInReason[iClient] = false;
	iTargetCache[iClient] = -1;
}

void AddCooldown(int iClient)
{
	fNextUse[iClient] = GetGameTime() + fCooldown;
}

bool OnCooldown(int iClient)
{
	return (fNextUse[iClient] - GetGameTime()) > 0.0;
}

float GetRemainingTime(int iClient)
{
	float fOffset = fNextUse[iClient] - GetGameTime();
	
	if (fOffset > 0.0)
		return fOffset;
	else
		return 0.0;
}

public void OnConvarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (convar == Convars[Prefix])
		Convars[Prefix].GetString(sPrefix, sizeof sPrefix);
	else if (convar == Convars[Cooldown])
		fCooldown = Convars[Cooldown].FloatValue;
	else if (convar == Convars[MinLen])
		iMinLen = Convars[MinLen].IntValue;
}

stock bool IsValidClient(int iClient, bool bAlive = false)
{
	if (iClient >= 1 &&
	iClient <= MaxClients &&
	IsClientConnected(iClient) &&
	IsClientInGame(iClient) &&
	!IsFakeClient(iClient) &&
	(bAlive == false || IsPlayerAlive(iClient)))
	{
		return true;
	}

	return false;
}